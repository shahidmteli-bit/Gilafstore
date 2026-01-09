<?php
/**
 * AJAX Batch Verification Handler
 * Returns batch data with lifecycle features for homepage verification
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/batch_functions.php';

header('Content-Type: application/json');

// Auto-update expired batches
check_and_update_expired_batches();

$batchCode = $_GET['code'] ?? '';

if (empty($batchCode)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a batch code']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Fetch batch details with lifecycle data
    $stmt = $db->prepare("
        SELECT bc.*, p.image as product_image, p.price as product_price
        FROM batch_codes bc
        LEFT JOIN products p ON p.id = bc.product_id
        WHERE bc.batch_code = :batch_code
    ");
    $stmt->execute([':batch_code' => trim($batchCode)]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        echo json_encode([
            'success' => false,
            'error_type' => 'not_found',
            'message' => 'Batch Not Found',
            'description' => 'The batch code <strong>' . htmlspecialchars($batchCode) . '</strong> does not exist in our system. This could indicate a counterfeit product or an incorrect batch code.'
        ]);
        exit;
    }
    
    // Log verification
    log_batch_verification($batch['id'], $batch['batch_code'], 'manual_entry');
    
    // Get approver name if approved
    $approverName = null;
    if ($batch['quality_approved'] && $batch['quality_approver_id']) {
        $approverStmt = $db->prepare("SELECT name FROM users WHERE id = :id");
        $approverStmt->execute([':id' => $batch['quality_approver_id']]);
        $approver = $approverStmt->fetch(PDO::FETCH_ASSOC);
        $approverName = $approver ? $approver['name'] : 'Admin';
    }
    
    // Check batch status
    $status = $batch['status'] ?? 'production';
    $isValid = !in_array($status, ['expired', 'recalled', 'blocked', 'rejected']);
    
    // Prepare response
    $response = [
        'success' => true,
        'valid' => $isValid,
        'status' => $status,
        'batch' => [
            'code' => $batch['batch_code'],
            'product_name' => $batch['product_name'],
            'product_image' => $batch['product_image'] ? base_url('uploads/products/' . $batch['product_image']) : null,
            'net_weight' => $batch['net_weight'],
            'mrp' => $batch['product_price'],
            'manufacturing_date' => date('M d, Y', strtotime($batch['manufacturing_date'])),
            'expiry_date' => date('M d, Y', strtotime($batch['expiry_date'])),
            'country_of_origin' => $batch['country_of_origin'],
            'grade' => $batch['grade'],
            'is_lab_tested' => (bool)$batch['is_lab_tested'],
            'is_organic' => (bool)$batch['is_organic'],
            'approver_name' => $approverName,
            'verified_at' => date('F j, Y, g:i a')
        ]
    ];
    
    // Add status-specific messages
    if ($status === 'expired') {
        $response['error_type'] = 'expired';
        $response['message'] = 'Batch Expired';
        $response['description'] = 'This batch has passed its expiry date and should not be consumed.';
    } elseif ($status === 'recalled') {
        $response['error_type'] = 'recalled';
        $response['message'] = 'Batch Recalled';
        $response['description'] = 'This batch has been recalled. Please do not use this product.';
        if ($batch['recall_reason']) {
            $response['recall_reason'] = $batch['recall_reason'];
        }
    } elseif ($status === 'blocked') {
        $response['error_type'] = 'blocked';
        $response['message'] = 'Batch Blocked';
        $response['description'] = 'This batch has been blocked and is not available for sale.';
    } elseif ($status === 'rejected') {
        $response['error_type'] = 'rejected';
        $response['message'] = 'Batch Rejected';
        $response['description'] = 'This batch did not pass quality testing.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Batch verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error_type' => 'system_error',
        'message' => 'System Error',
        'description' => 'Unable to verify batch at this time. Please try again later.'
    ]);
}
