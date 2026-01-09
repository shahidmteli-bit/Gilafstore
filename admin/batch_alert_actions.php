<?php
/**
 * Batch Alert Actions Handler
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$action = $_POST['action'] ?? '';
$alertId = (int)($_POST['alert_id'] ?? 0);

if ($alertId <= 0) {
    $_SESSION['message'] = 'Invalid alert ID';
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/batch_alerts_dashboard.php');
    exit;
}

try {
    $db = get_db_connection();
    $userId = $_SESSION['user']['id'];
    
    switch ($action) {
        case 'mark_read':
            $sql = "UPDATE batch_alerts SET is_read = 1 WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $alertId]);
            
            $_SESSION['message'] = 'Alert marked as read';
            $_SESSION['message_type'] = 'success';
            break;
            
        case 'resolve':
            $sql = "UPDATE batch_alerts 
                    SET is_resolved = 1, 
                        is_read = 1,
                        resolved_by = :user_id, 
                        resolved_at = NOW() 
                    WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':user_id' => $userId, ':id' => $alertId]);
            
            $_SESSION['message'] = 'Alert resolved';
            $_SESSION['message_type'] = 'success';
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    header('Location: /Gilaf Ecommerce website/admin/batch_alerts_dashboard.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/batch_alerts_dashboard.php');
    exit;
}
