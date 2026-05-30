<?php
/**
 * Get unserved location requests for frontend display
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = get_db_connection();

    // Create table if not exists (safe)
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

    // Get aggregated requests — group by query, show count and latest date
    $stmt = $db->query("
        SELECT 
            search_query,
            search_type,
            COUNT(*) AS request_count,
            MAX(created_at) AS last_requested
        FROM unserved_location_requests
        GROUP BY search_query, search_type
        ORDER BY request_count DESC, last_requested DESC
        LIMIT 50
    ");

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'requests' => $requests
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Could not fetch requests']);
}
