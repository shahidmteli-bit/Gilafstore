<?php
/**
 * Get Active Batches API
 * Returns all active batches for a specific product
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.batch_code,
            b.product_id,
            b.product_name,
            b.net_weight,
            b.status,
            b.manufacturing_date,
            b.expiry_date,
            b.is_active
        FROM batch_codes b
        WHERE b.product_id = :product_id
        AND b.is_active = 1
        AND b.status IN ('production', 'quality_check', 'approved', 'in_stock')
        ORDER BY b.created_at DESC
    ");
    
    $stmt->execute([':product_id' => $productId]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'batches' => $batches
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching active batches: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading batches'
    ]);
}
