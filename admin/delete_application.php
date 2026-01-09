<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

error_log("Delete Application Request - ID: " . ($_GET['id'] ?? 'none'));

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    error_log("Invalid application ID: $id");
    $_SESSION['message'] = 'Invalid application ID';
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
    exit;
}

try {
    $db = get_db_connection();
    
    // Get application details before deletion
    $stmt = $db->prepare("SELECT * FROM distributor_applications WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        throw new Exception('Application not found');
    }
    
    // Delete uploaded documents if they exist
    $uploadDir = __DIR__ . '/../uploads/distributor_applications/';
    
    if (!empty($app['documents']) && file_exists($uploadDir . $app['documents'])) {
        @unlink($uploadDir . $app['documents']);
    }
    
    // Delete the application from database
    $stmt = $db->prepare("DELETE FROM distributor_applications WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    // Log deletion
    $adminId = $_SESSION['user']['id'];
    error_log("Application #$id (Type: {$app['application_type']}, Name: {$app['applicant_name']}) deleted by admin #$adminId");
    
    $message = 'Distributor/Application deleted successfully';
    if ($app['status'] === 'approved') {
        $message = 'Approved distributor removed from system successfully';
    }
    
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'success';
    header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
    exit;
    
} catch (Exception $e) {
    error_log("Delete Application Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['message'] = 'Error deleting application: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: /Gilaf Ecommerce website/admin/manage_applications.php');
    exit;
}
