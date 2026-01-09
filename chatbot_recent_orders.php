<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

try {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        echo json_encode([
            'success' => false,
            'action' => 'login_required',
            'message' => 'Please login to view your recent orders.'
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

    // Fetch ALL recent orders (no filtering)
    $allOrders = get_user_orders($userId);
    
    // Get up to 20 most recent orders
    $recentOrders = array_slice($allOrders, 0, 20);

    $result = [];
    foreach ($recentOrders as $o) {
        $orderId = (int)($o['id'] ?? 0);
        $result[] = [
            'id' => $orderId,
            'reference' => 'ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT),
            'status' => (string)($o['status'] ?? ''),
            'total_amount' => (float)($o['total_amount'] ?? 0),
            'created_at' => (string)($o['created_at'] ?? ''),
            'tracking_number' => (string)($o['tracking_number'] ?? ''),
        ];
    }

    echo json_encode([
        'success' => true,
        'orders' => $result
    ]);
} catch (Exception $e) {
    error_log('chatbot_recent_orders error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load recent orders.'
    ]);
}
