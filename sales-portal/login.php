<?php
/**
 * Sales Executive Portal - Login Page
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (sales_is_logged_in()) {
    header('Location: ' . sales_base_url('index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (sales_attempt_login($email, $password)) {
        $authToken = sales_get_auth_token();
        // Redirect with token — footer JS on index.php will store it in localStorage
        header('Location: ' . sales_base_url('index.php') . '?_token=' . urlencode($authToken) . '&_store_token=1');
        exit;
    } else {
        $error = 'Invalid credentials or account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Portal Login — Gilaf Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $portalCssPath = __DIR__ . '/assets/css/portal.css'; ?>
    <link rel="stylesheet" href="<?= sales_base_url('assets/css/portal.css') ?>?v=<?= file_exists($portalCssPath) ? filemtime($portalCssPath) : '20260224' ?>">
</head>
<body style="display:block;">
    <div class="sp-login-page">
        <div class="sp-login-card">
            <div class="sp-login-brand">
                <div class="sp-brand-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h1>GILAF STORE</h1>
                <p>Sales Executive Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="sp-login-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="sp-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="sp-input" placeholder="Enter your email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="sp-form-group">
                    <label for="password">Password</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" class="sp-input" placeholder="Enter your password" required>
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="sp-btn sp-btn-primary sp-btn-lg sp-btn-block" style="margin-top:8px;">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div style="text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
                <p style="font-size:12px;color:#9ca3af;">
                    <i class="fas fa-lock" style="margin-right:4px;"></i>
                    Authorized personnel only. Contact admin for access.
                </p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>
