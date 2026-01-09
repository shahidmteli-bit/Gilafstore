<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/error_logger.php';

require_admin();

$action = $_POST['action'] ?? '';

function handle_file_upload(array $file): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$file['type']])) {
        throw new RuntimeException('Unsupported image format');
    }

    $extension = $allowed[$file['type']];
    $filename = uniqid('product_', true) . '.' . $extension;
    $uploadDir = __DIR__ . '/assets/images/products/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new RuntimeException('Unable to create upload directory. Please check folder permissions.');
        }
        chmod($uploadDir, 0777);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save uploaded file. Please check folder permissions.');
    }
    
    chmod($destination, 0644);

    return $filename;
}

function handle_lab_report_upload(array $file): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Lab report upload failed');
    }

    // Check file type
    if ($file['type'] !== 'application/pdf') {
        throw new RuntimeException('Lab report must be a PDF file');
    }

    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Lab report file size must be less than 5MB');
    }

    $filename = uniqid('lab_report_', true) . '.pdf';
    $uploadDir = __DIR__ . '/assets/lab_reports/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new RuntimeException('Unable to create lab reports directory');
        }
        chmod($uploadDir, 0777);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save lab report file');
    }
    
    chmod($destination, 0644);

    return $filename;
}

try {
    switch ($action) {
        case 'create_product':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $costPrice = (float)($_POST['cost_price'] ?? 0);
            $sellingPrice = (float)($_POST['selling_price'] ?? 0);
            $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
            $highlights = trim($_POST['highlights'] ?? '');
            
            // Handle weight from new simplified form
            $weightValue = (float)($_POST['weight_value'] ?? 0);
            $weightUnit = trim($_POST['weight_unit'] ?? 'g');
            $netWeight = $weightValue > 0 ? $weightValue . $weightUnit : '';
            
            // Set unit_type to net_weight by default (since we only use weight now)
            $unitType = 'net_weight';

            if ($name === '' || $description === '' || !$categoryId || $costPrice < 0 || $sellingPrice < 0 || $stockQuantity < 0) {
                throw new RuntimeException('Please fill in all product fields correctly');
            }

            // Handle image uploads (minimum 2, maximum 4)
            $image1 = handle_file_upload($_FILES['image_1'] ?? []);
            $image2 = handle_file_upload($_FILES['image_2'] ?? []);
            $image3 = handle_file_upload($_FILES['image_3'] ?? []);
            $image4 = handle_file_upload($_FILES['image_4'] ?? []);
            
            // Validate minimum 2 images required
            if (!$image1 || !$image2) {
                throw new RuntimeException('At least 2 product images are required');
            }

            admin_create_product([
                'name' => $name,
                'description' => $description,
                'category_id' => $categoryId,
                'unit_type' => $unitType,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'price' => $sellingPrice, // Keep for backward compatibility
                'stock_quantity' => $stockQuantity,
                'stock' => $stockQuantity, // Keep for backward compatibility
                'image_1' => $image1,
                'image_2' => $image2,
                'image_3' => $image3,
                'image_4' => $image4,
                'image' => $image1, // Keep for backward compatibility
                'highlights' => $highlights,
                'net_weight' => $netWeight,
            ]);

            redirect_with_message('/admin/manage_products.php', 'Product created successfully');
            break;

        case 'update_product':
            $productId = (int)($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $ean = trim($_POST['ean'] ?? '');

            if (!$productId || $name === '' || $description === '' || !$categoryId || $price < 0 || $stock < 0) {
                throw new RuntimeException('Please fill in all product fields correctly');
            }

            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = handle_file_upload($_FILES['image']);
            }

            admin_update_product($productId, [
                'name' => $name,
                'description' => $description,
                'category_id' => $categoryId,
                'price' => $price,
                'stock' => $stock,
                'ean' => $ean,
                'image' => $image,
            ]);

            redirect_with_message('/admin/product_edit.php?id=' . $productId, 'Product updated successfully');
            break;

        case 'delete_product':
            $productId = (int)($_POST['product_id'] ?? 0);
            if (!$productId) {
                throw new RuntimeException('Invalid product');
            }
            admin_delete_product($productId);
            redirect_with_message('/admin/manage_products.php', 'Product deleted', 'info');
            break;

        case 'create_category':
            $name = trim($_POST['name'] ?? '');
            $categoryCode = trim($_POST['category_code'] ?? '');
            
            log_info('CAT000', 'Category creation attempt', ['name' => $name, 'code' => $categoryCode]);
            
            if ($name === '') {
                log_error('CAT001', 'Category creation failed - empty name', ['submitted_name' => $_POST['name'] ?? 'null']);
                throw new RuntimeException('[CAT001] Category name is required');
            }
            
            try {
                admin_create_category($name, $categoryCode);
                log_success('CAT100', 'Category created successfully', ['name' => $name, 'code' => $categoryCode]);
                redirect_with_message('/admin/manage_categories.php', 'Category created successfully');
            } catch (Exception $e) {
                log_error('CAT002', 'Category creation failed - database error', [
                    'name' => $name,
                    'code' => $categoryCode,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new RuntimeException('[CAT002] Failed to create category: ' . $e->getMessage());
            }
            break;

        case 'update_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $categoryCode = trim($_POST['category_code'] ?? '');
            
            log_info('CAT010', 'Category update attempt', ['id' => $categoryId, 'name' => $name, 'code' => $categoryCode]);
            
            if (!$categoryId) {
                log_error('CAT003', 'Category update failed - invalid ID', ['id' => $categoryId]);
                throw new RuntimeException('[CAT003] Invalid category ID');
            }
            
            if ($name === '') {
                log_error('CAT001', 'Category update failed - empty name', ['id' => $categoryId]);
                throw new RuntimeException('[CAT001] Category name is required');
            }
            
            try {
                admin_update_category($categoryId, $name, $categoryCode);
                log_success('CAT101', 'Category updated successfully', ['id' => $categoryId, 'name' => $name, 'code' => $categoryCode]);
                redirect_with_message('/admin/manage_categories.php', 'Category updated');
            } catch (Exception $e) {
                log_error('CAT004', 'Category update failed - database error', [
                    'id' => $categoryId,
                    'name' => $name,
                    'code' => $categoryCode,
                    'error' => $e->getMessage()
                ]);
                throw new RuntimeException('[CAT004] Failed to update category: ' . $e->getMessage());
            }
            break;

        case 'delete_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            
            log_info('CAT020', 'Category delete attempt', ['id' => $categoryId]);
            
            if (!$categoryId) {
                log_error('CAT005', 'Category delete failed - invalid ID', ['id' => $categoryId]);
                throw new RuntimeException('[CAT005] Invalid category ID');
            }
            
            try {
                admin_delete_category($categoryId);
                log_success('CAT102', 'Category deleted successfully', ['id' => $categoryId]);
                redirect_with_message('/admin/manage_categories.php', 'Category deleted', 'info');
            } catch (Exception $e) {
                log_error('CAT007', 'Category delete failed - database error', [
                    'id' => $categoryId,
                    'error' => $e->getMessage()
                ]);
                throw new RuntimeException('[CAT007] Failed to delete category: ' . $e->getMessage());
            }
            break;

        case 'update_order_status':
            $orderId = (int)($_POST['order_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $courierCompany = trim($_POST['courier_company'] ?? '');
            $trackingId = trim($_POST['tracking_id'] ?? '');
            
            if (!$orderId || !in_array($status, ['pending', 'accepted', 'shipped', 'delivered'], true)) {
                throw new RuntimeException('Invalid order update');
            }
            
            // If status is shipped, courier and tracking are required
            if ($status === 'shipped' && (empty($courierCompany) || empty($trackingId))) {
                throw new RuntimeException('Courier company and tracking ID are required for shipped orders');
            }
            
            // Update order status with courier info
            $db = get_db_connection();
            
            // Get old status for history (use order_status column from orders table)
            $oldOrder = $db->prepare("SELECT order_status FROM orders WHERE id = ?");
            $oldOrder->execute([$orderId]);
            $oldStatus = $oldOrder->fetchColumn();
            
            // Update order
            $sql = "UPDATE orders SET order_status = ?, courier_company = ?, tracking_id = ?";
            $params = [$status, $courierCompany ?: null, $trackingId ?: null];
            
            if ($status === 'shipped') {
                $sql .= ", picked_up_at = NOW()";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $orderId;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Log status change in history
            $historyStmt = $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $adminId = $_SESSION['user']['id'] ?? null;
            $notes = $courierCompany ? "Courier: $courierCompany, Tracking: $trackingId" : null;
            $historyStmt->execute([$orderId, $oldStatus, $status, $adminId, $notes]);
            
            redirect_with_message('/admin/manage_orders.php', 'Order status updated successfully');
            break;

        case 'manage_user_access':
            $userId = (int)($_POST['user_id'] ?? 0);
            $action = $_POST['access_action'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            
            if (!$userId || !in_array($action, ['block', 'unblock', 'restrict', 'unrestrict'], true)) {
                throw new RuntimeException('Invalid user access action');
            }
            
            if (in_array($action, ['block', 'restrict']) && empty($reason)) {
                throw new RuntimeException('Reason is required for blocking or restricting users');
            }
            
            $db = get_db_connection();
            $adminId = $_SESSION['user']['id'] ?? null;
            
            // Update user status based on action
            switch ($action) {
                case 'block':
                    $stmt = $db->prepare("UPDATE users SET is_blocked = 1, is_restricted = 0, restriction_reason = ?, blocked_at = NOW(), blocked_by = ? WHERE id = ?");
                    $stmt->execute([$reason, $adminId, $userId]);
                    
                    // Log in history
                    $histStmt = $db->prepare("INSERT INTO user_restriction_history (user_id, action, reason, admin_id) VALUES (?, 'blocked', ?, ?)");
                    $histStmt->execute([$userId, $reason, $adminId]);
                    
                    $message = 'User has been blocked successfully';
                    break;
                    
                case 'unblock':
                    $stmt = $db->prepare("UPDATE users SET is_blocked = 0, is_restricted = 0, restriction_reason = NULL, blocked_at = NULL, blocked_by = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Log in history
                    $histStmt = $db->prepare("INSERT INTO user_restriction_history (user_id, action, reason, admin_id) VALUES (?, 'unblocked', ?, ?)");
                    $histStmt->execute([$userId, $reason ?: 'Access restored', $adminId]);
                    
                    $message = 'User has been unblocked successfully';
                    break;
                    
                case 'restrict':
                    $stmt = $db->prepare("UPDATE users SET is_blocked = 0, is_restricted = 1, restriction_reason = ? WHERE id = ?");
                    $stmt->execute([$reason, $userId]);
                    
                    // Log in history
                    $histStmt = $db->prepare("INSERT INTO user_restriction_history (user_id, action, reason, admin_id) VALUES (?, 'restricted', ?, ?)");
                    $histStmt->execute([$userId, $reason, $adminId]);
                    
                    $message = 'User has been restricted successfully';
                    break;
                    
                case 'unrestrict':
                    $stmt = $db->prepare("UPDATE users SET is_restricted = 0, restriction_reason = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Log in history
                    $histStmt = $db->prepare("INSERT INTO user_restriction_history (user_id, action, reason, admin_id) VALUES (?, 'unrestricted', ?, ?)");
                    $histStmt->execute([$userId, $reason ?: 'Restrictions removed', $adminId]);
                    
                    $message = 'User restrictions have been removed successfully';
                    break;
            }
            
            redirect_with_message('/admin/manage_users.php', $message, 'success');
            break;

        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if (!$userId) {
                throw new RuntimeException('Invalid user');
            }
            if (!empty($_SESSION['user']) && (int)$_SESSION['user']['id'] === $userId) {
                throw new RuntimeException('You cannot delete your own admin account');
            }
            admin_delete_user($userId);
            redirect_with_message('/admin/manage_users.php', 'User removed', 'info');
            break;

        default:
            redirect_with_message('/admin/index.php', 'Unknown action requested', 'danger');
    }
} catch (Throwable $exception) {
    redirect_with_message($_SERVER['HTTP_REFERER'] ?? '/admin/index.php', $exception->getMessage(), 'danger');
}
