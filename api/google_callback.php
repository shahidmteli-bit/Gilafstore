<?php
/**
 * Google OAuth Callback Handler
 * Handles both the initial redirect to Google and the callback with auth code
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/google_oauth.php';

$google = new GoogleOAuth();

// Check if Google OAuth is configured
if (!$google->isConfigured()) {
    redirect_with_message('/user/login.php', 'Google login is not configured yet. Please use phone OTP or password.', 'warning');
}

$action = $_GET['action'] ?? '';
$redirect = $_GET['redirect'] ?? '';

// Step 1: Redirect to Google
if ($action === 'login' || empty($_GET['code'])) {
    // Build state param with redirect info
    $state = base64_encode(json_encode(['redirect' => $redirect]));
    $authUrl = $google->getAuthUrl($state);
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Handle callback from Google
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code)) {
    redirect_with_message('/user/login.php', 'Google login failed. Please try again.', 'danger');
}

// Decode state
$stateData = json_decode(base64_decode($state), true) ?: [];
$redirectTo = $stateData['redirect'] ?? '';

// Exchange code for token
$tokenData = $google->getAccessToken($code);
if (!$tokenData) {
    redirect_with_message('/user/login.php', 'Failed to authenticate with Google. Please try again.', 'danger');
}

// Get user info
$googleUser = $google->getUserInfo($tokenData['access_token']);
if (!$googleUser) {
    redirect_with_message('/user/login.php', 'Failed to get Google account info. Please try again.', 'danger');
}

// Find or create user
$user = $google->findOrCreateUser($googleUser);
if (!$user) {
    redirect_with_message('/user/login.php', 'Failed to create account. Please try again.', 'danger');
}

// Log in
$google->loginUser($user);

// Redirect
if ($redirectTo === 'checkout') {
    redirect_with_message('/checkout.php', 'Welcome, ' . htmlspecialchars($user['name']) . '!');
} else {
    redirect_with_message('/index.php', 'Welcome, ' . htmlspecialchars($user['name']) . '!');
}
