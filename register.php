<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Create Account — Gilaf Store';
$activePage = 'register';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . base_url('user/profile.php'));
    exit;
}

$errors = [];
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '') {
        $errors['first_name'] = 'Please enter your first name';
    } elseif (strlen($firstName) < 2) {
        $errors['first_name'] = 'First name must be at least 2 characters';
    }

    if ($lastName === '') {
        $errors['last_name'] = 'Please enter your last name';
    } elseif (strlen($lastName) < 2) {
        $errors['last_name'] = 'Last name must be at least 2 characters';
    }

    if ($phone === '') {
        $errors['phone'] = 'Please enter your phone number';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit phone number';
    }

    if ($email === '') {
        $errors['email'] = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain letters and numbers';
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!$errors) {
        $fullName = $firstName . ' ' . $lastName;
        if (register_user($fullName, $email, $password)) {
            attempt_login($email, $password);
            header('Location: ' . base_url('user/profile.php'));
            exit;
        } else {
            $errors['general'] = 'An account with this email already exists. Please try logging in.';
        }
    }
}

include __DIR__ . '/includes/new-header.php';
?>

<link rel="stylesheet" href="<?= asset_url('css/signup-page.css'); ?>">

<main class="signup-page" role="main">
    <div class="signup-container">
        <article class="signup-card" aria-labelledby="signup-title">
            
            <header class="signup-header">
                <div class="signup-icon-wrap" aria-hidden="true">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 id="signup-title">Create Your Account</h1>
                <p>Join Gilaf Store and start shopping</p>
            </header>
            
            <div class="signup-body">
                
                <?php if (!empty($errors['general'])): ?>
                <div class="signup-alert signup-alert--error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errors['general']); ?></span>
                </div>
                <?php endif; ?>
                
                <form method="post" class="signup-form" id="signupForm" novalidate autocomplete="on">
                    
                    <div class="signup-form__row">
                        <div class="signup-form__group">
                            <label for="first_name" class="signup-label">
                                First Name <span class="signup-label__required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                class="signup-input <?= !empty($errors['first_name']) ? 'signup-input--error' : '' ?>"
                                value="<?= htmlspecialchars($firstName); ?>"
                                placeholder="Enter first name"
                                autocomplete="given-name"
                                required
                                aria-required="true"
                            />
                            <?php if (!empty($errors['first_name'])): ?>
                            <div class="signup-error" role="alert">
                                <i class="fas fa-times-circle"></i>
                                <span><?= htmlspecialchars($errors['first_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="signup-form__group">
                            <label for="last_name" class="signup-label">
                                Last Name <span class="signup-label__required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="last_name" 
                                name="last_name" 
                                class="signup-input <?= !empty($errors['last_name']) ? 'signup-input--error' : '' ?>"
                                value="<?= htmlspecialchars($lastName); ?>"
                                placeholder="Enter last name"
                                autocomplete="family-name"
                                required
                                aria-required="true"
                            />
                            <?php if (!empty($errors['last_name'])): ?>
                            <div class="signup-error" role="alert">
                                <i class="fas fa-times-circle"></i>
                                <span><?= htmlspecialchars($errors['last_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="signup-form__group">
                        <label for="email" class="signup-label">
                            Email Address <span class="signup-label__required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="signup-input <?= !empty($errors['email']) ? 'signup-input--error' : '' ?>"
                            value="<?= htmlspecialchars($email); ?>"
                            placeholder="you@example.com"
                            autocomplete="email"
                            inputmode="email"
                            required
                            aria-required="true"
                        />
                        <?php if (!empty($errors['email'])): ?>
                        <div class="signup-error" role="alert">
                            <i class="fas fa-times-circle"></i>
                            <span><?= htmlspecialchars($errors['email']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="signup-form__group">
                        <label for="phone" class="signup-label">
                            Phone Number <span class="signup-label__required">*</span>
                        </label>
                        <div class="signup-phone-wrap">
                            <span class="signup-phone-code">+91</span>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                class="signup-input signup-phone-input <?= !empty($errors['phone']) ? 'signup-input--error' : '' ?>"
                                value="<?= htmlspecialchars($phone); ?>"
                                placeholder="10-digit mobile number"
                                autocomplete="tel-national"
                                inputmode="numeric"
                                pattern="[0-9]{10}"
                                maxlength="10"
                                required
                                aria-required="true"
                            />
                        </div>
                        <?php if (!empty($errors['phone'])): ?>
                        <div class="signup-error" role="alert">
                            <i class="fas fa-times-circle"></i>
                            <span><?= htmlspecialchars($errors['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="signup-form__group">
                        <label for="password" class="signup-label">
                            Password <span class="signup-label__required">*</span>
                        </label>
                        <div class="signup-input-wrap">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="signup-input signup-input--password <?= !empty($errors['password']) ? 'signup-input--error' : '' ?>"
                                placeholder="Create a strong password"
                                autocomplete="new-password"
                                minlength="8"
                                inputmode="text"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                required
                                aria-required="true"
                            />
                            <button type="button" class="signup-password-toggle" data-target="password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="signup-password-rules">
                            <div class="signup-password-rule" id="rule-length">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </div>
                            <div class="signup-password-rule" id="rule-mixed">
                                <i class="fas fa-circle"></i> Contains letters and numbers
                            </div>
                        </div>
                        <div class="signup-password-strength">
                            <div class="signup-strength-bar" id="strength-1"></div>
                            <div class="signup-strength-bar" id="strength-2"></div>
                            <div class="signup-strength-bar" id="strength-3"></div>
                            <div class="signup-strength-bar" id="strength-4"></div>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                        <div class="signup-error" role="alert">
                            <i class="fas fa-times-circle"></i>
                            <span><?= htmlspecialchars($errors['password']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="signup-form__group">
                        <label for="confirm_password" class="signup-label">
                            Confirm Password <span class="signup-label__required">*</span>
                        </label>
                        <div class="signup-input-wrap">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="signup-input signup-input--password <?= !empty($errors['confirm_password']) ? 'signup-input--error' : '' ?>"
                                placeholder="Re-enter your password"
                                autocomplete="new-password"
                                inputmode="text"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                required
                                aria-required="true"
                            />
                            <button type="button" class="signup-password-toggle" data-target="confirm_password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($errors['confirm_password'])): ?>
                        <div class="signup-error" role="alert">
                            <i class="fas fa-times-circle"></i>
                            <span><?= htmlspecialchars($errors['confirm_password']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="signup-submit" id="submitBtn">
                        <span class="signup-submit__text">Create Account</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <div class="signup-trust">
                        <i class="fas fa-shield-alt"></i>
                        <span>Your data is secure and encrypted</span>
                    </div>
                </form>
                
                <div class="signup-footer">
                    <p>Already have an account? <a href="#" onclick="openLoginModal(); return false;">Sign in</a></p>
                </div>
            </div>
        </article>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    document.querySelectorAll('.signup-password-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const ruleLength = document.getElementById('rule-length');
    const ruleMixed = document.getElementById('rule-mixed');
    const strengthBars = document.querySelectorAll('.signup-strength-bar');
    
    passwordInput.addEventListener('input', function() {
        const value = this.value;
        let strength = 0;
        
        // Check length
        if (value.length >= 8) {
            ruleLength.classList.add('signup-password-rule--valid');
            strength++;
        } else {
            ruleLength.classList.remove('signup-password-rule--valid');
        }
        
        // Check mixed
        if (/[A-Za-z]/.test(value) && /[0-9]/.test(value)) {
            ruleMixed.classList.add('signup-password-rule--valid');
            strength++;
        } else {
            ruleMixed.classList.remove('signup-password-rule--valid');
        }
        
        // Special chars bonus
        if (/[^A-Za-z0-9]/.test(value)) strength++;
        if (value.length >= 12) strength++;
        
        // Update strength bars
        strengthBars.forEach((bar, i) => {
            bar.className = 'signup-strength-bar';
            if (i < strength) {
                if (strength === 1) bar.classList.add('signup-strength-bar--weak');
                else if (strength === 2) bar.classList.add('signup-strength-bar--fair');
                else if (strength === 3) bar.classList.add('signup-strength-bar--good');
                else bar.classList.add('signup-strength-bar--strong');
            }
        });
    });
    
    // Prevent double submission
    const form = document.getElementById('signupForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
    });
    
    // Phone number - digits only
    document.getElementById('phone').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Mobile password input fix - ensure proper focus and keyboard behavior
    if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            // Ensure focus works properly on mobile
            input.addEventListener('touchstart', function(e) {
                // Allow default touch behavior for focusing
                setTimeout(() => {
                    this.focus();
                }, 100);
            });
            
            // Prevent zoom on focus (iOS)
            input.addEventListener('focus', function() {
                const viewport = document.querySelector('meta[name="viewport"]');
                if (viewport) {
                    const originalContent = viewport.getAttribute('content');
                    viewport.setAttribute('content', originalContent + ', maximum-scale=1.0');
                    
                    // Restore original viewport on blur
                    this.addEventListener('blur', function() {
                        viewport.setAttribute('content', originalContent);
                    }, { once: true });
                }
            });
        });
    }
});
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
