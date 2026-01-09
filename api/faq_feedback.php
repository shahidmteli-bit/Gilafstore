<?php
/**
 * FAQ Feedback API
 * Handles user feedback on FAQ helpfulness
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../includes/db_connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$faqId = isset($input['faq_id']) ? intval($input['faq_id']) : 0;
$wasHelpful = isset($input['was_helpful']) ? intval($input['was_helpful']) : null;

if (!$faqId || $wasHelpful === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid parameters'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Update helpful count if positive feedback
    if ($wasHelpful === 1) {
        $stmt = $pdo->prepare("UPDATE faqs SET helpful_count = helpful_count + 1 WHERE id = ?");
        $stmt->execute([$faqId]);
    }
    
    // Update analytics
    $stmt = $pdo->prepare(
        "UPDATE faq_analytics 
         SET was_helpful = ? 
         WHERE faq_id = ? 
         ORDER BY created_at DESC 
         LIMIT 1"
    );
    $stmt->execute([$wasHelpful, $faqId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Feedback recorded'
    ]);
    
} catch (PDOException $e) {
    error_log("FAQ Feedback Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
