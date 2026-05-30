<?php
/**
 * TOTP 2FA Verification Screen
 * Shown after admin password login when Authenticator 2FA is enabled
 * Verifies TOTP code or recovery code
 */
$pageTitle = '2FA Verification — Gilaf Admin';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';

// Must have a pending admin session
if (empty($_SESSION['_2fa_pending_admin']) || empty($_SESSION['_2fa_pending_time'])) {
    header('Location: ' . base_url('gs-secure-portal-92XK'));
    exit;
}

// 5-minute timeout
if (time() - $_SESSION['_2fa_pending_time'] > 300) {
    unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method']);
    header('Location: ' . base_url('gs-secure-portal-92XK'));
    exit;
}

$pendingAdmin = $_SESSION['_2fa_pending_admin'];
$error = '';
$showRecovery = isset($_GET['recovery']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $db = get_db_connection();
    $stmt = $db->prepare("SELECT totp_secret, recovery_codes FROM users WHERE id = ?");
    $stmt->execute([$pendingAdmin['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $action = $_POST['action'] ?? 'totp';

    if ($action === 'recovery') {
        // Recovery code verification
        $recoveryCode = trim($_POST['recovery_code'] ?? '');
        $encryptedRecovery = $row['recovery_codes'] ?? '';
        $decryptedRecovery = decrypt_data($encryptedRecovery);

        if ($decryptedRecovery) {
            $codes = json_decode($decryptedRecovery, true);
            $found = false;

            if (is_array($codes)) {
                foreach ($codes as &$entry) {
                    if (!$entry['used'] && hash_equals($entry['code'], $recoveryCode)) {
                        $entry['used'] = true;
                        $found = true;
                        break;
                    }
                }
                unset($entry);
            }

            if ($found) {
                // Update recovery codes
                $encUpdated = encrypt_data(json_encode($codes));
                $stmt = $db->prepare("UPDATE users SET recovery_codes = ? WHERE id = ?");
                $stmt->execute([$encUpdated, $pendingAdmin['id']]);

                // Complete login
                $_SESSION['admin'] = $pendingAdmin;
                unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method']);
                secure_session_regenerate();
                security_log_successful_login($pendingAdmin['id'], $pendingAdmin['email'], 'admin');
                security_log('2FA_RECOVERY_USED', 'WARNING', "Admin {$pendingAdmin['email']} used a recovery code");

                header('Location: ' . base_url('admin/index.php'));
                exit;
            }
        }
        $error = 'Invalid recovery code.';
        $showRecovery = true;

    } else {
        // TOTP verification
        $code = trim($_POST['totp_code'] ?? '');
        $encryptedSecret = $row['totp_secret'] ?? '';
        $decryptedSecret = decrypt_data($encryptedSecret);

        if ($decryptedSecret && !empty($code)) {
            $totp = new TOTP($decryptedSecret);
            if ($totp->verify($code)) {
                // Complete login
                $_SESSION['admin'] = $pendingAdmin;
                unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method']);
                secure_session_regenerate();
                security_log_successful_login($pendingAdmin['id'], $pendingAdmin['email'], 'admin');
                security_log('2FA_VERIFIED', 'INFO', "Admin {$pendingAdmin['email']} passed TOTP 2FA");

                header('Location: ' . base_url('admin/index.php'));
                exit;
            }
        }

        $error = 'Invalid authenticator code. Please try again.';
        security_log('2FA_FAILED', 'WARNING', "Failed TOTP attempt for {$pendingAdmin['email']}");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0f2420 0%, #1A3C34 40%, #234d43 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }

        .tfa-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 440px; width: 100%; overflow: hidden;
        }

        .tfa-header {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.92));
            padding: 32px 28px; text-align: center;
        }
        .tfa-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.12); border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .tfa-icon i { color: #C5A059; font-size: 28px; }
        .tfa-header h2 { color: #fff; font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .tfa-header p { color: rgba(255,255,255,0.65); font-size: 13px; }

        .tfa-body { padding: 32px 28px; }

        .tfa-alert {
            padding: 12px 16px; border-radius: 10px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; font-size: 13px;
        }
        .tfa-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        .tfa-label {
            font-size: 13px; font-weight: 600; color: #374151;
            margin-bottom: 10px; display: block; text-align: center;
        }

        .tfa-input-wrap { display: flex; justify-content: center; margin-bottom: 20px; }
        .tfa-input {
            width: 220px; padding: 16px 20px;
            border: 2px solid #e5e7eb; border-radius: 14px;
            font-size: 28px; font-family: 'Courier New', monospace;
            text-align: center; letter-spacing: 10px; font-weight: 700;
            color: #1A3C34; transition: all 0.3s;
        }
        .tfa-input:focus {
            outline: none; border-color: #C5A059;
            box-shadow: 0 0 0 4px rgba(197,160,89,0.15);
        }
        .tfa-recovery-input {
            width: 100%; padding: 14px 16px;
            border: 2px solid #e5e7eb; border-radius: 12px;
            font-size: 16px; font-family: 'Courier New', monospace;
            text-align: center; letter-spacing: 3px; font-weight: 700;
            color: #1A3C34; transition: all 0.3s; margin-bottom: 20px;
        }
        .tfa-recovery-input:focus {
            outline: none; border-color: #C5A059;
            box-shadow: 0 0 0 4px rgba(197,160,89,0.15);
        }

        .tfa-btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s;
        }
        .tfa-btn-primary {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.9));
            color: #fff; box-shadow: 0 4px 12px rgba(26,60,52,0.3);
        }
        .tfa-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(26,60,52,0.4);
        }

        .tfa-footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f3f4f6; }
        .tfa-link { color: #C5A059; text-decoration: none; font-size: 13px; font-weight: 600; }
        .tfa-link:hover { text-decoration: underline; }

        .tfa-back {
            display: block; text-align: center; margin-top: 16px;
            color: #6b7280; font-size: 13px; text-decoration: none;
        }
        .tfa-back:hover { color: #374151; }

        @media (max-width: 480px) {
            .tfa-body { padding: 24px 20px; }
            .tfa-input { width: 180px; font-size: 24px; letter-spacing: 8px; }
        }
    </style>
</head>
<body>
    <div class="tfa-card">
        <div class="tfa-header">
            <div class="tfa-icon">
                <i class="fas fa-<?= $showRecovery ? 'key' : 'mobile-alt' ?>"></i>
            </div>
            <h2><?= $showRecovery ? 'Recovery Code' : 'Authenticator Verification' ?></h2>
            <p><?= $showRecovery ? 'Enter one of your saved recovery codes' : 'Enter the code from your authenticator app' ?></p>
        </div>

        <div class="tfa-body">
            <?php if ($error): ?>
                <div class="tfa-alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($showRecovery): ?>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="recovery">
                    <label class="tfa-label">Enter your recovery code</label>
                    <input type="text" name="recovery_code" class="tfa-recovery-input"
                           placeholder="XXXX-XXXX" required autofocus>
                    <button type="submit" class="tfa-btn tfa-btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Verify Recovery Code
                    </button>
                </form>
                <div class="tfa-footer">
                    <a href="<?= base_url('admin/two_factor_verify.php') ?>" class="tfa-link">
                        <i class="fas fa-mobile-alt"></i> Use authenticator app instead
                    </a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="totp">
                    <label class="tfa-label">Enter the 6-digit code</label>
                    <div class="tfa-input-wrap">
                        <input type="text" name="totp_code" class="tfa-input" maxlength="6"
                               pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"
                               placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" class="tfa-btn tfa-btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Verify & Login
                    </button>
                </form>
                <div class="tfa-footer">
                    <a href="<?= base_url('admin/two_factor_verify.php?recovery=1') ?>" class="tfa-link">
                        <i class="fas fa-key"></i> Use a recovery code instead
                    </a>
                </div>
            <?php endif; ?>

            <a href="<?= base_url('gs-secure-portal-92XK') ?>" class="tfa-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
    // Auto-submit when 6 digits entered (TOTP only)
    const input = document.querySelector('.tfa-input');
    if (input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) {
                this.closest('form').submit();
            }
        });
    }
    </script>
</body>
</html>
