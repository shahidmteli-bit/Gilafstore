<?php
/**
 * FAQ Categories API - Returns FAQs filtered by category
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../includes/db_connect.php';

try {
    $pdo = get_db_connection();
    
    // Get category from query parameter
    $category = $_GET['category'] ?? 'all';
    
    // Build query based on category
    if ($category === 'all') {
        // Get all active FAQs
        $stmt = $pdo->query("
            SELECT 
                id,
                question,
                answer,
                category,
                view_count,
                helpful_count,
                priority
            FROM faqs
            WHERE is_active = 1
            ORDER BY 
                priority DESC,
                helpful_count DESC,
                view_count DESC
            LIMIT 50
        ");
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get FAQs for specific category
        $stmt = $pdo->prepare("
            SELECT 
                id,
                question,
                answer,
                category,
                view_count,
                helpful_count,
                priority
            FROM faqs
            WHERE is_active = 1 AND category = ?
            ORDER BY 
                priority DESC,
                helpful_count DESC,
                view_count DESC
            LIMIT 50
        ");
        $stmt->execute([$category]);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'faqs' => $faqs,
        'count' => count($faqs),
        'category' => $category
    ]);
    
} catch (PDOException $e) {
    error_log("FAQ Categories Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'Unable to load FAQs'
    ]);
}
