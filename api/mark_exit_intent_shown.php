<?php
/**
 * API: Mark Exit Intent Popup as Shown
 */

session_start();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$popupId = (int)($data['popup_id'] ?? 0);

if (!$popupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid popup ID']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/promotional_system.php';
    mark_exit_intent_shown($popupId);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
