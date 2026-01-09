<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';

require_login();

$userId = (int)$_SESSION['user']['id'];
$db = get_db_connection();

// Create table if it doesn't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS user_addresses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'home',
        flat_number VARCHAR(100),
        address_line1 VARCHAR(255) NOT NULL,
        address_line2 VARCHAR(255),
        landmark VARCHAR(255),
        city VARCHAR(100) NOT NULL,
        state VARCHAR(100) NOT NULL,
        zip_code VARCHAR(20) NOT NULL,
        country VARCHAR(100) DEFAULT 'India',
        phone VARCHAR(20),
        is_default BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add new columns if they don't exist (for existing tables)
    $db->exec("ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS flat_number VARCHAR(100) AFTER type");
    $db->exec("ALTER TABLE user_addresses ADD COLUMN IF NOT EXISTS landmark VARCHAR(255) AFTER address_line2");
} catch (Exception $e) {
    // Table might already exist, continue
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!validate_csrf_token($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'add':
                // Validate address data
                $addressData = [
                    'address_line1' => trim($_POST['address_line1'] ?? ''),
                    'city' => trim($_POST['city'] ?? ''),
                    'state' => trim($_POST['state'] ?? ''),
                    'zip_code' => trim($_POST['zip_code'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? '')
                ];
                
                $validation = validate_address_data($addressData);
                if (!$validation['valid']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Validation failed',
                        'errors' => $validation['errors']
                    ]);
                    break;
                }
                
                // Begin transaction for data consistency
                $db->beginTransaction();
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO user_addresses (user_id, type, flat_number, address_line1, address_line2, landmark, city, state, zip_code, phone, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $_POST['type'] ?? 'home',
                        sanitize_input_safe($_POST['flat_number'] ?? ''),
                        sanitize_input_safe($addressData['address_line1']),
                        sanitize_input_safe($_POST['address_line2'] ?? ''),
                        sanitize_input_safe($_POST['landmark'] ?? ''),
                        sanitize_input_safe($addressData['city']),
                        $addressData['state'],
                        $addressData['zip_code'],
                        $addressData['phone'],
                        isset($_POST['is_default']) ? 1 : 0
                    ]);
                    
                    $newAddressId = $db->lastInsertId();
                    
                    // If this is set as default, unset all other defaults
                    if (isset($_POST['is_default']) && $_POST['is_default']) {
                        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id != ?")
                           ->execute([$userId, $newAddressId]);
                    }
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Address added successfully']);
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
                
            case 'edit':
                $addressId = (int)$_POST['id'];
                
                // Verify address belongs to user
                $checkStmt = $db->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
                $checkStmt->execute([$addressId, $userId]);
                if (!$checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Address not found or access denied']);
                    break;
                }
                
                // Validate address data
                $addressData = [
                    'address_line1' => trim($_POST['address_line1'] ?? ''),
                    'city' => trim($_POST['city'] ?? ''),
                    'state' => trim($_POST['state'] ?? ''),
                    'zip_code' => trim($_POST['zip_code'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? '')
                ];
                
                $validation = validate_address_data($addressData);
                if (!$validation['valid']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Validation failed',
                        'errors' => $validation['errors']
                    ]);
                    break;
                }
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    $stmt = $db->prepare("
                        UPDATE user_addresses 
                        SET type = ?, flat_number = ?, address_line1 = ?, address_line2 = ?, landmark = ?, city = ?, state = ?, zip_code = ?, phone = ?, is_default = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $_POST['type'] ?? 'home',
                        sanitize_input_safe($_POST['flat_number'] ?? ''),
                        sanitize_input_safe($addressData['address_line1']),
                        sanitize_input_safe($_POST['address_line2'] ?? ''),
                        sanitize_input_safe($_POST['landmark'] ?? ''),
                        sanitize_input_safe($addressData['city']),
                        $addressData['state'],
                        $addressData['zip_code'],
                        $addressData['phone'],
                        isset($_POST['is_default']) ? 1 : 0,
                        $addressId,
                        $userId
                    ]);
                    
                    // If this is set as default, unset all other defaults
                    if (isset($_POST['is_default']) && $_POST['is_default']) {
                        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id != ?")
                           ->execute([$userId, $addressId]);
                    }
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Address updated successfully']);
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
                
            case 'delete':
                $addressId = (int)$_POST['id'];
                
                // Verify address belongs to user
                $checkStmt = $db->prepare("SELECT id, is_default FROM user_addresses WHERE id = ? AND user_id = ?");
                $checkStmt->execute([$addressId, $userId]);
                $address = $checkStmt->fetch();
                
                if (!$address) {
                    echo json_encode(['success' => false, 'message' => 'Address not found or access denied']);
                    break;
                }
                
                // Delete the address
                $stmt = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addressId, $userId]);
                
                echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
                break;
                
            case 'get':
                $addressId = (int)$_POST['id'];
                $stmt = $db->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addressId, $userId]);
                $address = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$address) {
                    echo json_encode(['success' => false, 'message' => 'Address not found']);
                    break;
                }
                
                echo json_encode(['success' => true, 'address' => $address]);
                break;
                
            case 'select_for_checkout':
                $addressId = (int)$_POST['id'];
                $stmt = $db->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$addressId, $userId]);
                $address = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($address) {
                    $_SESSION['selected_checkout_address'] = $address;
                    echo json_encode(['success' => true, 'message' => 'Address selected']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Address not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Address management error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit;
}

// Fetch all addresses
$stmt = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if coming from checkout
$fromCheckout = isset($_GET['from']) && $_GET['from'] === 'checkout';

$pageTitle = 'Manage Addresses — Gilaf Store';
include __DIR__ . '/../includes/new-header.php';
include __DIR__ . '/../includes/delete-modal.php';
?>

<style>
:root {
    --color-green: #1A3C34;
    --color-gold: #C5A089;
    --color-primary: #3b82f6;
    --color-primary-dark: #2563eb;
}

.addresses-page {
    background: #f8f9fa;
    min-height: calc(100vh - 120px);
    padding: 40px 0;
}

.addresses-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.addresses-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.btn-back-profile {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(26, 60, 52, 0.2);
}

.btn-back-profile:hover {
    background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}

.btn-back-profile i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .btn-back-profile {
        width: 100%;
        justify-content: center;
    }
}

.page-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    color: var(--color-green);
}

.btn-add-address {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--color-green) 0%, #2d5a4e 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-add-address:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
}

.address-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.address-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s ease;
    position: relative;
}

.address-card.default {
    border-color: var(--color-primary);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.address-card:hover {
    border-color: var(--color-primary);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
    transform: translateY(-5px);
}

.default-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.address-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f3f4f6;
}

.address-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--color-green) 0%, #2d5a4e 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

.address-type {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--color-green);
    font-family: 'Playfair Display', serif;
    text-transform: capitalize;
}

.address-details {
    margin-bottom: 20px;
}

.address-details p {
    color: #666;
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 5px 0;
}

.address-actions {
    display: flex;
    gap: 12px;
}

.btn-edit, .btn-delete {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-edit {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    position: relative;
}

.modal-header h2 {
    font-family: 'Playfair Display', serif;
    color: var(--color-green);
    font-size: 1.8rem;
    padding-right: 50px;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #f0f0f0;
    border: 2px solid #e0e0e0;
    font-size: 1.8rem;
    color: #333;
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    z-index: 9999;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.modal-close:hover {
    background: #ef4444;
    color: white;
    transform: rotate(90deg);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--color-green);
    margin-bottom: 8px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-group label {
    margin: 0;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn-save, .btn-cancel {
    flex: 1;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-save {
    background: linear-gradient(135deg, var(--color-green) 0%, #2d5a4e 100%);
    color: white;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
}

.btn-cancel {
    background: #f0f0f0;
    color: #666;
}

.btn-cancel:hover {
    background: #e0e0e0;
}

.btn-use-location {
    width: 100%;
    padding: 12px 20px;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-use-location:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.btn-use-location.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-use-location.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

#stateSearch {
    width: 100%;
    padding: 10px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
}

#stateSearch:focus {
    outline: none;
    border-color: var(--color-primary);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 16px;
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(26, 60, 52, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.5rem;
    color: var(--color-primary);
}

/* Delete Confirmation Modal */
.delete-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

.delete-modal.active {
    display: flex;
}

.delete-modal-content {
    background: white;
    border-radius: 24px;
    padding: 40px;
    max-width: 480px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: scaleIn 0.3s ease-out;
    text-align: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.delete-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    animation: pulse 2s infinite;
}

.delete-icon i {
    font-size: 2.5rem;
    color: #ef4444;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.delete-modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: var(--color-green);
    margin-bottom: 12px;
}

.delete-modal p {
    color: #666;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 32px;
}

.delete-modal-actions {
    display: flex;
    gap: 12px;
}

.btn-confirm-delete,
.btn-cancel-delete {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-confirm-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-confirm-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.btn-cancel-delete {
    background: #f0f0f0;
    color: #666;
}

.btn-cancel-delete:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
    .address-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .delete-modal-content {
        padding: 32px 24px;
    }
    
    .delete-modal-actions {
        flex-direction: column;
    }
}

.global-add-address {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
    padding: 0 20px;
}

.btn-global-add {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 14px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-global-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-global-add i {
    font-size: 18px;
}

.btn-delete-checkout:hover {
    background: #fee2e2 !important;
    border-color: #b91c1c !important;
    color: #b91c1c !important;
}
</style>

<div class="addresses-page">
    <div class="addresses-container">
        <div class="addresses-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-map-marker-alt"></i> <?= $fromCheckout ? 'Select Delivery Address' : 'Manage Addresses'; ?></h1>
                    <p style="color: #666; margin-top: 5px;"><?= $fromCheckout ? 'Choose an address or add a new one' : 'Save multiple addresses for faster checkout'; ?></p>
                </div>
                <a href="<?= base_url('user/profile.php'); ?>" class="btn-back-profile">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>

        <?php if (empty($addresses)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3 style="color: var(--color-green); margin-bottom: 10px;">No Addresses Yet</h3>
                <p style="color: #666; margin-bottom: 20px;"><?= $fromCheckout ? 'Add your delivery address to continue with checkout' : 'Add your first address to make checkout faster'; ?></p>
                <button class="btn-add-address" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Address
                </button>
            </div>
        <?php else: ?>
            <div class="address-grid">
                <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                        <?php if ($address['is_default']): ?>
                            <div class="default-badge">
                                <i class="fas fa-star"></i> Default
                            </div>
                        <?php endif; ?>
                        
                        <div class="address-header">
                            <div class="address-icon">
                                <i class="fas fa-<?= $address['type'] === 'work' ? 'briefcase' : 'home' ?>"></i>
                            </div>
                            <div class="address-type"><?= htmlspecialchars($address['type']) ?></div>
                        </div>
                        
                        <div class="address-details">
                            <p><?= htmlspecialchars($address['address_line1']) ?></p>
                            <?php if ($address['address_line2']): ?>
                                <p><?= htmlspecialchars($address['address_line2']) ?></p>
                            <?php endif; ?>
                            <p><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['zip_code']) ?></p>
                            <?php if ($address['phone']): ?>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($address['phone']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="address-actions">
                            <?php if ($fromCheckout): ?>
                                <button class="btn-select-address" onclick="selectAddressForCheckout(<?= $address['id'] ?>)" style="flex: 1; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                    <i class="fas fa-check-circle"></i> DELIVER HERE
                                </button>
                                <button class="btn-delete-checkout" onclick="deleteAddress(<?= $address['id'] ?>)" style="flex: 1; background: white; color: #dc2626; padding: 12px; border: 2px solid #dc2626; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-left: 12px;">
                                    <i class="fas fa-trash"></i> DELETE
                                </button>
                            <?php else: ?>
                                <button class="btn-edit" onclick="openEditModal(<?= $address['id'] ?>)">
                                    <i class="fas fa-edit"></i> EDIT
                                </button>
                                <button class="btn-delete" onclick="deleteAddress(<?= $address['id'] ?>)">
                                    <i class="fas fa-trash"></i> DELETE
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($fromCheckout): ?>
            <!-- Global Add New Address Button -->
            <div class="global-add-address">
                <button class="btn-global-add" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> Add New Address
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3>Delete Address?</h3>
        <p>Are you sure you want to delete this address? This action cannot be undone.</p>
        <div class="delete-modal-actions">
            <button type="button" class="btn-cancel-delete" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-confirm-delete" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addressModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Address</h2>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addressForm">
            <input type="hidden" id="addressId" name="id">
            
            <div class="form-group">
                <label>Address Type *</label>
                <select name="type" id="addressType" required>
                    <option value="home">Home</option>
                    <option value="work">Work</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <button type="button" onclick="useMyLocation()" class="btn-use-location">
                    <i class="fas fa-location-arrow"></i> Use My Current Location
                </button>
            </div>
            
            <div class="form-group">
                <label>Flat/House Number</label>
                <input type="text" name="flat_number" id="flatNumber" placeholder="e.g., Flat 301, House #45">
            </div>
            
            <div class="form-group">
                <label>Address Line 1 *</label>
                <input type="text" name="address_line1" id="addressLine1" required>
            </div>
            
            <div class="form-group">
                <label>Address Line 2</label>
                <input type="text" name="address_line2" id="addressLine2">
            </div>
            
            <div class="form-group">
                <label>Landmark</label>
                <input type="text" name="landmark" id="landmark">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>City *</label>
                    <input type="text" name="city" id="city" required>
                </div>
                
                <div class="form-group">
                    <label>State / UT *</label>
                    <select name="state" id="state" required onkeyup="filterStates(event)" onkeydown="filterStates(event)">
                        <option value="">Select State/UT</option>
                        <optgroup label="States">
                            <option value="Andhra Pradesh">Andhra Pradesh</option>
                            <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                            <option value="Assam">Assam</option>
                            <option value="Bihar">Bihar</option>
                            <option value="Chhattisgarh">Chhattisgarh</option>
                            <option value="Goa">Goa</option>
                            <option value="Gujarat">Gujarat</option>
                            <option value="Haryana">Haryana</option>
                            <option value="Himachal Pradesh">Himachal Pradesh</option>
                            <option value="Jharkhand">Jharkhand</option>
                            <option value="Karnataka">Karnataka</option>
                            <option value="Kerala">Kerala</option>
                            <option value="Madhya Pradesh">Madhya Pradesh</option>
                            <option value="Maharashtra">Maharashtra</option>
                            <option value="Manipur">Manipur</option>
                            <option value="Meghalaya">Meghalaya</option>
                            <option value="Mizoram">Mizoram</option>
                            <option value="Nagaland">Nagaland</option>
                            <option value="Odisha">Odisha</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Rajasthan">Rajasthan</option>
                            <option value="Sikkim">Sikkim</option>
                            <option value="Tamil Nadu">Tamil Nadu</option>
                            <option value="Telangana">Telangana</option>
                            <option value="Tripura">Tripura</option>
                            <option value="Uttar Pradesh">Uttar Pradesh</option>
                            <option value="Uttarakhand">Uttarakhand</option>
                            <option value="West Bengal">West Bengal</option>
                        </optgroup>
                        <optgroup label="Union Territories">
                            <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                            <option value="Chandigarh">Chandigarh</option>
                            <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                            <option value="Delhi">Delhi</option>
                            <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                            <option value="Ladakh">Ladakh</option>
                            <option value="Lakshadweep">Lakshadweep</option>
                            <option value="Puducherry">Puducherry</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>PIN Code *</label>
                    <input type="text" name="zip_code" id="zipCode" required pattern="[0-9]{6}" title="Enter 6-digit PIN code">
                </div>
                
                <div class="form-group">
                    <label>Phone Number *</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="countryCode" readonly style="width: 80px; background: #f8f9fa; font-weight: 600;" value="+91">
                        <input type="tel" name="phone" id="phone" required pattern="[0-9]{10}" title="Enter 10-digit phone number" style="flex: 1;">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Country</label>
                <input type="text" name="country" id="country" value="India" readonly style="background: #f8f9fa;">
                <small style="color: #666; font-size: 0.85rem;">Auto-detected via IP address</small>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="is_default" id="isDefault">
                <label for="isDefault">Set as default address</label>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Address
                </button>
                <button type="button" class="btn-cancel" onclick="closeModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const fromCheckout = <?= $fromCheckout ? 'true' : 'false'; ?>;

function selectAddressForCheckout(addressId) {
    // Save selected address to session and redirect to checkout
    fetch('manage_addresses.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=select_for_checkout&id=' + addressId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= base_url('checkout.php'); ?>';
        } else {
            alert('Error selecting address. Please try again.');
        }
    });
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Address';
    document.getElementById('addressForm').reset();
    document.getElementById('addressId').value = '';
    document.getElementById('addressModal').classList.add('active');
    
    // Auto-detect country via IP
    detectCountryByIP();
}

// Detect country and country code automatically via IP address
async function detectCountryByIP() {
    try {
        // Use ipapi.co for free IP geolocation (no API key needed)
        const response = await fetch('https://ipapi.co/json/');
        if (response.ok) {
            const data = await response.json();
            console.log('IP Location:', data);
            
            const countryInput = document.getElementById('country');
            const countryCodeInput = document.getElementById('countryCode');
            
            if (data.country_name) {
                countryInput.value = data.country_name;
                console.log('✓ Country auto-detected:', data.country_name);
                
                // Set country code based on detected country
                if (data.country_calling_code) {
                    countryCodeInput.value = data.country_calling_code;
                    console.log('✓ Country code auto-detected:', data.country_calling_code);
                }
                
                // If not India, show notification
                if (data.country_name !== 'India') {
                    showNotification(`Country detected: ${data.country_name} (${data.country_calling_code}). You can edit if using VPN.`, 'info');
                    countryInput.readOnly = false;
                    countryInput.style.background = 'white';
                }
            }
        }
    } catch (error) {
        console.log('IP detection failed, using default India (+91)');
    }
}

function openEditModal(addressId) {
    document.getElementById('modalTitle').textContent = 'Edit Address';
    
    fetch('manage_addresses.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get&id=' + addressId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const addr = data.address;
            document.getElementById('addressId').value = addr.id;
            document.getElementById('addressType').value = addr.type;
            document.getElementById('flatNumber').value = addr.flat_number || '';
            document.getElementById('addressLine1').value = addr.address_line1;
            document.getElementById('addressLine2').value = addr.address_line2 || '';
            document.getElementById('landmark').value = addr.landmark || '';
            document.getElementById('city').value = addr.city;
            document.getElementById('state').value = addr.state;
            document.getElementById('zipCode').value = addr.zip_code;
            document.getElementById('phone').value = addr.phone || '';
            document.getElementById('isDefault').checked = addr.is_default == 1;
            document.getElementById('addressModal').classList.add('active');
        }
    });
}

function closeModal() {
    document.getElementById('addressModal').classList.remove('active');
}

// Use the reusable delete confirmation modal
function deleteAddress(addressId) {
    showDeleteConfirmation({
        title: 'Delete Address?',
        message: 'Are you sure you want to delete this address? This action cannot be undone.',
        onConfirm: function() {
            fetch('manage_addresses.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete&id=' + addressId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Address deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            });
        }
    });
}

document.getElementById('addressForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const addressId = document.getElementById('addressId').value;
    formData.append('action', addressId ? 'edit' : 'add');
    
    fetch('manage_addresses.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (fromCheckout) {
                // If coming from checkout, redirect back to checkout after adding address
                window.location.href = '<?= base_url('user/manage_addresses.php?from=checkout'); ?>';
            } else {
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
        }
    });
});

// Close modal on outside click
document.getElementById('addressModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// State search functionality - works when typing in dropdown
let stateSearchBuffer = '';
let stateSearchTimeout = null;

function filterStates(event) {
    const stateSelect = document.getElementById('state');
    const key = event.key;
    
    // Clear timeout
    if (stateSearchTimeout) {
        clearTimeout(stateSearchTimeout);
    }
    
    // Add character to search buffer
    if (key.length === 1 && /[a-zA-Z ]/.test(key)) {
        stateSearchBuffer += key.toLowerCase();
        
        // Find matching option
        const options = Array.from(stateSelect.options);
        const match = options.find(opt => 
            opt.value && opt.textContent.toLowerCase().startsWith(stateSearchBuffer)
        );
        
        if (match) {
            stateSelect.value = match.value;
        }
    }
    
    // Clear buffer after 1 second of no typing
    stateSearchTimeout = setTimeout(() => {
        stateSearchBuffer = '';
    }, 1000);
}

// Use My Location functionality
function useMyLocation() {
    const btn = event.target.closest('.btn-use-location');
    const originalHTML = btn.innerHTML;
    
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner"></i> Getting Location...';
    
    navigator.geolocation.getCurrentPosition(
        async function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            console.log('📍 Your Coordinates:', {
                latitude: lat,
                longitude: lon,
                accuracy: position.coords.accuracy + ' meters'
            });
            
            try {
                // TRIPLE API STRATEGY - Try 3 different APIs simultaneously for maximum reliability
                const fetchPromises = [
                    // API 1: BigDataCloud
                    fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`)
                        .then(r => r.json())
                        .then(d => ({ source: 'BigDataCloud', data: d, success: true }))
                        .catch(e => ({ source: 'BigDataCloud', error: e.message, success: false })),
                    
                    // API 2: Nominatim
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1&zoom=18`, 
                        { headers: { 'User-Agent': 'GilafStore/1.0' } })
                        .then(r => r.json())
                        .then(d => ({ source: 'Nominatim', data: d, success: true }))
                        .catch(e => ({ source: 'Nominatim', error: e.message, success: false })),
                    
                    // API 3: Geocode.xyz (Third fallback)
                    fetch(`https://geocode.xyz/${lat},${lon}?json=1&auth=your_api_key_here`)
                        .then(r => r.json())
                        .then(d => ({ source: 'Geocode.xyz', data: d, success: true }))
                        .catch(e => ({ source: 'Geocode.xyz', error: e.message, success: false }))
                ];
                
                // Wait for all APIs to respond
                const results = await Promise.all(fetchPromises);
                console.log('🌐 API Results from 3 sources:', results);
                
                // Log full API responses for debugging
                results.forEach(result => {
                    if (result.success && result.data) {
                        console.log(`📦 ${result.source} Response:`, result.data);
                    } else if (result.error) {
                        console.warn(`⚠️ ${result.source} Failed:`, result.error);
                    }
                });
                
                let data = null;
                let zipCode = '';
                let addressData = {};
                
                // COMPREHENSIVE ZIP CODE EXTRACTION - Try all 3 APIs with multiple field checks
                results.forEach(result => {
                    if (!result.success || result.error) {
                        return;
                    }
                    
                    // API 1: BigDataCloud
                    if (result.source === 'BigDataCloud' && result.data) {
                        data = result.data;
                        const possibleZip = result.data.postcode || 
                                          result.data.postalCode || 
                                          result.data.postal_code || 
                                          result.data.zipcode || 
                                          result.data.zip ||
                                          result.data.localityInfo?.administrative?.[0]?.postcode ||
                                          result.data.localityInfo?.administrative?.[1]?.postcode ||
                                          result.data.localityInfo?.administrative?.[2]?.postcode ||
                                          result.data.localityInfo?.administrative?.[3]?.postcode ||
                                          result.data.localityInfo?.administrative?.[4]?.postcode ||
                                          '';
                        if (possibleZip && !zipCode) {
                            zipCode = String(possibleZip).trim();
                            console.log(`✅ PIN CODE from BigDataCloud: ${zipCode}`);
                        }
                        addressData.locality = addressData.locality || result.data.locality;
                        addressData.city = addressData.city || result.data.city;
                        addressData.state = addressData.state || result.data.principalSubdivision;
                    }
                    
                    // API 2: Nominatim
                    if (result.source === 'Nominatim' && result.data && result.data.address) {
                        const addr = result.data.address;
                        const possibleZip = addr.postcode || 
                                          addr.postalCode || 
                                          addr.postal_code || 
                                          addr.zipcode || 
                                          addr.zip || 
                                          addr['addr:postcode'] ||
                                          '';
                        if (possibleZip && !zipCode) {
                            zipCode = String(possibleZip).trim();
                            console.log(`✅ PIN CODE from Nominatim: ${zipCode}`);
                        }
                        addressData.locality = addressData.locality || addr.road || addr.suburb || addr.neighbourhood;
                        addressData.city = addressData.city || addr.city || addr.town || addr.village || addr.municipality;
                        addressData.state = addressData.state || addr.state || addr['ISO3166-2-lvl4'];
                    }
                    
                    // API 3: Geocode.xyz
                    if (result.source === 'Geocode.xyz' && result.data) {
                        const possibleZip = result.data.postal || 
                                          result.data.postcode || 
                                          result.data.postalcode ||
                                          '';
                        if (possibleZip && !zipCode) {
                            zipCode = String(possibleZip).trim();
                            console.log(`✅ PIN CODE from Geocode.xyz: ${zipCode}`);
                        }
                        addressData.locality = addressData.locality || result.data.staddress;
                        addressData.city = addressData.city || result.data.city;
                        addressData.state = addressData.state || result.data.region;
                    }
                });
                
                // FINAL CHECK: If still no PIN code, log all API responses for manual inspection
                if (!zipCode) {
                    console.error('❌ NO PIN CODE FOUND FROM ANY OF 3 APIS');
                    console.error('Please check the API responses above to see if postcode exists anywhere');
                    results.forEach(result => {
                        if (result.success) {
                            console.error(`${result.source} full data:`, JSON.stringify(result.data, null, 2));
                        }
                    });
                }
                
                if (addressData.locality || addressData.city) {
                    console.log('Final data with ZIP:', addressData, 'ZIP Code:', zipCode);
                    
                    // Fill in the form fields using combined data
                    if (addressData.locality) {
                        document.getElementById('addressLine1').value = addressData.locality;
                    }
                    
                    // City
                    if (addressData.city) {
                        document.getElementById('city').value = addressData.city;
                    }
                    
                    // PIN CODE - CRITICAL FIX WITH VALIDATION
                    const zipInput = document.getElementById('zipCode');
                    console.log('🔍 ZIP Input Element:', zipInput);
                    
                    if (zipCode) {
                        // Ensure it's a valid 6-digit Indian PIN code
                        const cleanZip = zipCode.replace(/\D/g, '').substring(0, 6);
                        console.log('🧹 Cleaned ZIP:', cleanZip, 'Original:', zipCode);
                        
                        if (cleanZip.length >= 5) { // Accept 5-6 digits
                            if (zipInput) {
                                zipInput.value = cleanZip;
                                zipInput.dispatchEvent(new Event('input', { bubbles: true }));
                                zipInput.dispatchEvent(new Event('change', { bubbles: true }));
                                console.log('✅ PIN CODE SUCCESSFULLY FILLED:', cleanZip);
                                showNotification(`PIN Code: ${cleanZip} ✓`, 'success');
                            } else {
                                console.error('❌ ZIP INPUT ELEMENT NOT FOUND!');
                                showNotification('Error: PIN code field not found', 'error');
                            }
                        } else if (cleanZip.length > 0) {
                            console.warn('⚠️ Partial PIN code found:', cleanZip);
                            if (zipInput) {
                                zipInput.value = cleanZip;
                            }
                            showNotification(`Partial PIN code found: ${cleanZip}. Please verify.`, 'warning');
                        } else {
                            console.warn('⚠️ Invalid PIN code format:', zipCode);
                            showNotification('PIN code format invalid. Please enter manually.', 'error');
                        }
                    } else {
                        console.error('❌ NO PIN CODE FOUND FROM ANY OF 3 APIS');
                        console.error('All API responses have been logged above. PIN code may not be available for this location.');
                        showNotification('Unable to fetch PIN code. Please enter manually.', 'warning');
                    }
                    
                    // State matching
                    if (addressData.state) {
                        const stateSelect = document.getElementById('state');
                        const stateName = addressData.state;
                        let matched = false;
                        
                        // Try exact match first
                        for (let option of stateSelect.options) {
                            if (option.value.toLowerCase() === stateName.toLowerCase()) {
                                stateSelect.value = option.value;
                                matched = true;
                                break;
                            }
                        }
                        
                        // Try partial match
                        if (!matched) {
                            for (let option of stateSelect.options) {
                                if (option.value.toLowerCase().includes(stateName.toLowerCase()) ||
                                    stateName.toLowerCase().includes(option.value.toLowerCase())) {
                                    stateSelect.value = option.value;
                                    break;
                                }
                            }
                        }
                    }
                    
                    btn.classList.remove('loading');
                    btn.innerHTML = '<i class="fas fa-check"></i> Location Added!';
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                    }, 2000);
                    
                    // Show success notification
                    showNotification('Address auto-filled successfully!', 'success');
                } else {
                    throw new Error('Geocoding failed');
                }
            } catch (error) {
                console.error('Geocoding error:', error);
                btn.classList.remove('loading');
                btn.innerHTML = originalHTML;
                showNotification('Unable to fetch address details. Please enter manually.', 'error');
            }
        },
        function(error) {
            btn.classList.remove('loading');
            btn.innerHTML = originalHTML;
            
            let errorMsg = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg += 'Please allow location access in your browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg += 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMsg += 'Location request timed out.';
                    break;
                default:
                    errorMsg += 'An unknown error occurred.';
            }
            showNotification(errorMsg, 'error');
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Show notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    `;
    
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    } else if (type === 'info') {
        notification.style.background = 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)';
        notification.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
    } else {
        notification.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        notification.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
    }
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 4000);
}
</script>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/new-footer.php'; ?>
