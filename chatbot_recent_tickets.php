<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support_functions.php';

header('Content-Type: application/json');

try {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        echo json_encode([
            'success' => false,
            'action' => 'login_required',
            'message' => 'Please login to view your tickets.'
        ]);
        exit;
    }

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $userEmail = (string)($_SESSION['user']['email'] ?? '');

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
        exit;
    }

    $tickets = get_user_tickets($userId, [], $userEmail);
    $tickets = array_slice($tickets, 0, 5);

    $result = [];
    foreach ($tickets as $t) {
        $result[] = [
            'ticket_id' => (string)($t['ticket_id'] ?? ''),
            'subject' => (string)($t['subject'] ?? ''),
            'status' => (string)($t['status'] ?? ''),
            'created_at' => (string)($t['created_at'] ?? ''),
            'updated_at' => (string)($t['updated_at'] ?? ''),
        ];
    }

    echo json_encode([
        'success' => true,
        'tickets' => $result
    ]);
} catch (Exception $e) {
    error_log('chatbot_recent_tickets error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load tickets.'
    ]);
}
