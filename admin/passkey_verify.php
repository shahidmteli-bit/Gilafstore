<?php
/**
 * Passkey Verification Screen
 * Shown after admin password login when Passkey is enabled
 * Uses WebAuthn API to verify the admin's registered passkey
 */
$pageTitle = 'Passkey Verification — Gilaf Admin';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

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

// Check if admin actually has passkeys registered
$pendingAdmin = $_SESSION['_2fa_pending_admin'];
$noPasskeysRegistered = false;
try {
    $pkDb = get_db_connection();
    $pkCnt = $pkDb->prepare("SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ?");
    $pkCnt->execute([$pendingAdmin['id']]);
    if ((int)$pkCnt->fetchColumn() === 0) {
        $noPasskeysRegistered = true;
    }
} catch (PDOException $e) {
    $noPasskeysRegistered = true;
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
        .pk-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 440px; width: 100%; overflow: hidden;
        }
        .pk-header {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.92));
            padding: 32px 28px; text-align: center;
        }
        .pk-icon {
            width: 72px; height: 72px;
            background: rgba(255,255,255,0.12); border-radius: 18px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .pk-icon i { color: #C5A059; font-size: 32px; }
        .pk-header h2 { color: #fff; font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .pk-header p  { color: rgba(255,255,255,0.65); font-size: 13px; }

        .pk-body { padding: 32px 28px; text-align: center; }

        .pk-status {
            font-size: 14px; color: #374151; margin-bottom: 24px;
            min-height: 20px;
        }
        .pk-status.loading { color: #9ca3af; }
        .pk-status.success { color: #16a34a; font-weight: 600; }
        .pk-status.error   { color: #dc2626; }

        .pk-fingerprint {
            width: 80px; height: 80px; margin: 0 auto 24px;
            border-radius: 50%; background: #f0fdf4; border: 3px solid #bbf7d0;
            display: flex; align-items: center; justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        .pk-fingerprint i { font-size: 36px; color: #1A3C34; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(26,60,52,0.2); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(26,60,52,0); }
        }

        .pk-btn {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s;
        }
        .pk-btn-primary {
            background: linear-gradient(135deg, #1A3C34, rgba(26,60,52,0.9));
            color: #fff; box-shadow: 0 4px 12px rgba(26,60,52,0.3);
        }
        .pk-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(26,60,52,0.4);
        }
        .pk-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .pk-alert {
            padding: 12px 16px; border-radius: 10px; margin-top: 16px;
            display: none; align-items: center; gap: 8px; font-size: 13px;
            text-align: left;
        }
        .pk-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .pk-alert.show { display: flex; }

        .pk-back {
            display: block; text-align: center; margin-top: 20px;
            color: #6b7280; font-size: 13px; text-decoration: none;
        }
        .pk-back:hover { color: #374151; }

        .pk-unsupported {
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 10px; padding: 16px; margin-bottom: 20px;
            font-size: 13px; color: #92400e;
        }

        @media (max-width: 480px) {
            .pk-body { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="pk-card">
        <div class="pk-header">
            <div class="pk-icon"><i class="fas fa-fingerprint"></i></div>
            <h2>Passkey Verification</h2>
            <p>Use your registered passkey to verify your identity</p>
        </div>

        <div class="pk-body">
            <?php if ($noPasskeysRegistered): ?>
                <div style="padding:20px 0; text-align:center;">
                    <div style="width:80px; height:80px; margin:0 auto 20px; border-radius:50%; background:#fef2f2; border:3px solid #fecaca; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-exclamation-triangle" style="font-size:32px; color:#dc2626;"></i>
                    </div>
                    <h3 style="font-size:18px; color:#991b1b; margin-bottom:10px;">No Passkeys Registered</h3>
                    <p style="font-size:14px; color:#6b7280; margin-bottom:20px; line-height:1.6;">
                        No passkey has been registered for your account yet.<br>
                        Please log in with your password first, then register a passkey<br>
                        in <strong>Security Center → Passkey</strong> tab.
                    </p>
                    <a href="<?= base_url('gs-secure-portal-92XK') ?>" class="pk-btn pk-btn-primary" style="display:inline-flex; width:auto; padding:14px 28px; text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>
                <div id="unsupported" class="pk-unsupported" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>WebAuthn not supported.</strong> Your browser does not support passkeys.
                    Please use a modern browser or try another verification method.
                </div>

                <div class="pk-fingerprint" id="fpIcon">
                    <i class="fas fa-fingerprint"></i>
                </div>

                <div class="pk-status loading" id="status">Initializing passkey verification...</div>

                <button class="pk-btn pk-btn-primary" id="verifyBtn" disabled>
                    <i class="fas fa-fingerprint"></i> Verify with Passkey
                </button>

                <div class="pk-alert error" id="errorBox">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorMsg"></span>
                </div>

                <a href="<?= base_url('gs-secure-portal-92XK') ?>" class="pk-back">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$noPasskeysRegistered): ?>
    <script>
    const apiBase = '<?= base_url("admin/passkey_api.php") ?>';
    const statusEl = document.getElementById('status');
    const errorBox = document.getElementById('errorBox');
    const errorMsg = document.getElementById('errorMsg');
    const verifyBtn = document.getElementById('verifyBtn');

    function showError(msg) {
        errorMsg.textContent = msg;
        errorBox.classList.add('show');
        statusEl.textContent = 'Verification failed';
        statusEl.className = 'pk-status error';
    }

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

    async function startVerification() {
        verifyBtn.disabled = true;
        errorBox.classList.remove('show');
        statusEl.textContent = 'Requesting challenge from server...';
        statusEl.className = 'pk-status loading';

        try {
            // 1. Get auth options
            const res = await fetch(apiBase + '?action=auth_options');
            const data = await res.json();
            if (data.error) { showError(data.error); return; }

            const opts = data.options;
            opts.challenge = b64urlToBuffer(opts.challenge);
            if (opts.allowCredentials) {
                opts.allowCredentials = opts.allowCredentials.map(c => ({
                    ...c, id: b64urlToBuffer(c.id)
                }));
            }

            statusEl.textContent = 'Touch your authenticator or use biometrics...';

            // 2. Get credential from browser
            const credential = await navigator.credentials.get({ publicKey: opts });

            statusEl.textContent = 'Verifying with server...';

            // 3. Send to server
            const verifyRes = await fetch(apiBase + '?action=auth_verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credentialId:      bufferToB64url(credential.rawId),
                    clientDataJSON:    bufferToB64url(credential.response.clientDataJSON),
                    authenticatorData: bufferToB64url(credential.response.authenticatorData),
                    signature:         bufferToB64url(credential.response.signature),
                })
            });

            const result = await verifyRes.json();
            if (result.success) {
                statusEl.textContent = 'Verified! Redirecting...';
                statusEl.className = 'pk-status success';
                window.location.href = result.redirect;
            } else {
                showError(result.error || 'Verification failed');
                verifyBtn.disabled = false;
            }
        } catch (err) {
            if (err.name === 'NotAllowedError') {
                showError('Authentication was cancelled or timed out.');
            } else {
                showError('Passkey error: ' + err.message);
            }
            verifyBtn.disabled = false;
        }
    }

    // Init
    (function() {
        if (!window.PublicKeyCredential) {
            document.getElementById('unsupported').style.display = 'block';
            statusEl.textContent = 'Passkeys are not supported in this browser.';
            statusEl.className = 'pk-status error';
            return;
        }

        verifyBtn.disabled = false;
        verifyBtn.onclick = startVerification;

        statusEl.textContent = 'Click the button or use your passkey when prompted';
        statusEl.className = 'pk-status';

        // Auto-trigger
        startVerification();
    })();
    </script>
    <?php endif; ?>
</body>
</html>
