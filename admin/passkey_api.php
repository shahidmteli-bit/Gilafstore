<?php
/**
 * Passkey (WebAuthn) AJAX API
 * Handles registration + authentication ceremony endpoints
 * All responses are JSON
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/webauthn.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = get_db_connection();

// ─── Ensure DB schema ───
try {
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

    $col = $db->query("SHOW COLUMNS FROM users LIKE 'passkey_enabled'");
    if ($col->rowCount() === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN passkey_enabled TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    error_log("Passkey DB setup: " . $e->getMessage());
}

$webauthn = new SimpleWebAuthn();

switch ($action) {

    /* ════════════ Registration: get options ════════════ */
    case 'register_options':
        if (empty($_SESSION['admin'])) { json_err('Not authenticated'); }

        $aid   = $_SESSION['admin']['id'];
        $aName = $_SESSION['admin']['name'] ?? 'Admin';
        $aEmail = $_SESSION['admin']['email'] ?? '';

        // Exclude already-registered credential IDs
        $stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$aid]);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Optional: force cross-platform (phone/tablet) or platform (this device)
        $attachment = $_GET['attachment'] ?? '';
        $result = $webauthn->getRegistrationOptions($aid, $aEmail, $aName, $existing, $attachment);
        $_SESSION['_wn_reg_challenge'] = $result['challenge'];
        $_SESSION['_wn_reg_time'] = time();

        echo json_encode(['success' => true, 'options' => $result['options']]);
        break;

    /* ════════════ Registration: verify ════════════ */
    case 'register_verify':
        if (empty($_SESSION['admin'])) { json_err('Not authenticated'); }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { json_err('Invalid request body'); }

        $challenge = $_SESSION['_wn_reg_challenge'] ?? '';
        if (!$challenge || (time() - ($_SESSION['_wn_reg_time'] ?? 0)) > 120) {
            json_err('Challenge expired. Please try again.');
        }

        $cred = $webauthn->verifyRegistration(
            $input['clientDataJSON'] ?? '',
            $input['attestationObject'] ?? '',
            $challenge
        );
        if (!$cred) { json_err('Registration verification failed'); }

        $deviceName = trim($input['deviceName'] ?? 'Passkey');
        if ($deviceName === '') $deviceName = 'Passkey';

        $aid = $_SESSION['admin']['id'];
        $stmt = $db->prepare(
            "INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count, device_name) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$aid, $cred['credentialId'], $cred['publicKeyPem'], $cred['signCount'], htmlspecialchars($deviceName)]);

        $db->prepare("UPDATE users SET passkey_enabled = 1 WHERE id = ?")->execute([$aid]);

        unset($_SESSION['_wn_reg_challenge'], $_SESSION['_wn_reg_time']);
        security_log('PASSKEY_REGISTERED', 'INFO', "Admin registered passkey: {$deviceName}");

        echo json_encode(['success' => true, 'message' => 'Passkey registered successfully']);
        break;

    /* ════════════ Remove a passkey ════════════ */
    case 'remove':
        if (empty($_SESSION['admin'])) { json_err('Not authenticated'); }

        $input = json_decode(file_get_contents('php://input'), true);
        $rowId = (int)($input['row_id'] ?? 0);
        if (!$rowId) { json_err('Missing credential row ID'); }

        $aid = $_SESSION['admin']['id'];
        $db->prepare("DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?")
           ->execute([$rowId, $aid]);

        // If none left, disable
        $cnt = $db->prepare("SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ?");
        $cnt->execute([$aid]);
        if ((int)$cnt->fetchColumn() === 0) {
            $db->prepare("UPDATE users SET passkey_enabled = 0 WHERE id = ?")->execute([$aid]);
        }

        security_log('PASSKEY_REMOVED', 'INFO', "Admin removed passkey row {$rowId}");
        echo json_encode(['success' => true]);
        break;

    /* ════════════ Authentication: get options ════════════ */
    case 'auth_options':
        if (empty($_SESSION['_2fa_pending_admin'])) { json_err('No pending authentication'); }

        $aid  = $_SESSION['_2fa_pending_admin']['id'];
        $stmt = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$aid]);
        $cids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$cids) { json_err('No passkeys registered'); }

        $result = $webauthn->getAuthenticationOptions($cids);
        $_SESSION['_wn_auth_challenge'] = $result['challenge'];
        $_SESSION['_wn_auth_time'] = time();

        echo json_encode(['success' => true, 'options' => $result['options']]);
        break;

    /* ════════════ Authentication: verify ════════════ */
    case 'auth_verify':
        if (empty($_SESSION['_2fa_pending_admin'])) { json_err('No pending authentication'); }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { json_err('Invalid request body'); }

        $challenge = $_SESSION['_wn_auth_challenge'] ?? '';
        if (!$challenge || (time() - ($_SESSION['_wn_auth_time'] ?? 0)) > 120) {
            json_err('Challenge expired');
        }

        $credId = $input['credentialId'] ?? '';
        $aid    = $_SESSION['_2fa_pending_admin']['id'];

        $stmt = $db->prepare("SELECT * FROM webauthn_credentials WHERE credential_id = ? AND user_id = ?");
        $stmt->execute([$credId, $aid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { json_err('Unknown credential'); }

        $result = $webauthn->verifyAuthentication(
            $credId,
            $input['clientDataJSON'] ?? '',
            $input['authenticatorData'] ?? '',
            $input['signature'] ?? '',
            $challenge,
            $row['public_key'],
            (int)$row['sign_count']
        );

        if (!$result) {
            security_log('PASSKEY_AUTH_FAILED', 'WARNING', "Failed passkey auth for admin ID {$aid}");
            json_err('Passkey verification failed');
        }

        // Update sign count + last used
        $db->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?")
           ->execute([$result['newSignCount'], $row['id']]);

        // Complete login
        $pending = $_SESSION['_2fa_pending_admin'];
        $_SESSION['admin'] = $pending;
        unset(
            $_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method'],
            $_SESSION['_wn_auth_challenge'], $_SESSION['_wn_auth_time']
        );
        secure_session_regenerate();
        security_log_successful_login($pending['id'], $pending['email'], 'admin');
        security_log('PASSKEY_VERIFIED', 'INFO', "Admin {$pending['email']} passed Passkey verification");

        echo json_encode(['success' => true, 'redirect' => base_url('admin/index.php')]);
        break;

    /* ════════════ Passwordless Login: check if any passkeys exist ════════════ */
    case 'check_available':
        try {
            $count = $db->query("SELECT COUNT(*) FROM webauthn_credentials")->fetchColumn();
            echo json_encode(['available' => (int)$count > 0]);
        } catch (PDOException $e) {
            echo json_encode(['available' => false]);
        }
        break;

    /* ════════════ Passwordless Login: get options (no session needed) ════════════ */
    case 'login_options':
        // For passwordless login — no pending session required
        // We allow any registered credential (discoverable / resident key)
        try {
            $allCreds = $db->query("SELECT wc.credential_id, wc.user_id FROM webauthn_credentials wc INNER JOIN users u ON wc.user_id = u.id WHERE u.is_admin = 1")->fetchAll(PDO::FETCH_ASSOC);
            if (!$allCreds) { json_err('No passkeys registered. Please log in with password first and register a passkey in Security Center.'); }

            $cids = array_column($allCreds, 'credential_id');
            $result = $webauthn->getAuthenticationOptions($cids);
            $_SESSION['_wn_login_challenge'] = $result['challenge'];
            $_SESSION['_wn_login_time'] = time();

            echo json_encode(['success' => true, 'options' => $result['options']]);
        } catch (PDOException $e) {
            json_err('Passkey system unavailable');
        }
        break;

    /* ════════════ Passwordless Login: verify and complete login ════════════ */
    case 'login_verify':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { json_err('Invalid request body'); }

        $challenge = $_SESSION['_wn_login_challenge'] ?? '';
        if (!$challenge || (time() - ($_SESSION['_wn_login_time'] ?? 0)) > 120) {
            json_err('Challenge expired. Please try again.');
        }

        $credId = $input['credentialId'] ?? '';

        // Find the credential and its owner
        $stmt = $db->prepare("SELECT wc.*, u.id AS uid, u.name, u.email, u.is_admin FROM webauthn_credentials wc INNER JOIN users u ON wc.user_id = u.id WHERE wc.credential_id = ? AND u.is_admin = 1");
        $stmt->execute([$credId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { json_err('Unknown credential. The passkey may have been removed.'); }

        $result = $webauthn->verifyAuthentication(
            $credId,
            $input['clientDataJSON'] ?? '',
            $input['authenticatorData'] ?? '',
            $input['signature'] ?? '',
            $challenge,
            $row['public_key'],
            (int)$row['sign_count']
        );

        if (!$result) {
            security_log('PASSKEY_LOGIN_FAILED', 'WARNING', "Failed passwordless passkey login for admin ID {$row['uid']}");
            json_err('Passkey verification failed. Please try again.');
        }

        // Update sign count + last used
        $db->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?")
           ->execute([$result['newSignCount'], $row['id']]);

        // Complete login directly — no MFA needed (passkey IS the strong auth)
        $_SESSION['admin'] = [
            'id' => $row['uid'],
            'name' => $row['name'],
            'email' => $row['email'],
            'is_admin' => $row['is_admin'],
        ];
        unset($_SESSION['_wn_login_challenge'], $_SESSION['_wn_login_time']);
        secure_session_regenerate();
        security_log_successful_login($row['uid'], $row['email'], 'admin');
        security_log('PASSKEY_PASSWORDLESS_LOGIN', 'INFO', "Admin {$row['email']} logged in via passkey (passwordless)");

        echo json_encode(['success' => true, 'redirect' => base_url('admin/index.php')]);
        break;

    default:
        json_err('Unknown action');
}

function json_err(string $msg): never
{
    echo json_encode(['error' => $msg]);
    exit;
}
