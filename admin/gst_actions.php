<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = get_db_connection();
$adminId = $_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'get_config':
            $configId = (int)($_POST['id'] ?? 0);
            
            if (!$configId) {
                echo json_encode(['success' => false, 'message' => 'Invalid configuration ID']);
                exit();
            }
            
            $stmt = $db->prepare("SELECT * FROM gst_configuration WHERE id = ?");
            $stmt->execute([$configId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($config) {
                echo json_encode(['success' => true, 'config' => $config]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Configuration not found']);
            }
            break;
            
        case 'update_config':
            $configId = (int)($_POST['id'] ?? 0);
            $gstSlab = (float)($_POST['gst_slab'] ?? 0);
            $hsnCode = $_POST['hsn_code'] ?? null;
            $cessRate = (float)($_POST['cess_rate'] ?? 0);
            $isExempt = isset($_POST['is_exempt']) ? 1 : 0;
            $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-d H:i:s');
            $effectiveTo = $_POST['effective_to'] ?? null;
            
            if (!$configId) {
                echo json_encode(['success' => false, 'message' => 'Invalid configuration ID']);
                exit();
            }
            
            // Check if HSN code already exists for a different configuration (if provided)
            if (!empty($hsnCode)) {
                $checkStmt = $db->prepare("
                    SELECT id, entity_type, entity_id 
                    FROM gst_configuration 
                    WHERE hsn_code = ? AND status = 'active' AND id != ?
                ");
                $checkStmt->execute([$hsnCode, $configId]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "HSN Code '{$hsnCode}' is already assigned to another {$existing['entity_type']} (ID: {$existing['entity_id']}). Each HSN code must be unique."
                    ]);
                    exit();
                }
            }
            
            $stmt = $db->prepare("
                UPDATE gst_configuration 
                SET gst_slab = ?, hsn_code = ?, cess_rate = ?, is_exempt = ?, 
                    effective_from = ?, effective_to = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $gstSlab, 
                $hsnCode, 
                $cessRate, 
                $isExempt, 
                $effectiveFrom, 
                $effectiveTo, 
                $adminId, 
                $configId
            ]);
            
            // Log audit trail
            $stmt = $db->prepare("
                INSERT INTO gst_audit_trail (action_type, table_name, record_id, changed_by, new_values)
                VALUES ('update', 'gst_configuration', ?, ?, ?)
            ");
            
            $newValues = json_encode([
                'gst_slab' => $gstSlab,
                'hsn_code' => $hsnCode,
                'cess_rate' => $cessRate,
                'is_exempt' => $isExempt
            ]);
            
            $stmt->execute([$configId, $adminId, $newValues]);
            
            echo json_encode(['success' => true, 'message' => 'Configuration updated successfully']);
            break;
            
        case 'delete_config':
            $configId = (int)($_POST['id'] ?? 0);
            
            if (!$configId) {
                echo json_encode(['success' => false, 'message' => 'Invalid configuration ID']);
                exit();
            }
            
            // Deactivate instead of delete
            $stmt = $db->prepare("
                UPDATE gst_configuration 
                SET status = 'inactive', effective_to = NOW(), updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$adminId, $configId]);
            
            // Log audit trail
            $stmt = $db->prepare("
                INSERT INTO gst_audit_trail (action_type, table_name, record_id, changed_by, new_values)
                VALUES ('delete', 'gst_configuration', ?, ?, ?)
            ");
            
            $newValues = json_encode(['status' => 'inactive', 'deactivated_at' => date('Y-m-d H:i:s')]);
            $stmt->execute([$configId, $adminId, $newValues]);
            
            echo json_encode(['success' => true, 'message' => 'Configuration deactivated successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("GST Actions Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("GST Actions Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
