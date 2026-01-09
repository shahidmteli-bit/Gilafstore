<?php
/**
 * CHATBOT TICKET CREATION API
 * Handles ticket creation from chatbot and returns ticket number
 */

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/support_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to create a support ticket.',
        'action' => 'login_required'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $issueType = $data['issue_type'] ?? '';
    $priority = $data['priority'] ?? 'medium';
    $description = trim($data['description'] ?? '');
    
    // Auto-generate subject from issue type
    $issueTypeLabels = [
        'order' => 'Order Issue',
        'product' => 'Product Question',
        'payment' => 'Payment Issue',
        'shipping' => 'Shipping & Delivery',
        'account' => 'Account Support',
        'technical' => 'Technical Issue',
        'other' => 'General Inquiry'
    ];
    $subject = $issueTypeLabels[$issueType] ?? 'Support Request';
    
    // Validation
    if (empty($issueType)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select an issue type'
        ]);
        exit;
    }
    
    if (empty($description) || strlen($description) < 20) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide more details (at least 20 characters)'
        ]);
        exit;
    }
    
    // Create ticket
    $ticketData = [
        'user_id' => $_SESSION['user']['id'],
        'user_name' => $_SESSION['user']['name'],
        'user_email' => $_SESSION['user']['email'],
        'subject' => $subject,
        'issue_type' => $issueType,
        'priority' => $priority,
        'description' => $description
    ];
    
    $result = create_support_ticket($ticketData);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'ticket_id' => $result['ticket_id'],
            'message' => "✅ **Ticket Created Successfully!**\n\n📋 **Your Ticket Number:** `{$result['ticket_id']}`\n\n✉️ A confirmation email has been sent to your registered email address.\n\n**What happens next?**\n• Our support team will review your ticket\n• You'll receive email updates on progress\n• Expected response time: 24 hours\n\n**Please save this ticket number for your reference!**",
            'ticket_url' => base_url('user/my_tickets.php?ticket=' . $result['ticket_id'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to create ticket. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
