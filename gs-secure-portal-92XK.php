<?php
/**
 * Gilaf Admin Secure Gateway
 * ==========================
 * This is the ONLY entry point to the admin login page.
 * Direct access to /admin/admin_login.php without this gate returns 404.
 * 
 * URL: gilafstore.com/gs-secure-portal-92XK
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set the admin gate token (valid for 30 minutes)
$_SESSION['_admin_gate_token'] = bin2hex(random_bytes(16));
$_SESSION['_admin_gate_time'] = time();

// Log access to the secure portal
try {
    require_once __DIR__ . '/includes/security.php';
    security_log('ADMIN_GATE_ACCESSED', 'INFO', 'Admin secure portal gateway accessed', [
        'ip' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
} catch (Exception $e) {
    // Silent — logging should never block access
}

// Redirect to the actual admin login
header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/admin/admin_login.php');
exit;
