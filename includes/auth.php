<?php
require_once __DIR__ . '/db_connect.php';

function attempt_login(string $identifier, string $password): bool
{
    $user = db_fetch('SELECT * FROM users WHERE email = ?', [$identifier]);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (bool)($user['is_admin'] ?? 0),
        ];
        return true;
    }

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

function require_admin(): void
{
    if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
        redirect_with_message(base_url('admin/admin_login.php'), 'Admin access required', 'danger');
    }
}

function logout_user(): void
{
    // Get user ID before clearing session
    $userId = $_SESSION['user']['id'] ?? null;
    
    // Clear user-specific cache
    require_once __DIR__ . '/auto_cache_clear.php';
    clearCacheOnLogout($userId);
    
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
