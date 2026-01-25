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
    
    // Filter orders: show active orders + delivered orders from last 20 days
    $twentyDaysAgo = strtotime('-20 days');
    $filteredOrders = array_filter($allOrders, function($o) use ($twentyDaysAgo) {
        $status = strtolower($o['order_status'] ?? $o['status'] ?? '');
        
        // Always exclude cancelled, returned, refunded
        if (in_array($status, ['cancelled', 'returned', 'refunded'])) {
            return false;
        }
        
        // For delivered orders, only show if within last 20 days
        if ($status === 'delivered') {
            $orderDate = strtotime($o['created_at'] ?? 'now');
            return $orderDate >= $twentyDaysAgo;
        }
        
        // Show all other active orders
        return true;
    });
    
    // Get up to 20 most recent orders
    $recentOrders = array_slice(array_values($filteredOrders), 0, 20);

    $result = [];
    foreach ($recentOrders as $o) {
        $orderId = (int)($o['id'] ?? 0);
        $status = $o['order_status'] ?? $o['status'] ?? 'pending';
        $result[] = [
            'id' => $orderId,
            'reference' => 'ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT),
            'status' => ucfirst(str_replace('_', ' ', $status)),
            'total_amount' => (float)($o['total_amount'] ?? 0),
            'created_at' => (string)($o['created_at'] ?? ''),
            'tracking_number' => (string)($o['tracking_id'] ?? $o['tracking_number'] ?? ''),
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
