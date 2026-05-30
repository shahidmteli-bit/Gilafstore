<?php
/**
 * Chatbot API: Get orders eligible for return
 * Returns delivered orders within the 7-day return window
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

try {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        echo json_encode([
            'success' => false,
            'action' => 'login_required',
            'message' => 'Please login to check return eligibility.'
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

    $allOrders = get_user_orders($userId);

    // Return window: 7 days from delivery
    $returnWindowDays = 7;
    $now = time();

    $eligible = [];
    foreach ($allOrders as $o) {
        $status = strtolower($o['order_status'] ?? $o['status'] ?? '');

        // Only delivered orders can be returned
        if ($status !== 'delivered') continue;

        // Check if already returned/refunded
        if (in_array($status, ['returned', 'refunded', 'cancelled'])) continue;

        // Check delivery date — use delivered_at if available, else created_at + 7 days estimate
        $deliveredAt = !empty($o['delivered_at']) ? strtotime($o['delivered_at']) : null;
        if (!$deliveredAt) {
            // Estimate: if no delivered_at, use created_at + 5 days as estimated delivery
            $createdAt = strtotime($o['created_at'] ?? 'now');
            $deliveredAt = $createdAt + (5 * 86400);
        }

        $returnDeadline = $deliveredAt + ($returnWindowDays * 86400);

        if ($now <= $returnDeadline) {
            $daysLeft = max(0, (int)ceil(($returnDeadline - $now) / 86400));
            $orderId = (int)($o['id'] ?? 0);
            $eligible[] = [
                'id'             => $orderId,
                'reference'      => 'ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT),
                'total_amount'   => (float)($o['total_amount'] ?? 0),
                'created_at'     => (string)($o['created_at'] ?? ''),
                'delivered_at'   => (string)($o['delivered_at'] ?? ''),
                'days_left'      => $daysLeft,
                'item_count'     => (int)($o['item_count'] ?? 0),
            ];
        }
    }

    // Sort by days_left ascending (most urgent first)
    usort($eligible, function($a, $b) {
        return $a['days_left'] - $b['days_left'];
    });

    echo json_encode([
        'success'        => true,
        'eligible_orders' => array_slice($eligible, 0, 10),
        'return_window'  => $returnWindowDays,
    ]);
} catch (Exception $e) {
    error_log('chatbot_eligible_returns error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check return eligibility.'
    ]);
}
