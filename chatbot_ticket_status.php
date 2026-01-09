<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support_functions.php';

header('Content-Type: application/json');

try {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        echo json_encode([
            'success' => false,
            'action' => 'login_required',
            'message' => 'Please login to view ticket status.'
        ]);
        exit;
    }

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $userEmail = (string)($_SESSION['user']['email'] ?? '');

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
        exit;
    }

    $ticketId = isset($_GET['ticket_id']) ? trim((string)$_GET['ticket_id']) : '';
    if ($ticketId === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket id.']);
        exit;
    }

    // Ownership enforced here
    $ticket = get_ticket_by_id($ticketId, $userId, $userEmail);
    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    $comments = get_ticket_comments($ticketId, false);

    // Return last 3 comments
    $lastComments = [];
    if (is_array($comments) && count($comments) > 0) {
        $slice = array_slice($comments, -3);
        foreach ($slice as $c) {
            $lastComments[] = [
                'commenter_name' => (string)($c['commenter_name'] ?? ''),
                'comment' => (string)($c['comment'] ?? ''),
                'created_at' => (string)($c['created_at'] ?? ''),
                'is_admin' => (int)($c['is_admin'] ?? 0),
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'ticket' => [
            'ticket_id' => (string)($ticket['ticket_id'] ?? $ticketId),
            'subject' => (string)($ticket['subject'] ?? ''),
            'status' => (string)($ticket['status'] ?? ''),
            'priority' => (string)($ticket['priority'] ?? ''),
            'issue_type' => (string)($ticket['issue_type'] ?? ''),
            'created_at' => (string)($ticket['created_at'] ?? ''),
            'updated_at' => (string)($ticket['updated_at'] ?? ''),
        ],
        'last_comments' => $lastComments
    ]);
} catch (Exception $e) {
    error_log('chatbot_ticket_status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load ticket status.'
    ]);
}
