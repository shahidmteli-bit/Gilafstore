<?php
/**
 * USER PORTAL - CREATE SUPPORT TICKET
 * Submit new support ticket with chatbot integration
 */

$pageTitle = 'Create Support Ticket — Gilaf Store';
$activePage = 'support';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_functions.php';

// Require user to be logged in
if (!is_logged_in()) {
    redirect_with_message('/user/login.php', 'Please login to create a support ticket', 'info');
}

$userId = $_SESSION['user']['id'];
$userName = $_SESSION['user']['name'];
$userEmail = $_SESSION['user']['email'];

$success = false;
$error = '';
$ticketId = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueType = $_POST['issue_type'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    
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
        $error = 'Please select an issue type';
    } elseif (empty($description)) {
        $error = 'Description is required';
    } elseif (strlen($description) < 20) {
        $error = 'Please provide more details (at least 20 characters)';
    } else {
        // Create ticket
        $ticketData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'subject' => $subject,
            'issue_type' => $issueType,
            'priority' => $priority,
            'description' => $description
        ];
        
        $result = create_support_ticket($ticketData);
        
        if ($result['success']) {
            $success = true;
            $ticketId = $result['ticket_id'];
        } else {
            $error = $result['message'];
        }
    }
}

include __DIR__ . '/../includes/new-header.php';
?>

<style>
    .create-ticket-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .page-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .page-header h1 {
        font-family: 'Playfair Display', serif;
        color: #1A3C34;
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .page-header p {
        color: #7f8c8d;
        font-size: 1.1rem;
    }
    
    .form-card {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .success-card {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-left: 5px solid #27ae60;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
    }
    
    .success-icon {
        font-size: 4rem;
        color: #27ae60;
        margin-bottom: 20px;
    }
    
    .ticket-id-display {
        font-family: 'Courier New', monospace;
        font-size: 1.5rem;
        font-weight: 700;
        color: #1A3C34;
        margin: 20px 0;
        padding: 15px;
        background: white;
        border-radius: 8px;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-error {
        background: #f8d7da;
        border-left: 4px solid #e74c3c;
        color: #721c24;
    }
    
    .alert-info {
        background: #d1ecf1;
        border-left: 4px solid #3498db;
        color: #0c5460;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 15px;
    }
    
    .form-label .required {
        color: #e74c3c;
        margin-left: 3px;
    }
    
    .form-control {
        width: 100%;
        padding: 14px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 15px;
        font-family: inherit;
        transition: border-color 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #1A3C34;
    }
    
    textarea.form-control {
        min-height: 150px;
        resize: vertical;
    }
    
    .form-help {
        font-size: 13px;
        color: #7f8c8d;
        margin-top: 5px;
    }
    
    .issue-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    
    .issue-type-option {
        position: relative;
    }
    
    .issue-type-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .issue-type-label {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .issue-type-option input[type="radio"]:checked + .issue-type-label {
        border-color: #1A3C34;
        background: linear-gradient(135deg, rgba(26, 60, 52, 0.05) 0%, rgba(26, 60, 52, 0.02) 100%);
    }
    
    .issue-type-label:hover {
        border-color: #1A3C34;
    }
    
    .issue-icon {
        font-size: 1.5rem;
        color: #1A3C34;
    }
    
    .issue-text {
        flex: 1;
    }
    
    .issue-name {
        font-weight: 600;
        color: #2c3e50;
        display: block;
    }
    
    .issue-desc {
        font-size: 12px;
        color: #7f8c8d;
        display: block;
        margin-top: 3px;
    }
    
    .priority-options {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .priority-option {
        position: relative;
    }
    
    .priority-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .priority-label {
        display: inline-block;
        padding: 10px 20px;
        border: 2px solid #e9ecef;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 600;
        font-size: 14px;
    }
    
    .priority-option input[type="radio"]:checked + .priority-label {
        border-color: #1A3C34;
        background: #1A3C34;
        color: white;
    }
    
    .priority-label:hover {
        border-color: #1A3C34;
    }
    
    .btn {
        padding: 16px 40px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        width: 100%;
        justify-content: center;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
    }
    
    .btn-secondary {
        background: white;
        color: #1A3C34;
        border: 2px solid #1A3C34;
        text-decoration: none;
    }
    
    .btn-secondary:hover {
        background: #1A3C34;
        color: white;
    }
    
    .chatbot-hint {
        background: linear-gradient(135deg, #e8f4f8 0%, #d4ebf2 100%);
        border-left: 4px solid #3498db;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    
    .chatbot-hint-title {
        font-weight: 700;
        color: #2980b9;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .chatbot-hint-text {
        color: #555;
        font-size: 14px;
        line-height: 1.6;
    }
</style>

<section style="min-height: 70vh; background: linear-gradient(135deg, rgba(26, 60, 52, 0.03) 0%, rgba(197, 160, 89, 0.03) 100%); padding: 60px 0 30px 0;">
    <div class="create-ticket-container">
        <div class="page-header">
            <h1>Create Support Ticket</h1>
            <p>We're here to help! Describe your issue and we'll get back to you soon.</p>
        </div>
        
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="form-card">
                <div class="success-card">
                    <div class="success-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="64" height="64" role="img" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2l2.2 1.4 2.6-.2 1.2 2.3 2.4 1.1-.2 2.6L22 12l-1.4 2.2.2 2.6-2.3 1.2-1.1 2.4-2.6-.2L12 22l-2.2-1.4-2.6.2-1.2-2.3-2.4-1.1.2-2.6L2 12l1.4-2.2-.2-2.6L5.5 6l1.1-2.4 2.6.2L12 2z" fill="#3B82F6"/>
                            <path d="M10.1 14.6l-2.2-2.2a1 1 0 10-1.4 1.4l2.9 2.9a1 1 0 001.4 0l6.3-6.3a1 1 0 10-1.4-1.4l-5.6 5.6z" fill="#FFFFFF"/>
                        </svg>
                    </div>
                    <h2 style="color: #155724; margin: 0 0 10px 0;">Ticket Created Successfully!</h2>
                    <p style="color: #155724; margin-bottom: 20px;">Your support ticket has been submitted and our team has been notified.</p>
                    
                    <div class="ticket-id-display">
                        Ticket Number: <?= htmlspecialchars($ticketId) ?>
                    </div>
                    
                    <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                        <h4 style="color: #2c3e50; margin: 0 0 15px 0;">What happens next?</h4>
                        <ul style="color: #555; line-height: 2; margin: 0; padding-left: 20px;">
                            <li>✉️ You'll receive a confirmation email at <strong><?= htmlspecialchars($userEmail) ?></strong></li>
                            <li>👥 Our support team will review your ticket</li>
                            <li>📧 You'll get email notifications for any updates</li>
                            <li>⏱️ We aim to respond within <strong>24 hours</strong></li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap;">
                        <a href="my_tickets.php?ticket=<?= urlencode($ticketId) ?>" class="btn btn-primary" style="width: auto;">
                            <i class="fas fa-eye"></i> View Ticket
                        </a>
                        <a href="my_tickets.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> All Tickets
                        </a>
                        <a href="create_ticket.php" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Create Another
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Ticket Creation Form -->
            
            <!-- Chatbot Hint -->
            <div class="chatbot-hint">
                <div class="chatbot-hint-title">
                    <i class="fas fa-robot"></i> Quick Help Available
                </div>
                <div class="chatbot-hint-text">
                    Need instant answers? Try our <strong>AI Chatbot</strong> (bottom right corner) for immediate assistance with common questions before creating a ticket.
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="form-card">
                <form method="POST">
                    <!-- Issue Type -->
                    <div class="form-group">
                        <label class="form-label">
                            Issue Type <span class="required">*</span>
                        </label>
                        <div class="issue-type-grid">
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="order" id="type_order" <?= ($_POST['issue_type'] ?? '') === 'order' ? 'checked' : '' ?> required>
                                <label for="type_order" class="issue-type-label">
                                    <i class="fas fa-shopping-cart issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Order Issues</span>
                                        <span class="issue-desc">Tracking, delivery problems</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="product" id="type_product" <?= ($_POST['issue_type'] ?? '') === 'product' ? 'checked' : '' ?>>
                                <label for="type_product" class="issue-type-label">
                                    <i class="fas fa-box issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Product Questions</span>
                                        <span class="issue-desc">Quality, specifications</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="payment" id="type_payment" <?= ($_POST['issue_type'] ?? '') === 'payment' ? 'checked' : '' ?>>
                                <label for="type_payment" class="issue-type-label">
                                    <i class="fas fa-credit-card issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Payment Issues</span>
                                        <span class="issue-desc">Billing, refunds</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="shipping" id="type_shipping" <?= ($_POST['issue_type'] ?? '') === 'shipping' ? 'checked' : '' ?>>
                                <label for="type_shipping" class="issue-type-label">
                                    <i class="fas fa-truck issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Shipping</span>
                                        <span class="issue-desc">Delays, damaged items</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="account" id="type_account" <?= ($_POST['issue_type'] ?? '') === 'account' ? 'checked' : '' ?>>
                                <label for="type_account" class="issue-type-label">
                                    <i class="fas fa-user issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Account</span>
                                        <span class="issue-desc">Login, settings</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="technical" id="type_technical" <?= ($_POST['issue_type'] ?? '') === 'technical' ? 'checked' : '' ?>>
                                <label for="type_technical" class="issue-type-label">
                                    <i class="fas fa-cog issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Technical</span>
                                        <span class="issue-desc">Website bugs, errors</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="issue-type-option">
                                <input type="radio" name="issue_type" value="other" id="type_other" <?= ($_POST['issue_type'] ?? '') === 'other' ? 'checked' : '' ?>>
                                <label for="type_other" class="issue-type-label">
                                    <i class="fas fa-question-circle issue-icon"></i>
                                    <div class="issue-text">
                                        <span class="issue-name">Other</span>
                                        <span class="issue-desc">General inquiry</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Priority -->
                    <div class="form-group">
                        <label class="form-label">
                            Priority
                        </label>
                        <div class="priority-options">
                            <div class="priority-option">
                                <input type="radio" name="priority" value="low" id="priority_low" <?= ($_POST['priority'] ?? 'medium') === 'low' ? 'checked' : '' ?>>
                                <label for="priority_low" class="priority-label">Low</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="medium" id="priority_medium" <?= ($_POST['priority'] ?? 'medium') === 'medium' ? 'checked' : '' ?>>
                                <label for="priority_medium" class="priority-label">Medium</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="high" id="priority_high" <?= ($_POST['priority'] ?? 'medium') === 'high' ? 'checked' : '' ?>>
                                <label for="priority_high" class="priority-label">High</label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" name="priority" value="urgent" id="priority_urgent" <?= ($_POST['priority'] ?? 'medium') === 'urgent' ? 'checked' : '' ?>>
                                <label for="priority_urgent" class="priority-label">Urgent</label>
                            </div>
                        </div>
                        <div class="form-help">Select "Urgent" only for critical issues requiring immediate attention</div>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">
                            Detailed Description <span class="required">*</span>
                        </label>
                        <textarea name="description" class="form-control" placeholder="Please provide as much detail as possible about your issue..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        <div class="form-help">Include relevant details like order numbers, error messages, or steps to reproduce the issue (minimum 20 characters)</div>
                    </div>
                    
                    <!-- Contact Info Display -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Your Contact Information:</strong><br>
                            Name: <?= htmlspecialchars($userName) ?><br>
                            Email: <?= htmlspecialchars($userEmail) ?>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php 
// Display FAQ section for Customer Support
require_once __DIR__ . '/../includes/faq_section.php';
display_faq_section('Customer Support', 'Support FAQs', 8);

include __DIR__ . '/../includes/new-footer.php'; 
?>
