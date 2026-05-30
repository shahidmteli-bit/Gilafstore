<?php
/**
 * Email OTP Verification Screen
 * Shown after admin password login when Email OTP is enabled
 * Verifies the 6-digit code sent to the admin's security email
 */
$pageTitle = 'Email Verification — Gilaf Admin';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';

// Must have a pending admin session
if (empty($_SESSION['_2fa_pending_admin']) || empty($_SESSION['_2fa_pending_time'])) {
    header('Location: ' . base_url('gs-secure-portal-92XK'));
    exit;
}

// 5-minute timeout
if (time() - $_SESSION['_2fa_pending_time'] > 300) {
    unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method'], $_SESSION['_email_otp_target']);
    clear_email_otp();
    header('Location: ' . base_url('gs-secure-portal-92XK'));
    exit;
}

$pendingAdmin = $_SESSION['_2fa_pending_admin'];
$targetEmail = $_SESSION['_email_otp_target'] ?? $pendingAdmin['email'];
$maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $targetEmail);

$error = '';
$resendMsg = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        // Resend OTP
        $otp = generate_email_otp();
        $sent = send_email_otp($targetEmail, $otp);
        if ($sent) {
            $resendMsg = 'A new code has been sent to your email.';
        } else {
            $error = 'Failed to resend code. Please try again.';
        }
    } else {
        // Verify OTP
        $code = trim($_POST['otp_code'] ?? '');

        if (empty($code) || strlen($code) !== 6) {
            $error = 'Please enter a valid 6-digit code.';
        } elseif (verify_email_otp($code)) {
            // Success — complete login
            $_SESSION['admin'] = $pendingAdmin;
            unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method'], $_SESSION['_email_otp_target']);
            secure_session_regenerate();
            security_log_successful_login($pendingAdmin['id'], $pendingAdmin['email'], 'admin');
            security_log('EMAIL_OTP_VERIFIED', 'INFO', "Admin {$pendingAdmin['email']} passed Email OTP verification");

            header('Location: ' . base_url('admin/index.php'));
            exit;
        } else {
            // Check if it was expired vs wrong
            if (empty($_SESSION['_email_otp_code'])) {
                $error = 'Code expired or too many attempts. Please request a new code.';
            } else {
                $attempts = $_SESSION['_email_otp_attempts'] ?? 0;
                $remaining = max(0, 5 - $attempts);
                $error = "Invalid code. {$remaining} attempt(s) remaining.";
            }
            security_log('EMAIL_OTP_FAILED', 'WARNING', "Failed email OTP attempt for {$pendingAdmin['email']}");
        }
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

        .otp-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
        }

        .otp-header {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.92));
            padding: 32px 28px;
            text-align: center;
        }
        .otp-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.12);
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .otp-icon i { color: #C5A059; font-size: 28px; }
        .otp-header h2 { color: #fff; font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .otp-header p { color: rgba(255,255,255,0.65); font-size: 13px; }

        .otp-body { padding: 32px 28px; }

        .otp-email-badge {
            display: flex; align-items: center; gap: 10px;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 10px; padding: 12px 16px; margin-bottom: 24px;
        }
        .otp-email-badge i { color: #16a34a; font-size: 16px; }
        .otp-email-badge span { font-size: 13px; color: #166534; }
        .otp-email-badge strong { font-weight: 700; }

        .otp-alert {
            padding: 12px 16px; border-radius: 10px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px; font-size: 13px;
        }
        .otp-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .otp-alert.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .otp-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

        .otp-label {
            font-size: 13px; font-weight: 600; color: #374151;
            margin-bottom: 10px; display: block; text-align: center;
        }

        .otp-input-wrap { display: flex; justify-content: center; margin-bottom: 20px; }
        .otp-input {
            width: 220px; padding: 16px 20px;
            border: 2px solid #e5e7eb; border-radius: 14px;
            font-size: 28px; font-family: 'Courier New', monospace;
            text-align: center; letter-spacing: 10px; font-weight: 700;
            color: #1A3C34; transition: all 0.3s;
        }
        .otp-input:focus {
            outline: none; border-color: #C5A059;
            box-shadow: 0 0 0 4px rgba(197,160,89,0.15);
        }

        .otp-btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s;
        }
        .otp-btn-primary {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.9));
            color: #fff; box-shadow: 0 4px 12px rgba(26,60,52,0.3);
        }
        .otp-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(26,60,52,0.4);
        }

        .otp-footer {
            text-align: center; margin-top: 20px;
            padding-top: 20px; border-top: 1px solid #f3f4f6;
        }
        .otp-footer form { display: inline; }
        .otp-resend {
            background: none; border: none; color: #C5A059;
            font-size: 13px; font-weight: 600; cursor: pointer;
            text-decoration: underline;
        }
        .otp-resend:hover { color: #a88940; }
        .otp-resend:disabled { color: #9ca3af; cursor: not-allowed; text-decoration: none; }

        .otp-timer {
            font-size: 12px; color: #9ca3af; margin-top: 8px;
        }

        .otp-back {
            display: block; text-align: center; margin-top: 16px;
            color: #6b7280; font-size: 13px; text-decoration: none;
        }
        .otp-back:hover { color: #374151; }

        @media (max-width: 480px) {
            .otp-body { padding: 24px 20px; }
            .otp-input { width: 180px; font-size: 24px; letter-spacing: 8px; }
        }
    </style>
</head>
<body>
    <div class="otp-card">
        <div class="otp-header">
            <div class="otp-icon"><i class="fas fa-envelope-open-text"></i></div>
            <h2>Email Verification</h2>
            <p>A security code has been sent to your email</p>
        </div>

        <div class="otp-body">
            <div class="otp-email-badge">
                <i class="fas fa-paper-plane"></i>
                <span>Code sent to: <strong><?= htmlspecialchars($maskedEmail) ?></strong></span>
            </div>

            <?php if ($error): ?>
                <div class="otp-alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($resendMsg): ?>
                <div class="otp-alert success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($resendMsg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="verify">
                <label class="otp-label">Enter the 6-digit code</label>
                <div class="otp-input-wrap">
                    <input type="text" name="otp_code" class="otp-input" maxlength="6"
                           pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"
                           placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="otp-btn otp-btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Verify & Login
                </button>
            </form>

            <div class="otp-footer">
                <form method="POST" style="display:inline;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="otp-resend" id="resendBtn">
                        <i class="fas fa-redo"></i> Resend Code
                    </button>
                </form>
                <div class="otp-timer" id="timerText">Code expires in <span id="countdown">5:00</span></div>
            </div>

            <a href="<?= base_url('gs-secure-portal-92XK') ?>" class="otp-back">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
    // Countdown timer
    (function() {
        const otpTime = <?= (int)($_SESSION['_email_otp_time'] ?? time()) ?>;
        const expiryTime = otpTime + 300; // 5 minutes

        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            let remaining = expiryTime - now;
            if (remaining < 0) remaining = 0;

            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const el = document.getElementById('countdown');
            if (el) el.textContent = mins + ':' + String(secs).padStart(2, '0');

            if (remaining <= 0) {
                const timer = document.getElementById('timerText');
                if (timer) timer.innerHTML = '<span style="color:#dc2626;">Code expired. Please resend.</span>';
            } else {
                setTimeout(updateTimer, 1000);
            }
        }
        updateTimer();

        // Auto-focus and auto-submit when 6 digits entered
        const input = document.querySelector('.otp-input');
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length === 6) {
                    this.closest('form').submit();
                }
            });
        }
    })();
    </script>
</body>
</html>
