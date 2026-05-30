<?php
/**
 * Sales Executive Portal - Authentication
 * Uses token-based auth for Capacitor WebView compatibility
 */

// Configure session BEFORE any other include can call session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400 * 30);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_secure', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400 * 30);
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../../includes/db_connect.php';

// Auto-restore session from token (for WebView/APK where cookies fail)
if (empty($_SESSION['sales_executive']) && !empty($_GET['_token'])) {
    $tokenData = db_fetch(
        'SELECT t.executive_id, e.* FROM sales_auth_tokens t 
         JOIN sales_executives e ON e.id = t.executive_id 
         WHERE t.token = ? AND t.expires_at > NOW() AND e.is_active = 1',
        [$_GET['_token']]
    );
    if ($tokenData) {
        $_SESSION['sales_executive'] = [
            'id' => $tokenData['id'],
            'name' => $tokenData['name'],
            'designation' => $tokenData['designation'] ?? 'Sales Executive',
            'email' => $tokenData['email'],
            'phone' => $tokenData['phone'],
            'district' => $tokenData['district'],
            'location' => $tokenData['location'],
            'reporting_manager' => $tokenData['reporting_manager'],
        ];
        $_SESSION['sales_auth_token'] = $_GET['_token'];
    }
}

function sales_attempt_login(string $email, string $password): bool
{
    $exec = db_fetch('SELECT * FROM sales_executives WHERE email = ? AND is_active = 1', [$email]);

    if ($exec && password_verify($password, $exec['password'])) {
        $_SESSION['sales_executive'] = [
            'id' => $exec['id'],
            'name' => $exec['name'],
            'designation' => $exec['designation'] ?? 'Sales Executive',
            'email' => $exec['email'],
            'phone' => $exec['phone'],
            'district' => $exec['district'],
            'location' => $exec['location'],
            'reporting_manager' => $exec['reporting_manager'],
        ];

        // Generate persistent auth token for WebView
        $token = bin2hex(random_bytes(32));
        db_query('DELETE FROM sales_auth_tokens WHERE executive_id = ?', [$exec['id']]);
        db_query(
            'INSERT INTO sales_auth_tokens (executive_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))',
            [$exec['id'], $token]
        );
        $_SESSION['sales_auth_token'] = $token;

        // Update last login
        db_query('UPDATE sales_executives SET last_login = NOW() WHERE id = ?', [$exec['id']]);
        return true;
    }
    return false;
}

function sales_get_auth_token(): string
{
    return $_SESSION['sales_auth_token'] ?? '';
}

function sales_require_login(): void
{
    if (empty($_SESSION['sales_executive'])) {
        header('Location: ' . sales_base_url('login.php'));
        exit;
    }
}

function sales_is_logged_in(): bool
{
    return !empty($_SESSION['sales_executive']);
}

function sales_get_executive(): ?array
{
    return $_SESSION['sales_executive'] ?? null;
}

function sales_logout(): void
{
    if (!empty($_SESSION['sales_auth_token'])) {
        db_query('DELETE FROM sales_auth_tokens WHERE token = ?', [$_SESSION['sales_auth_token']]);
    }
    unset($_SESSION['sales_executive']);
    unset($_SESSION['sales_auth_token']);
}

function sales_base_url(string $path = ''): string
{
    return base_url('sales-portal/' . ltrim($path, '/'));
}

function sales_generate_order_number(): string
{
    $prefix = 'SO';
    $date = date('Ymd');
    $last = db_fetch("SELECT order_number FROM sales_orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . $date . '%']);
    if ($last) {
        $seq = (int)substr($last['order_number'], -4) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
}
