<?php
/**
 * Get Product Weights API
 * Returns all weights for a specific product
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
        SELECT id, weight_value, weight_unit, display_weight, is_default, sort_order
        FROM product_weights
        WHERE product_id = :product_id
        ORDER BY sort_order ASC, weight_value ASC
    ");
    
    $stmt->execute([':product_id' => $productId]);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'weights' => $weights
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching product weights: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading weights'
    ]);
}
