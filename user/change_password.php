<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';

require_login();

$pageTitle = 'Change Password — Gilaf Store';
$activePage = 'dashboard';

$userId = (int)$_SESSION['user']['id'];
$user = $_SESSION['user'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    $db = get_db_connection();
    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userRecord || !password_verify($currentPassword, $userRecord['password'])) {
        $errors['current_password'] = 'Current password is incorrect';
    }
    
    // Validate new password
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    } else {
        $passwordValidation = validate_password($newPassword, 8);
        if (!$passwordValidation['valid']) {
            $errors['new_password'] = $passwordValidation['error'];
        }
    }
    
    // Validate confirm password
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        if ($stmt->execute([$hashedPassword, $userId])) {
            $success = true;
            $_SESSION['flash'] = [
                'message' => 'Password changed successfully!',
                'type' => 'success'
            ];
        } else {
            $errors['general'] = 'Failed to update password. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/new-header.php';
?>

<link href="<?= base_url('assets/css/signup-page.css'); ?>" rel="stylesheet">

<style>
.change-password-page {
    min-height: calc(100vh - 120px);
    background: var(--signup-color-bg);
    padding: var(--signup-space-4) var(--signup-space-4);
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

/* Mobile/Tablet: Reduce top padding */
@media (max-width: 768px) {
    .change-password-page {
        margin-top: -75px !important;
        padding-top: 0 !important;
    }
}

.change-password-container {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
}

.change-password-card {
    background: white;
    border-radius: var(--signup-radius-lg);
    box-shadow: var(--signup-shadow-lg);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #1A3C34 0%, #0f2820 100%);
    padding: var(--signup-space-6);
    text-align: center;
    color: white;
}

.card-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 1.75rem;
    margin-bottom: var(--signup-space-2);
}

.card-header p {
    font-size: 0.9375rem;
    opacity: 0.9;
}

.card-body {
    padding: var(--signup-space-6);
}

.form-group {
    margin-bottom: var(--signup-space-5);
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: var(--signup-space-2);
    color: var(--signup-color-text);
    font-size: 0.9375rem;
}

.form-input {
    width: 100%;
    padding: var(--signup-space-3) var(--signup-space-4);
    border: 2px solid var(--signup-color-border);
    border-radius: var(--signup-radius-sm);
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--signup-color-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-error {
    display: block;
    color: #ef4444;
    font-size: 0.8125rem;
    margin-top: var(--signup-space-2);
}

.form-hint {
    display: block;
    color: #6b7280;
    font-size: 0.8125rem;
    margin-top: var(--signup-space-2);
}

.alert {
    padding: var(--signup-space-4);
    border-radius: var(--signup-radius-sm);
    margin-bottom: var(--signup-space-4);
}

.alert-success {
    background: var(--signup-color-success);
    color: white;
    font-size: 0.9375rem;
}

.alert-error {
    background: #ef4444;
    color: white;
    font-size: 0.9375rem;
}

.btn {
    padding: var(--signup-space-3) var(--signup-space-5);
    border-radius: var(--signup-radius-sm);
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4e 100%);
    color: white;
    width: 100%;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    width: 100%;
    margin-top: var(--signup-space-3);
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.password-requirements {
    background: #f8f9fa;
    border-left: 4px solid #1A3C34;
    padding: var(--signup-space-4);
    border-radius: var(--signup-radius-sm);
    margin-top: var(--signup-space-4);
}

.password-requirements h4 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: var(--signup-space-2);
    color: #1A3C34;
}

.password-requirements ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.password-requirements li {
    font-size: 0.8125rem;
    color: #6b7280;
    margin-bottom: var(--signup-space-1);
    padding-left: var(--signup-space-4);
    position: relative;
}

.password-requirements li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #10b981;
    font-weight: bold;
}

@media (max-width: 640px) {
    .change-password-page {
        padding: var(--signup-space-3) var(--signup-space-3);
    }
    
    .card-header {
        padding: var(--signup-space-5);
    }
    
    .card-body {
        padding: var(--signup-space-5);
    }
}
</style>

<section class="change-password-page">
    <div class="change-password-container">
        <div class="change-password-card">
            <div class="card-header">
                <h1>Change Password</h1>
                <p>Update your account password securely</p>
            </div>
            
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Password changed successfully! You can now use your new password to log in.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($errors['general']); ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <?php csrf_field(); ?>
                    
                    <div class="form-group">
                        <label class="form-label">Current Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="current_password" class="form-input" required />
                        <?php if (!empty($errors['current_password'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['current_password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="new_password" class="form-input" required minlength="8" />
                        <?php if (!empty($errors['new_password'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['new_password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" name="confirm_password" class="form-input" required minlength="8" />
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <span class="form-error"><?= htmlspecialchars($errors['confirm_password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>Minimum 8 characters</li>
                            <li>At least one uppercase letter</li>
                            <li>At least one lowercase letter</li>
                            <li>At least one number</li>
                            <li>At least one special character</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <a href="<?= base_url('user/profile.php'); ?>" class="btn btn-secondary">Back to Profile</a>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
include __DIR__ . '/../includes/new-footer.php';
?>
