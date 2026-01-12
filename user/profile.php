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

// Fetch primary address
$db = get_db_connection();
$primaryAddress = null;
try {
    $stmt = $db->prepare('SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $primaryAddress = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$primaryAddress) {
        $stmt = $db->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        $primaryAddress = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // No address found
}

// Split name into first and last
$nameParts = explode(' ', $user['name'], 2);
$firstName = $nameParts[0] ?? '';
$lastName = $nameParts[1] ?? '';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    require_csrf_token();
    
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $name = trim($firstName . ' ' . $lastName);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

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

    if (!$errors) {
        if (update_user_profile($userId, $name, $email, null, $phone ?: null)) {
            $success = true;
            $user = get_user($userId);
            // Update session with fresh user data
            $_SESSION['user'] = $user;
            // Update name parts for display
            $nameParts = explode(' ', $user['name'], 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            // Flash message removed - using toast notification only
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

.address-display {
    padding: var(--signup-space-4);
    background: #f8f9fa;
    border-radius: var(--signup-radius-sm);
    border: 2px solid var(--signup-color-border);
    color: #374151;
    font-size: 0.9375rem;
    line-height: 1.6;
}

.password-section {
    margin-top: var(--signup-space-6);
    padding-top: var(--signup-space-6);
    border-top: 2px solid #e5e7eb;
}

.form-input[readonly] {
    background-color: #f9fafb;
    cursor: not-allowed;
    color: #6b7280;
}

.form-input[readonly]:focus {
    border-color: var(--signup-color-border);
    box-shadow: none;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

/* Modern Professional Button Styles */
#editProfileBtn {
    background: linear-gradient(135deg, #C5A059 0%, #D4AF6A 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    font-weight: 600;
    font-size: 0.9375rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#editProfileBtn:hover {
    background: linear-gradient(135deg, #D4AF6A 0%, #C5A059 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(197, 160, 89, 0.4);
}

#editProfileBtn.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

#editProfileBtn.btn-secondary:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    box-shadow: 0 6px 16px rgba(107, 114, 128, 0.4);
}

.password-section .btn {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4e 100%);
    color: white;
    border: none;
    padding: 12px 28px;
    font-weight: 600;
    font-size: 0.9375rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.password-section .btn:hover {
    background: linear-gradient(135deg, #2d5a4e 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(26, 60, 52, 0.4);
}

/* Toast Notification Styles */
.toast-container {
    position: fixed;
    top: 100px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    pointer-events: none;
}

.toast {
    background: white;
    padding: 16px 24px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    max-width: 500px;
    animation: slideDown 0.3s ease, slideUp 0.3s ease 2.7s;
    pointer-events: auto;
}

.toast.success {
    border-left: 4px solid #10b981;
}

.toast.error {
    border-left: 4px solid #ef4444;
}

.toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.toast.success .toast-icon {
    background: #d1fae5;
    color: #10b981;
}

.toast.error .toast-icon {
    background: #fee2e2;
    color: #ef4444;
}

.toast-message {
    flex: 1;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #374151;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
    }
}

@media (max-width: 640px) {
    .toast-container {
        top: 80px;
        left: 10px;
        right: 10px;
        transform: none;
    }
    
    .toast {
        min-width: auto;
        width: 100%;
    }
}
</style>

<script>
// Toast Notification System
function showToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = document.createElement('div');
    icon.className = 'toast-icon';
    icon.textContent = type === 'success' ? '✓' : '✕';
    
    const messageEl = document.createElement('div');
    messageEl.className = 'toast-message';
    messageEl.textContent = message;
    
    toast.appendChild(icon);
    toast.appendChild(messageEl);
    container.appendChild(toast);
    
    // Remove toast after animation
    setTimeout(() => {
        toast.remove();
        if (container.children.length === 0) {
            container.remove();
        }
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editProfileBtn');
    const saveBtn = document.getElementById('saveChangesBtn');
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input[readonly]');
    let isEditing = false;
    
    editBtn.addEventListener('click', function() {
        isEditing = !isEditing;
        
        if (isEditing) {
            // Enable editing
            inputs.forEach(input => {
                input.removeAttribute('readonly');
            });
            editBtn.textContent = 'Cancel';
            editBtn.classList.remove('btn-outline');
            editBtn.classList.add('btn-secondary');
            saveBtn.style.display = 'block';
        } else {
            // Disable editing
            inputs.forEach(input => {
                input.setAttribute('readonly', 'readonly');
            });
            editBtn.textContent = 'Edit Profile';
            editBtn.classList.remove('btn-secondary');
            editBtn.classList.add('btn-outline');
            saveBtn.style.display = 'none';
            // Reset form to original values
            form.reset();
            location.reload();
        }
    });
});
</script>

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
          <button type="button" id="editProfileBtn" class="btn btn-outline">Edit Profile</button>
        </div>
        
        <div class="content-body">
          <?php if ($success): ?>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                showToast('Profile updated successfully!', 'success');
              });
            </script>
          <?php endif; ?>
          
          <form method="post" class="profile-form" id="profileForm">
            <?php csrf_field(); ?>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">First Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($_POST['first_name'] ?? $firstName); ?>" required minlength="2" maxlength="50" readonly />
                <?php if (!empty($errors['name'])): ?><span class="form-error"><?= htmlspecialchars($errors['name']); ?></span><?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label">Last Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($_POST['last_name'] ?? $lastName); ?>" required minlength="2" maxlength="50" readonly />
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email Address <span style="color: #ef4444;">*</span></label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($_POST['email'] ?? $user['email']); ?>" required readonly />
                <?php if (!empty($errors['email'])): ?><span class="form-error"><?= htmlspecialchars($errors['email']); ?></span><?php endif; ?>
              </div>
              
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>" placeholder="10-digit mobile number" pattern="[6-9][0-9]{9}" maxlength="10" readonly />
                <small style="color: #6b7280; font-size: 0.8125rem; margin-top: 4px; display: block;">Enter 10-digit Indian mobile number</small>
                <?php if (!empty($errors['phone'])): ?><span class="form-error"><?= htmlspecialchars($errors['phone']); ?></span><?php endif; ?>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Primary Address</label>
              <div class="address-display">
                <?php if ($primaryAddress): ?>
                  <?php 
                    $addressParts = array_filter([
                      $primaryAddress['flat_number'] ?? '',
                      $primaryAddress['address_line1'] ?? '',
                      $primaryAddress['address_line2'] ?? '',
                      $primaryAddress['landmark'] ?? '',
                      $primaryAddress['city'] ?? '',
                      $primaryAddress['state'] ?? '',
                      $primaryAddress['zip_code'] ?? ''
                    ]);
                    echo htmlspecialchars(implode(', ', $addressParts));
                  ?>
                <?php else: ?>
                  <span style="color: #9ca3af;">No address saved. <a href="<?= base_url('user/manage_addresses.php'); ?>" style="color: #1A3C34; font-weight: 600;">Add Address</a></span>
                <?php endif; ?>
              </div>
              <small style="color: #6b7280; font-size: 0.8125rem; margin-top: 4px; display: block;">
                <a href="<?= base_url('user/manage_addresses.php'); ?>" style="color: #1A3C34; font-weight: 600; text-decoration: none;">Manage Addresses</a>
              </small>
            </div>
            
            <button type="submit" class="btn btn-primary" id="saveChangesBtn" style="display: none;">Save Changes</button>
          </form>
          
          <!-- Change Password Section -->
          <div class="password-section">
            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #1A3C34;">Change Password</h3>
            <p style="color: #6b7280; font-size: 0.9375rem; margin-bottom: 1rem;">Update your account password securely</p>
            <a href="<?= base_url('user/change_password.php'); ?>" class="btn btn-outline" style="display: inline-block; width: auto;">Change Password</a>
          </div>
          
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
