<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/functions.php';

$trackingNumber = $_GET['tracking'] ?? '';

if (empty($trackingNumber)) {
    echo json_encode(['success' => false, 'error' => 'Tracking number is required']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Get order with tracking ID - orders table has courier_company and tracking_id columns
    $stmt = $db->prepare("
        SELECT * FROM orders WHERE tracking_id = ?
    ");
    $stmt->execute([$trackingNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'No order found with this tracking number']);
        exit;
    }
    
    // Calculate estimated delivery (7 days from shipped date or created date)
    $baseDate = $order['shipped_at'] ?? $order['created_at'];
    $estimatedDelivery = date('D, d M Y', strtotime($baseDate . ' +7 days'));
    
    // Tracking URL not available in this implementation
    $trackingUrl = null;
    
    // Build timeline based on order status
    $timeline = [];
    
    // Order Confirmed
    $timeline[] = [
        'title' => 'Order Confirmed',
        'date' => date('M d, h:i A', strtotime($order['created_at'])),
        'status' => 'completed'
    ];
    
    // Packed (if shipped or later)
    if (in_array($order['order_status'], ['shipped', 'delivered'])) {
        $shippedAt = $order['shipped_at'] ?? $order['updated_at'] ?? null;
        $packedTime = $shippedAt ? date('M d, h:i A', strtotime($shippedAt . ' -2 hours')) : 'Processing';
        $timeline[] = [
            'title' => 'Packed',
            'date' => $packedTime,
            'status' => 'completed'
        ];
    }
    
    // Picked Up (if shipped or later)
    if (in_array($order['order_status'], ['shipped', 'delivered'])) {
        $shippedAt = $order['shipped_at'] ?? $order['updated_at'] ?? null;
        $timeline[] = [
            'title' => 'Picked Up',
            'date' => $shippedAt ? date('M d, h:i A', strtotime($shippedAt)) : 'Awaiting pickup',
            'status' => 'completed'
        ];
    }
    
    // Transit (if shipped)
    if ($order['order_status'] === 'shipped') {
        $timeline[] = [
            'title' => 'Transit',
            'date' => 'In transit to destination',
            'status' => 'active'
        ];
    }
    
    // Out for Delivery
    $timeline[] = [
        'title' => 'Out for Delivery',
        'date' => $order['order_status'] === 'delivered' ? 'Completed' : 'Estimated ' . $estimatedDelivery,
        'status' => $order['order_status'] === 'delivered' ? 'completed' : 'pending'
    ];
    
    // Delivered
    $deliveredAt = $order['delivered_at'] ?? null;
    $deliveredDate = '';
    if ($order['order_status'] === 'delivered') {
        $deliveredDate = $deliveredAt ? date('M d, h:i A', strtotime($deliveredAt)) : date('M d, h:i A', strtotime($order['updated_at'] ?? 'now'));
    } else {
        $deliveredDate = 'Expected ' . $estimatedDelivery;
    }
    $timeline[] = [
        'title' => 'Delivered',
        'date' => $deliveredDate,
        'status' => $order['order_status'] === 'delivered' ? 'completed' : 'pending'
    ];
    
    echo json_encode([
        'success' => true,
        'order' => [
            'id' => $order['id'],
            'tracking_number' => $order['tracking_id'],
            'status' => $order['order_status'],
            'total' => $order['total_amount']
        ],
        'courier' => [
            'name' => $order['courier_company'] ?? 'Not assigned',
            'tracking_url' => $trackingUrl
        ],
        'estimated_delivery' => $estimatedDelivery,
        'timeline' => $timeline
    ]);
    
} catch (Exception $e) {
    error_log("Tracking API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error fetching tracking information']);
}
