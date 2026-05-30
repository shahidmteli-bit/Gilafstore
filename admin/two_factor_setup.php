<?php
/**
 * Admin Security Center
 * Manage: Authenticator App 2FA + Email OTP + Passkey (WebAuthn) Login Security
 * Both methods can be enabled/disabled independently
 */
$pageTitle = 'Security Center — Gilaf Admin';
$adminPage = 'two_factor';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/webauthn.php';

require_admin();

$adminId = $_SESSION['admin']['id'];
$adminEmail = $_SESSION['admin']['email'];
$db = get_db_connection();

// ─── Ensure all security columns exist ───
try {
    $check = $db->query("SHOW COLUMNS FROM users LIKE 'totp_secret'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(512) NULL DEFAULT NULL");
        $db->exec("ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0");
        $db->exec("ALTER TABLE users ADD COLUMN recovery_codes TEXT NULL DEFAULT NULL");
    } else {
        $db->exec("ALTER TABLE users MODIFY COLUMN totp_secret VARCHAR(512) NULL DEFAULT NULL");
    }
    $check2 = $db->query("SHOW COLUMNS FROM users LIKE 'email_otp_enabled'");
    if ($check2->rowCount() === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN email_otp_enabled TINYINT(1) DEFAULT 0");
        $db->exec("ALTER TABLE users ADD COLUMN security_email VARCHAR(255) NULL DEFAULT NULL");
    }
    $check3 = $db->query("SHOW COLUMNS FROM users LIKE 'passkey_enabled'");
    if ($check3->rowCount() === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN passkey_enabled TINYINT(1) DEFAULT 0");
    }
    $db->exec("CREATE TABLE IF NOT EXISTS webauthn_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(512) NOT NULL,
        public_key TEXT NOT NULL,
        sign_count INT UNSIGNED DEFAULT 0,
        device_name VARCHAR(255) DEFAULT 'Passkey',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP NULL,
        UNIQUE KEY uq_cred (credential_id(255)),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log("Security columns setup: " . $e->getMessage());
}

// ─── Load current status ───
$stmt = $db->prepare("SELECT totp_secret, totp_enabled, recovery_codes, email_otp_enabled, security_email, passkey_enabled, email FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$adminData = $stmt->fetch(PDO::FETCH_ASSOC);

$is2FAEnabled = !empty($adminData['totp_enabled']);
$isEmailOtpEnabled = !empty($adminData['email_otp_enabled']);
$isPasskeyEnabled = !empty($adminData['passkey_enabled']);
$securityEmail = $adminData['security_email'] ?? '';
$accountEmail = $adminData['email'] ?? $adminEmail;

// Load registered passkeys
$passkeys = [];
try {
    $pkStmt = $db->prepare("SELECT id, device_name, created_at, last_used_at FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC");
    $pkStmt->execute([$adminId]);
    $passkeys = $pkStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Load configured emails for dropdown
$configuredEmails = [];
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'email_configurations'")->rowCount();
    if ($tableCheck) {
        $emailStmt = $db->query("SELECT email_address, display_name FROM email_configurations WHERE is_active = 1 ORDER BY display_name ASC");
        $configuredEmails = $emailStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

$message = '';
$messageType = '';
$showQR = false;
$qrUrl = '';
$secretKey = '';
$recoveryCodes = [];
$activeTab = $_GET['tab'] ?? 'overview';

// ─── Handle POST actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // === AUTHENTICATOR ACTIONS ===
    if ($action === 'totp_generate') {
        $secretKey = TOTP::generateSecret();
        $_SESSION['_2fa_pending_secret'] = $secretKey;
        $totp = new TOTP($secretKey);
        $qrUrl = $totp->getQRCodeUrl($adminEmail, 'Gilaf Store Admin');
        $showQR = true;
        $activeTab = 'authenticator';

    } elseif ($action === 'totp_verify_enable') {
        $code = trim($_POST['totp_code'] ?? '');
        $pendingSecret = $_SESSION['_2fa_pending_secret'] ?? '';
        $activeTab = 'authenticator';

        if (empty($pendingSecret)) {
            $message = 'Session expired. Please start the setup again.';
            $messageType = 'error';
        } elseif (empty($code) || strlen($code) !== 6) {
            $message = 'Please enter a valid 6-digit code.';
            $messageType = 'error';
            $secretKey = $pendingSecret;
            $totp = new TOTP($secretKey);
            $qrUrl = $totp->getQRCodeUrl($adminEmail, 'Gilaf Store Admin');
            $showQR = true;
        } else {
            $totp = new TOTP($pendingSecret);
            if ($totp->verify($code)) {
                $recoveryCodes = TOTP::generateRecoveryCodes(8);
                $recoveryJson = json_encode(array_map(fn($c) => ['code' => $c, 'used' => false], $recoveryCodes));
                $encryptedSecret = encrypt_data($pendingSecret);
                $encryptedRecovery = encrypt_data($recoveryJson);
                $stmt = $db->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1, recovery_codes = ? WHERE id = ?");
                $stmt->execute([$encryptedSecret, $encryptedRecovery, $adminId]);
                unset($_SESSION['_2fa_pending_secret']);
                $is2FAEnabled = true;
                security_log('2FA_ENABLED', 'INFO', "Admin {$adminEmail} enabled Authenticator 2FA");
                $message = 'Authenticator 2FA enabled! Save your recovery codes below.';
                $messageType = 'success';
            } else {
                $message = 'Invalid code. Please try again with the current code from your authenticator app.';
                $messageType = 'error';
                $secretKey = $pendingSecret;
                $totp = new TOTP($secretKey);
                $qrUrl = $totp->getQRCodeUrl($adminEmail, 'Gilaf Store Admin');
                $showQR = true;
            }
        }

    } elseif ($action === 'totp_disable') {
        $code = trim($_POST['totp_code'] ?? '');
        $encryptedSecret = $adminData['totp_secret'] ?? '';
        $decryptedSecret = decrypt_data($encryptedSecret);
        $activeTab = 'authenticator';

        if ($decryptedSecret && !empty($code)) {
            $totp = new TOTP($decryptedSecret);
            if ($totp->verify($code)) {
                $stmt = $db->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0, recovery_codes = NULL WHERE id = ?");
                $stmt->execute([$adminId]);
                $is2FAEnabled = false;
                security_log('2FA_DISABLED', 'WARNING', "Admin {$adminEmail} disabled Authenticator 2FA");
                $message = 'Authenticator 2FA has been disabled.';
                $messageType = 'warning';
            } else {
                $message = 'Invalid code. Authenticator 2FA was NOT disabled.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please enter your authenticator code to disable.';
            $messageType = 'error';
        }

    // === EMAIL OTP ACTIONS ===
    } elseif ($action === 'email_otp_enable') {
        $chosenEmail = trim($_POST['security_email'] ?? '');
        $customEmail = trim($_POST['custom_email'] ?? '');
        $activeTab = 'email_otp';

        // Use custom email if "custom" was selected
        if ($chosenEmail === '__custom__' && !empty($customEmail)) {
            $chosenEmail = $customEmail;
        }

        if (empty($chosenEmail) || !filter_var($chosenEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please select or enter a valid security email address.';
            $messageType = 'error';
        } else {
            // Send a test OTP to verify the email works
            $otp = generate_email_otp();
            $sent = send_email_otp($chosenEmail, $otp);

            if ($sent) {
                $_SESSION['_email_otp_setup_email'] = $chosenEmail;
                $message = 'A verification code has been sent to ' . htmlspecialchars($chosenEmail) . '. Enter it below to activate Email OTP.';
                $messageType = 'info';
            } else {
                $message = 'Failed to send verification email. Please check the email address and SMTP settings.';
                $messageType = 'error';
                clear_email_otp();
            }
        }

    } elseif ($action === 'email_otp_verify_enable') {
        $code = trim($_POST['email_otp_code'] ?? '');
        $pendingEmail = $_SESSION['_email_otp_setup_email'] ?? '';
        $activeTab = 'email_otp';

        if (empty($pendingEmail)) {
            $message = 'Session expired. Please start the email OTP setup again.';
            $messageType = 'error';
        } elseif (verify_email_otp($code)) {
            $stmt = $db->prepare("UPDATE users SET email_otp_enabled = 1, security_email = ? WHERE id = ?");
            $stmt->execute([$pendingEmail, $adminId]);
            $isEmailOtpEnabled = true;
            $securityEmail = $pendingEmail;
            unset($_SESSION['_email_otp_setup_email']);
            security_log('EMAIL_OTP_ENABLED', 'INFO', "Admin {$adminEmail} enabled Email OTP to {$pendingEmail}");
            $message = 'Email OTP has been enabled successfully!';
            $messageType = 'success';
        } else {
            $message = 'Invalid or expired code. Please try again.';
            $messageType = 'error';
            // Keep the pending state so they can retry
            if (!empty($pendingEmail)) {
                $_SESSION['_email_otp_setup_email'] = $pendingEmail;
            }
        }

    } elseif ($action === 'email_otp_disable') {
        $activeTab = 'email_otp';
        $stmt = $db->prepare("UPDATE users SET email_otp_enabled = 0 WHERE id = ?");
        $stmt->execute([$adminId]);
        $isEmailOtpEnabled = false;
        security_log('EMAIL_OTP_DISABLED', 'WARNING', "Admin {$adminEmail} disabled Email OTP");
        $message = 'Email OTP has been disabled.';
        $messageType = 'warning';

    } elseif ($action === 'update_security_email') {
        $chosenEmail = trim($_POST['security_email'] ?? '');
        $customEmail = trim($_POST['custom_email'] ?? '');
        $activeTab = 'email_otp';

        if ($chosenEmail === '__custom__' && !empty($customEmail)) {
            $chosenEmail = $customEmail;
        }

        if (empty($chosenEmail) || !filter_var($chosenEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("UPDATE users SET security_email = ? WHERE id = ?");
            $stmt->execute([$chosenEmail, $adminId]);
            $securityEmail = $chosenEmail;
            security_log('SECURITY_EMAIL_UPDATED', 'INFO', "Admin security email updated to {$chosenEmail}");
            $message = 'Security email updated to ' . htmlspecialchars($chosenEmail);
            $messageType = 'success';
        }
    }
}

// Determine pending email OTP setup
$pendingEmailSetup = !empty($_SESSION['_email_otp_setup_email']);
$pendingSetupEmail = $_SESSION['_email_otp_setup_email'] ?? '';

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ─── Security Center Layout ─── */
.sec-container { max-width: 900px; margin: 24px auto; padding: 0 16px; }

.sec-page-header { background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.92)); border-radius: 16px; padding: 28px 32px; display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
.sec-page-header-icon { width: 56px; height: 56px; background: rgba(255,255,255,0.12); border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sec-page-header-icon i { color: #C5A059; font-size: 24px; }
.sec-page-header-text h1 { margin: 0; font-size: 22px; font-weight: 700; color: #fff; }
.sec-page-header-text p { margin: 4px 0 0; font-size: 13px; color: rgba(255,255,255,0.65); }

/* ─── Security Overview Badges ─── */
.sec-overview { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.sec-badge { background: #fff; border-radius: 14px; padding: 22px 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; border-left: 4px solid transparent; transition: transform 0.2s; }
.sec-badge:hover { transform: translateY(-2px); }
.sec-badge.active { border-left-color: #16a34a; }
.sec-badge.inactive { border-left-color: #d1d5db; }
.sec-badge-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sec-badge.active .sec-badge-icon { background: linear-gradient(135deg, #16a34a, #15803d); }
.sec-badge.inactive .sec-badge-icon { background: #f3f4f6; }
.sec-badge-icon i { font-size: 20px; }
.sec-badge.active .sec-badge-icon i { color: #fff; }
.sec-badge.inactive .sec-badge-icon i { color: #9ca3af; }
.sec-badge-text h4 { margin: 0; font-size: 15px; font-weight: 600; color: #111827; }
.sec-badge-text p { margin: 2px 0 0; font-size: 12px; }
.sec-badge.active .sec-badge-text p { color: #16a34a; font-weight: 600; }
.sec-badge.inactive .sec-badge-text p { color: #9ca3af; }

/* ─── Tabs ─── */
.sec-tabs { display: flex; gap: 4px; background: #f3f4f6; border-radius: 12px; padding: 4px; margin-bottom: 24px; }
.sec-tab { flex: 1; padding: 12px 16px; border-radius: 10px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all 0.25s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.sec-tab:hover { color: #374151; background: rgba(255,255,255,0.6); }
.sec-tab.active { background: #fff; color: #1A3C34; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.sec-tab i { font-size: 15px; }

/* ─── Cards ─── */
.sec-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 24px; display: none; }
.sec-card.visible { display: block; }
.sec-card-head { padding: 20px 28px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
.sec-card-title { display: flex; align-items: center; gap: 12px; }
.sec-card-title i { font-size: 18px; color: #C5A059; }
.sec-card-title h3 { margin: 0; font-size: 17px; font-weight: 700; color: #111827; }
.sec-card-status { padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.sec-card-status.on { background: #dcfce7; color: #166534; }
.sec-card-status.off { background: #fee2e2; color: #991b1b; }
.sec-card-body { padding: 28px; }

/* ─── Alerts ─── */
.sec-alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
.sec-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.sec-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.sec-alert.warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.sec-alert.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

/* ─── QR Section ─── */
.sec-qr { text-align: center; margin: 20px 0; }
.sec-qr img { border: 4px solid #e5e7eb; border-radius: 16px; padding: 8px; background: #fff; }
.sec-secret { margin: 16px auto; padding: 14px 20px; background: #f3f4f6; border-radius: 10px; font-family: 'Courier New', monospace; font-size: 15px; font-weight: 700; letter-spacing: 3px; color: #1A3C34; display: inline-block; word-break: break-all; }

/* ─── Steps ─── */
.sec-steps { margin: 20px 0; padding: 0; list-style: none; }
.sec-steps li { padding: 10px 0; display: flex; align-items: flex-start; gap: 14px; font-size: 14px; color: #374151; border-bottom: 1px solid #f3f4f6; }
.sec-steps li:last-child { border: none; }
.sec-step-num { width: 26px; height: 26px; background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.8)); color: #C5A059; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }

/* ─── Code Input ─── */
.sec-code-input { width: 200px; padding: 14px 18px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 22px; font-family: 'Courier New', monospace; text-align: center; letter-spacing: 8px; font-weight: 700; transition: all 0.3s; }
.sec-code-input:focus { outline: none; border-color: #C5A059; box-shadow: 0 0 0 4px rgba(197,160,89,0.1); }
.sec-code-group { display: flex; gap: 12px; align-items: center; justify-content: center; margin: 16px 0; }

/* ─── Buttons ─── */
.sec-btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
.sec-btn-primary { background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.9)); color: #fff; box-shadow: 0 4px 12px rgba(26,60,52,0.25); }
.sec-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(26,60,52,0.35); }
.sec-btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220,38,38,0.25); }
.sec-btn-danger:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220,38,38,0.35); }
.sec-btn-outline { background: #fff; color: #374151; border: 1.5px solid #d1d5db; }
.sec-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }

/* ─── Email Selector ─── */
.sec-email-group { margin: 16px 0; }
.sec-email-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.sec-email-select, .sec-email-input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; color: #111827; background: #fff; transition: border-color 0.3s; box-sizing: border-box; }
.sec-email-select:focus, .sec-email-input:focus { outline: none; border-color: #C5A059; box-shadow: 0 0 0 4px rgba(197,160,89,0.1); }
.sec-custom-email { display: none; margin-top: 10px; }
.sec-custom-email.show { display: block; }
.sec-email-current { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.sec-email-current i { color: #16a34a; font-size: 16px; }
.sec-email-current span { font-size: 14px; color: #166534; }
.sec-email-current strong { font-weight: 700; }

/* ─── Recovery Codes ─── */
.sec-recovery { margin-top: 24px; padding: 22px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; }
.sec-recovery h4 { margin: 0 0 10px; font-size: 15px; color: #92400e; display: flex; align-items: center; gap: 8px; }
.sec-recovery p { font-size: 13px; color: #92400e; margin: 0 0 14px; }
.sec-recovery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.sec-recovery-code { padding: 10px; background: #fff; border: 1px solid #fde68a; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 700; color: #1A3C34; text-align: center; letter-spacing: 2px; }

/* ─── Divider ─── */
.sec-divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }

/* ─── Passkey List ─── */
.pk-list { display: flex; flex-direction: column; gap: 8px; }
.pk-item { display: flex; align-items: center; gap: 14px; padding: 14px 18px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; transition: background 0.2s; }
.pk-item:hover { background: #f3f4f6; }
.pk-item-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.8)); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pk-item-icon i { color: #C5A059; font-size: 16px; }
.pk-item-info { flex: 1; min-width: 0; }
.pk-item-info strong { display: block; font-size: 14px; color: #111827; }
.pk-item-info span { font-size: 12px; color: #9ca3af; }
.pk-remove-btn { width: 36px; height: 36px; border: none; background: #fee2e2; border-radius: 8px; color: #dc2626; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
.pk-remove-btn:hover { background: #fecaca; transform: scale(1.05); }

@media (max-width: 640px) {
    .sec-overview { grid-template-columns: 1fr; }
    .sec-tabs { flex-direction: column; }
    .sec-card-body { padding: 20px; }
    .sec-recovery-grid { grid-template-columns: 1fr; }
    .sec-code-input { width: 160px; }
    .sec-page-header { flex-direction: column; text-align: center; }
}
</style>

<div class="sec-container">
    <!-- Page Header -->
    <div class="sec-page-header">
        <div class="sec-page-header-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="sec-page-header-text">
            <h1>Admin Security Center</h1>
            <p>Manage multi-factor authentication methods for your admin account</p>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($message): ?>
        <div class="sec-alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : ($messageType === 'info' ? 'info-circle' : 'exclamation-triangle')) ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Security Overview Badges -->
    <div class="sec-overview">
        <div class="sec-badge <?= $is2FAEnabled ? 'active' : 'inactive' ?>">
            <div class="sec-badge-icon"><i class="fas fa-mobile-alt"></i></div>
            <div class="sec-badge-text">
                <h4>Authenticator App</h4>
                <p><?= $is2FAEnabled ? '● Enabled & Active' : '○ Not Configured' ?></p>
            </div>
        </div>
        <div class="sec-badge <?= $isEmailOtpEnabled ? 'active' : 'inactive' ?>">
            <div class="sec-badge-icon"><i class="fas fa-envelope-open-text"></i></div>
            <div class="sec-badge-text">
                <h4>Email OTP</h4>
                <p><?= $isEmailOtpEnabled ? '● Enabled — ' . htmlspecialchars($securityEmail) : '○ Not Configured' ?></p>
            </div>
        </div>
        <div class="sec-badge <?= $isPasskeyEnabled ? 'active' : 'inactive' ?>">
            <div class="sec-badge-icon"><i class="fas fa-fingerprint"></i></div>
            <div class="sec-badge-text">
                <h4>Passkey</h4>
                <p><?= $isPasskeyEnabled ? '● Enabled — ' . count($passkeys) . ' key(s)' : '○ Not Configured' ?></p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="sec-tabs">
        <button class="sec-tab <?= $activeTab === 'authenticator' ? 'active' : '' ?>" onclick="switchTab('authenticator')">
            <i class="fas fa-mobile-alt"></i> Authenticator App
        </button>
        <button class="sec-tab <?= $activeTab === 'email_otp' ? 'active' : '' ?>" onclick="switchTab('email_otp')">
            <i class="fas fa-envelope"></i> Email OTP
        </button>
        <button class="sec-tab <?= $activeTab === 'passkey' ? 'active' : '' ?>" onclick="switchTab('passkey')">
            <i class="fas fa-fingerprint"></i> Passkey
        </button>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- TAB 1: AUTHENTICATOR APP 2FA                    -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="sec-card <?= $activeTab === 'authenticator' ? 'visible' : '' ?>" id="tab-authenticator">
        <div class="sec-card-head">
            <div class="sec-card-title"><i class="fas fa-mobile-alt"></i><h3>Authenticator App (TOTP)</h3></div>
            <span class="sec-card-status <?= $is2FAEnabled ? 'on' : 'off' ?>"><?= $is2FAEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="sec-card-body">

            <?php if (!$is2FAEnabled && !$showQR): ?>
                <p style="color:#6b7280; font-size:14px; margin:0 0 20px;">Use Microsoft Authenticator, Google Authenticator, or any TOTP app to generate login codes every 30 seconds.</p>
                <ol class="sec-steps">
                    <li><span class="sec-step-num">1</span><span>Install <strong>Microsoft Authenticator</strong> or <strong>Google Authenticator</strong> on your phone</span></li>
                    <li><span class="sec-step-num">2</span><span>Click <strong>Generate QR Code</strong> below</span></li>
                    <li><span class="sec-step-num">3</span><span>Scan the QR code with your authenticator app</span></li>
                    <li><span class="sec-step-num">4</span><span>Enter the 6-digit code to verify and activate</span></li>
                </ol>
                <form method="POST" style="text-align:center; margin-top:20px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="totp_generate">
                    <button type="submit" class="sec-btn sec-btn-primary"><i class="fas fa-qrcode"></i> Generate QR Code</button>
                </form>

            <?php elseif ($showQR): ?>
                <div class="sec-qr">
                    <p style="font-size:15px; color:#374151; margin-bottom:16px;"><strong>Scan this QR code</strong> with your authenticator app:</p>
                    <img src="<?= htmlspecialchars($qrUrl) ?>" alt="2FA QR Code" width="220" height="220">
                    <div style="margin-top:16px;">
                        <p style="font-size:12px; color:#9ca3af; margin-bottom:6px;">Or enter this key manually:</p>
                        <div class="sec-secret"><?= htmlspecialchars(chunk_split($secretKey, 4, ' ')) ?></div>
                    </div>
                </div>
                <form method="POST" style="text-align:center;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="totp_verify_enable">
                    <p style="font-size:13px; color:#6b7280; margin-bottom:8px;">Enter the 6-digit code from your app:</p>
                    <div class="sec-code-group">
                        <input type="text" name="totp_code" class="sec-code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" class="sec-btn sec-btn-primary"><i class="fas fa-check-circle"></i> Verify & Enable</button>
                </form>
            <?php endif; ?>

            <?php if ($is2FAEnabled && empty($recoveryCodes)): ?>
                <p style="color:#16a34a; font-size:14px; margin:0 0 20px;"><i class="fas fa-check-circle"></i> Your account is protected with authenticator-based 2FA.</p>
                <hr class="sec-divider">
                <p style="font-size:13px; color:#6b7280; margin-bottom:12px;">To disable, enter your current authenticator code:</p>
                <form method="POST" style="text-align:center;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="totp_disable">
                    <div class="sec-code-group">
                        <input type="text" name="totp_code" class="sec-code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required>
                    </div>
                    <button type="submit" class="sec-btn sec-btn-danger" onclick="return confirm('Are you sure you want to disable Authenticator 2FA?')"><i class="fas fa-unlock"></i> Disable Authenticator</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($recoveryCodes)): ?>
                <div class="sec-recovery">
                    <h4><i class="fas fa-key"></i> Recovery Codes</h4>
                    <p><strong>Save these codes now!</strong> Each can only be used once. Use them if you lose access to your authenticator app.</p>
                    <div class="sec-recovery-grid">
                        <?php foreach ($recoveryCodes as $code): ?>
                            <div class="sec-recovery-code"><?= htmlspecialchars($code) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- TAB 2: EMAIL OTP                                -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="sec-card <?= $activeTab === 'email_otp' ? 'visible' : '' ?>" id="tab-email_otp">
        <div class="sec-card-head">
            <div class="sec-card-title"><i class="fas fa-envelope-open-text"></i><h3>Email OTP Verification</h3></div>
            <span class="sec-card-status <?= $isEmailOtpEnabled ? 'on' : 'off' ?>"><?= $isEmailOtpEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="sec-card-body">

            <?php if ($isEmailOtpEnabled): ?>
                <!-- Currently enabled -->
                <div class="sec-email-current">
                    <i class="fas fa-check-circle"></i>
                    <span>OTP codes are sent to: <strong><?= htmlspecialchars($securityEmail) ?></strong></span>
                </div>

                <!-- Update email -->
                <details style="margin-bottom:20px;">
                    <summary style="cursor:pointer; font-size:14px; font-weight:600; color:#1A3C34; padding:8px 0;">
                        <i class="fas fa-pen"></i> Change Security Email
                    </summary>
                    <form method="POST" style="margin-top:12px;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="update_security_email">
                        <div class="sec-email-group">
                            <label>Select or Enter Email Address</label>
                            <select name="security_email" class="sec-email-select" onchange="toggleCustomEmail(this, 'updateCustom')">
                                <option value="">— Choose email —</option>
                                <option value="<?= htmlspecialchars($accountEmail) ?>">📧 Account Email: <?= htmlspecialchars($accountEmail) ?></option>
                                <?php foreach ($configuredEmails as $ce): ?>
                                    <option value="<?= htmlspecialchars($ce['email_address']) ?>">📨 <?= htmlspecialchars($ce['display_name'] ?: $ce['email_address']) ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">✏️ Enter custom email...</option>
                            </select>
                            <div class="sec-custom-email" id="updateCustom">
                                <label>Custom Email Address</label>
                                <input type="email" name="custom_email" class="sec-email-input" placeholder="your-email@example.com">
                            </div>
                        </div>
                        <button type="submit" class="sec-btn sec-btn-primary" style="margin-top:12px;"><i class="fas fa-save"></i> Update Email</button>
                    </form>
                </details>

                <hr class="sec-divider">
                <form method="POST" style="text-align:center;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="email_otp_disable">
                    <button type="submit" class="sec-btn sec-btn-danger" onclick="return confirm('Disable Email OTP? You will no longer receive login codes via email.')">
                        <i class="fas fa-power-off"></i> Disable Email OTP
                    </button>
                </form>

            <?php elseif ($pendingEmailSetup): ?>
                <!-- Verification step -->
                <div class="sec-alert info">
                    <i class="fas fa-paper-plane"></i>
                    A 6-digit code was sent to <strong><?= htmlspecialchars($pendingSetupEmail) ?></strong>. Enter it below to activate Email OTP.
                </div>
                <form method="POST" style="text-align:center;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="email_otp_verify_enable">
                    <p style="font-size:13px; color:#6b7280; margin-bottom:8px;">Enter the 6-digit verification code:</p>
                    <div class="sec-code-group">
                        <input type="text" name="email_otp_code" class="sec-code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" class="sec-btn sec-btn-primary"><i class="fas fa-check-circle"></i> Verify & Enable Email OTP</button>
                </form>

            <?php else: ?>
                <!-- Setup -->
                <p style="color:#6b7280; font-size:14px; margin:0 0 20px;">Receive a one-time 6-digit code via email every time you log in to the admin panel. The code expires after 5 minutes.</p>

                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="email_otp_enable">

                    <div class="sec-email-group">
                        <label><i class="fas fa-at" style="color:#C5A059;"></i> Security Email Address</label>
                        <select name="security_email" class="sec-email-select" onchange="toggleCustomEmail(this, 'setupCustom')" required>
                            <option value="">— Choose where to receive OTP codes —</option>
                            <option value="<?= htmlspecialchars($accountEmail) ?>">📧 Account Email: <?= htmlspecialchars($accountEmail) ?></option>
                            <?php foreach ($configuredEmails as $ce): ?>
                                <option value="<?= htmlspecialchars($ce['email_address']) ?>">📨 <?= htmlspecialchars($ce['display_name'] ?: $ce['email_address']) ?></option>
                            <?php endforeach; ?>
                            <option value="__custom__">✏️ Enter custom email manually...</option>
                        </select>
                        <div class="sec-custom-email" id="setupCustom">
                            <label>Custom Email Address</label>
                            <input type="email" name="custom_email" class="sec-email-input" placeholder="your-security-email@example.com">
                        </div>
                    </div>

                    <div style="text-align:center; margin-top:20px;">
                        <button type="submit" class="sec-btn sec-btn-primary"><i class="fas fa-paper-plane"></i> Send Verification Code</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════ -->
    <!-- TAB 3: PASSKEY (WebAuthn)                       -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="sec-card <?= $activeTab === 'passkey' ? 'visible' : '' ?>" id="tab-passkey">
        <div class="sec-card-head">
            <div class="sec-card-title"><i class="fas fa-fingerprint"></i><h3>Passkey (WebAuthn / FIDO2)</h3></div>
            <span class="sec-card-status <?= $isPasskeyEnabled ? 'on' : 'off' ?>"><?= $isPasskeyEnabled ? 'Enabled' : 'Disabled' ?></span>
        </div>
        <div class="sec-card-body">

            <div id="pk-unsupported" style="display:none;" class="sec-alert warning">
                <i class="fas fa-exclamation-triangle"></i>
                Your browser does not support WebAuthn. Please use a modern browser (Chrome, Edge, Safari, Firefox).
            </div>

            <p style="color:#6b7280; font-size:14px; margin:0 0 20px;">
                Use fingerprint, Face ID, Windows Hello, or a hardware security key to verify your identity at login.
                Passkeys are phishing-resistant and the most secure MFA method available.
            </p>

            <?php if (!empty($passkeys)): ?>
                <!-- Registered passkeys list -->
                <div style="margin-bottom:24px;">
                    <h4 style="font-size:14px; font-weight:700; color:#374151; margin:0 0 12px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-key" style="color:#C5A059;"></i> Registered Passkeys (<?= count($passkeys) ?>)
                    </h4>
                    <div class="pk-list">
                        <?php foreach ($passkeys as $pk): ?>
                            <div class="pk-item" id="pk-row-<?= $pk['id'] ?>">
                                <div class="pk-item-icon"><i class="fas fa-fingerprint"></i></div>
                                <div class="pk-item-info">
                                    <strong><?= htmlspecialchars($pk['device_name']) ?></strong>
                                    <span>
                                        Added <?= date('M j, Y', strtotime($pk['created_at'])) ?>
                                        <?php if ($pk['last_used_at']): ?>
                                            · Last used <?= date('M j, Y g:ia', strtotime($pk['last_used_at'])) ?>
                                        <?php else: ?>
                                            · Never used
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <button class="pk-remove-btn" onclick="removePasskey(<?= $pk['id'] ?>, '<?= htmlspecialchars(addslashes($pk['device_name'])) ?>')" title="Remove">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Register new passkey -->
            <div style="padding:20px 0; border-top:1px solid #f3f4f6;">
                <div class="sec-email-group" style="max-width:360px; margin:0 auto 16px;">
                    <label>Device Name (optional)</label>
                    <input type="text" id="pk-device-name" class="sec-email-input" placeholder="e.g. MacBook Pro, iPhone, YubiKey" maxlength="100">
                </div>
                <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <button type="button" class="sec-btn sec-btn-primary" id="pk-register-btn" onclick="registerPasskey('platform')">
                        <i class="fas fa-laptop"></i> This Device
                    </button>
                    <button type="button" class="sec-btn sec-btn-outline" id="pk-register-phone-btn" onclick="registerPasskey('cross-platform')" style="border-color:#1A3C34; color:#1A3C34;">
                        <i class="fas fa-mobile-alt"></i> Phone / Tablet
                    </button>
                </div>
                <div id="pk-status" style="font-size:13px; color:#6b7280; margin-top:12px; min-height:20px; text-align:center;"></div>
                <div style="margin-top:14px; padding:12px 16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; font-size:12px; color:#1e40af; line-height:1.6;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Tip:</strong> Each device needs its own passkey. Use <strong>"This Device"</strong> for Windows Hello / Touch ID. Use <strong>"Phone / Tablet"</strong> to scan a QR code and register your phone — then you can sign in from your phone too.
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sec-card').forEach(c => c.classList.remove('visible'));
    document.getElementById('tab-' + tab).classList.add('visible');
    event.currentTarget.classList.add('active');
}

function toggleCustomEmail(sel, targetId) {
    const custom = document.getElementById(targetId);
    if (sel.value === '__custom__') {
        custom.classList.add('show');
        custom.querySelector('input').required = true;
        custom.querySelector('input').focus();
    } else {
        custom.classList.remove('show');
        custom.querySelector('input').required = false;
        custom.querySelector('input').value = '';
    }
}

// Auto-activate correct tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = '<?= $activeTab ?>';
    if (activeTab !== 'overview') {
        const tabs = document.querySelectorAll('.sec-tab');
        tabs.forEach(t => {
            t.classList.remove('active');
            const txt = t.textContent.toLowerCase();
            if ((activeTab === 'authenticator' && txt.includes('authenticator')) ||
                (activeTab === 'email_otp' && txt.includes('email')) ||
                (activeTab === 'passkey' && txt.includes('passkey'))) {
                t.classList.add('active');
            }
        });
    }
    // Check WebAuthn support
    if (!window.PublicKeyCredential) {
        const el = document.getElementById('pk-unsupported');
        if (el) el.style.display = 'flex';
        const btn = document.getElementById('pk-register-btn');
        if (btn) btn.disabled = true;
    }
});

/* ─── Passkey (WebAuthn) Functions ─── */
const pkApiBase = '<?= base_url("admin/passkey_api.php") ?>';

function b64urlToBuffer(b64) {
    const pad = '='.repeat((4 - b64.length % 4) % 4);
    const raw = atob(b64.replace(/-/g, '+').replace(/_/g, '/') + pad);
    return Uint8Array.from(raw, c => c.charCodeAt(0)).buffer;
}

function bufferToB64url(buf) {
    const bytes = new Uint8Array(buf);
    let s = '';
    for (const b of bytes) s += String.fromCharCode(b);
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

async function registerPasskey(attachment) {
    const statusEl = document.getElementById('pk-status');
    const btn = document.getElementById('pk-register-btn');
    const btnPhone = document.getElementById('pk-register-phone-btn');
    const deviceName = document.getElementById('pk-device-name').value.trim() || (attachment === 'cross-platform' ? 'Phone' : 'Passkey');

    statusEl.textContent = 'Requesting registration options...';
    statusEl.style.color = '#6b7280';
    btn.disabled = true;
    btnPhone.disabled = true;

    try {
        // 1. Get registration options (with optional attachment hint)
        const url = pkApiBase + '?action=register_options' + (attachment ? '&attachment=' + attachment : '');
        const res = await fetch(url);
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const opts = data.options;
        opts.challenge = b64urlToBuffer(opts.challenge);
        opts.user.id = b64urlToBuffer(opts.user.id);
        if (opts.excludeCredentials) {
            opts.excludeCredentials = opts.excludeCredentials.map(c => ({
                ...c, id: b64urlToBuffer(c.id)
            }));
        }

        statusEl.textContent = 'Complete the prompt on your device...';

        // 2. Create credential
        const credential = await navigator.credentials.create({ publicKey: opts });

        statusEl.textContent = 'Verifying with server...';

        // 3. Send to server
        const verifyRes = await fetch(pkApiBase + '?action=register_verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                clientDataJSON: bufferToB64url(credential.response.clientDataJSON),
                attestationObject: bufferToB64url(credential.response.attestationObject),
                deviceName: deviceName,
            })
        });

        const result = await verifyRes.json();
        if (result.error) throw new Error(result.error);

        statusEl.textContent = 'Passkey registered! Reloading...';
        statusEl.style.color = '#16a34a';
        setTimeout(() => location.reload(), 800);

    } catch (err) {
        statusEl.style.color = '#dc2626';
        if (err.name === 'NotAllowedError') {
            statusEl.textContent = 'Registration was cancelled or timed out.';
        } else if (err.name === 'InvalidStateError') {
            statusEl.textContent = 'This device is already registered.';
        } else {
            statusEl.textContent = 'Error: ' + err.message;
        }
        btn.disabled = false;
        btnPhone.disabled = false;
    }
}

async function removePasskey(rowId, name) {
    if (!confirm('Remove passkey "' + name + '"? This cannot be undone.')) return;

    try {
        const res = await fetch(pkApiBase + '?action=remove', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ row_id: rowId })
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const row = document.getElementById('pk-row-' + rowId);
        if (row) {
            row.style.transition = 'opacity 0.3s, transform 0.3s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(() => { row.remove(); location.reload(); }, 350);
        }
    } catch (err) {
        alert('Failed to remove passkey: ' + err.message);
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
