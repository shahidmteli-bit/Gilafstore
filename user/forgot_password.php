<?php
$pageTitle = 'Forgot Password — Gilaf Store';
$activePage = '';
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
            
            // Check if user exists (regular user or admin)
            $stmt = $db->prepare("SELECT id, name, email, is_admin FROM users WHERE email = ?");
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
                $resetLink = base_url("user/reset_password.php?token=" . $token);
                
                // Handle special case: if user is admin and email matches SMTP email
                $isSelfEmail = ($email === 'gilafstore@gmail.com' && $user['is_admin'] == 1);
                
                // Send email
                require_once __DIR__ . '/../includes/email_config.php';
                $emailSubject = $isSelfEmail 
                    ? 'Admin Password Reset - Gilaf Store (Self-Reset)'
                    : 'Password Reset Request - Gilaf Store';
                
                $emailBody = $isSelfEmail 
                    ? generate_self_reset_email($user['name'], $resetLink)
                    : generate_user_reset_email($user['name'], $resetLink);
                
                $emailSent = send_email($email, $emailSubject, $emailBody);
                
                if ($emailSent) {
                    $success = true;
                    if ($isSelfEmail) {
                        $success = true; // Special handling for admin self-reset
                    }
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

function generate_user_reset_email($userName, $resetLink) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #C5A059 0%, #d4b068 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 Password Reset Request</h1>
            </div>
            <div class="content">
                <p>Hello <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                
                <p>We received a request to reset your password for your Gilaf Store account. Click the button below to create a new password:</p>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($resetLink) . '" class="button">Reset Password</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style="word-break: break-all; background: white; padding: 12px; border-radius: 4px; font-size: 13px;">' . htmlspecialchars($resetLink) . '</p>
                
                <div class="warning">
                    <strong>⚠️ Security Notice:</strong>
                    <ul style="margin: 10px 0;">
                        <li>This link will expire in <strong>15 minutes</strong></li>
                        <li>If you didn\'t request this, please ignore this email</li>
                        <li>Never share this link with anyone</li>
                    </ul>
                </div>
                
                <p>If you have any questions, contact our support team.</p>
                
                <p>Best regards,<br><strong>Gilaf Store Team</strong></p>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Gilaf Store. All rights reserved.</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}

function generate_self_reset_email($userName, $resetLink) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .warning { background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; margin: 20px 0; }
            .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 12px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>⚠️ Admin Self-Password Reset</h1>
            </div>
            <div class="content">
                <p>Hello <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                
                <div class="warning">
                    <strong>⚠️ IMPORTANT:</strong> You are resetting your own admin password using the same email address that sends emails from this system.
                </div>
                
                <p>This is a special case where you are both the sender and receiver of this password reset email.</p>
                
                <div class="info">
                    <strong>ℹ️ Technical Note:</strong> This email was sent from your own account (gilafstore@gmail.com) to yourself for password reset purposes.
                </div>
                
                <p>Click the button below to create a new admin password:</p>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($resetLink) . '" class="button">Reset Admin Password</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style="word-break: break-all; background: white; padding: 12px; border-radius: 4px; font-size: 13px;">' . htmlspecialchars($resetLink) . '</p>
                
                <div class="warning">
                    <strong>⚠️ Security Notice:</strong>
                    <ul style="margin: 10px 0;">
                        <li>This link will expire in <strong>1 hour</strong></li>
                        <li>This is an admin password reset</li>
                        <li>If you didn\'t request this, secure your account immediately</li>
                    </ul>
                </div>
                
                <p>Best regards,<br><strong>Gilaf Store System</strong></p>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Gilaf Store. All rights reserved.</p>
                <p>This is an automated email. Please do not reply.</p>
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
            animation: userForgotSecurityPulse 3s ease-in-out infinite, userForgotSecurityFloat 4s ease-in-out infinite;
        }
        
        .forgot-icon i {
            animation: userForgotShieldShine 2s ease-in-out infinite;
        }
        
        @keyframes userForgotSecurityPulse {
            0%, 100% {
                box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3), 0 0 0 0 rgba(197, 160, 89, 0.4);
            }
            50% {
                box-shadow: 0 10px 40px rgba(197, 160, 89, 0.5), 0 0 0 20px rgba(197, 160, 89, 0);
            }
        }
        
        @keyframes userForgotSecurityFloat {
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
        
        @keyframes userForgotShieldShine {
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
        
        .special-note {
            background: rgba(220, 53, 69, 0.08);
            border-left: 4px solid #dc3545;
            padding: 14px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #721c24;
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
                        If an account exists with this email, you'll receive password reset instructions shortly. Please check your inbox and spam folder.
                    </div>
                    
                    <?php if ($email === 'gilafstore@gmail.com'): ?>
                        <div class="special-note">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Admin Self-Reset Detected:</strong><br>
                            You requested a password reset for the admin account that uses the same email for sending emails. This is a special case where you'll receive an email from your own account.
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i> The reset link will expire in 15 minutes for security reasons.
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
                                Email Address
                            </label>
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($email); ?>" placeholder="your-email@example.com" required autofocus />
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Reset Link</span>
                        </button>
                    </form>
                    
                    <?php if ($email === 'gilafstore@gmail.com'): ?>
                        <div class="special-note">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Note:</strong> If you enter gilafstore@gmail.com, you'll receive an email from your own account (admin self-reset).
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <i class="fas fa-shield-alt"></i> For security, we'll send a password reset link to your registered email address.
                    </div>
                <?php endif; ?>
                
                <div class="footer-links">
                    <p>
                        <a href="<?= base_url('user/login.php'); ?>">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </p>
                    <p style="margin-top: 10px;">
                        <a href="<?= base_url('index.php'); ?>">
                            <i class="fas fa-home"></i> Back to Store
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
