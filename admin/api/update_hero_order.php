<?php
session_start();
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Check if admin
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order']) || !is_array($input['order'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE hero_banner_slides SET display_order = ? WHERE id = ?");
    
    foreach ($input['order'] as $item) {
        $stmt->execute([$item['order'], $item['id']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
