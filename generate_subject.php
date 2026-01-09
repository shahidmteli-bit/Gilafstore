<?php
/**
 * Subject Generation API Endpoint
 * Generates intelligent subject lines from descriptions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/subject_generator.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['description'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Description is required'
    ]);
    exit;
}

$description = trim($data['description']);
$category = $data['category'] ?? '';

// Validate description length
if (strlen($description) < 50) {
    echo json_encode([
        'success' => false,
        'message' => 'Description must be at least 50 characters'
    ]);
    exit;
}

try {
    // Generate subject using advanced algorithm
    $subject = SubjectGenerator::generateAdvanced($description, $category);
    
    // Log for debugging (optional)
    error_log("Generated subject: $subject for category: $category");
    
    echo json_encode([
        'success' => true,
        'subject' => $subject,
        'category' => $category,
        'description_length' => strlen($description)
    ]);
    
} catch (Exception $e) {
    error_log("Subject generation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate subject',
        'error' => $e->getMessage()
    ]);
}
