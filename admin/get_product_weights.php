<?php
/**
 * API endpoint to fetch product weights for batch code generator
 * Returns all weights associated with a product
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID', 'weights' => [], 'debug' => 'product_id was 0 or invalid']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Fetch all weights for the product from product_weights table (including EAN)
    $stmt = $db->prepare(
        "SELECT id, display_weight, price, ean FROM product_weights WHERE product_id = ? ORDER BY sort_order ASC, weight_value ASC"
    );
    $stmt->execute([$productId]);
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add product_id to each weight for consistency
    foreach ($weights as &$w) {
        $w['product_id'] = $productId;
        $w['is_default'] = 0;
    }
    unset($w);
    
    // Fetch batches that should BLOCK weight selection
    // Table is batch_codes (not product_batches)
    // Blocking statuses: production, quality_testing, quality_approved, released_for_sale, in_distribution
    // Non-blocking (allow new batch): expired, recalled, blocked, sold_out, archived, rejected
    $activeBatchStmt = $db->prepare(
        "SELECT weight_id, net_weight, batch_code, status 
         FROM batch_codes 
         WHERE product_id = ? AND status IN ('production', 'quality_testing', 'quality_approved', 'released_for_sale', 'in_distribution')
         ORDER BY created_at DESC"
    );
    $activeBatchStmt->execute([$productId]);
    $activeBatches = $activeBatchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create maps for matching - by weight_id AND by net_weight string
    $activeBatchByWeightId = [];
    $activeBatchByNetWeight = [];
    foreach ($activeBatches as $batch) {
        if (!empty($batch['weight_id']) && !isset($activeBatchByWeightId[$batch['weight_id']])) {
            $activeBatchByWeightId[$batch['weight_id']] = $batch;
        }
        if (!empty($batch['net_weight']) && !isset($activeBatchByNetWeight[$batch['net_weight']])) {
            $activeBatchByNetWeight[$batch['net_weight']] = $batch;
        }
    }
    
    // Add has_active_batch flag to each weight (check both weight_id and display_weight match)
    foreach ($weights as &$weight) {
        $weightId = $weight['id'];
        $displayWeight = $weight['display_weight'];
        
        // Check by weight_id first, then by display_weight string match
        if (isset($activeBatchByWeightId[$weightId])) {
            $weight['has_active_batch'] = true;
            $weight['active_batch_code'] = $activeBatchByWeightId[$weightId]['batch_code'];
            $weight['active_batch_status'] = $activeBatchByWeightId[$weightId]['status'];
        } elseif (isset($activeBatchByNetWeight[$displayWeight])) {
            $weight['has_active_batch'] = true;
            $weight['active_batch_code'] = $activeBatchByNetWeight[$displayWeight]['batch_code'];
            $weight['active_batch_status'] = $activeBatchByNetWeight[$displayWeight]['status'];
        } else {
            $weight['has_active_batch'] = false;
            $weight['active_batch_code'] = null;
            $weight['active_batch_status'] = null;
        }
    }
    unset($weight);
    
    if ($weights && count($weights) > 0) {
        echo json_encode([
            'success' => true,
            'weights' => $weights,
            'count' => count($weights),
            'active_batches' => $activeBatches
        ]);
    } else {
        // Fallback: Check if product has a weight in the products table
        $stmt2 = $db->prepare("SELECT id, name, weight FROM products WHERE id = ?");
        $stmt2->execute([$productId]);
        $product = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($product && !empty($product['weight'])) {
            // Return product's base weight as fallback
            echo json_encode([
                'success' => true,
                'weights' => [
                    [
                        'id' => 0,
                        'product_id' => $productId,
                        'weight_value' => 0,
                        'weight_unit' => '',
                        'display_weight' => $product['weight'],
                        'price' => 0,
                        'is_default' => 1
                    ]
                ],
                'count' => 1,
                'fallback' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No weights found for this product',
                'weights' => []
            ]);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching product weights: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'weights' => []
    ]);
}
