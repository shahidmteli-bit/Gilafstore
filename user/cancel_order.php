<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$reason = trim($_POST['cancel_reason'] ?? '');

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Ensure cancel columns exist (safe migration)
    try {
        $cols = $db->query("SHOW COLUMNS FROM orders LIKE 'cancel_reason'")->rowCount();
        if ($cols === 0) { $db->exec("ALTER TABLE orders ADD COLUMN cancel_reason TEXT DEFAULT NULL"); }
    } catch (Exception $migErr) {}
    try {
        $cols = $db->query("SHOW COLUMNS FROM orders LIKE 'cancelled_at'")->rowCount();
        if ($cols === 0) { $db->exec("ALTER TABLE orders ADD COLUMN cancelled_at DATETIME DEFAULT NULL"); }
    } catch (Exception $migErr) {}
    
    // Fetch the order - must belong to this user (only select guaranteed columns)
    $stmt = $db->prepare("SELECT id, order_status, payment_method, total_amount, created_at FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $orderStatus = $order['order_status'] ?? $order['status'] ?? 'pending';
    
    // Only allow cancellation if status is before "shipped"
    $cancellableStatuses = ['pending', 'processing', 'confirmed'];
    
    if (!in_array(strtolower($orderStatus), $cancellableStatuses)) {
        echo json_encode([
            'success' => false, 
            'message' => 'This order cannot be cancelled. Orders can only be cancelled before they are shipped.'
        ]);
        exit;
    }
    
    // Only allow cancellation within 4 hours of order placement
    $orderCreatedAt = strtotime($order['created_at']);
    $hoursSinceOrder = (time() - $orderCreatedAt) / 3600;
    
    if ($hoursSinceOrder > 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Cancellation window has expired. Orders can only be cancelled within 4 hours of placement.'
        ]);
        exit;
    }
    
    // Already cancelled check
    if (strtolower($orderStatus) === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'This order is already cancelled']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Update order status to cancelled
    // Try order_status column first, fallback to status
    try {
        $stmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', cancelled_at = NOW(), cancel_reason = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$reason, $orderId, $userId]);
    } catch (PDOException $e) {
        // If order_status or cancelled_at columns don't exist, try simpler update
        try {
            $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $stmt->execute([$orderId, $userId]);
        } catch (PDOException $e2) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to cancel order: ' . $e2->getMessage()]);
            exit;
        }
    }
    
    // Restore stock for each item
    $itemStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$orderId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        try {
            $db->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
        } catch (Exception $e) {
            // Non-critical: log but continue
            error_log("Stock restore failed for product {$item['product_id']}: " . $e->getMessage());
        }
    }
    
    $db->commit();
    
    // Log the cancellation
    error_log("Order #{$orderId} cancelled by user #{$userId}. Reason: {$reason}");
    
    // Send cancellation email
    try {
        require_once __DIR__ . '/../includes/order_emails.php';
        send_order_cancellation_email($orderId, $reason);
    } catch (Exception $emailErr) {
        error_log("WARNING: Cancellation email failed for order #{$orderId} - " . $emailErr->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order cancelled successfully. Your refund will be processed within 5-7 business days if payment was already made.'
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Order cancellation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
