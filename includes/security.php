<?php
/**
 * Gilaf E-Commerce Security Module
 * ================================
 * Handles: Rate Limiting, Session Hardening, Security Monitoring,
 *          Bot Protection, Cookie Encryption, PII Encryption
 * 
 * Zero layout impact — all backend logic only.
 */

require_once __DIR__ . '/db_connect.php';

// ============================================================
// SECTION 0: PRODUCTION ERROR DISPLAY CONTROL (Category A6)
// ============================================================
// On production server, suppress error display but keep logging
$isProduction = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'gilafstore.com') !== false);
if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// ============================================================
// SECTION 1: SECURITY DATABASE SETUP (auto-creates tables)
// ============================================================

function security_ensure_tables(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $db = get_db_connection();

        // Rate limiting table
        $db->exec("CREATE TABLE IF NOT EXISTS security_rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt DATETIME NOT NULL,
            last_attempt DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            INDEX idx_ip_endpoint (ip_address, endpoint),
            INDEX idx_blocked (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Security audit log table
        $db->exec("CREATE TABLE IF NOT EXISTS security_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            severity ENUM('INFO','WARNING','CRITICAL') DEFAULT 'INFO',
            ip_address VARCHAR(45),
            user_id INT NULL,
            user_email VARCHAR(255) NULL,
            description TEXT,
            metadata JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_severity (severity),
            INDEX idx_created (created_at),
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // File integrity hashes table
        $db->exec("CREATE TABLE IF NOT EXISTS security_file_hashes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_path VARCHAR(500) NOT NULL UNIQUE,
            file_hash VARCHAR(64) NOT NULL,
            file_size INT NOT NULL,
            last_checked DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_changed DATETIME NULL,
            INDEX idx_path (file_path(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Password reset tokens table
        $db->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token_hash),
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    } catch (PDOException $e) {
        error_log("Security tables setup error: " . $e->getMessage());
    }
}

// ============================================================
// SECTION 2: RATE LIMITING (Categories D1-D5)
// ============================================================

/**
 * Check if a request should be rate-limited
 * @param string $endpoint Identifier for the endpoint (e.g., 'login', 'otp', 'order', 'search', 'payment')
 * @param int $maxAttempts Maximum attempts allowed in the window
 * @param int $windowSeconds Time window in seconds
 * @param int $blockSeconds How long to block after exceeding limit
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
 */
function rate_limit_check(string $endpoint, int $maxAttempts = 10, int $windowSeconds = 60, int $blockSeconds = 900): array
{
    security_ensure_tables();
    $ip = get_client_ip();

    try {
        $db = get_db_connection();

        // Check if currently blocked
        $stmt = $db->prepare("SELECT blocked_until FROM security_rate_limits 
                              WHERE ip_address = :ip AND endpoint = :ep AND blocked_until > NOW() 
                              LIMIT 1");
        $stmt->execute([':ip' => $ip, ':ep' => $endpoint]);
        $blocked = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($blocked) {
            $retryAfter = max(0, strtotime($blocked['blocked_until']) - time());
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
        }

        // Count attempts in current window
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM security_rate_limits 
                              WHERE ip_address = :ip AND endpoint = :ep AND last_attempt > :ws");
        $stmt->execute([':ip' => $ip, ':ep' => $endpoint, ':ws' => $windowStart]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= $maxAttempts) {
            // Block this IP for this endpoint
            $blockedUntil = date('Y-m-d H:i:s', time() + $blockSeconds);
            $stmt = $db->prepare("UPDATE security_rate_limits SET blocked_until = :bu 
                                  WHERE ip_address = :ip AND endpoint = :ep");
            $stmt->execute([':bu' => $blockedUntil, ':ip' => $ip, ':ep' => $endpoint]);

            // Log the block event
            security_log('RATE_LIMIT_BLOCKED', 'WARNING', "IP blocked on endpoint: {$endpoint} after {$maxAttempts} attempts", [
                'endpoint' => $endpoint, 'attempts' => $maxAttempts
            ]);

            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $blockSeconds];
        }

        // Record this attempt
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO security_rate_limits (ip_address, endpoint, attempts, first_attempt, last_attempt) 
                              VALUES (:ip, :ep, 1, :now, :now)");
        $stmt->execute([':ip' => $ip, ':ep' => $endpoint, ':now' => $now]);

        $remaining = max(0, $maxAttempts - $count - 1);
        return ['allowed' => true, 'remaining' => $remaining, 'retry_after' => null];

    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => null]; // Fail open
    }
}

/**
 * Enforce rate limit — returns error response if blocked
 * @param string $endpoint
 * @param int $maxAttempts
 * @param int $windowSeconds
 * @param int $blockSeconds
 * @return bool True if allowed, false if blocked (sends 429 response)
 */
function rate_limit_enforce(string $endpoint, int $maxAttempts = 10, int $windowSeconds = 60, int $blockSeconds = 900): bool
{
    $result = rate_limit_check($endpoint, $maxAttempts, $windowSeconds, $blockSeconds);

    if (!$result['allowed']) {
        http_response_code(429);
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['retry_after']
            ]);
        } else {
            // Set flash message and don't die — let the page handle it
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['message'] = 'Too many attempts. Please wait ' . ceil($result['retry_after'] / 60) . ' minutes before trying again.';
                $_SESSION['message_type'] = 'error';
            }
        }
        return false;
    }

    return true;
}

/**
 * Clean up old rate limit records (call via cron or periodically)
 */
function rate_limit_cleanup(): void
{
    try {
        $db = get_db_connection();
        // Delete records older than 24 hours
        $db->exec("DELETE FROM security_rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (PDOException $e) {
        error_log("Rate limit cleanup error: " . $e->getMessage());
    }
}

// ============================================================
// SECTION 3: SESSION HARDENING (Categories B1-B8)
// ============================================================

/**
 * Initialize secure session with hardened settings
 * Call this BEFORE session_start() or at the very beginning
 */
function secure_session_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Session already started
    }

    // Set secure session cookie parameters
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $cookieParams = [
        'lifetime' => 0,                    // Session cookie (expires when browser closes)
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,               // B2: HTTPS only
        'httponly' => true,                  // B1: No JS access
        'samesite' => 'Lax'                 // B3: CSRF protection (Lax allows normal navigation)
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; SameSite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    // Use strong session ID hash
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();

    // B4: Regenerate session ID periodically (every 30 min)
    if (!isset($_SESSION['_security_created'])) {
        $_SESSION['_security_created'] = time();
        $_SESSION['_security_fingerprint'] = generate_session_fingerprint();
    } elseif (time() - $_SESSION['_security_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_security_created'] = time();
        $_SESSION['_security_fingerprint'] = generate_session_fingerprint();
    }

    // B5: Session timeout (30 min idle for users, 20 min for admin)
    $maxIdle = isset($_SESSION['admin']) ? 1200 : 1800; // B8: shorter for admin
    if (isset($_SESSION['_security_last_activity']) && (time() - $_SESSION['_security_last_activity'] > $maxIdle)) {
        // Session expired
        $wasAdmin = isset($_SESSION['admin']);
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['message'] = 'Your session has expired. Please log in again.';
        $_SESSION['message_type'] = 'warning';
        $_SESSION['_security_created'] = time();
        $_SESSION['_security_fingerprint'] = generate_session_fingerprint();
        return;
    }
    $_SESSION['_security_last_activity'] = time();

    // B6: Session fingerprint validation (IP + User-Agent)
    if (isset($_SESSION['_security_fingerprint'])) {
        $currentFingerprint = generate_session_fingerprint();
        if (!hash_equals($_SESSION['_security_fingerprint'], $currentFingerprint)) {
            // Possible session hijacking
            security_log('SESSION_HIJACK_ATTEMPT', 'CRITICAL', 'Session fingerprint mismatch detected', [
                'expected' => substr($_SESSION['_security_fingerprint'], 0, 16) . '...',
                'got' => substr($currentFingerprint, 0, 16) . '...'
            ]);
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['_security_created'] = time();
            $_SESSION['_security_fingerprint'] = generate_session_fingerprint();
        }
    }
}

/**
 * Generate a fingerprint for session binding
 * Uses User-Agent + partial IP (first 3 octets for IPv4 tolerance)
 */
function generate_session_fingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = get_client_ip();

    // Use first 3 octets of IP (allows for minor IP changes within subnet)
    $ipParts = explode('.', $ip);
    $partialIp = count($ipParts) >= 3 ? implode('.', array_slice($ipParts, 0, 3)) : $ip;

    return hash('sha256', $ua . '|' . $partialIp . '|' . 'gilaf_security_salt_2026');
}

/**
 * Regenerate session on login (B4)
 */
function secure_session_regenerate(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['_security_created'] = time();
        $_SESSION['_security_last_activity'] = time();
        $_SESSION['_security_fingerprint'] = generate_session_fingerprint();
    }
}

// ============================================================
// SECTION 4: COOKIE ENCRYPTION (Category B7)
// ============================================================

/**
 * Set an encrypted cookie
 */
function set_encrypted_cookie(string $name, string $value, int $expiry = 0, string $path = '/'): bool
{
    $key = get_encryption_key();
    $encrypted = encrypt_data($value, $key);
    if ($encrypted === false) return false;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return setcookie($name, $encrypted, [
        'expires' => $expiry,
        'path' => $path,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Get and decrypt a cookie value
 */
function get_encrypted_cookie(string $name): ?string
{
    if (!isset($_COOKIE[$name])) return null;

    $key = get_encryption_key();
    $decrypted = decrypt_data($_COOKIE[$name], $key);
    return $decrypted !== false ? $decrypted : null;
}

// ============================================================
// SECTION 5: PII ENCRYPTION (Category G4)
// ============================================================

/**
 * Encrypt sensitive data (phone numbers, addresses)
 */
function encrypt_data(string $data, ?string $key = null): string|false
{
    $key = $key ?? get_encryption_key();
    $cipher = 'aes-256-gcm';
    $iv = random_bytes(openssl_cipher_iv_length($cipher));
    $tag = '';

    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($encrypted === false) return false;

    // Pack: IV + Tag + Encrypted data, then base64
    return base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decrypt_data(string $encoded, ?string $key = null): string|false
{
    $key = $key ?? get_encryption_key();
    $cipher = 'aes-256-gcm';
    $ivLen = openssl_cipher_iv_length($cipher);
    $tagLen = 16; // GCM tag is always 16 bytes

    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < ($ivLen + $tagLen + 1)) return false;

    $iv = substr($raw, 0, $ivLen);
    $tag = substr($raw, $ivLen, $tagLen);
    $encrypted = substr($raw, $ivLen + $tagLen);

    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
}

/**
 * Get encryption key from environment or generate one
 */
function get_encryption_key(): string
{
    static $key = null;
    if ($key !== null) return $key;

    // Try to load from a key file outside web root
    $keyFile = dirname(__DIR__) . '/.security_key';
    if (!file_exists($keyFile)) {
        // Also try one level higher (outside htdocs)
        $keyFile = dirname(dirname(__DIR__)) . '/.gilaf_security_key';
    }

    if (file_exists($keyFile)) {
        $key = trim(file_get_contents($keyFile));
        return $key;
    }

    // Fallback: generate and save key
    $key = bin2hex(random_bytes(32));
    $saveFile = dirname(__DIR__) . '/.security_key';
    @file_put_contents($saveFile, $key);
    @chmod($saveFile, 0600);

    return $key;
}

// ============================================================
// SECTION 6: SECURITY MONITORING & AUDIT LOG (Categories F1-F5)
// ============================================================

/**
 * Log a security event
 */
function security_log(string $eventType, string $severity, string $description, array $metadata = []): void
{
    security_ensure_tables();

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO security_audit_log 
            (event_type, severity, ip_address, user_id, user_email, description, metadata) 
            VALUES (:et, :sev, :ip, :uid, :uemail, :desc, :meta)");

        $stmt->execute([
            ':et' => $eventType,
            ':sev' => $severity,
            ':ip' => get_client_ip(),
            ':uid' => $_SESSION['admin']['id'] ?? $_SESSION['user']['id'] ?? null,
            ':uemail' => $_SESSION['admin']['email'] ?? $_SESSION['user']['email'] ?? null,
            ':desc' => $description,
            ':meta' => !empty($metadata) ? json_encode($metadata) : null
        ]);
    } catch (PDOException $e) {
        // Fallback to file log
        error_log("[SECURITY][{$severity}][{$eventType}] {$description} | " . json_encode($metadata));
    }
}

/**
 * Log failed login attempt (F1)
 */
function security_log_failed_login(string $identifier, string $scope = 'user'): void
{
    security_log('LOGIN_FAILED', 'WARNING', "Failed {$scope} login attempt for: {$identifier}", [
        'scope' => $scope,
        'identifier' => $identifier,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    // Check if we need to send alert (5+ failures from same IP in 10 min)
    check_and_alert_brute_force();
}

/**
 * Log successful login
 */
function security_log_successful_login(int $userId, string $email, string $scope = 'user'): void
{
    security_log('LOGIN_SUCCESS', 'INFO', "Successful {$scope} login: {$email}", [
        'scope' => $scope,
        'user_id' => $userId
    ]);

    // Check for admin login from new IP (F5)
    if ($scope === 'admin') {
        check_admin_new_ip_login($userId, $email);
    }
}

/**
 * Log admin action (F2)
 */
function security_log_admin_action(string $action, string $description, array $details = []): void
{
    security_log('ADMIN_ACTION', 'INFO', "[Admin] {$action}: {$description}", $details);
}

/**
 * Check for brute force and send alert (F5)
 */
function check_and_alert_brute_force(): void
{
    try {
        $db = get_db_connection();
        $ip = get_client_ip();

        $stmt = $db->prepare("SELECT COUNT(*) FROM security_audit_log 
                              WHERE event_type = 'LOGIN_FAILED' AND ip_address = :ip 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $stmt->execute([':ip' => $ip]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= 5) {
            // Check if we already sent an alert for this IP recently
            $stmt = $db->prepare("SELECT COUNT(*) FROM security_audit_log 
                                  WHERE event_type = 'BRUTE_FORCE_ALERT' AND ip_address = :ip 
                                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([':ip' => $ip]);
            $alertSent = (int)$stmt->fetchColumn();

            if ($alertSent === 0) {
                security_log('BRUTE_FORCE_ALERT', 'CRITICAL', "Brute force detected: {$count} failed logins from IP {$ip} in 10 minutes", [
                    'failed_count' => $count
                ]);
                // Email alert would go here — using existing email infrastructure
                send_security_alert_email("Brute Force Alert", "IP {$ip} has made {$count} failed login attempts in the last 10 minutes.");
            }
        }
    } catch (PDOException $e) {
        error_log("Brute force check error: " . $e->getMessage());
    }
}

/**
 * Check if admin is logging in from a new IP (F5)
 */
function check_admin_new_ip_login(int $userId, string $email): void
{
    try {
        $db = get_db_connection();
        $ip = get_client_ip();

        $stmt = $db->prepare("SELECT COUNT(*) FROM security_audit_log 
                              WHERE event_type = 'LOGIN_SUCCESS' AND user_id = :uid 
                              AND ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute([':uid' => $userId, ':ip' => $ip]);
        $knownIp = (int)$stmt->fetchColumn();

        if ($knownIp === 0) {
            security_log('ADMIN_NEW_IP_LOGIN', 'WARNING', "Admin {$email} logged in from new IP: {$ip}", [
                'admin_id' => $userId
            ]);
            send_security_alert_email("Admin Login from New IP", "Admin {$email} logged in from a new IP address: {$ip}. If this wasn't you, please change your password immediately.");
        }
    } catch (PDOException $e) {
        error_log("Admin new IP check error: " . $e->getMessage());
    }
}

/**
 * Send security alert email (F5)
 */
function send_security_alert_email(string $subject, string $message): void
{
    try {
        // Use existing email infrastructure if available
        if (function_exists('get_db_connection')) {
            $db = get_db_connection();
            // Get admin email from settings or use first admin user
            $stmt = $db->query("SELECT email FROM users WHERE is_admin = 1 LIMIT 1");
            $adminEmail = $stmt->fetchColumn();

            if ($adminEmail) {
                $fullSubject = "[Gilaf Security] {$subject}";
                $fullMessage = "Security Alert\n" .
                    "==============\n\n" .
                    "Time: " . date('Y-m-d H:i:s') . "\n" .
                    "Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'unknown') . "\n\n" .
                    $message . "\n\n" .
                    "— Gilaf Security System";

                @mail($adminEmail, $fullSubject, $fullMessage, "From: security@gilafstore.com\r\nContent-Type: text/plain; charset=UTF-8");
            }
        }
    } catch (Exception $e) {
        error_log("Security alert email failed: " . $e->getMessage());
    }
}

// ============================================================
// SECTION 7: FILE INTEGRITY MONITORING (Category F3)
// ============================================================

/**
 * Calculate and store file hashes for critical files
 * Run this once to establish baseline, then periodically to check
 */
function file_integrity_baseline(array $files): void
{
    security_ensure_tables();

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("INSERT INTO security_file_hashes (file_path, file_hash, file_size, last_checked) 
                              VALUES (:fp, :fh, :fs, NOW()) 
                              ON DUPLICATE KEY UPDATE file_hash = :fh2, file_size = :fs2, last_checked = NOW()");

        foreach ($files as $file) {
            if (file_exists($file)) {
                $hash = hash_file('sha256', $file);
                $size = filesize($file);
                $stmt->execute([
                    ':fp' => $file, ':fh' => $hash, ':fs' => $size,
                    ':fh2' => $hash, ':fs2' => $size
                ]);
            }
        }
    } catch (PDOException $e) {
        error_log("File integrity baseline error: " . $e->getMessage());
    }
}

/**
 * Check file integrity against stored hashes (F3)
 * Returns array of changed files
 */
function file_integrity_check(): array
{
    security_ensure_tables();
    $changes = [];

    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT file_path, file_hash, file_size FROM security_file_hashes");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $file = $row['file_path'];
            if (!file_exists($file)) {
                $changes[] = ['file' => $file, 'status' => 'DELETED'];
                continue;
            }

            $currentHash = hash_file('sha256', $file);
            if ($currentHash !== $row['file_hash']) {
                $changes[] = [
                    'file' => $file,
                    'status' => 'MODIFIED',
                    'old_hash' => substr($row['file_hash'], 0, 12),
                    'new_hash' => substr($currentHash, 0, 12),
                    'old_size' => $row['file_size'],
                    'new_size' => filesize($file)
                ];
            }
        }

        if (!empty($changes)) {
            security_log('FILE_INTEGRITY_VIOLATION', 'CRITICAL', count($changes) . ' file(s) changed since last baseline', [
                'files' => array_column($changes, 'file')
            ]);
            send_security_alert_email("File Integrity Alert", "The following files have been modified:\n\n" . implode("\n", array_map(function ($c) {
                return "- {$c['file']} ({$c['status']})";
            }, $changes)));
        }

    } catch (PDOException $e) {
        error_log("File integrity check error: " . $e->getMessage());
    }

    return $changes;
}

// ============================================================
// SECTION 8: BOT PROTECTION (Categories D6-D8)
// ============================================================

/**
 * Basic bot detection via User-Agent and request pattern analysis (D8)
 * Returns true if request appears to be from a bot
 */
function is_suspicious_bot(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // No User-Agent = suspicious
    if (empty($ua) || strlen($ua) < 10) {
        return true;
    }

    // Known bad bot patterns
    $badBots = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'nuclei',
        'gobuster', 'dirbuster', 'wfuzz', 'hydra', 'medusa',
        'scrapy', 'python-requests', 'curl/', 'wget/',
        'ahrefsbot', 'semrushbot', 'dotbot', 'mj12bot',
        'bytespider', 'petalbot',
    ];

    $uaLower = strtolower($ua);
    foreach ($badBots as $bot) {
        if (strpos($uaLower, $bot) !== false) {
            security_log('BOT_DETECTED', 'WARNING', "Suspicious bot detected: {$ua}", ['bot_pattern' => $bot]);
            return true;
        }
    }

    return false;
}

/**
 * Enforce bot protection on sensitive endpoints
 */
function bot_protection_enforce(): bool
{
    if (is_suspicious_bot()) {
        http_response_code(403);
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
        }
        return false;
    }
    return true;
}

// ============================================================
// SECTION 9: SECURE PASSWORD RESET (Category E3)
// ============================================================

/**
 * Generate a secure password reset token
 * @param int $userId
 * @return string The raw token to send via email
 */
function generate_password_reset_token(int $userId): string
{
    security_ensure_tables();

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

    try {
        $db = get_db_connection();

        // Invalidate any existing tokens for this user
        $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = :uid AND used = 0");
        $stmt->execute([':uid' => $userId]);

        // Insert new token
        $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :th, :ea)");
        $stmt->execute([':uid' => $userId, ':th' => $tokenHash, ':ea' => $expiresAt]);

        security_log('PASSWORD_RESET_REQUESTED', 'INFO', "Password reset token generated for user ID: {$userId}", [
            'user_id' => $userId
        ]);

    } catch (PDOException $e) {
        error_log("Password reset token error: " . $e->getMessage());
    }

    return $rawToken;
}

/**
 * Validate a password reset token
 * @param string $rawToken The token from the email link
 * @return array|false User data if valid, false otherwise
 */
function validate_password_reset_token(string $rawToken): array|false
{
    security_ensure_tables();

    $tokenHash = hash('sha256', $rawToken);

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT prt.*, u.email, u.name FROM password_reset_tokens prt 
                              JOIN users u ON u.id = prt.user_id
                              WHERE prt.token_hash = :th AND prt.used = 0 AND prt.expires_at > NOW()
                              LIMIT 1");
        $stmt->execute([':th' => $tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            security_log('PASSWORD_RESET_INVALID', 'WARNING', 'Invalid or expired password reset token used');
            return false;
        }

        return $result;

    } catch (PDOException $e) {
        error_log("Password reset token validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark a password reset token as used (single-use)
 */
function consume_password_reset_token(string $rawToken): void
{
    $tokenHash = hash('sha256', $rawToken);

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token_hash = :th");
        $stmt->execute([':th' => $tokenHash]);
    } catch (PDOException $e) {
        error_log("Password reset token consume error: " . $e->getMessage());
    }
}

// ============================================================
// SECTION 10: DATABASE ANOMALY DETECTION (Category F4)
// ============================================================

/**
 * Check for unusual database activity
 * Call periodically (e.g., via cron)
 */
function check_database_anomalies(): array
{
    $anomalies = [];

    try {
        $db = get_db_connection();

        // Check for mass deletions in the last hour
        $tables = ['products', 'orders', 'users', 'batch_codes'];
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
                $count = (int)$stmt->fetchColumn();

                // Store count for trend comparison
                $key = "db_count_{$table}";
                $lastCount = $_SESSION["_security_{$key}"] ?? null;

                if ($lastCount !== null && $count < ($lastCount * 0.8)) {
                    // More than 20% reduction
                    $anomalies[] = [
                        'table' => $table,
                        'previous_count' => $lastCount,
                        'current_count' => $count,
                        'reduction_percent' => round((($lastCount - $count) / $lastCount) * 100, 1)
                    ];
                }

                $_SESSION["_security_{$key}"] = $count;
            } catch (PDOException $e) {
                // Table might not exist, skip
            }
        }

        if (!empty($anomalies)) {
            security_log('DATABASE_ANOMALY', 'CRITICAL', 'Unusual data reduction detected', ['anomalies' => $anomalies]);
            $msg = "Database anomaly detected:\n\n";
            foreach ($anomalies as $a) {
                $msg .= "- Table '{$a['table']}': reduced from {$a['previous_count']} to {$a['current_count']} ({$a['reduction_percent']}% drop)\n";
            }
            send_security_alert_email("Database Anomaly Alert", $msg);
        }

    } catch (PDOException $e) {
        error_log("Database anomaly check error: " . $e->getMessage());
    }

    return $anomalies;
}

// ============================================================
// SECTION 11: INPUT SANITIZATION HELPERS (Category C)
// ============================================================

/**
 * Sanitize output for HTML context (XSS prevention) — C2
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate and sanitize integer input
 */
function safe_int($value, int $default = 0): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? $filtered : $default;
}

/**
 * Validate and sanitize string input with length limit
 */
function safe_string($value, int $maxLength = 255): string
{
    if (!is_string($value)) return '';
    $value = trim($value);
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

// ============================================================
// SECTION 12: UTILITY FUNCTIONS
// ============================================================

/**
 * Get the real client IP address
 */
function get_client_ip(): string
{
    // Check for proxy headers (in order of trust)
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // X-Forwarded-For can contain multiple IPs, take the first
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if current request is AJAX
 */
function is_ajax_request(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

// ============================================================
// SECTION 13: UPLOAD SECURITY (Category H2)
// ============================================================

/**
 * Validate a file upload securely
 * @param array $file $_FILES['field'] array
 * @param array $allowedMimes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes (default 5MB)
 * @return array ['valid' => bool, 'error' => string|null, 'safe_name' => string|null]
 */
function validate_file_upload(array $file, array $allowedMimes = [], int $maxSize = 5242880): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'Invalid upload', 'safe_name' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error code: ' . $file['error'], 'safe_name' => null];
    }

    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File too large. Max: ' . round($maxSize / 1048576, 1) . 'MB', 'safe_name' => null];
    }

    // Server-side MIME type check using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!empty($allowedMimes) && !in_array($mime, $allowedMimes)) {
        return ['valid' => false, 'error' => 'File type not allowed: ' . $mime, 'safe_name' => null];
    }

    // Check for PHP in file content (prevent PHP injection via images)
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if (preg_match('/<\?php|<\?=|<script/i', $content)) {
        security_log('MALICIOUS_UPLOAD', 'CRITICAL', 'PHP code detected in uploaded file', [
            'original_name' => $file['name'], 'mime' => $mime
        ]);
        return ['valid' => false, 'error' => 'File contains suspicious content', 'safe_name' => null];
    }

    // Generate safe filename (random hash + original extension)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
    if (!in_array($ext, $safeExts)) {
        $ext = 'bin';
    }
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;

    return ['valid' => true, 'error' => null, 'safe_name' => $safeName, 'mime' => $mime];
}

// ============================================================
// SECTION 14: SECURITY DASHBOARD HELPERS
// ============================================================

/**
 * Get security statistics for admin dashboard
 */
function get_security_stats(): array
{
    security_ensure_tables();

    try {
        $db = get_db_connection();

        // Failed logins in last 24h
        $stmt = $db->query("SELECT COUNT(*) FROM security_audit_log WHERE event_type = 'LOGIN_FAILED' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $failedLogins24h = (int)$stmt->fetchColumn();

        // Blocked IPs currently
        $stmt = $db->query("SELECT COUNT(DISTINCT ip_address) FROM security_rate_limits WHERE blocked_until > NOW()");
        $blockedIps = (int)$stmt->fetchColumn();

        // Critical events in last 24h
        $stmt = $db->query("SELECT COUNT(*) FROM security_audit_log WHERE severity = 'CRITICAL' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $criticalEvents = (int)$stmt->fetchColumn();

        // Recent security events
        $stmt = $db->query("SELECT * FROM security_audit_log ORDER BY created_at DESC LIMIT 20");
        $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'failed_logins_24h' => $failedLogins24h,
            'blocked_ips' => $blockedIps,
            'critical_events_24h' => $criticalEvents,
            'recent_events' => $recentEvents
        ];

    } catch (PDOException $e) {
        error_log("Security stats error: " . $e->getMessage());
        return ['failed_logins_24h' => 0, 'blocked_ips' => 0, 'critical_events_24h' => 0, 'recent_events' => []];
    }
}

// ============================================================
// SECTION 12: EMAIL OTP FOR ADMIN LOGIN
// ============================================================

/**
 * Generate a 6-digit email OTP and store in session
 * @return string The generated OTP code
 */
function generate_email_otp(): string
{
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['_email_otp_code'] = $otp;
    $_SESSION['_email_otp_time'] = time();
    $_SESSION['_email_otp_attempts'] = 0;
    return $otp;
}

/**
 * Send OTP code via email
 * @param string $email Recipient email
 * @param string $otp The OTP code
 * @return bool Success
 */
function send_email_otp(string $email, string $otp): bool
{
    require_once __DIR__ . '/email_helper.php';

    $subject = 'Gilaf Admin - Security Login Code';
    $body = '
    <div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#1A3C34,rgba(26,60,52,0.9));padding:30px;text-align:center;">
            <h2 style="color:#fff;margin:0 0 8px;font-size:20px;">Gilaf Store Admin</h2>
            <p style="color:rgba(255,255,255,0.8);margin:0;font-size:13px;">Security Verification Code</p>
        </div>
        <div style="padding:32px;text-align:center;">
            <p style="color:#374151;font-size:15px;margin:0 0 20px;">Your one-time verification code is:</p>
            <div style="background:#f3f4f6;border:2px dashed #C5A059;border-radius:12px;padding:20px;margin:0 auto 20px;display:inline-block;">
                <span style="font-family:Courier New,monospace;font-size:36px;font-weight:700;color:#1A3C34;letter-spacing:10px;">' . htmlspecialchars($otp) . '</span>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 6px;">This code expires in <strong>5 minutes</strong>.</p>
            <p style="color:#9ca3af;font-size:12px;margin:0;">If you did not request this code, please secure your account immediately.</p>
        </div>
        <div style="background:#f9fafb;padding:16px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af;font-size:11px;margin:0;">&copy; Gilaf Store — Automated Security Alert</p>
        </div>
    </div>';

    $result = send_task_email('admin_otp', $email, $subject, $body);

    if ($result) {
        security_log('EMAIL_OTP_SENT', 'INFO', "Email OTP sent to {$email}");
    } else {
        security_log('EMAIL_OTP_FAILED', 'WARNING', "Failed to send email OTP to {$email}");
    }

    return $result;
}

/**
 * Verify an email OTP code
 * @param string $code The code entered by the user
 * @return bool True if valid
 */
function verify_email_otp(string $code): bool
{
    if (empty($_SESSION['_email_otp_code']) || empty($_SESSION['_email_otp_time'])) {
        return false;
    }

    // Max 5 attempts
    $_SESSION['_email_otp_attempts'] = ($_SESSION['_email_otp_attempts'] ?? 0) + 1;
    if ($_SESSION['_email_otp_attempts'] > 5) {
        clear_email_otp();
        return false;
    }

    // Expires after 5 minutes
    if (time() - $_SESSION['_email_otp_time'] > 300) {
        clear_email_otp();
        return false;
    }

    if (hash_equals($_SESSION['_email_otp_code'], $code)) {
        clear_email_otp();
        return true;
    }

    return false;
}

/**
 * Clear email OTP session data
 */
function clear_email_otp(): void
{
    unset($_SESSION['_email_otp_code'], $_SESSION['_email_otp_time'], $_SESSION['_email_otp_attempts']);
}

/**
 * Check if admin has email OTP enabled
 * @param int $userId
 * @return array|false Returns ['enabled' => bool, 'security_email' => string] or false
 */
function admin_email_otp_status(int $userId)
{
    try {
        $db = get_db_connection();
        $check = $db->query("SHOW COLUMNS FROM users LIKE 'email_otp_enabled'");
        if ($check->rowCount() === 0) return false;

        $stmt = $db->prepare("SELECT email_otp_enabled, security_email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        return [
            'enabled' => (bool)($row['email_otp_enabled'] ?? 0),
            'security_email' => $row['security_email'] ?? ''
        ];
    } catch (PDOException $e) {
        return false;
    }
}
