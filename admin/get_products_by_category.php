<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

header('Content-Type: application/json');

$categoryId = (int)($_GET['category_id'] ?? 0);

if (!$categoryId) {
    echo json_encode(['products' => []]);
    exit;
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT id, name, net_weight FROM products WHERE category_id = ? ORDER BY name ASC');
    $stmt->execute([$categoryId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['products' => $products]);
} catch (Exception $e) {
    echo json_encode(['products' => [], 'error' => $e->getMessage()]);
}
