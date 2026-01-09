<?php
/**
 * Download Invoice Endpoint
 * Generates and displays HTML invoice for printing to PDF
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/invoice_generator.php';

require_login();

$orderId = (int)($_GET['order_id'] ?? 0);
$userId = (int)$_SESSION['user']['id'];

if (!$orderId) {
    die('Invalid order ID');
}

// Verify order belongs to user
$db = get_db_connection();
$stmt = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found or access denied');
}

try {
    $invoiceHTML = generateInvoice($orderId);
    echo $invoiceHTML;
} catch (Exception $e) {
    die('Error generating invoice: ' . $e->getMessage());
}
?>
