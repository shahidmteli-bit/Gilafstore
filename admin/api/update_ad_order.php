<?php
/**
 * API endpoint for updating advertisement media order
 * Called via AJAX when drag & drop reordering occurs
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Check if admin
if (!is_admin()) {
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
    $db = get_db_connection();
    $db->beginTransaction();
    
    foreach ($input['order'] as $item) {
        $id = (int)$item['id'];
        $order = (int)$item['order'];
        
        db_query("UPDATE advertisements_media SET display_order = ? WHERE id = ?", [$order, $id]);
    }
    
    $db->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
