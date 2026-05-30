<?php
/**
 * Razorpay Create Order
 * Creates a Razorpay order and returns order_id for frontend checkout
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/security.php';

header('Content-Type: application/json');

// D3/D5: Rate limit order creation (10 per minute, block 10 min)
if (!rate_limit_enforce('order_create', 10, 60, 600)) { exit; }

if (!isset($_SESSION['user']) && empty($_SESSION['pending_order']['guest_info'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check Razorpay is enabled
if (get_setting('razorpay_enabled', '0') !== '1') {
    echo json_encode(['success' => false, 'error' => 'Razorpay is not enabled']);
    exit;
}

$keyId = get_setting('razorpay_key_id', '');
$keySecret = get_setting('razorpay_key_secret', '');

if (empty($keyId) || empty($keySecret)) {
    echo json_encode(['success' => false, 'error' => 'Razorpay API keys not configured']);
    exit;
}

// Get order details from session
if (!isset($_SESSION['pending_order'])) {
    echo json_encode(['success' => false, 'error' => 'No pending order found']);
    exit;
}

$pendingOrder = $_SESSION['pending_order'];
$amount = (float)$pendingOrder['total'];
$orderId = $pendingOrder['order_id'];

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order amount']);
    exit;
}

// Amount in paise (Razorpay expects smallest currency unit)
$amountPaise = (int)round($amount * 100);

// Create Razorpay order via API
$orderData = [
    'amount' => $amountPaise,
    'currency' => 'INR',
    'receipt' => $orderId,
    'notes' => [
        'order_id' => $orderId,
        'user_id' => $_SESSION['user']['id'] ?? 0,
        'user_email' => $_SESSION['user']['email'] ?? ($_SESSION['pending_order']['guest_info']['email'] ?? '')
    ]
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($orderData),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_USERPWD => $keyId . ':' . $keySecret,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("Razorpay cURL error: " . $curlError);
    echo json_encode(['success' => false, 'error' => 'Payment gateway connection failed']);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200 || empty($result['id'])) {
    $errorMsg = $result['error']['description'] ?? 'Failed to create payment order';
    error_log("Razorpay order creation failed: " . $response);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$razorpayOrderId = $result['id'];

// Save to payments table
try {
    $db = get_db_connection();
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT DEFAULT NULL,
        internal_order_id VARCHAR(50) DEFAULT NULL,
        razorpay_order_id VARCHAR(100) DEFAULT NULL,
        razorpay_payment_id VARCHAR(100) DEFAULT NULL,
        razorpay_signature VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'INR',
        status ENUM('created','authorized','captured','failed','refunded') DEFAULT 'created',
        payment_method VARCHAR(50) DEFAULT NULL,
        error_code VARCHAR(100) DEFAULT NULL,
        error_description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_internal_order_id (internal_order_id),
        INDEX idx_razorpay_order_id (razorpay_order_id),
        INDEX idx_razorpay_payment_id (razorpay_payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Best-effort migration for older installs
    try {
        $col = $db->query("SHOW COLUMNS FROM payments LIKE 'order_id'")->fetch(PDO::FETCH_ASSOC);
        if ($col && strtoupper((string)($col['Null'] ?? '')) === 'NO') {
            $db->exec("ALTER TABLE payments MODIFY COLUMN order_id INT DEFAULT NULL");
        }
    } catch (Exception $e) {}

    try {
        $col = $db->query("SHOW COLUMNS FROM payments LIKE 'internal_order_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $db->exec("ALTER TABLE payments ADD COLUMN internal_order_id VARCHAR(50) DEFAULT NULL AFTER order_id");
        }
    } catch (Exception $e) {}

    try {
        $idx = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_internal_order_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $db->exec("ALTER TABLE payments ADD INDEX idx_internal_order_id (internal_order_id)");
        }
    } catch (Exception $e) {}

    try {
        $idx = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_order_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $db->exec("ALTER TABLE payments ADD INDEX idx_order_id (order_id)");
        }
    } catch (Exception $e) {}

    try {
        $idx = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_razorpay_order_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $db->exec("ALTER TABLE payments ADD INDEX idx_razorpay_order_id (razorpay_order_id)");
        }
    } catch (Exception $e) {}

    try {
        $idx = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_razorpay_payment_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$idx) {
            $db->exec("ALTER TABLE payments ADD INDEX idx_razorpay_payment_id (razorpay_payment_id)");
        }
    } catch (Exception $e) {}

    $stmt = $db->prepare("INSERT INTO payments (internal_order_id, razorpay_order_id, amount, currency, status) VALUES (?, ?, ?, 'INR', 'created')");
    $stmt->execute([$orderId, $razorpayOrderId, $amount]);
} catch (Exception $e) {
    error_log("Payment record insert error. internal_order_id={$orderId}, rzp_order_id={$razorpayOrderId} - " . $e->getMessage());
}

// Store razorpay_order_id in session
$_SESSION['pending_order']['razorpay_order_id'] = $razorpayOrderId;

// Get user details for prefill
if (isset($_SESSION['user'])) {
    $user = get_user((int)$_SESSION['user']['id']);
} else {
    $gInfo = $_SESSION['pending_order']['guest_info'] ?? [];
    $user = ['name' => $gInfo['name'] ?? 'Guest', 'email' => $gInfo['email'] ?? '', 'phone' => $gInfo['phone'] ?? ''];
}
$address = $pendingOrder['address'] ?? [];

echo json_encode([
    'success' => true,
    'razorpay_order_id' => $razorpayOrderId,
    'amount' => $amountPaise,
    'currency' => 'INR',
    'key_id' => $keyId,
    'order_receipt' => $orderId,
    'prefill' => [
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'contact' => $user['phone'] ?? ($address['phone'] ?? '')
    ],
    'notes' => [
        'order_id' => $orderId
    ]
]);
