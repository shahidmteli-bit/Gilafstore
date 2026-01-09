<?php
/**
 * Email Configuration using PHPMailer
 * SMTP Settings for sending emails from gilaffoods@gmail.com
 */

// Load PHPMailer manually
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get configured PHPMailer instance
 * 
 * @return PHPMailer Configured mailer instance
 */
function getMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Enable verbose debug output for troubleshooting
        $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gilaf.secure@gmail.com';
        $mail->Password   = 'mzzn wtnw mmuj kqqo'; // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        // Timeout settings
        $mail->Timeout    = 30;
        $mail->SMTPKeepAlive = false;
        
        // Default sender
        $mail->setFrom('gilaf.secure@gmail.com', 'Gilaf Store');
        
        // Content type
        $mail->isHTML(true);
        
        error_log("PHPMailer: Mailer instance created successfully");
        
    } catch (Exception $e) {
        error_log("PHPMailer Configuration Error: " . $e->getMessage());
        throw $e;
    }
    
    return $mail;
}

/**
 * Send email using SMTP
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $fromEmail Sender email (default: gilaffoods@gmail.com)
 * @param string $fromName Sender name
 * @return bool Success status
 */
function send_email($to, $subject, $body, $fromEmail = 'gilaf.secure@gmail.com', $fromName = 'Gilaf Security Team') {
    // Validate recipient email
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("send_email() FAILED: Invalid recipient email - " . $to);
        return false;
    }
    
    try {
        error_log("send_email() called - To: {$to}, Subject: {$subject}");
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gilaf.secure@gmail.com';
        $mail->Password   = 'mzzn wtnw mmuj kqqo'; // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        if ($mail->send()) {
            error_log("send_email() SUCCESS: Email sent to {$to}");
            return true;
        } else {
            error_log("send_email() FAILED: {$mail->ErrorInfo}");
            return false;
        }
    } catch (Exception $e) {
        error_log("send_email() EXCEPTION: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 * 
 * @param string $email User email
 * @param string $resetLink Password reset link
 * @param string $userName User name
 * @return bool Success status
 */
function send_password_reset_email($email, $resetLink, $userName = 'User') {
    $subject = 'Password Reset Request - Gilaf Store';
    
    // Use security email for password resets
    $fromEmail = 'gilaf.secure@gmail.com';
    $fromName = 'Gilaf Security Team';
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #2c3e50; background: #f4f7fa; }
            .email-wrapper { width: 100%; background: #f4f7fa; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 50px 40px; text-align: center; position: relative; }
            .header-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
            .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
            .header p { color: rgba(255,255,255,0.95); font-size: 15px; margin: 10px 0 0 0; position: relative; z-index: 1; }
            .content { padding: 50px 40px; }
            .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 25px; font-weight: 600; }
            .message-text { font-size: 15px; color: #555; line-height: 1.8; margin: 20px 0; }
            .button-container { text-align: center; margin: 35px 0; }
            .reset-button { display: inline-block !important; padding: 18px 45px !important; background: #C5A059 !important; color: #ffffff !important; text-decoration: none !important; border-radius: 50px !important; font-weight: 700 !important; font-size: 16px !important; text-transform: uppercase !important; letter-spacing: 1px !important; mso-padding-alt: 18px 45px; }
            .reset-button:hover { background: #d4b068 !important; }
            .link-box { background: #f8f9fa; border: 2px dashed #dee2e6; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .link-box-title { font-size: 13px; color: #7f8c8d; margin-bottom: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
            .link-text { word-break: break-all; color: #3498db; font-size: 13px; font-family: "Courier New", monospace; line-height: 1.6; }
            .security-notice { background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); border-left: 5px solid #f39c12; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .security-notice-title { font-size: 16px; font-weight: 700; color: #d68910; margin-bottom: 15px; }
            .security-list { margin: 0; padding-left: 20px; }
            .security-list li { color: #7f8c8d; font-size: 14px; margin: 8px 0; line-height: 1.6; }
            .divider { height: 1px; background: linear-gradient(90deg, transparent 0%, #dee2e6 50%, transparent 100%); margin: 35px 0; }
            .signature { margin-top: 30px; }
            .signature-text { font-size: 15px; color: #555; line-height: 1.6; }
            .signature-name { font-weight: 700; color: #2c3e50; font-size: 16px; }
            .footer { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); padding: 35px 40px; text-align: center; }
            .footer-text { color: rgba(255,255,255,0.8); font-size: 13px; margin: 8px 0; }
            .footer-brand { color: #ffffff; font-weight: 700; font-size: 18px; margin-bottom: 15px; letter-spacing: 1px; }
            @media only screen and (max-width: 600px) {
                .email-wrapper { padding: 20px 10px; }
                .header { padding: 40px 25px; }
                .content { padding: 35px 25px; }
                .header h1 { font-size: 24px; }
                .reset-button { padding: 16px 35px; font-size: 14px; }
                .footer { padding: 30px 25px; }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="header">
                    <div class="header-icon">🔐</div>
                    <h1>Password Reset Request</h1>
                    <p>Secure Account Recovery</p>
                </div>
                
                <div class="content">
                    <div class="greeting">Hello ' . htmlspecialchars($userName) . ',</div>
                    
                    <div class="message-text">
                        We received a request to reset your password for your Gilaf Store account. To proceed with creating a new secure password, please click the button below:
                    </div>
                    
                    <div class="button-container">
                        <a href="' . htmlspecialchars($resetLink) . '" class="reset-button">Reset Password</a>
                    </div>
                    
                    <div class="link-box">
                        <div class="link-box-title">Or copy this link to your browser:</div>
                        <div class="link-text">' . htmlspecialchars($resetLink) . '</div>
                    </div>
                    
                    <div class="security-notice">
                        <div class="security-notice-title">🛡️ Security Notice</div>
                        <ul class="security-list">
                            <li>This secure link will <strong>expire in 15 minutes</strong> for your protection</li>
                            <li>If you did not request this, please ignore this email</li>
                            <li>Never share this link with anyone, including support staff</li>
                            <li>Use a strong, unique password for your account</li>
                        </ul>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="signature">
                        <div class="signature-text">Best regards,</div>
                        <div class="signature-name">Gilaf Store Team</div>
                    </div>
                </div>
                
                <div class="footer">
                    <div class="footer-brand">GILAF STORE</div>
                    <div class="footer-text">Premium Quality Products & Services</div>
                    <div class="footer-text" style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <strong style="color: #C5A059;">🔒 Bank-Grade Security</strong><br>
                        <span style="font-size: 11px; opacity: 0.9;">Your password reset is protected with military-grade 256-bit encryption. All communications are secured end-to-end to ensure your account safety.</span>
                    </div>
                    <div class="footer-text">© ' . date('Y') . ' Gilaf Store. All rights reserved.</div>
                    <div class="footer-text" style="margin-top: 10px; font-size: 12px; opacity: 0.7;">This is an automated security email. Please do not reply to this message.</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return send_email($email, $subject, $body, $fromEmail, $fromName);
}
?>
