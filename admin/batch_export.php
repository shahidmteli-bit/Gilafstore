<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Get all batch codes
$db = get_db_connection();
$stmt = $db->query("SELECT b.*, p.name as product_name_db FROM batch_codes b LEFT JOIN products p ON b.product_id = p.id ORDER BY b.created_at DESC");
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="batch_codes_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Batch Code',
    'Product Name',
    'Product ID',
    'Grade',
    'Net Weight',
    'Manufacturing Date',
    'Expiry Date',
    'Country of Origin',
    'Lab Report URL',
    'Status',
    'Created At'
]);

// Add batch data
foreach ($batches as $batch) {
    $status = 'Inactive';
    if ($batch['is_active'] == 1) {
        $status = 'Active';
    } elseif ($batch['is_active'] == 2) {
        $status = 'Paused';
    } elseif ($batch['is_active'] == 0) {
        $status = 'Blocked';
    }
    
    fputcsv($output, [
        $batch['batch_code'],
        $batch['product_name'],
        $batch['product_id'] ?? 'N/A',
        $batch['grade'] ?? 'N/A',
        $batch['net_weight'],
        $batch['manufacturing_date'],
        $batch['expiry_date'],
        $batch['country_of_origin'],
        $batch['lab_report_url'] ?? 'N/A',
        $status,
        $batch['created_at'] ?? date('Y-m-d H:i:s')
    ]);
}

fclose($output);
exit;
