<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$batchCode = trim($_GET['batch_code'] ?? $_POST['batch_code'] ?? '');

if (empty($batchCode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a batch code.'
    ]);
    exit;
}

try {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT 
            batch_code,
            product_name,
            grade,
            net_weight,
            manufacturing_date,
            expiry_date,
            country_of_origin,
            lab_report_url,
            is_active
        FROM batch_codes 
        WHERE batch_code = :batch_code AND is_active = 1
    ");
    
    $stmt->execute([':batch_code' => strtoupper($batchCode)]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        echo json_encode([
            'success' => false,
            'message' => 'Batch code not found or inactive.'
        ]);
        exit;
    }
    
    // Format dates
    $mfgDate = date('M Y', strtotime($batch['manufacturing_date']));
    $expDate = date('M Y', strtotime($batch['expiry_date']));
    
    // Get current date and time (dynamic - not stored)
    $verificationDateTime = date('F d, Y \a\t h:i A');
    
    echo json_encode([
        'success' => true,
        'data' => [
            'batch_code' => $batch['batch_code'],
            'product_name' => $batch['product_name'],
            'grade' => $batch['grade'],
            'net_weight' => $batch['net_weight'],
            'manufacturing_date' => $mfgDate,
            'expiry_date' => $expDate,
            'country_of_origin' => $batch['country_of_origin'],
            'lab_report_url' => $batch['lab_report_url'],
            'verification_datetime' => $verificationDateTime
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while verifying the batch code.'
    ]);
}
