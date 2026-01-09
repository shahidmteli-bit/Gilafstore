<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Log the request
error_log("Process Application Request - ID: " . ($_GET['id'] ?? 'none') . ", Action: " . ($_GET['action'] ?? 'none'));

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve', 'reject'])) {
    error_log("Invalid request - ID: $id, Action: $action");
    $_SESSION['message'] = 'Invalid request';
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
    exit;
}

try {
    $db = get_db_connection();
    
    // Get application details
    $stmt = $db->prepare("SELECT * FROM distributor_applications WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        throw new Exception('Application not found');
    }
    
    if ($app['status'] !== 'pending') {
        throw new Exception('This application has already been processed');
    }
    
    $adminId = $_SESSION['user']['id'];
    
    if ($action === 'approve') {
        // Update application status
        $stmt = $db->prepare("
            UPDATE distributor_applications 
            SET status = 'approved', updated_at = NOW() 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        
        // Store approval note in admin_notes
        $adminNotes = [
            'approved_by' => $adminId,
            'approved_at' => date('Y-m-d H:i:s'),
            'status' => 'approved'
        ];
        
        $stmt = $db->prepare("
            UPDATE distributor_applications 
            SET admin_notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            ':notes' => json_encode($adminNotes),
            ':id' => $id
        ]);
        
        // Log approval
        error_log("Application #$id approved by admin #$adminId");
        
        $_SESSION['message'] = 'Application approved successfully!';
        $_SESSION['message_type'] = 'success';
        header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
        exit;
        
    } else {
        // Reject application
        $reason = $_GET['reason'] ?? 'Application does not meet our requirements';
        
        $adminNotes = [
            'rejected_by' => $adminId,
            'rejected_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
            'status' => 'rejected'
        ];
        
        $stmt = $db->prepare("
            UPDATE distributor_applications 
            SET status = 'rejected', admin_notes = :notes, updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $id,
            ':notes' => json_encode($adminNotes)
        ]);
        
        // Log rejection
        error_log("Application #$id rejected by admin #$adminId. Reason: $reason");
        
        $_SESSION['message'] = 'Application rejected successfully';
        $_SESSION['message_type'] = 'success';
        header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Process Application Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
    exit;
}
