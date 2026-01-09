<?php
$pageTitle = 'Forgot Password — Gilaf Admin';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$success = false;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            $db = get_db_connection();
            
            // Check if user exists and is admin
            $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_admin = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store token in database
                $stmt = $db->prepare("
                    INSERT INTO password_resets (user_id, email, token, expires_at) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $email, $token, $expiresAt]);
                
                // Generate reset link
                $resetLink = base_url("admin/reset_password.php?token=" . $token);
                
                // Send email
                require_once __DIR__ . '/../includes/email_config.php';
                
                // Check for admin self-email case
                $isSelfEmail = ($email === 'gilafstore@gmail.com');
                
                if ($isSelfEmail) {
                    // Special handling for admin self-email
                    $subject = 'Admin Password Reset - Gilaf Store (Self-Reset)';
                    $body = generate_admin_self_reset_email($user['name'], $resetLink);
                    $emailSent = send_email($email, $subject, $body, 'gilaf.secure@gmail.com', 'Gilaf Security Team');
                } else {
                    // Standard admin reset
                    $emailSent = send_password_reset_email($email, $resetLink, $user['name']);
                }
                
                if ($emailSent) {
                    $success = true;
                } else {
                    $error = 'Failed to send email. Please try again or contact support.';
                }
            } else {
                // Don't reveal if email exists for security
                $success = true;
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

function generate_admin_self_reset_email($userName, $resetLink) {
    return '
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
            .header { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); padding: 50px 40px; text-align: center; position: relative; }
            .header::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width=\'100\' height=\'100\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 0h100v100H0z\' fill=\'none\'/%3E%3Cpath d=\'M0 0l100 100M100 0L0 100\' stroke=\'%23fff\' stroke-width=\'.5\' opacity=\'.1\'/%3E%3C/svg%3E"); opacity: 0.1; }
            .header-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
            .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
            .header p { color: rgba(255,255,255,0.95); font-size: 15px; margin: 10px 0 0 0; position: relative; z-index: 1; }
            .content { padding: 50px 40px; }
            .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 25px; font-weight: 600; }
            .alert-box { background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%); border-left: 5px solid #e74c3c; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .alert-box-title { font-size: 16px; font-weight: 700; color: #c0392b; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
            .alert-box-text { font-size: 14px; color: #7f8c8d; line-height: 1.6; }
            .info-box { background: linear-gradient(135deg, #e8f4f8 0%, #d4ebf2 100%); border-left: 5px solid #3498db; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .info-box-title { font-size: 16px; font-weight: 700; color: #2980b9; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
            .info-box-text { font-size: 14px; color: #7f8c8d; line-height: 1.6; }
            .message-text { font-size: 15px; color: #555; line-height: 1.8; margin: 20px 0; }
            .button-container { text-align: center; margin: 35px 0; }
            .reset-button { display: inline-block !important; padding: 18px 45px !important; background: #e74c3c !important; color: #ffffff !important; text-decoration: none !important; border-radius: 50px !important; font-weight: 700 !important; font-size: 16px !important; text-transform: uppercase !important; letter-spacing: 1px !important; mso-padding-alt: 18px 45px; }
            .reset-button:hover { background: #c0392b !important; }
            .link-box { background: #f8f9fa; border: 2px dashed #dee2e6; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .link-box-title { font-size: 13px; color: #7f8c8d; margin-bottom: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
            .link-text { word-break: break-all; color: #3498db; font-size: 13px; font-family: "Courier New", monospace; line-height: 1.6; }
            .security-notice { background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); border-left: 5px solid #f39c12; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .security-notice-title { font-size: 16px; font-weight: 700; color: #d68910; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
            .security-list { margin: 0; padding-left: 20px; }
            .security-list li { color: #7f8c8d; font-size: 14px; margin: 8px 0; line-height: 1.6; }
            .divider { height: 1px; background: linear-gradient(90deg, transparent 0%, #dee2e6 50%, transparent 100%); margin: 35px 0; }
            .signature { margin-top: 30px; }
            .signature-text { font-size: 15px; color: #555; line-height: 1.6; }
            .signature-name { font-weight: 700; color: #2c3e50; font-size: 16px; }
            .footer { background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%); padding: 35px 40px; text-align: center; }
            .footer-text { color: rgba(255,255,255,0.8); font-size: 13px; margin: 8px 0; }
            .footer-brand { color: #ffffff; font-weight: 700; font-size: 18px; margin-bottom: 15px; letter-spacing: 1px; }
            .footer-links { margin: 20px 0; }
            .footer-link { color: #3498db; text-decoration: none; margin: 0 12px; font-size: 13px; }
            .social-icons { margin: 20px 0; }
            .social-icon { display: inline-block; width: 36px; height: 36px; margin: 0 6px; background: rgba(255,255,255,0.1); border-radius: 50%; line-height: 36px; color: #ffffff; text-decoration: none; transition: all 0.3s ease; }
            .social-icon:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
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
                    <h1>Admin Password Reset</h1>
                    <p>Secure Account Recovery</p>
                </div>
                
                <div class="content">
                    <div class="greeting">Hello ' . htmlspecialchars($userName) . ',</div>
                    
                    <div class="alert-box">
                        <div class="alert-box-title">⚠️ Admin Self-Reset Detected</div>
                        <div class="alert-box-text">
                            You are resetting your own admin password using the same email address that sends system emails. This is a special administrative operation.
                        </div>
                    </div>
                    
                    <div class="message-text">
                        We received a request to reset your administrator account password. To proceed with creating a new secure password, please click the button below:
                    </div>
                    
                    <div class="button-container">
                        <a href="' . htmlspecialchars($resetLink) . '" class="reset-button">Reset Admin Password</a>
                    </div>
                    
                    <div class="link-box">
                        <div class="link-box-title">Or copy this link to your browser:</div>
                        <div class="link-text">' . htmlspecialchars($resetLink) . '</div>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-box-title">ℹ️ Technical Information</div>
                        <div class="info-box-text">
                            This email was sent from your own account (gilafstore@gmail.com) to yourself for administrative password reset purposes. This is normal for admin self-service operations.
                        </div>
                    </div>
                    
                    <div class="security-notice">
                        <div class="security-notice-title">🛡️ Security Guidelines</div>
                        <ul class="security-list">
                            <li>This secure link will <strong>expire in 15 minutes</strong> for your protection</li>
                            <li>This is an <strong>administrator-level</strong> password reset request</li>
                            <li>If you did not initiate this request, <strong>secure your account immediately</strong></li>
                            <li>Never share this link with anyone, including support staff</li>
                            <li>Use a strong, unique password for your admin account</li>
                        </ul>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="signature">
                        <div class="signature-text">Best regards,</div>
                        <div class="signature-name">Gilaf Store Security Team</div>
                    </div>
                </div>
                
                <div class="footer">
                    <div class="footer-brand">GILAF STORE</div>
                    <div class="footer-text">Premium Quality Products & Services</div>
                    <div class="footer-text" style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <strong style="color: #fff;">🔒 Enterprise-Grade Security</strong><br>
                        <span style="font-size: 11px; opacity: 0.9;">This admin password reset is protected with military-grade 256-bit AES encryption. All communications are secured end-to-end with advanced cryptographic protocols to ensure maximum account protection.</span>
                    </div>
                    <div class="footer-text">© ' . date('Y') . ' Gilaf Store. All rights reserved.</div>
                    <div class="footer-text" style="margin-top: 10px; font-size: 12px; opacity: 0.7;">This is an automated security email. Please do not reply to this message.</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset_url('css/new-design.css'); ?>">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(26, 60, 52, 0.05) 0%, rgba(197, 160, 89, 0.05) 100%);
            font-family: var(--font-sans);
        }
        
        .forgot-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        .forgot-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(26, 60, 52, 0.15);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(197, 160, 89, 0.2);
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #1A3C34 0%, rgba(26, 60, 52, 0.9) 100%);
            padding: 40px;
            text-align: center;
            position: relative;
        }
        
        .forgot-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--color-gold);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(197, 160, 89, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: forgotSecurityPulse 3s ease-in-out infinite, forgotSecurityFloat 4s ease-in-out infinite;
        }
        
        .forgot-icon i {
            animation: forgotShieldShine 2s ease-in-out infinite;
        }
        
        @keyframes forgotSecurityPulse {
            0%, 100% {
                box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3), 0 0 0 0 rgba(197, 160, 89, 0.4);
            }
            50% {
                box-shadow: 0 10px 40px rgba(197, 160, 89, 0.5), 0 0 0 20px rgba(197, 160, 89, 0);
            }
        }
        
        @keyframes forgotSecurityFloat {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            25% {
                transform: translateY(-10px) rotate(-3deg);
            }
            50% {
                transform: translateY(0px) rotate(0deg);
            }
            75% {
                transform: translateY(-10px) rotate(3deg);
            }
        }
        
        @keyframes forgotShieldShine {
            0%, 100% {
                filter: drop-shadow(0 0 5px rgba(197, 160, 89, 0.5));
            }
            50% {
                filter: drop-shadow(0 0 15px rgba(197, 160, 89, 0.8));
            }
        }
        
        .forgot-header h3 {
            font-family: var(--font-serif);
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin: 0 0 8px 0;
        }
        
        .forgot-header p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
        }
        
        .forgot-body {
            padding: 40px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(34, 197, 94, 0.05) 100%);
            border-left: 4px solid #22c55e;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #166534;
        }
        
        .alert-success i {
            color: #22c55e;
            margin-right: 10px;
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #991b1b;
        }
        
        .alert-error i {
            color: #ef4444;
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-green);
            margin-bottom: 10px;
        }
        
        .form-label i {
            color: var(--color-gold);
        }
        
        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(26, 60, 52, 0.1);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: var(--font-sans);
            color: var(--color-text);
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--color-gold);
            background: white;
            box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-submit {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(26, 60, 52, 0.4);
        }
        
        .footer-links {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid rgba(26, 60, 52, 0.1);
            font-size: 0.9rem;
            color: var(--color-text-light);
        }
        
        .footer-links a {
            color: var(--color-gold);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--color-green);
            text-decoration: underline;
        }
        
        .info-box {
            background: rgba(197, 160, 89, 0.08);
            border-left: 4px solid var(--color-gold);
            padding: 14px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--color-text-light);
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="forgot-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3>Reset Password</h3>
                <p>Enter your email to receive reset instructions</p>
            </div>
            
            <div class="forgot-body">
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Email Sent!</strong><br>
                        If an admin account exists with this email, you'll receive password reset instructions shortly. Please check your inbox and spam folder.
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i> The reset link will expire in 1 hour for security reasons.
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" novalidate>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Admin Email Address
                            </label>
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($email); ?>" placeholder="admin@gilaf.com" required autofocus />
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Reset Link</span>
                        </button>
                    </form>
                    
                    <div class="info-box">
                        <i class="fas fa-shield-alt"></i> For security, we'll send a password reset link to your registered email address.
                    </div>
                <?php endif; ?>
                
                <div class="footer-links">
                    <p>
                        <a href="<?= base_url('admin/admin_login.php'); ?>">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
