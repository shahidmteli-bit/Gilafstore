<?php

require_once __DIR__ . '/db_connect.php';

require_once __DIR__ . '/security.php';



// Initialize secure session (B1-B8: hardened cookie flags, fingerprinting, timeout)

if (session_status() === PHP_SESSION_NONE) {

    secure_session_init();

}



function attempt_login(string $identifier, string $password, string $scope = 'user'): bool

{

    // D1: Rate limit login attempts (5 attempts per 5 min, block for 15 min)

    $endpoint = ($scope === 'admin') ? 'admin_login' : 'user_login';

    $rateCheck = rate_limit_check($endpoint, 5, 300, 900);

    if (!$rateCheck['allowed']) {

        return false;

    }



    $user = db_fetch('SELECT * FROM users WHERE email = ?', [$identifier]);



    if ($user && password_verify($password, $user['password'])) {

        $userData = [

            'id' => $user['id'],

            'name' => $user['name'],

            'email' => $user['email'],

            'is_admin' => (bool)($user['is_admin'] ?? 0),

        ];



        if ($scope === 'admin') {

            if (!empty($user['is_admin'])) {

                // E5: Check if MFA (TOTP, Passkey, or Email OTP) is enabled

                $hasTOTP = admin_has_2fa_enabled($user['id']);

                $hasPasskey = admin_has_passkey_enabled($user['id']);

                $hasEmailOtp = admin_has_email_otp_enabled($user['id']);



                if ($hasTOTP || $hasPasskey || $hasEmailOtp) {

                    // Store admin data in pending session for MFA verification

                    $_SESSION['_2fa_pending_admin'] = $userData;

                    $_SESSION['_2fa_pending_time'] = time();

                    // Priority: TOTP > Passkey > Email OTP

                    if ($hasTOTP) {

                        $_SESSION['_2fa_method'] = 'totp';

                    } elseif ($hasPasskey) {

                        $_SESSION['_2fa_method'] = 'passkey';

                    } else {

                        $_SESSION['_2fa_method'] = 'email_otp';

                    }

                    secure_session_regenerate();

                    return true; // Password verified, but MFA still needed

                }

                $_SESSION['admin'] = $userData;

                secure_session_regenerate(); // B4: Regenerate session on login

                security_log_successful_login($user['id'], $user['email'], 'admin');

                return true;

            }

            security_log_failed_login($identifier, 'admin'); // F1: Log failed admin login

            return false;

        } else {

            $_SESSION['user'] = $userData;

            secure_session_regenerate(); // B4: Regenerate session on login

            security_log_successful_login($user['id'], $user['email'], 'user');

            return true;

        }

    }



    security_log_failed_login($identifier, $scope); // F1: Log failed login

    return false;

}



function register_user(string $name, string $email, string $password): bool

{

    $existing = db_fetch('SELECT id FROM users WHERE email = ?', [$email]);

    if ($existing) {

        return false;

    }



    $hashed = password_hash($password, PASSWORD_DEFAULT);



    db_query('INSERT INTO users (name, email, password) VALUES (?, ?, ?)', [

        $name,

        $email,

        $hashed,

    ]);



    // Send welcome email

    try {

        require_once __DIR__ . '/order_emails.php';

        send_welcome_email($email, $name);

    } catch (Exception $e) {

        error_log("WARNING: Welcome email failed for $email - " . $e->getMessage());

    }



    return true;

}



function require_login(): void

{

    if (empty($_SESSION['user'])) {

        redirect_with_message('/user/login.php', 'Please log in to continue', 'danger');

    }

}



function is_logged_in(): bool

{

    return !empty($_SESSION['user']);

}



function is_admin(): bool

{

    return !empty($_SESSION['admin']) && !empty($_SESSION['admin']['is_admin']);

}



function require_admin(): void

{

    if (empty($_SESSION['admin']) || empty($_SESSION['admin']['is_admin'])) {

        // Do NOT reveal admin login URL — redirect to homepage
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>';
        echo '<h1>Not Found</h1><p>The requested URL was not found on this server.</p>';
        echo '</body></html>';
        exit;

    }

}



function logout_user(): void

{

    // Get user ID before clearing session

    $userId = $_SESSION['user']['id'] ?? null;

    

    // Clear user-specific cache

    require_once __DIR__ . '/auto_cache_clear.php';

    if ($userId) {

        clearCacheOnLogout($userId);

    }

    

    unset($_SESSION['user']);

}



function logout_admin(): void

{

    unset($_SESSION['admin']);

}



/**

 * Check if admin has TOTP 2FA enabled

 */

function admin_has_2fa_enabled(int $userId): bool

{

    try {

        $db = get_db_connection();

        $check = $db->query("SHOW COLUMNS FROM users LIKE 'totp_enabled'");

        if ($check->rowCount() === 0) return false;

        $stmt = $db->prepare("SELECT totp_enabled FROM users WHERE id = ? AND totp_enabled = 1");

        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;

    } catch (PDOException $e) {

        return false;

    }

}



/**

 * Check if admin has email OTP enabled

 */

function admin_has_email_otp_enabled(int $userId): bool

{

    $status = admin_email_otp_status($userId);

    return $status && !empty($status['enabled']) && !empty($status['security_email']);

}



/**

 * Check if admin has passkey (WebAuthn) enabled

 */

function admin_has_passkey_enabled(int $userId): bool

{

    try {

        $db = get_db_connection();

        $col = $db->query("SHOW COLUMNS FROM users LIKE 'passkey_enabled'");

        if ($col->rowCount() === 0) return false;

        $stmt = $db->prepare("SELECT passkey_enabled FROM users WHERE id = ? AND passkey_enabled = 1");

        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;

    } catch (PDOException $e) {

        return false;

    }

}



/**

 * Check which MFA method is pending for admin

 * @return string|false 'totp', 'passkey', 'email_otp', or false

 */

function admin_pending_mfa_method()

{

    if (empty($_SESSION['_2fa_pending_admin']) || empty($_SESSION['_2fa_pending_time'])) {

        return false;

    }

    // 5-minute timeout on pending verification

    if (time() - $_SESSION['_2fa_pending_time'] > 300) {

        unset($_SESSION['_2fa_pending_admin'], $_SESSION['_2fa_pending_time'], $_SESSION['_2fa_method']);

        return false;

    }

    return $_SESSION['_2fa_method'] ?? false;

}

