<?php
ob_start(); // Buffer output

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$validStatuses = ['Unused', 'Used', 'Added to Product Design', 'Planned for Use', 'Archived'];

if ($id <= 0 || !in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID or Status']);
    exit;
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare("UPDATE barcode_inventory SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
