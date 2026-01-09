<?php
/**
 * GILAF SUPPORT TICKET EMAIL CONFIGURATION
 * Email functions for support ticket notifications
 * Uses: gilaf.help@gmail.com for customer support communications
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/email_config.php';

// Support email configuration
define('SUPPORT_EMAIL', 'gilaf.help@gmail.com');
define('SUPPORT_NAME', 'Gilaf Support Team');
define('SUPPORT_APP_PASSWORD', 'djruphlj nigdysnk'); // App Password for gilaf.help@gmail.com

// Admin notification email
define('ADMIN_NOTIFICATION_EMAIL', 'gilafstore@gmail.com');

/**
 * Send support email using gilaf.help@gmail.com
 */
function send_support_email($to, $subject, $body, $replyTo = null) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SUPPORT_EMAIL;
        $mail->Password   = SUPPORT_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom(SUPPORT_EMAIL, SUPPORT_NAME);
        $mail->addAddress($to);
        
        if ($replyTo) {
            $mail->addReplyTo($replyTo, SUPPORT_NAME);
        } else {
            $mail->addReplyTo(SUPPORT_EMAIL, SUPPORT_NAME);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Support email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Log email notification to database
 */
function log_ticket_email($ticketId, $recipientEmail, $emailType, $subject, $status = 'sent', $errorMessage = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("
            INSERT INTO ticket_email_log (ticket_id, recipient_email, email_type, subject, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $recipientEmail, $emailType, $subject, $status, $errorMessage]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to log ticket email: " . $e->getMessage());
        return false;
    }
}

/**
 * EMAIL TEMPLATE: Ticket Created Notification (User)
 */
function send_ticket_created_email($ticketData) {
    $subject = "Ticket Created – {$ticketData['ticket_id']} – Gilaf Support";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 40px; text-align: center; position: relative; }
            .header-icon { width: 70px; height: 70px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); }
            .header h1 { color: #ffffff; font-size: 26px; font-weight: 700; margin: 0; }
            .header p { color: rgba(255,255,255,0.9); font-size: 14px; margin: 10px 0 0 0; }
            .content { padding: 40px; }
            .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 20px; font-weight: 600; }
            .ticket-box { background: linear-gradient(135deg, #e8f4f8 0%, #d4ebf2 100%); border-left: 5px solid #3498db; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .ticket-box-title { font-size: 14px; color: #2980b9; margin-bottom: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
            .ticket-detail { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(52, 152, 219, 0.2); }
            .ticket-detail:last-child { border-bottom: none; }
            .ticket-label { font-weight: 600; color: #555; font-size: 14px; }
            .ticket-value { color: #2c3e50; font-size: 14px; }
            .message-text { font-size: 15px; color: #555; line-height: 1.8; margin: 20px 0; }
            .button-container { text-align: center; margin: 30px 0; }
            .button { display: inline-block; padding: 16px 40px; background: #1A3C34; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; }
            .info-box { background: #fff9e6; border-left: 5px solid #f39c12; padding: 15px; border-radius: 8px; margin: 25px 0; }
            .info-box-text { font-size: 14px; color: #7f8c8d; line-height: 1.6; }
            .footer { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); padding: 30px; text-align: center; }
            .footer-text { color: rgba(255,255,255,0.8); font-size: 13px; margin: 8px 0; }
            .footer-brand { color: #ffffff; font-weight: 700; font-size: 18px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <div class="header-icon">🎫</div>
                    <h1>Support Ticket Created</h1>
                    <p>We\'ve received your request</p>
                </div>
                
                <div class="content">
                    <div class="greeting">Hello ' . htmlspecialchars($ticketData['user_name']) . ',</div>
                    
                    <div class="message-text">
                        Thank you for contacting <strong>Gilaf Support</strong>. We\'ve successfully received your support request and our team is reviewing it.
                    </div>
                    
                    <div class="ticket-box">
                        <div class="ticket-box-title">📋 Ticket Details</div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Ticket ID:</span>
                            <span class="ticket-value"><strong>' . htmlspecialchars($ticketData['ticket_id']) . '</strong></span>
                        </div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Subject:</span>
                            <span class="ticket-value">' . htmlspecialchars($ticketData['subject']) . '</span>
                        </div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Issue Type:</span>
                            <span class="ticket-value">' . ucfirst(str_replace('_', ' ', $ticketData['issue_type'])) . '</span>
                        </div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Priority:</span>
                            <span class="ticket-value">' . ucfirst($ticketData['priority']) . '</span>
                        </div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Status:</span>
                            <span class="ticket-value"><strong>New</strong></span>
                        </div>
                        <div class="ticket-detail">
                            <span class="ticket-label">Created:</span>
                            <span class="ticket-value">' . date('M d, Y - h:i A') . '</span>
                        </div>
                    </div>
                    
                    <div class="button-container">
                        <a href="' . base_url('user/my_tickets.php?ticket=' . $ticketData['ticket_id']) . '" class="button">View Ticket Details</a>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-box-text">
                            <strong>⏱️ Response Time:</strong> We aim to respond within <strong>24 hours</strong>.<br>
                            <strong>💬 Updates:</strong> You\'ll receive email notifications for any updates.<br>
                            <strong>📧 Reply:</strong> You can reply directly to this email to add more information.
                        </div>
                    </div>
                    
                    <div class="message-text">
                        If you have additional details or attachments, please reply to this email or visit your ticket page.
                    </div>
                </div>
                
                <div class="footer">
                    <div class="footer-brand">GILAF SUPPORT</div>
                    <div class="footer-text">Need immediate assistance? Contact us:</div>
                    <div class="footer-text">📧 Email: gilaf.help@gmail.com</div>
                    <div class="footer-text">🌐 Website: ' . base_url() . '</div>
                    <div class="footer-text" style="margin-top: 20px;">© ' . date('Y') . ' Gilaf Store. All rights reserved.</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent = send_support_email($ticketData['user_email'], $subject, $body);
    log_ticket_email($ticketData['ticket_id'], $ticketData['user_email'], 'ticket_created', $subject, $sent ? 'sent' : 'failed');
    
    return $sent;
}

/**
 * EMAIL TEMPLATE: Ticket Status Changed (User)
 */
function send_ticket_status_changed_email($ticketData, $oldStatus, $newStatus) {
    $subject = "Ticket Update – Status Changed to " . ucfirst(str_replace('_', ' ', $newStatus)) . " – {$ticketData['ticket_id']}";
    
    $statusColors = [
        'new' => '#3498db',
        'open' => '#3498db',
        'acknowledged' => '#9b59b6',
        'in_progress' => '#f39c12',
        'on_hold' => '#e67e22',
        'resolved' => '#27ae60',
        'closed' => '#95a5a6'
    ];
    
    $statusColor = $statusColors[$newStatus] ?? '#3498db';
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, ' . $statusColor . ' 0%, ' . $statusColor . 'dd 100%); padding: 40px; text-align: center; }
            .header-icon { width: 70px; height: 70px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
            .header h1 { color: #ffffff; font-size: 26px; font-weight: 700; margin: 0; }
            .header p { color: rgba(255,255,255,0.9); font-size: 14px; margin: 10px 0 0 0; }
            .content { padding: 40px; }
            .status-change { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 8px; margin: 25px 0; text-align: center; }
            .status-badge { display: inline-block; padding: 8px 20px; border-radius: 20px; font-weight: 600; font-size: 14px; margin: 5px; }
            .status-old { background: #e9ecef; color: #6c757d; }
            .status-new { background: ' . $statusColor . '; color: white; }
            .arrow { font-size: 24px; color: #6c757d; margin: 0 10px; }
            .message-text { font-size: 15px; color: #555; line-height: 1.8; margin: 20px 0; }
            .button { display: inline-block; padding: 16px 40px; background: #1A3C34; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); padding: 30px; text-align: center; }
            .footer-text { color: rgba(255,255,255,0.8); font-size: 13px; margin: 8px 0; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <div class="header-icon">🔄</div>
                    <h1>Ticket Status Updated</h1>
                    <p>Your ticket has been updated</p>
                </div>
                
                <div class="content">
                    <div class="message-text">
                        Hello <strong>' . htmlspecialchars($ticketData['user_name']) . '</strong>,
                    </div>
                    
                    <div class="message-text">
                        The status of your support ticket <strong>' . htmlspecialchars($ticketData['ticket_id']) . '</strong> has been updated.
                    </div>
                    
                    <div class="status-change">
                        <span class="status-badge status-old">' . ucfirst(str_replace('_', ' ', $oldStatus)) . '</span>
                        <span class="arrow">→</span>
                        <span class="status-badge status-new">' . ucfirst(str_replace('_', ' ', $newStatus)) . '</span>
                    </div>
                    
                    <div class="message-text">
                        <strong>Subject:</strong> ' . htmlspecialchars($ticketData['subject']) . '<br>
                        <strong>Updated:</strong> ' . date('M d, Y - h:i A') . '
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . base_url('user/my_tickets.php?ticket=' . $ticketData['ticket_id']) . '" class="button">View Ticket</a>
                    </div>
                </div>
                
                <div class="footer">
                    <div class="footer-text">© ' . date('Y') . ' Gilaf Support. All rights reserved.</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent = send_support_email($ticketData['user_email'], $subject, $body);
    log_ticket_email($ticketData['ticket_id'], $ticketData['user_email'], 'status_changed', $subject, $sent ? 'sent' : 'failed');
    
    return $sent;
}

/**
 * EMAIL TEMPLATE: New Comment Added (User)
 */
function send_ticket_comment_email($ticketData, $comment, $commenterName) {
    $subject = "New Response on Your Ticket – {$ticketData['ticket_id']}";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 40px; text-align: center; }
            .header h1 { color: #ffffff; font-size: 24px; margin: 0; }
            .content { padding: 40px; }
            .comment-box { background: #f8f9fa; border-left: 4px solid #1A3C34; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .comment-author { font-weight: 700; color: #1A3C34; margin-bottom: 10px; }
            .comment-text { color: #555; line-height: 1.8; }
            .button { display: inline-block; padding: 14px 32px; background: #1A3C34; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { background: #34495e; padding: 30px; text-align: center; color: rgba(255,255,255,0.8); font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <h1>💬 New Response on Your Ticket</h1>
                </div>
                
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($ticketData['user_name']) . '</strong>,</p>
                    
                    <p style="margin: 20px 0;">Our support team has added a response to your ticket <strong>' . htmlspecialchars($ticketData['ticket_id']) . '</strong>.</p>
                    
                    <div class="comment-box">
                        <div class="comment-author">Response from: ' . htmlspecialchars($commenterName) . '</div>
                        <div class="comment-text">' . nl2br(htmlspecialchars($comment)) . '</div>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . base_url('user/my_tickets.php?ticket=' . $ticketData['ticket_id']) . '" class="button">View Full Conversation</a>
                    </div>
                    
                    <p style="color: #7f8c8d; font-size: 14px;">You can reply directly to this email or visit your ticket page to add a response.</p>
                </div>
                
                <div class="footer">
                    © ' . date('Y') . ' Gilaf Support. All rights reserved.
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent = send_support_email($ticketData['user_email'], $subject, $body);
    log_ticket_email($ticketData['ticket_id'], $ticketData['user_email'], 'comment_added', $subject, $sent ? 'sent' : 'failed');
    
    return $sent;
}

/**
 * EMAIL TEMPLATE: Ticket Resolved (User)
 */
function send_ticket_resolved_email($ticketData) {
    $subject = "Ticket Resolved – {$ticketData['ticket_id']} – Gilaf Support";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); padding: 40px; text-align: center; }
            .header-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
            .header h1 { color: #ffffff; font-size: 26px; margin: 0; }
            .content { padding: 40px; }
            .success-box { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 5px solid #27ae60; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .button { display: inline-block; padding: 14px 32px; background: #1A3C34; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { background: #34495e; padding: 30px; text-align: center; color: rgba(255,255,255,0.8); font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <div class="header-icon">✅</div>
                    <h1>Ticket Resolved</h1>
                </div>
                
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($ticketData['user_name']) . '</strong>,</p>
                    
                    <div class="success-box">
                        <p style="font-size: 16px; font-weight: 600; color: #155724; margin-bottom: 10px;">Your support ticket has been resolved!</p>
                        <p style="color: #155724;"><strong>Ticket ID:</strong> ' . htmlspecialchars($ticketData['ticket_id']) . '</p>
                        <p style="color: #155724;"><strong>Subject:</strong> ' . htmlspecialchars($ticketData['subject']) . '</p>
                    </div>
                    
                    <p style="margin: 20px 0;">We hope we were able to address your concern satisfactorily. If you need further assistance or have additional questions, please don\'t hesitate to reach out.</p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . base_url('user/my_tickets.php?ticket=' . $ticketData['ticket_id']) . '" class="button">View Ticket</a>
                    </div>
                    
                    <p style="color: #7f8c8d; font-size: 14px; margin-top: 30px;">Thank you for choosing Gilaf. We appreciate your business!</p>
                </div>
                
                <div class="footer">
                    © ' . date('Y') . ' Gilaf Support. All rights reserved.
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent = send_support_email($ticketData['user_email'], $subject, $body);
    log_ticket_email($ticketData['ticket_id'], $ticketData['user_email'], 'ticket_resolved', $subject, $sent ? 'sent' : 'failed');
    
    return $sent;
}

/**
 * EMAIL TEMPLATE: New Ticket Notification (Admin Only)
 */
function send_admin_new_ticket_notification($ticketData) {
    $subject = "New Support Ticket – {$ticketData['ticket_id']} – {$ticketData['issue_type']}";
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 40px; text-align: center; }
            .header h1 { color: #ffffff; font-size: 24px; margin: 0; }
            .content { padding: 40px; }
            .alert-box { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .ticket-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .detail-row { padding: 10px 0; border-bottom: 1px solid #dee2e6; }
            .detail-label { font-weight: 600; color: #555; }
            .button { display: inline-block; padding: 14px 32px; background: #e74c3c; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { background: #34495e; padding: 30px; text-align: center; color: rgba(255,255,255,0.8); font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <h1>🚨 New Support Ticket</h1>
                </div>
                
                <div class="content">
                    <div class="alert-box">
                        <strong>⚠️ Action Required:</strong> A new support ticket has been created and requires attention.
                    </div>
                    
                    <div class="ticket-details">
                        <div class="detail-row">
                            <span class="detail-label">Ticket ID:</span> <strong>' . htmlspecialchars($ticketData['ticket_id']) . '</strong>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Customer:</span> ' . htmlspecialchars($ticketData['user_name']) . ' (' . htmlspecialchars($ticketData['user_email']) . ')
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Subject:</span> ' . htmlspecialchars($ticketData['subject']) . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Issue Type:</span> ' . ucfirst(str_replace('_', ' ', $ticketData['issue_type'])) . '
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Priority:</span> <strong>' . ucfirst($ticketData['priority']) . '</strong>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created:</span> ' . date('M d, Y - h:i A') . '
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <strong>Description:</strong><br>
                        ' . nl2br(htmlspecialchars($ticketData['description'])) . '
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . base_url('admin/support_tickets.php?ticket=' . $ticketData['ticket_id']) . '" class="button">View & Respond</a>
                    </div>
                </div>
                
                <div class="footer">
                    Gilaf Admin Notification System
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent = send_support_email(ADMIN_NOTIFICATION_EMAIL, $subject, $body);
    log_ticket_email($ticketData['ticket_id'], ADMIN_NOTIFICATION_EMAIL, 'admin_notification', $subject, $sent ? 'sent' : 'failed');
    
    return $sent;
}

/**
 * Generate unique ticket ID
 */
function generate_ticket_id() {
    $prefix = 'GILAF-SUP-';
    $timestamp = date('ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return $prefix . $timestamp . '-' . $random;
}
?>
