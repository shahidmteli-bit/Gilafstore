<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';

require_login();

$pageTitle = 'My Dashboard — Gilaf Store';
$activePage = 'dashboard';

$userId = (int)$_SESSION['user']['id'];
$user = get_user($userId);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_csrf_token();
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate name
    $nameValidation = validate_name($name);
    if (!$nameValidation['valid']) {
        $errors['name'] = $nameValidation['error'];
    }

    // Validate email
    $emailValidation = validate_email($email, true);
    if (!$emailValidation['valid']) {
        $errors['email'] = $emailValidation['error'];
    }
    
    // Validate phone if provided
    if (!empty($phone)) {
        $phoneValidation = validate_phone($phone);
        if (!$phoneValidation['valid']) {
            $errors['phone'] = $phoneValidation['error'];
        } else {
            $phone = $phoneValidation['formatted'];
        }
    }

    // Validate password if provided
    if ($password) {
        $passwordValidation = validate_password($password, 8);
        if (!$passwordValidation['valid']) {
            $errors['password'] = $passwordValidation['error'];
        }
        
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }

    if (!$errors) {
        if (update_user_profile($userId, $name, $email, $password ?: null, $phone ?: null)) {
            $success = true;
            $user = get_user($userId);
            $_SESSION['flash'] = [
                'message' => 'Profile updated successfully!',
                'type' => 'success'
            ];
        } else {
            $errors['email'] = 'Email already in use by another account';
        }
    }
}

include __DIR__ . '/../includes/new-header.php';
?>

<!-- Profile Page Styles - Screen Adjustable -->
<link href="<?= base_url('assets/css/signup-page.css'); ?>" rel="stylesheet">

<style>
/* Profile Page Specific Styles */
.profile-page {
    min-height: calc(100vh - 120px);
    background: var(--signup-color-bg);
    padding: var(--signup-space-4) var(--signup-space-4);
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.profile-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

/* Profile Layout Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: var(--signup-space-6);
    align-items: start;
}

/* Profile Sidebar - Standardized styling */
.profile-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: sticky;
    top: 20px;
    height: fit-content;
}

/* Profile Content */
.profile-content {
    background: var(--signup-color-card);
    border-radius: var(--signup-radius-lg);
    box-shadow: var(--signup-shadow-lg);
    overflow: hidden;
}

.content-header {
    padding: var(--signup-space-5) var(--signup-space-6);
    border-bottom: 1px solid var(--signup-color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--signup-color-text);
    margin-bottom: var(--signup-space-1);
}

.content-subtitle {
    color: var(--signup-color-text-muted);
    font-size: 0.9375rem;
}

.content-body {
    padding: var(--signup-space-6);
}

/* Form Styles */
.profile-form {
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--signup-space-4);
    margin-bottom: var(--signup-space-4);
}

.form-group {
    margin-bottom: var(--signup-space-4);
}

.form-label {
    display: block;
    margin-bottom: var(--signup-space-2);
    font-weight: 500;
    color: var(--signup-color-text);
    font-size: 0.9375rem;
}

.form-input {
    width: 100%;
    height: var(--signup-input-height);
    padding: 0 var(--signup-space-4);
    border: 2px solid var(--signup-color-border);
    border-radius: var(--signup-radius-sm);
    font-size: 0.9375rem;
    transition: all 0.2s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: var(--signup-color-border-focus);
    box-shadow: 0 0 0 3px rgba(197, 160, 137, 0.1);
}

.form-error {
    color: var(--signup-color-error);
    font-size: 0.8125rem;
    margin-top: var(--signup-space-1);
    display: block;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: var(--signup-btn-height);
    padding: 0 var(--signup-space-6);
    border: none;
    border-radius: var(--signup-radius-sm);
    font-size: 0.9375rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: var(--signup-color-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--signup-color-primary-dark);
}

.btn-outline {
    background: transparent;
    color: var(--signup-color-primary);
    border: 2px solid var(--signup-color-primary);
}

.btn-outline:hover {
    background: var(--signup-color-primary);
    color: white;
}

/* Alert Styles */
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

/* Feature Cards */
.feature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--signup-space-4);
    margin-top: var(--signup-space-6);
}

.feature-card {
    padding: var(--signup-space-5);
    border-radius: var(--signup-radius-md);
    background: var(--signup-color-bg);
}

.feature-card.primary {
    background: linear-gradient(135deg, var(--signup-color-primary) 0%, var(--signup-color-primary-dark) 100%);
    color: white;
}

.feature-card h5 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: var(--signup-space-2);
}

.feature-card p {
    font-size: 0.9375rem;
    line-height: 1.5;
    opacity: 0.9;
}

/* Mobile/Tablet: Reduce top padding and hide alerts */
@media (max-width: 768px) {
    .profile-page {
        margin-top: -75px !important;
        padding-top: 0 !important;
    }
    
    .alert {
        display: none !important;
    }
}

/* Responsive Design */
@media (max-width: 968px) {
    .profile-grid {
        grid-template-columns: 1fr;
        gap: var(--signup-space-4);
    }
    
    .profile-sidebar {
        position: static;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .feature-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .profile-page {
        padding: var(--signup-space-3) var(--signup-space-3);
    }
    
    .content-header {
        padding: var(--signup-space-4) var(--signup-space-5);
        flex-direction: column;
        align-items: flex-start;
        gap: var(--signup-space-3);
    }
    
    .content-body {
        padding: var(--signup-space-5);
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<section class="profile-page">
  <div class="profile-container">
    <div class="profile-grid">
      <?php include __DIR__ . '/includes/user_sidebar.php'; ?>
      
      <!-- Profile Content -->
      <div class="profile-content">
        <div class="content-header">
          <div>
            <h1 class="content-title">Profile Overview</h1>
            <p class="content-subtitle">Manage your personal information and login credentials</p>
          </div>
          <a href="<?= base_url('user/orders.php'); ?>" class="btn btn-outline">View Orders</a>
        </div>
        
        <div class="content-body">
          <?php if ($success): ?>
            <div class="alert alert-success">Profile updated successfully.</div>
          <?php endif; ?>
          
          <form method="post" class="profile-form">
            <?php csrf_field(); ?>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($_POST['name'] ?? $user['name']); ?>" required minlength="2" maxlength="100" />
                <?php if (!empty($errors['name'])): ?><span class="form-error"><?= htmlspecialchars($errors['name']); ?></span><?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label">Email Address <span style="color: #ef4444;">*</span></label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($_POST['email'] ?? $user['email']); ?>" required />
                <?php if (!empty($errors['email'])): ?><span class="form-error"><?= htmlspecialchars($errors['email']); ?></span><?php endif; ?>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>" placeholder="10-digit mobile number" pattern="[6-9][0-9]{9}" maxlength="10" />
              <small style="color: #6b7280; font-size: 0.8125rem; margin-top: 4px; display: block;">Enter 10-digit Indian mobile number</small>
              <?php if (!empty($errors['phone'])): ?><span class="form-error"><?= htmlspecialchars($errors['phone']); ?></span><?php endif; ?>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-input" placeholder="Leave blank to keep current" minlength="8" />
                <small style="color: #6b7280; font-size: 0.8125rem; margin-top: 4px; display: block;">Minimum 8 characters with uppercase, lowercase, numbers, and special characters</small>
                <?php if (!empty($errors['password'])): ?><span class="form-error"><?= htmlspecialchars($errors['password']); ?></span><?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-input" placeholder="Leave blank to keep current" minlength="8" />
                <?php if (!empty($errors['confirm_password'])): ?><span class="form-error"><?= htmlspecialchars($errors['confirm_password']); ?></span><?php endif; ?>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
          
          <!-- Feature Cards -->
          <div class="feature-grid">
            <div class="feature-card primary">
              <h5>Track Orders</h5>
              <p>Stay informed with real-time order status updates right from your dashboard</p>
            </div>
            
            <div class="feature-card">
              <h5>Need Support?</h5>
              <p>Our team is ready to assist with returns, exchanges, or product recommendations</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
include __DIR__ . '/../includes/new-footer.php';
?>
