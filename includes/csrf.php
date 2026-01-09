<?php
/**
 * CSRF Protection Functions
 * Provides token generation and validation for form security
 */

/**
 * Generate a CSRF token and store it in session
 * @return string The generated token
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * @param string|null $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validate_csrf_token(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token from request (POST or header)
 * @return string|null
 */
function get_csrf_token_from_request(): ?string
{
    // Check POST data
    if (isset($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }
    
    // Check headers (for AJAX requests)
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    
    return null;
}

/**
 * Require valid CSRF token or die with error
 * @param string|null $token Token to validate (optional, will auto-detect)
 */
function require_csrf_token(?string $token = null): void
{
    if ($token === null) {
        $token = get_csrf_token_from_request();
    }
    
    if (!validate_csrf_token($token)) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        } else {
            die('Invalid security token. Please refresh the page and try again.');
        }
        exit;
    }
}

/**
 * Output CSRF token as hidden input field
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

/**
 * Get CSRF token as meta tag (for AJAX requests)
 * @return string
 */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(generate_csrf_token()) . '">';
}
