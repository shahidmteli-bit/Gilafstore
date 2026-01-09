<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('/index.php', 'Invalid access', 'error');
}

$orderId = (int)$_POST['order_id'];
$cardNumber = $_POST['card_number'] ?? '';
$cardName = $_POST['card_name'] ?? '';
$expiry = $_POST['expiry'] ?? '';
$cvv = $_POST['cvv'] ?? '';

// Simulate payment processing
// In production, integrate with Stripe, PayPal, Razorpay, etc.

// Basic validation
$errors = [];
if (empty($cardNumber)) $errors[] = 'Card number is required';
if (empty($cardName)) $errors[] = 'Cardholder name is required';
if (empty($expiry)) $errors[] = 'Expiry date is required';
if (empty($cvv)) $errors[] = 'CVV is required';

if ($errors) {
    $_SESSION['flash_message'] = implode(', ', $errors);
    $_SESSION['flash_type'] = 'error';
    header('Location: payment_gateway.php?order_id=' . $orderId);
    exit;
}

// Simulate successful payment processing
// In production, call payment gateway API here

// Update order status to paid
try {
    $db = get_db_connection();
    $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
    $stmt->execute([$orderId]);
    
    // Clear cart
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    
    // Auto-generate invoice for the order
    require_once __DIR__ . '/includes/invoice_functions.php';
    create_invoice($orderId);
    
    // Redirect to success page
    header('Location: thank-you.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Payment processing failed. Please try again.';
    $_SESSION['flash_type'] = 'error';
    header('Location: payment_gateway.php?order_id=' . $orderId);
    exit;
}
?>
