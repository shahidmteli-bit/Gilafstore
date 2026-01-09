<?php
$pageTitle = 'Reset Password — Gilaf Store';
$activePage = '';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$tokenValid = false;
$userData = null;

// Validate token
if ($token) {
    try {
        $db = get_db_connection();
        
        // Debug logging
        error_log("[RESET PASSWORD] Token received: " . substr($token, 0, 16) . "...");
        
        // First check if token exists at all
        $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $tokenExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenExists) {
            error_log("[RESET PASSWORD] Token not found in database");
            $error = 'Invalid or expired reset link. Please request a new password reset.';
        } else {
            error_log("[RESET PASSWORD] Token found - User ID: " . $tokenExists['user_id'] . ", Used: " . $tokenExists['used'] . ", Expires: " . $tokenExists['expires_at']);
            
            // Check current time vs expiry
            $currentTime = date('Y-m-d H:i:s');
            $expiresAt = $tokenExists['expires_at'];
            $stmt = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), ?) as seconds_remaining");
            $stmt->execute([$expiresAt]);
            $timeCheck = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("[RESET PASSWORD] Current time: " . $currentTime . ", Expires: " . $expiresAt . ", Seconds remaining: " . $timeCheck['seconds_remaining']);
            
            // Check if token exists and is valid
            $stmt = $db->prepare("
                SELECT pr.*, u.name, u.email, u.is_admin 
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.token = ? 
                AND pr.used = 0 
                AND pr.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData) {
                error_log("[RESET PASSWORD] Token validation SUCCESS for user: " . $userData['email']);
                $tokenValid = true;
            } else {
                // Detailed error logging
                if ($tokenExists['used'] == 1) {
                    error_log("[RESET PASSWORD] Token validation FAILED - Token already used");
                } else {
                    error_log("[RESET PASSWORD] Token validation FAILED - Token expired or user not found");
                }
                $error = 'Invalid or expired reset link. Please request a new password reset.';
            }
        }
    } catch (Exception $e) {
        error_log("[RESET PASSWORD] Exception: " . $e->getMessage());
        error_log("[RESET PASSWORD] Stack trace: " . $e->getTraceAsString());
        $error = 'An error occurred. Please try again.';
    }
} else {
    error_log("[RESET PASSWORD] No token provided in URL");
    $error = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Password is required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            $db = get_db_connection();
            
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userData['user_id']]);
            
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = true;
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Failed to reset password. Please try again.';
        }
    }
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
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        .reset-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(26, 60, 52, 0.15);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(197, 160, 89, 0.2);
        }
        
        .reset-header {
            background: linear-gradient(135deg, #1A3C34 0%, rgba(26, 60, 52, 0.9) 100%);
            padding: 40px;
            text-align: center;
        }
        
        .reset-icon {
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
        }
        
        .reset-header.admin {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .reset-header.admin .reset-icon {
            color: #fff;
        }
        
        .reset-header h3 {
            font-family: var(--font-serif);
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin: 0 0 8px 0;
        }
        
        .reset-header p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
        }
        
        .reset-body {
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
        
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #991b1b;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.08) 0%, rgba(220, 53, 69, 0.05) 100%);
            border-left: 4px solid #dc3545;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #721c24;
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
        
        .btn-login {
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
            background: linear-gradient(135deg, #C5A059 0%, #d4b068 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(197, 160, 89, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(197, 160, 89, 0.4);
        }
        
        .btn-admin {
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(220, 53, 69, 0.4);
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
        
        .password-requirements {
            background: rgba(197, 160, 89, 0.08);
            border-left: 4px solid var(--color-gold);
            padding: 14px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin: 4px 0;
            color: var(--color-text-light);
        }
        
        .admin-notice {
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
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header <?= $userData && $userData['is_admin'] ? 'admin' : '' ?>">
                <div class="reset-icon">
                    <i class="fas fa-<?= $userData && $userData['is_admin'] ? 'user-shield' : 'lock' ?>"></i>
                </div>
                <h3><?= $success ? 'Password Updated!' : ($userData && $userData['is_admin'] ? 'Create New Admin Password' : 'Create New Password'); ?></h3>
                <p><?= $success ? 'Your password has been successfully reset' : 'Enter your new password below'; ?></p>
            </div>
            
            <div class="reset-body">
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Success!</strong><br>
                        Your password has been reset successfully. You can now login with your new password.
                    </div>
                    
                    <?php if ($userData && $userData['is_admin']): ?>
                        <div class="admin-notice">
                            <i class="fas fa-shield-alt"></i>
                            <strong>Admin Password Reset:</strong><br>
                            Your admin password has been updated. You can now access the admin panel with your new credentials.
                        </div>
                        
                        <a href="<?= base_url('admin/admin_login.php'); ?>" class="btn-admin">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Go to Admin Login</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('user/login.php'); ?>" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Go to Login</span>
                        </a>
                    <?php endif; ?>
                    
                <?php elseif (!$tokenValid): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error); ?>
                    </div>
                    
                    <div class="footer-links">
                        <p>
                            <a href="<?= base_url('user/forgot_password.php'); ?>">
                                <i class="fas fa-redo"></i> Request New Reset Link
                            </a>
                        </p>
                        <p style="margin-top: 10px;">
                            <a href="<?= base_url('user/login.php'); ?>">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </p>
                    </div>
                    
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($userData && $userData['is_admin']): ?>
                        <div class="alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Admin Password Reset:</strong><br>
                            You are resetting an admin account password. Please choose a strong, secure password.
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" novalidate>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>
                                New Password
                            </label>
                            <input type="password" name="password" class="form-input" placeholder="Enter new password" required autofocus />
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>
                                Confirm Password
                            </label>
                            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password" required />
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check"></i>
                            <span>Reset Password</span>
                        </button>
                    </form>
                    
                    <div class="password-requirements">
                        <strong><i class="fas fa-info-circle"></i> Password Requirements:</strong>
                        <ul>
                            <li>Minimum 8 characters long</li>
                            <li>Use a strong, unique password</li>
                            <li>Avoid common words or patterns</li>
                            <?php if ($userData && $userData['is_admin']): ?>
                                <li>Admin passwords should be extra secure</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="footer-links">
                    <p>
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
