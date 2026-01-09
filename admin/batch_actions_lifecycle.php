<?php
/**
 * Batch Lifecycle Actions Handler
 * Handles status changes, approvals, recalls, and other batch operations
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/batch_functions.php';

require_admin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$batchId = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);

if ($batchId <= 0) {
    $_SESSION['message'] = 'Invalid batch ID';
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_batches.php');
    exit;
}

try {
    $db = get_db_connection();
    $userId = $_SESSION['user']['id'];
    $userName = $_SESSION['user']['name'];
    
    // Get batch details
    $stmt = $db->prepare("SELECT * FROM batch_codes WHERE id = :id");
    $stmt->execute([':id' => $batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        throw new Exception('Batch not found');
    }
    
    switch ($action) {
        case 'quality_approve':
            // Approve batch quality - requires officer name
            $officerName = trim($_POST['approval_officer_name'] ?? '');
            $notes = $_POST['notes'] ?? '';
            
            if (empty($officerName)) {
                throw new Exception('Approval Officer Name is required');
            }
            
            $sql = "UPDATE batch_codes 
                    SET status = 'quality_approved',
                        quality_approved = 1,
                        quality_approver_id = :approver_id,
                        quality_approved_at = NOW(),
                        quality_notes = :notes,
                        last_status_change = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':approver_id' => $userId,
                ':notes' => $notes,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'quality_approved', $batch['status'], 'quality_approved', $userId, $userName, [
                'approval_officer' => $officerName,
                'notes' => $notes
            ]);
            
            $_SESSION['message'] = 'Batch quality approved by ' . $officerName;
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'quality_reject':
            // Reject batch quality - removes lab tested and organic tags
            $reason = $_POST['reason'] ?? 'Quality standards not met';
            
            $sql = "UPDATE batch_codes 
                    SET status = 'rejected',
                        quality_approved = 0,
                        is_lab_tested = 0,
                        is_organic = 0,
                        quality_notes = :reason,
                        last_status_change = NOW(),
                        status_change_reason = :reason
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':reason' => $reason,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'quality_rejected', $batch['status'], 'rejected', $userId, $userName, [
                'reason' => $reason,
                'tags_removed' => 'lab_tested, organic'
            ]);
            
            $_SESSION['message'] = 'Batch rejected - Lab Tested and Organic tags removed';
            $_SESSION['message_type'] = 'warning';
            break;
            
        case 'release_for_sale':
            // Release batch for sale - requires officer name and auto-activates
            if ($batch['status'] !== 'quality_approved') {
                throw new Exception('Batch must be quality approved before release');
            }
            
            $officerName = trim($_POST['release_officer_name'] ?? '');
            
            if (empty($officerName)) {
                throw new Exception('Release Officer Name is required');
            }
            
            $sql = "UPDATE batch_codes 
                    SET status = 'released_for_sale',
                        released_for_sale = 1,
                        releaser_id = :releaser_id,
                        released_at = NOW(),
                        is_active = 1,
                        last_status_change = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':releaser_id' => $userId,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'released_for_sale', $batch['status'], 'released_for_sale', $userId, $userName, [
                'release_officer' => $officerName,
                'auto_activated' => true
            ]);
            
            $_SESSION['message'] = 'Batch released for sale by ' . $officerName . ' and activated';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'mark_in_distribution':
            // Mark as in distribution
            update_batch_status($batchId, 'in_distribution', $userId, $userName, 'Batch moved to distribution');
            
            $_SESSION['message'] = 'Batch marked as in distribution';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'mark_sold_out':
            // Mark as sold out
            $sql = "UPDATE batch_codes 
                    SET status = 'sold_out',
                        units_remaining = 0,
                        last_status_change = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $batchId]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'marked_sold_out', $batch['status'], 'sold_out', $userId, $userName, 'Manually marked as sold out');
            
            $_SESSION['message'] = 'Batch marked as sold out';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'recall':
            // Recall batch - removes all certifications
            $recallReason = $_POST['recall_reason'] ?? 'Quality/Safety concern';
            $recallQuantity = (int)($_POST['recall_quantity'] ?? 0);
            
            $sql = "UPDATE batch_codes 
                    SET status = 'recalled',
                        is_lab_tested = 0,
                        is_organic = 0,
                        recalled_quantity = :quantity,
                        recall_reason = :reason,
                        recalled_by = :recalled_by,
                        recalled_at = NOW(),
                        last_status_change = NOW(),
                        status_change_reason = :reason
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':quantity' => $recallQuantity,
                ':reason' => $recallReason,
                ':recalled_by' => $userId,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'recalled', $batch['status'], 'recalled', $userId, $userName, [
                'reason' => $recallReason,
                'recalled_quantity' => $recallQuantity,
                'certifications_removed' => 'lab_tested, organic'
            ]);
            
            // Create critical alert
            create_batch_alert($batchId, $batch['batch_code'], 'recalled_verified', 'critical', 
                "Batch {$batch['batch_code']} has been recalled: $recallReason", [
                    'recalled_quantity' => $recallQuantity,
                    'recalled_by' => $userName
                ]);
            
            $_SESSION['message'] = 'Batch recalled - All certifications removed';
            $_SESSION['message_type'] = 'warning';
            break;
            
        case 'block':
            // Block batch - removes lab tested and organic tags
            $blockReason = $_POST['block_reason'] ?? 'Administrative block';
            
            $sql = "UPDATE batch_codes 
                    SET status = 'blocked',
                        is_lab_tested = 0,
                        is_organic = 0,
                        blocked_reason = :reason,
                        blocked_by = :blocked_by,
                        blocked_at = NOW(),
                        last_status_change = NOW(),
                        status_change_reason = :reason
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':reason' => $blockReason,
                ':blocked_by' => $userId,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'blocked', $batch['status'], 'blocked', $userId, $userName, [
                'reason' => $blockReason,
                'tags_removed' => 'lab_tested, organic'
            ]);
            
            $_SESSION['message'] = 'Batch blocked - Lab Tested and Organic tags removed';
            $_SESSION['message_type'] = 'warning';
            break;
            
        case 'unblock':
            // Unblock batch
            $newStatus = $_POST['new_status'] ?? 'production';
            
            $sql = "UPDATE batch_codes 
                    SET status = :new_status,
                        blocked_reason = NULL,
                        blocked_by = NULL,
                        blocked_at = NULL,
                        last_status_change = NOW(),
                        status_change_reason = 'Unblocked by admin'
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':new_status' => $newStatus,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'unblocked', 'blocked', $newStatus, $userId, $userName);
            
            $_SESSION['message'] = 'Batch unblocked';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'archive':
            // Archive batch
            $sql = "UPDATE batch_codes 
                    SET status = 'archived',
                        archived_at = NOW(),
                        last_status_change = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $batchId]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'archived', $batch['status'], 'archived', $userId, $userName);
            
            $_SESSION['message'] = 'Batch archived';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'update_units':
            // Update unit counts
            $totalUnits = (int)($_POST['total_units'] ?? 0);
            $unitsSold = (int)($_POST['units_sold'] ?? 0);
            $unitsRemaining = $totalUnits - $unitsSold;
            
            $sql = "UPDATE batch_codes 
                    SET total_units_manufactured = :total,
                        units_sold = :sold,
                        units_remaining = :remaining
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':total' => $totalUnits,
                ':sold' => $unitsSold,
                ':remaining' => $unitsRemaining,
                ':id' => $batchId
            ]);
            
            log_batch_audit($batchId, $batch['batch_code'], 'units_updated', null, null, $userId, $userName, null, [
                'total_units' => $totalUnits,
                'units_sold' => $unitsSold,
                'units_remaining' => $unitsRemaining
            ]);
            
            $_SESSION['message'] = 'Unit counts updated';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'toggle_lab_tested':
            // Toggle lab tested status
            $isLabTested = $batch['is_lab_tested'] ? 0 : 1;
            
            $sql = "UPDATE batch_codes SET is_lab_tested = :is_lab_tested WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':is_lab_tested' => $isLabTested, ':id' => $batchId]);
            
            $_SESSION['message'] = 'Lab tested status updated';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'toggle_organic':
            // Toggle organic status
            $isOrganic = $batch['is_organic'] ? 0 : 1;
            
            $sql = "UPDATE batch_codes SET is_organic = :is_organic WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':is_organic' => $isOrganic, ':id' => $batchId]);
            
            $_SESSION['message'] = 'Organic status updated';
            $_SESSION['message_type'] = 'success';
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    header('Location: /Gilaf Ecommerce website/admin/manage_batches.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_batches.php');
    exit;
}
