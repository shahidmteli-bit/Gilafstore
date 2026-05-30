<?php
/**
 * GilafStore CRM Webhook Receiver
 * 
 * Receives callbacks from WACRM (e.g., message delivery status,
 * customer replied, automation triggered, etc.)
 * 
 * POST /api/crm_webhook.php
 * Headers: X-WACRM-Key, X-WACRM-Signature, X-WACRM-Timestamp
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/crm_engine.php';

$crm = CRMEngine::getInstance();

// Validate incoming request
$apiKey = $_SERVER['HTTP_X_WACRM_KEY'] ?? '';
$signature = $_SERVER['HTTP_X_WACRM_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_WACRM_TIMESTAMP'] ?? '';

// Verify API key matches our stored key
$ourKey = $crm->getActiveApiKey();
if (!$ourKey || $apiKey !== $ourKey['api_key']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// Replay protection (reject if older than 5 minutes)
if ($timestamp && abs(time() - (int)$timestamp) > 300) {
    http_response_code(408);
    echo json_encode(['error' => 'Request too old']);
    exit;
}

// Verify HMAC signature
$rawBody = file_get_contents('php://input');
if ($signature && $ourKey['api_secret']) {
    $expectedSig = hash_hmac('sha256', $rawBody, $ourKey['api_secret']);
    if (!hash_equals($expectedSig, $signature)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$body = json_decode($rawBody, true);
if (!$body || empty($body['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$event = $body['event'];
$data = $body['data'] ?? [];

// Log incoming webhook
$crm->logActivity('wacrm', null, "webhook_received:$event", 'webhook', null, $data);

// Route event to handler
$result = handleIncomingEvent($event, $data, $crm);

echo json_encode($result);

// ─── Event Handlers ─────────────────────────────────────────

function handleIncomingEvent(string $event, array $data, CRMEngine $crm): array
{
    switch ($event) {
        case 'message.delivered':
            return handleMessageDelivered($data);

        case 'message.read':
            return handleMessageRead($data);

        case 'message.failed':
            return handleMessageFailed($data);

        case 'customer.replied':
            return handleCustomerReplied($data, $crm);

        case 'automation.triggered':
            return handleAutomationTriggered($data, $crm);

        case 'contact.updated':
            return handleContactUpdated($data);

        case 'conversation.closed':
            return handleConversationClosed($data);

        default:
            return ['success' => true, 'message' => "Event $event acknowledged"];
    }
}

function handleMessageDelivered(array $data): array
{
    global $pdo;
    $messageId = $data['message_id'] ?? '';
    
    // Update OTP delivery status if applicable
    if ($messageId) {
        try {
            $pdo->prepare(
                "UPDATE crm_whatsapp_otp SET status = 'delivered' WHERE whatsapp_message_id = ? AND status = 'sent'"
            )->execute([$messageId]);
        } catch (\Throwable $e) {
            // Silent
        }
    }
    
    return ['success' => true];
}

function handleMessageRead(array $data): array
{
    // Track that customer opened the message (useful for analytics)
    global $pdo;
    try {
        $pdo->prepare(
            "UPDATE crm_order_notifications SET read_at = NOW() WHERE whatsapp_message_id = ? AND read_at IS NULL"
        )->execute([$data['message_id'] ?? '']);
    } catch (\Throwable $e) {
        // Silent
    }
    return ['success' => true];
}

function handleMessageFailed(array $data): array
{
    global $pdo;
    $messageId = $data['message_id'] ?? '';
    $error = $data['error'] ?? 'Unknown error';
    
    try {
        $pdo->prepare(
            "UPDATE crm_whatsapp_otp SET status = 'failed' WHERE whatsapp_message_id = ? AND status = 'sent'"
        )->execute([$messageId]);
        
        $pdo->prepare(
            "UPDATE crm_order_notifications SET status = 'failed', error_message = ? WHERE whatsapp_message_id = ?"
        )->execute([$error, $messageId]);
    } catch (\Throwable $e) {
        // Silent
    }
    
    return ['success' => true];
}

function handleCustomerReplied(array $data, CRMEngine $crm): array
{
    // Customer replied to a CRM message — log it for admin visibility
    $phone = $data['phone'] ?? '';
    $message = $data['message'] ?? '';
    $contactId = $data['contact_id'] ?? '';
    
    $crm->logActivity('customer', $contactId, 'replied', 'message', null, [
        'phone' => $phone,
        'message' => substr($message, 0, 200),
    ]);
    
    return ['success' => true];
}

function handleAutomationTriggered(array $data, CRMEngine $crm): array
{
    $crm->logActivity('wacrm', null, 'automation_triggered', 'automation', $data['automation_id'] ?? null, $data);
    return ['success' => true];
}

function handleContactUpdated(array $data): array
{
    // Bidirectional sync: CRM updated a contact, reflect in GilafStore
    global $pdo;
    $crmContactId = $data['contact_id'] ?? '';
    $phone = $data['phone'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    
    if (!$phone && !$email) return ['success' => true, 'message' => 'No identifiers'];
    
    try {
        // Find matching local user
        $user = null;
        if ($phone) {
            $cleaned = preg_replace('/[^0-9]/', '', $phone);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone LIKE ? OR mobile LIKE ? LIMIT 1");
            $stmt->execute(['%' . substr($cleaned, -10), '%' . substr($cleaned, -10)]);
            $user = $stmt->fetch();
        }
        if (!$user && $email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }
        
        if ($user && $name) {
            $pdo->prepare("UPDATE users SET name = ? WHERE id = ? AND (name IS NULL OR name = '')")->execute([$name, $user['id']]);
        }
        
        // Update sync record
        if ($user && $crmContactId) {
            $pdo->prepare(
                "UPDATE crm_customer_sync SET crm_contact_id = ?, last_synced_at = NOW() WHERE local_user_id = ?"
            )->execute([$crmContactId, $user['id']]);
        }
    } catch (\Throwable $e) {
        // Silent
    }
    
    return ['success' => true];
}

function handleConversationClosed(array $data): array
{
    // Log that a conversation was resolved
    return ['success' => true, 'message' => 'Acknowledged'];
}
