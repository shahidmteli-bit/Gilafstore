<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

try {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        echo json_encode([
            'success' => false,
            'action' => 'login_required',
            'message' => 'Please login to view order status.'
        ]);
        exit;
    }

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user session.'
        ]);
        exit;
    }

    $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    if ($orderId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order id.'
        ]);
        exit;
    }

    $order = get_order_with_items($orderId);
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found.'
        ]);
        exit;
    }

    // Ownership enforcement
    if ((int)($order['user_id'] ?? 0) !== $userId) {
        echo json_encode([
            'success' => false,
            'message' => 'You are not allowed to view this order.'
        ]);
        exit;
    }

    $items = $order['items'] ?? [];
    $itemCount = is_array($items) ? count($items) : 0;

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$order['id'],
            'status' => (string)($order['status'] ?? ''),
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'created_at' => (string)($order['created_at'] ?? ''),
            'tracking_number' => (string)($order['tracking_number'] ?? ''),
            'item_count' => $itemCount,
            'items' => $items,
        ]
    ]);
} catch (Exception $e) {
    error_log('chatbot_order_status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load order status.'
    ]);
}
