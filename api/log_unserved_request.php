<?php
/**
 * Log unserved location requests for expansion tracking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$query = trim($_POST['query'] ?? $_GET['query'] ?? '');

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'No query provided']);
    exit;
}

try {
    $db = get_db_connection();

    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS `unserved_location_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `search_query` VARCHAR(255) NOT NULL,
        `search_type` ENUM('pincode','city') NOT NULL DEFAULT 'pincode',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `search_query` (`search_query`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $isPincode = preg_match('/^[0-9]{6}$/', $query);
    $searchType = $isPincode ? 'pincode' : 'city';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Prevent duplicate logging for same query + IP within 1 hour
    $stmt = $db->prepare("
        SELECT id FROM unserved_location_requests 
        WHERE search_query = :q AND ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 1
    ");
    $stmt->execute([':q' => $query, ':ip' => $ip]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $db->prepare("
            INSERT INTO unserved_location_requests (search_query, search_type, ip_address)
            VALUES (:q, :type, :ip)
        ");
        $stmt->execute([':q' => $query, ':type' => $searchType, ':ip' => $ip]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Logging failed']);
}
