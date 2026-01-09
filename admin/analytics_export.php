<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

// Get comprehensive analytics data
$query = "SELECT 
    v.visitor_id,
    v.first_visit_at,
    v.country,
    v.device_type,
    v.browser,
    v.os,
    COUNT(DISTINCT pv.id) as page_views,
    COUNT(DISTINCT CASE WHEN pe.event_type = 'click' THEN pe.id END) as product_clicks,
    COUNT(DISTINCT CASE WHEN pe.event_type = 'purchase' THEN pe.id END) as purchases
FROM analytics_visitors v
LEFT JOIN analytics_page_views pv ON v.visitor_id = pv.visitor_id
LEFT JOIN analytics_product_events pe ON v.visitor_id = pe.visitor_id
WHERE DATE(v.first_visit_at) BETWEEN ? AND ?
GROUP BY v.visitor_id, v.first_visit_at, v.country, v.device_type, v.browser, v.os
ORDER BY v.first_visit_at DESC
LIMIT 10000";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_' . $fromDate . '_to_' . $toDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Visitor ID', 'First Visit', 'Country', 'Device', 'Browser', 'OS', 'Page Views', 'Product Clicks', 'Purchases']);
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['visitor_id'],
            $row['first_visit_at'],
            $row['country'] ?? 'Unknown',
            $row['device_type'],
            $row['browser'],
            $row['os'],
            $row['page_views'],
            $row['product_clicks'],
            $row['purchases']
        ]);
    }
    
    fclose($output);
    exit;
}

// For Excel format, you would need PHPSpreadsheet library
// For PDF format, you would need TCPDF or similar library
