<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Get export parameters
$dateRange = $_GET['date_range'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$statusFilter = $_GET['status_filter'] ?? 'all';

$db = get_db_connection();

// Build date filter based on selection
$dateCondition = "1=1";
$today = date('Y-m-d');

switch ($dateRange) {
    case 'today':
        $dateCondition = "DATE(o.created_at) = '$today'";
        $reportTitle = "Orders Report - Today ($today)";
        break;
    case 'last_7_days':
        $startDateCalc = date('Y-m-d', strtotime('-7 days'));
        $dateCondition = "DATE(o.created_at) BETWEEN '$startDateCalc' AND '$today'";
        $reportTitle = "Orders Report - Last 7 Days ($startDateCalc to $today)";
        break;
    case 'current_month':
        $startDateCalc = date('Y-m-01');
        $dateCondition = "DATE(o.created_at) BETWEEN '$startDateCalc' AND '$today'";
        $reportTitle = "Orders Report - Current Month (" . date('F Y') . ")";
        break;
    case 'last_month':
        $startDateCalc = date('Y-m-01', strtotime('first day of last month'));
        $endDateCalc = date('Y-m-t', strtotime('last day of last month'));
        $dateCondition = "DATE(o.created_at) BETWEEN '$startDateCalc' AND '$endDateCalc'";
        $reportTitle = "Orders Report - Last Month (" . date('F Y', strtotime('last month')) . ")";
        break;
    case 'custom':
        if (!empty($startDate) && !empty($endDate)) {
            $startDate = date('Y-m-d', strtotime($startDate));
            $endDate = date('Y-m-d', strtotime($endDate));
            $dateCondition = "DATE(o.created_at) BETWEEN '$startDate' AND '$endDate'";
            $reportTitle = "Orders Report - Custom Range ($startDate to $endDate)";
        } else {
            $reportTitle = "Orders Report - All Time";
        }
        break;
    default:
        $reportTitle = "Orders Report - All Time";
}

// Configurable thresholds (same as dashboard)
$highValueThreshold = 5000;
$delayedDays = 3;

// Build status filter
$statusCondition = "1=1";
switch ($statusFilter) {
    // Fulfilment Status
    case 'pending':
        $statusCondition = "o.order_status = 'pending'";
        break;
    case 'processing':
        $statusCondition = "o.order_status IN ('accepted', 'processing')";
        break;
    case 'unshipped':
        $statusCondition = "o.order_status = 'accepted' AND (o.courier_company IS NULL OR o.courier_company = '')";
        break;
    case 'in_transit':
        $statusCondition = "o.order_status = 'shipped'";
        break;
    case 'delivered':
        $statusCondition = "o.order_status = 'delivered'";
        break;
    
    // Payment & Risk Monitoring
    case 'pending_payment':
        $statusCondition = "o.payment_status = 'pending'";
        break;
    case 'failed_payment':
        $statusCondition = "o.payment_status = 'failed'";
        break;
    case 'cod':
        $statusCondition = "o.payment_method = 'cod'";
        break;
    case 'refund_pending':
        $statusCondition = "o.payment_status = 'refund_pending'";
        break;
    
    // Returns & Post-Delivery
    case 'return_requested':
        $statusCondition = "o.order_status = 'return_requested'";
        break;
    case 'returned':
        $statusCondition = "o.order_status = 'returned'";
        break;
    case 'refunded':
        $statusCondition = "o.payment_status = 'refunded'";
        break;
    
    // Exceptions & Attention Required
    case 'cancelled':
        $statusCondition = "o.order_status = 'cancelled'";
        break;
    case 'delayed':
        $statusCondition = "o.order_status IN ('pending', 'accepted') AND o.created_at < DATE_SUB(NOW(), INTERVAL $delayedDays DAY)";
        break;
    case 'failed_delivery':
        $statusCondition = "o.order_status = 'delivery_failed'";
        break;
    case 'high_value':
        $statusCondition = "o.total_amount >= $highValueThreshold";
        break;
}

// Fetch orders with filters
$query = "
    SELECT 
        o.id as order_id,
        o.created_at as order_date,
        u.name as customer_name,
        u.email as customer_email,
        o.shipping_address,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.total_amount,
        o.courier_company,
        o.tracking_id,
        o.transaction_id,
        o.verified_at
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $dateCondition AND $statusCondition
    ORDER BY o.created_at DESC
";

$orders = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get order items for each order
foreach ($orders as &$order) {
    $itemsQuery = $db->prepare("
        SELECT oi.quantity, oi.price, p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemsQuery->execute([$order['order_id']]);
    $items = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
    
    $itemsList = [];
    foreach ($items as $item) {
        $itemsList[] = $item['product_name'] . ' (x' . $item['quantity'] . ')';
    }
    $order['items'] = implode(', ', $itemsList);
}
unset($order);

// Generate Excel file (CSV format with Excel-compatible encoding)
$filename = 'Orders_Report_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for Excel download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write report title and metadata
fputcsv($output, [$reportTitle]);
fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Orders: ' . count($orders)]);
fputcsv($output, []); // Empty row

// Write headers
$headers = [
    'Order ID',
    'Order Date',
    'Customer Name',
    'Customer Email',
    'Shipping Address',
    'Items',
    'Total Amount (₹)',
    'Order Status',
    'Payment Status',
    'Payment Method',
    'Courier Company',
    'Tracking ID',
    'Transaction ID',
    'Verified At'
];
fputcsv($output, $headers);

// Calculate totals
$totalAmount = 0;
$paidOrders = 0;
$pendingOrders = 0;
$deliveredOrders = 0;

// Write data rows
foreach ($orders as $order) {
    $row = [
        '#' . $order['order_id'],
        date('Y-m-d H:i:s', strtotime($order['order_date'])),
        $order['customer_name'] ?? 'Guest',
        $order['customer_email'] ?? '',
        $order['shipping_address'] ?? '',
        $order['items'],
        number_format($order['total_amount'], 2),
        ucfirst($order['order_status'] ?? 'pending'),
        ucfirst($order['payment_status'] ?? 'pending'),
        strtoupper($order['payment_method'] ?? 'N/A'),
        $order['courier_company'] ?? '',
        $order['tracking_id'] ?? '',
        $order['transaction_id'] ?? '',
        $order['verified_at'] ?? ''
    ];
    fputcsv($output, $row);
    
    // Calculate totals
    $totalAmount += $order['total_amount'];
    if ($order['payment_status'] === 'completed') $paidOrders++;
    if ($order['payment_status'] === 'pending') $pendingOrders++;
    if ($order['order_status'] === 'delivered') $deliveredOrders++;
}

// Add summary section
fputcsv($output, []); // Empty row
fputcsv($output, ['--- SUMMARY ---']);
fputcsv($output, ['Total Orders', count($orders)]);
fputcsv($output, ['Total Revenue', '₹' . number_format($totalAmount, 2)]);
fputcsv($output, ['Paid Orders', $paidOrders]);
fputcsv($output, ['Pending Payment', $pendingOrders]);
fputcsv($output, ['Delivered Orders', $deliveredOrders]);

fclose($output);
exit;
