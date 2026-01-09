<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $productId = (int)$_POST['product_id'];
            $discountType = $_POST['discount_type'];
            $discountValue = (float)$_POST['discount_value'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate
            if (!$productId || !in_array($discountType, ['percentage', 'flat'])) {
                throw new Exception('Invalid input data');
            }
            
            if ($discountType === 'percentage' && ($discountValue < 0 || $discountValue > 100)) {
                throw new Exception('Percentage must be between 0 and 100');
            }
            
            if ($discountValue < 0) {
                throw new Exception('Discount value cannot be negative');
            }
            
            // Check if discount already exists for this product
            $existing = db_fetch('SELECT id FROM product_discounts WHERE product_id = ?', [$productId]);
            if ($existing) {
                throw new Exception('A discount already exists for this product. Please edit or delete it first.');
            }
            
            // Insert discount
            $sql = 'INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)';
            db_query($sql, [$productId, $discountType, $discountValue, $startDate, $endDate, $isActive]);
            
            echo json_encode(['success' => true, 'message' => 'Discount added successfully']);
            break;
            
        case 'edit':
            $discountId = (int)$_POST['discount_id'];
            $productId = (int)$_POST['product_id'];
            $discountType = $_POST['discount_type'];
            $discountValue = (float)$_POST['discount_value'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate
            if (!$discountId || !$productId || !in_array($discountType, ['percentage', 'flat'])) {
                throw new Exception('Invalid input data');
            }
            
            if ($discountType === 'percentage' && ($discountValue < 0 || $discountValue > 100)) {
                throw new Exception('Percentage must be between 0 and 100');
            }
            
            if ($discountValue < 0) {
                throw new Exception('Discount value cannot be negative');
            }
            
            // Check if another discount exists for this product (excluding current)
            $existing = db_fetch('SELECT id FROM product_discounts WHERE product_id = ? AND id != ?', [$productId, $discountId]);
            if ($existing) {
                throw new Exception('Another discount already exists for this product.');
            }
            
            // Update discount
            $sql = 'UPDATE product_discounts 
                    SET product_id = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ?, is_active = ? 
                    WHERE id = ?';
            db_query($sql, [$productId, $discountType, $discountValue, $startDate, $endDate, $isActive, $discountId]);
            
            echo json_encode(['success' => true, 'message' => 'Discount updated successfully']);
            break;
            
        case 'delete':
            $discountId = (int)$_POST['discount_id'];
            
            if (!$discountId) {
                throw new Exception('Invalid discount ID');
            }
            
            // Delete discount
            db_query('DELETE FROM product_discounts WHERE id = ?', [$discountId]);
            
            echo json_encode(['success' => true, 'message' => 'Discount deleted successfully']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
