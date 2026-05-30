<?php
/**
 * API: Track Promotional Events
 * Handles analytics tracking for promotional system
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

$promoId = (int)($data['promo_id'] ?? 0);
$promoType = $data['promo_type'] ?? '';
$eventType = $data['event_type'] ?? '';

if (!$promoId || !$promoType || !$eventType) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/promotional_system.php';
    
    if ($eventType === 'view') {
        track_promo_view($promoId, $promoType);
    } elseif ($eventType === 'click') {
        track_promo_click($promoId, $promoType);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
