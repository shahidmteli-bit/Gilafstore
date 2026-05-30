<?php
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_admin();
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$adminUser = $_SESSION['admin']['username'] ?? 'admin';

try {
    $db = get_db_connection();

    // Ensure tables exist
    $db->exec("CREATE TABLE IF NOT EXISTS barcode_edit_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barcode_id INT NOT NULL,
        requested_by VARCHAR(100) NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        reviewed_by VARCHAR(100) NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_barcode (barcode_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN edit_approved TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e) {}

    if ($action === 'raise_request') {
        $barcodeId = (int)($_POST['barcode_id'] ?? 0);
        $reason    = trim($_POST['reason'] ?? '');
        if (!$barcodeId || empty($reason)) {
            echo json_encode(['success' => false, 'error' => 'Barcode ID and reason are required.']); exit;
        }

        // Check if a pending request already exists
        $existing = $db->prepare("SELECT id FROM barcode_edit_requests WHERE barcode_id = ? AND status = 'pending'");
        $existing->execute([$barcodeId]);
        if ($existing->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'A pending request already exists for this barcode.']); exit;
        }

        $stmt = $db->prepare("INSERT INTO barcode_edit_requests (barcode_id, requested_by, reason) VALUES (?, ?, ?)");
        $stmt->execute([$barcodeId, $adminUser, $reason]);
        echo json_encode(['success' => true, 'message' => 'Edit request submitted. Awaiting approval.']);

    } elseif ($action === 'approve_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) { echo json_encode(['success' => false, 'error' => 'Invalid request ID']); exit; }

        // Get barcode_id
        $req = $db->prepare("SELECT barcode_id FROM barcode_edit_requests WHERE id = ? AND status = 'pending'");
        $req->execute([$requestId]);
        $row = $req->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Request not found or already reviewed']); exit; }

        // Approve request
        $db->prepare("UPDATE barcode_edit_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$adminUser, $requestId]);
        // Set edit_approved flag on barcode
        $db->prepare("UPDATE barcode_inventory SET edit_approved = 1 WHERE id = ?")
           ->execute([$row['barcode_id']]);

        echo json_encode(['success' => true, 'message' => 'Request approved. Barcode is now editable.']);

    } elseif ($action === 'reject_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if (!$requestId) { echo json_encode(['success' => false, 'error' => 'Invalid request ID']); exit; }

        $db->prepare("UPDATE barcode_edit_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
           ->execute([$adminUser, $requestId]);
        echo json_encode(['success' => true, 'message' => 'Request rejected.']);

    } elseif ($action === 'revoke_edit') {
        // After editing, revoke the edit permission and reset status
        $barcodeId = (int)($_POST['barcode_id'] ?? 0);
        if (!$barcodeId) { echo json_encode(['success' => false, 'error' => 'Invalid barcode ID']); exit; }
        $db->prepare("UPDATE barcode_inventory SET edit_approved = 0 WHERE id = ?")
           ->execute([$barcodeId]);
        // Mark approved requests for this barcode as consumed
        $db->prepare("UPDATE barcode_edit_requests SET status='rejected' WHERE barcode_id = ? AND status = 'approved'")
           ->execute([$barcodeId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
