<?php
$pageTitle = 'Admin Login — Gilaf Store';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['user']) && !empty($_SESSION['user']['is_admin'])) {
    header('Location: ' . base_url('admin/index.php'));
    exit;
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '') {
        $errors['email'] = 'Username is required';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required';
    }

    if (!$errors) {
        if (attempt_login($identifier, $password) && !empty($_SESSION['user']['is_admin'])) {
            redirect_with_message('/admin/index.php', 'Welcome back, admin!');
        } else {
            unset($_SESSION['user']);
            $errors['general'] = 'Invalid credentials or insufficient permissions';
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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
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
        
        .admin-login-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        
        .admin-login-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(26, 60, 52, 0.15);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(197, 160, 89, 0.2);
        }
        
        .admin-header {
            background: linear-gradient(135deg, #1A3C34 0%, rgba(26, 60, 52, 0.9) 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(197, 160, 89, 0.15) 0%, transparent 70%);
            animation: adminPulse 8s ease-in-out infinite;
        }
        
        @keyframes adminPulse {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-10px, -10px) scale(1.1); }
        }
        
        .admin-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #C5A059;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(197, 160, 89, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
            animation: securityPulse 3s ease-in-out infinite, securityFloat 4s ease-in-out infinite;
        }
        
        @keyframes securityPulse {
            0%, 100% {
                box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3), 0 0 0 0 rgba(197, 160, 89, 0.4);
            }
            50% {
                box-shadow: 0 10px 40px rgba(197, 160, 89, 0.5), 0 0 0 20px rgba(197, 160, 89, 0);
            }
        }
        
        @keyframes securityFloat {
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
        
        .admin-icon i {
            animation: shieldShine 2s ease-in-out infinite;
        }
        
        @keyframes shieldShine {
            0%, 100% {
                filter: drop-shadow(0 0 5px rgba(197, 160, 89, 0.5));
            }
            50% {
                filter: drop-shadow(0 0 15px rgba(197, 160, 89, 0.8));
            }
        }
        
        .admin-header h3 {
            font-family: var(--font-serif);
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            position: relative;
            z-index: 1;
        }
        
        .admin-header h3 .admin-text {
            color: white;
        }
        
        .admin-header h3 .portal-text {
            color: #C5A059;
        }
        
        .admin-header p {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .admin-body {
            padding: 40px;
        }
        
        .alert-admin {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left: 4px solid #ef4444;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #991b1b;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group-admin {
            margin-bottom: 24px;
        }
        
        .form-label-admin {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-green);
            margin-bottom: 10px;
            letter-spacing: 0.3px;
        }
        
        .form-label-admin i {
            font-size: 0.9rem;
            color: var(--color-gold);
        }
        
        .form-input-admin {
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
        
        .form-input-admin:focus {
            outline: none;
            border-color: var(--color-gold);
            background: white;
            box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.1);
            transform: translateY(-1px);
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
            letter-spacing: 0.5px;
            cursor: pointer;
            background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(26, 60, 52, 0.4);
        }
        
        .btn-admin:hover::before {
            left: 100%;
        }
        
        .admin-footer {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid rgba(26, 60, 52, 0.1);
            font-size: 0.9rem;
            color: var(--color-text-light);
        }
        
        .admin-footer a {
            color: var(--color-gold);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .admin-footer a:hover {
            color: var(--color-green);
            text-decoration: underline;
        }
        
        .error-text {
            color: #ef4444;
            font-size: 0.85rem;
            margin-top: 6px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-header">
                <div class="admin-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Admin Portal</h3>
                <p>Secure access to store management</p>
            </div>
            
            <div class="admin-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert-admin">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($errors['general']); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="post" novalidate>
                    <div class="form-group-admin">
                        <label class="form-label-admin">
                            <i class="fas fa-user-shield"></i>
                            Username / Email
                        </label>
                        <input type="text" name="identifier" class="form-input-admin" value="<?= htmlspecialchars($identifier); ?>" placeholder="admin@gilaf.com" required />
                        <?php if (!empty($errors['email'])): ?>
                            <span class="error-text"><?= htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group-admin">
                        <label class="form-label-admin">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <input type="password" name="password" class="form-input-admin" placeholder="••••••••" required />
                        <?php if (!empty($errors['password'])): ?>
                            <span class="error-text"><?= htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-admin">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Access Portal</span>
                    </button>
                </form>
                
                <div class="admin-footer">
                    <p><a href="<?= base_url('admin/forgot_password.php'); ?>"><i class="fas fa-key"></i> Forgot Password?</a></p>
                    <p style="margin-top: 10px;"><a href="<?= base_url('index.php'); ?>"><i class="fas fa-arrow-left"></i> Back to Store</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
