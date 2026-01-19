<?php
/**
 * Product Weight Management API
 * Handles AJAX requests for adding, removing, and updating product weights
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
    $db = get_db_connection();
    
    switch ($action) {
        case 'add_weight':
            $productId = (int)($_POST['product_id'] ?? 0);
            $weightValue = (float)($_POST['weight_value'] ?? 0);
            $weightUnit = $_POST['weight_unit'] ?? 'g';
            
            if ($productId <= 0 || $weightValue <= 0) {
                throw new Exception('Invalid product ID or weight value');
            }
            
            // Create display weight
            $displayWeight = $weightValue . ' ' . $weightUnit;
            
            // Check if this weight already exists for this product
            $checkStmt = $db->prepare("
                SELECT id FROM product_weights 
                WHERE product_id = :product_id 
                AND weight_value = :weight_value 
                AND weight_unit = :weight_unit
            ");
            $checkStmt->execute([
                ':product_id' => $productId,
                ':weight_value' => $weightValue,
                ':weight_unit' => $weightUnit
            ]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('This weight already exists for this product');
            }
            
            // Get next sort order
            $sortStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM product_weights WHERE product_id = :product_id");
            $sortStmt->execute([':product_id' => $productId]);
            $sortOrder = $sortStmt->fetch(PDO::FETCH_ASSOC)['next_order'];
            
            // Insert new weight
            $stmt = $db->prepare("
                INSERT INTO product_weights (product_id, weight_value, weight_unit, display_weight, is_default, sort_order)
                VALUES (:product_id, :weight_value, :weight_unit, :display_weight, :is_default, :sort_order)
            ");
            
            $stmt->execute([
                ':product_id' => $productId,
                ':weight_value' => $weightValue,
                ':weight_unit' => $weightUnit,
                ':display_weight' => $displayWeight,
                ':is_default' => 0,
                ':sort_order' => $sortOrder
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Weight added successfully';
            $response['weight_id'] = $db->lastInsertId();
            $response['display_weight'] = $displayWeight;
            break;
            
        case 'remove_weight':
            $weightId = (int)($_POST['weight_id'] ?? 0);
            
            if ($weightId <= 0) {
                throw new Exception('Invalid weight ID');
            }
            
            // Check if this is the only weight for the product
            $checkStmt = $db->prepare("
                SELECT product_id FROM product_weights WHERE id = :weight_id
            ");
            $checkStmt->execute([':weight_id' => $weightId]);
            $weight = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$weight) {
                throw new Exception('Weight not found');
            }
            
            $countStmt = $db->prepare("
                SELECT COUNT(*) as count FROM product_weights WHERE product_id = :product_id
            ");
            $countStmt->execute([':product_id' => $weight['product_id']]);
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count <= 1) {
                throw new Exception('Cannot remove the last weight. Product must have at least one weight.');
            }
            
            // Delete weight
            $stmt = $db->prepare("DELETE FROM product_weights WHERE id = :weight_id");
            $stmt->execute([':weight_id' => $weightId]);
            
            $response['success'] = true;
            $response['message'] = 'Weight removed successfully';
            break;
            
        case 'set_default_weight':
            $weightId = (int)($_POST['weight_id'] ?? 0);
            
            if ($weightId <= 0) {
                throw new Exception('Invalid weight ID');
            }
            
            // Get product_id for this weight
            $stmt = $db->prepare("SELECT product_id FROM product_weights WHERE id = :weight_id");
            $stmt->execute([':weight_id' => $weightId]);
            $weight = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$weight) {
                throw new Exception('Weight not found');
            }
            
            // Remove default from all weights of this product
            $stmt = $db->prepare("UPDATE product_weights SET is_default = 0 WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $weight['product_id']]);
            
            // Set new default
            $stmt = $db->prepare("UPDATE product_weights SET is_default = 1 WHERE id = :weight_id");
            $stmt->execute([':weight_id' => $weightId]);
            
            $response['success'] = true;
            $response['message'] = 'Default weight updated';
            break;
            
        case 'get_weights':
            $productId = (int)($_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                throw new Exception('Invalid product ID');
            }
            
            $stmt = $db->prepare("
                SELECT id, weight_value, weight_unit, display_weight, is_default, sort_order
                FROM product_weights
                WHERE product_id = :product_id
                ORDER BY sort_order ASC
            ");
            $stmt->execute([':product_id' => $productId]);
            $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['weights'] = $weights;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Product weight action error: " . $e->getMessage());
}

echo json_encode($response);
