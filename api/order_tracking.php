<?php
/**
 * Order Tracking API
 * Returns order status and tracking info as JSON
 * Used by WhatsApp chatbot and tracking page
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-GilafStore-Key');

require_once __DIR__ . '/../includes/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$orderId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? $_POST['phone'] ?? '');
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order ID required']);
    exit;
}

global $pdo;

// Fetch order
$stmt = $pdo->prepare("
    SELECT o.id, o.order_status, o.payment_status, o.payment_method, o.total_amount,
           o.tracking_id, o.courier_company, o.phone, o.created_at, o.updated_at,
           o.picked_up_at, o.user_id, u.name as customer_name, u.phone as user_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Verify access
$hasAccess = false;
$orderPhone = preg_replace('/[^0-9]/', '', $order['phone'] ?? $order['user_phone'] ?? '');

// Token validation
if ($token && hash_equals(substr(md5($orderId . $order['created_at']), 0, 12), $token)) {
    $hasAccess = true;
}

// Phone validation
if ($phone && strlen($phone) >= 10 && substr($orderPhone, -10) === substr($phone, -10)) {
    $hasAccess = true;
}

// Guest orders
if (!$order['user_id']) {
    $hasAccess = true;
}

// API key validation (for CRM/bot access)
$apiKey = $_SERVER['HTTP_X_GILAFSTORE_KEY'] ?? '';
if ($apiKey) {
    $keyCheck = $pdo->prepare("SELECT id FROM crm_api_keys WHERE api_key = ? AND is_active = 1");
    $keyCheck->execute([$apiKey]);
    if ($keyCheck->fetch()) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get status history
$historyStmt = $pdo->prepare("
    SELECT new_status, created_at, notes 
    FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY created_at ASC
");
$historyStmt->execute([$orderId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Build timeline
$steps = [
    'pending' => 'Order Placed',
    'confirmed' => 'Confirmed',
    'packed' => 'Packed',
    'shipped' => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered',
];

$timeline = [];
$currentStatus = $order['order_status'];
$statusOrder = array_keys($steps);
$currentIndex = array_search($currentStatus, $statusOrder);
if ($currentIndex === false) $currentIndex = 0;

foreach ($steps as $status => $label) {
    $stepIndex = array_search($status, $statusOrder);
    $historyEntry = array_filter($history, fn($h) => $h['new_status'] === $status);
    $historyEntry = reset($historyEntry);
    
    $timeline[] = [
        'status' => $status,
        'label' => $label,
        'completed' => $stepIndex <= $currentIndex,
        'current' => $status === $currentStatus,
        'timestamp' => $historyEntry ? $historyEntry['created_at'] : null,
        'notes' => $historyEntry ? $historyEntry['notes'] : null,
    ];
}

// Estimate delivery
$estimatedDelivery = null;
if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled') {
    $baseDate = $order['picked_up_at'] ?? $order['created_at'];
    $daysToAdd = in_array($order['order_status'], ['shipped', 'out_for_delivery']) ? 2 : 5;
    $estimatedDelivery = date('Y-m-d', strtotime($baseDate . " +$daysToAdd days"));
}

// Generate shareable tracking URL
$trackToken = substr(md5($orderId . $order['created_at']), 0, 12);
$trackingUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . "/order-tracking.php?id=$orderId&t=$trackToken";

// WhatsApp message format
$whatsappMessage = "🛒 *Order #{$order['id']} Status*\n\n";
$whatsappMessage .= "📦 Status: " . ucfirst(str_replace('_', ' ', $order['order_status'])) . "\n";
$whatsappMessage .= "💰 Total: ₹" . number_format($order['total_amount'], 2) . "\n";
if ($order['tracking_id']) {
    $whatsappMessage .= "🚚 Tracking: {$order['tracking_id']}\n";
}
if ($estimatedDelivery) {
    $whatsappMessage .= "📅 Est. Delivery: " . date('d M Y', strtotime($estimatedDelivery)) . "\n";
}
$whatsappMessage .= "\n🔗 Track: $trackingUrl";

echo json_encode([
    'success' => true,
    'order' => [
        'id' => (int)$order['id'],
        'status' => $order['order_status'],
        'status_label' => ucfirst(str_replace('_', ' ', $order['order_status'])),
        'payment_status' => $order['payment_status'],
        'payment_method' => $order['payment_method'],
        'total' => (float)$order['total_amount'],
        'tracking_id' => $order['tracking_id'],
        'courier' => $order['courier_company'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at'],
        'estimated_delivery' => $estimatedDelivery,
    ],
    'timeline' => $timeline,
    'tracking_url' => $trackingUrl,
    'whatsapp_message' => $whatsappMessage,
]);
