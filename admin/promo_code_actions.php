<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $description = trim($_POST['description'] ?? '');
            $promoMessage = trim($_POST['promo_message'] ?? '');
            $eligibilityType = $_POST['eligibility_type'] ?? 'all_users';
            $inactiveDays = !empty($_POST['inactive_days']) ? intval($_POST['inactive_days']) : null;
            $displayInHeader = isset($_POST['display_in_header']) ? 1 : 0;
            $discountType = $_POST['discount_type'] ?? 'percentage';
            $discountValue = floatval($_POST['discount_value'] ?? 0);
            $minOrderValue = floatval($_POST['min_order_value'] ?? 0);
            $maxDiscount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
            $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
            $validFrom = $_POST['valid_from'] ?? '';
            $validUntil = $_POST['valid_until'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if (empty($code)) {
                throw new Exception('Promo code is required');
            }
            
            if (!preg_match('/^[A-Z0-9]+$/', $code)) {
                throw new Exception('Promo code must contain only uppercase letters and numbers');
            }
            
            if ($discountValue <= 0) {
                throw new Exception('Discount value must be greater than 0');
            }
            
            if ($discountType === 'percentage' && $discountValue > 100) {
                throw new Exception('Percentage discount cannot exceed 100%');
            }
            
            if (empty($validFrom) || empty($validUntil)) {
                throw new Exception('Valid from and until dates are required');
            }
            
            if (strtotime($validFrom) >= strtotime($validUntil)) {
                throw new Exception('Valid until date must be after valid from date');
            }
            
            // Check if code already exists
            $existing = db_fetch("SELECT id FROM promo_codes WHERE code = ?", [$code]);
            if ($existing) {
                throw new Exception('Promo code already exists');
            }
            
            // Insert promo code
            $sql = "INSERT INTO promo_codes (code, description, promo_message, eligibility_type, inactive_days, display_in_header, 
                    discount_type, discount_value, min_order_value, max_discount, usage_limit, 
                    valid_from, valid_until, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            db_query($sql, [
                $code, $description, $promoMessage, $eligibilityType, $inactiveDays, $displayInHeader,
                $discountType, $discountValue, $minOrderValue, $maxDiscount, $usageLimit, 
                $validFrom, $validUntil, $isActive
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Promo code created successfully']);
            break;
            
        case 'edit':
            $promoId = intval($_POST['promo_id'] ?? 0);
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $description = trim($_POST['description'] ?? '');
            $promoMessage = trim($_POST['promo_message'] ?? '');
            $eligibilityType = $_POST['eligibility_type'] ?? 'all_users';
            $inactiveDays = !empty($_POST['inactive_days']) ? intval($_POST['inactive_days']) : null;
            $displayInHeader = isset($_POST['display_in_header']) ? 1 : 0;
            $discountType = $_POST['discount_type'] ?? 'percentage';
            $discountValue = floatval($_POST['discount_value'] ?? 0);
            $minOrderValue = floatval($_POST['min_order_value'] ?? 0);
            $maxDiscount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
            $usageLimit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
            $validFrom = $_POST['valid_from'] ?? '';
            $validUntil = $_POST['valid_until'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if ($promoId <= 0) {
                throw new Exception('Invalid promo code ID');
            }
            
            if (empty($code)) {
                throw new Exception('Promo code is required');
            }
            
            if (!preg_match('/^[A-Z0-9]+$/', $code)) {
                throw new Exception('Promo code must contain only uppercase letters and numbers');
            }
            
            if ($discountValue <= 0) {
                throw new Exception('Discount value must be greater than 0');
            }
            
            if ($discountType === 'percentage' && $discountValue > 100) {
                throw new Exception('Percentage discount cannot exceed 100%');
            }
            
            // Check if code already exists for another promo
            $existing = db_fetch("SELECT id FROM promo_codes WHERE code = ? AND id != ?", [$code, $promoId]);
            if ($existing) {
                throw new Exception('Promo code already exists');
            }
            
            // Update promo code
            $sql = "UPDATE promo_codes SET 
                    code = ?, description = ?, promo_message = ?, eligibility_type = ?, inactive_days = ?, display_in_header = ?,
                    discount_type = ?, discount_value = ?, min_order_value = ?, max_discount = ?, usage_limit = ?,
                    valid_from = ?, valid_until = ?, is_active = ?
                    WHERE id = ?";
            
            db_query($sql, [
                $code, $description, $promoMessage, $eligibilityType, $inactiveDays, $displayInHeader,
                $discountType, $discountValue, $minOrderValue, $maxDiscount, $usageLimit, 
                $validFrom, $validUntil, $isActive, $promoId
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Promo code updated successfully']);
            break;
            
        case 'delete':
            $promoId = intval($_POST['promo_id'] ?? 0);
            
            if ($promoId <= 0) {
                throw new Exception('Invalid promo code ID');
            }
            
            // Delete promo code (usage history will be cascade deleted)
            db_query("DELETE FROM promo_codes WHERE id = ?", [$promoId]);
            
            echo json_encode(['success' => true, 'message' => 'Promo code deleted successfully']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
