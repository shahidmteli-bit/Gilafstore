<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Login — Gilaf Store';
$activePage = '';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user'])) {
    redirect_with_message('/user/profile.php', 'You are already logged in');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required';
    }

    if (!$errors) {
        if (attempt_login($email, $password)) {
            // Check if user was trying to checkout
            if (isset($_GET['redirect']) && $_GET['redirect'] === 'checkout') {
                redirect_with_message('/checkout.php', 'Welcome back!');
            } else {
                redirect_with_message('/user/profile.php', 'Welcome back!');
            }
        } else {
            $errors['general'] = 'Invalid email or password';
        }
    }
}

include __DIR__ . '/../includes/new-header.php';
?>

<style>
.login-page-container {
    padding: 20px;
    padding-top: 30px;
    min-height: calc(100vh - 200px);
    background: #f8f9fa;
    margin-top: 0;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.login-card {
    max-width: 500px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    overflow: visible;
    position: relative;
    z-index: 1;
}

.login-tabs {
    display: flex;
    border-bottom: 2px solid #f0f0f0;
}

.login-tab {
    flex: 1;
    padding: 20px;
    text-align: center;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
}

.login-tab:hover {
    background: #f8f9fa;
}

.login-tab.active {
    color: var(--color-green);
    border-bottom-color: var(--color-green);
    background: #f8f9fa;
}

.login-form-section {
    display: none;
    padding: 40px;
}

.login-form-section.active {
    display: block;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--color-green);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--color-green);
}

.error-message {
    background: rgba(239, 68, 68, 0.1);
    border-left: 4px solid #ef4444;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    color: #991b1b;
    font-size: 0.9rem;
}

.login-btn {
    width: 100%;
    padding: 14px;
    background: var(--color-green);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.login-btn:hover {
    background: #15302a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}

.login-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
    font-size: 0.9rem;
}

.login-footer a {
    color: var(--color-gold);
    text-decoration: none;
    font-weight: 600;
}

.login-footer a:hover {
    text-decoration: underline;
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #0284c7;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    color: #0c4a6e;
}
</style>

<section class="login-page-container">
    <div class="container">
        <div class="login-card">
            <div class="login-tabs">
                <div class="login-tab active" id="tab-user" onclick="switchTab('user')">
                    <i class="fas fa-user"></i> User Login
                </div>
                <div class="login-tab" id="tab-distributor" onclick="switchTab('distributor')">
                    <i class="fas fa-building"></i> Distributor Login
                </div>
            </div>

            <!-- User Login Form -->
            <div id="form-user" class="login-form-section active">
                <h3 style="color: var(--color-green); margin-bottom: 10px; font-family: var(--font-serif);">Welcome Back</h3>
                <p style="color: #666; margin-bottom: 30px;">Sign in to your account to manage orders</p>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($email); ?>" placeholder="hello@example.com" required>
                        <?php if (!empty($errors['email'])): ?>
                            <span style="color: #ef4444; font-size: 0.85rem; margin-top: 6px; display: block;"><?= htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                        <?php if (!empty($errors['password'])): ?>
                            <span style="color: #ef4444; font-size: 0.85rem; margin-top: 6px; display: block;"><?= htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
                
                <div class="login-footer">
                    <p style="margin-bottom: 10px;">New to Gilaf? <a href="<?= base_url('register.php'); ?>">Create an Account</a></p>
                    <p><a href="<?= base_url('user/forgot_password.php'); ?>"><i class="fas fa-key"></i> Forgot Password?</a></p>
                </div>
            </div>

            <!-- Distributor Login Form -->
            <div id="form-distributor" class="login-form-section">
                <h3 style="color: var(--color-green); margin-bottom: 10px; font-family: var(--font-serif);">Distributor Portal</h3>
                <p style="color: #666; margin-bottom: 30px;">Access bulk pricing and wholesale management</p>
                
                <div class="info-box">
                    <strong>Note:</strong> This portal is for registered distributors only. Use your distributor ID or registered email to login.
                </div>
                
                <form action="<?= base_url('admin/admin_login.php'); ?>" method="post">
                    <div class="form-group">
                        <label class="form-label">Distributor ID / Email</label>
                        <input type="text" name="identifier" class="form-input" placeholder="DST-XXXX or email@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-lock"></i> Access Portal
                    </button>
                </form>
                
                <div class="login-footer">
                    <p style="margin-bottom: 10px;"><a href="<?= base_url('admin/forgot_password.php'); ?>"><i class="fas fa-key"></i> Forgot Password?</a></p>
                    <p>Want to become a distributor? <a href="<?= base_url('apply-distributor.php'); ?>">Apply Now</a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function switchTab(tab) {
    // Update tab styling
    document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    
    // Update form visibility
    document.querySelectorAll('.login-form-section').forEach(f => f.classList.remove('active'));
    document.getElementById('form-' + tab).classList.add('active');
}
</script>

<?php
include __DIR__ . '/../includes/new-footer.php';
?>
