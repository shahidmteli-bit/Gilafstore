<?php
/**
 * Guest Checkout & Auto Account Creation
 * Phase 1: Allows checkout without login, silently creates account after order
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Check if current session is a guest checkout
 */
function is_guest_checkout(): bool
{
    return !empty($_SESSION['guest_checkout']);
}

/**
 * Get the effective user ID (logged-in user or 0 for guest)
 */
function get_checkout_user_id(): int
{
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }
    return 0;
}

/**
 * Store guest info in session during checkout
 */
function set_guest_info(string $name, string $email, string $phone): void
{
    $_SESSION['guest_checkout'] = [
        'name' => trim($name),
        'email' => trim($email),
        'phone' => trim($phone),
    ];
}

/**
 * Get guest info from session
 */
function get_guest_info(): ?array
{
    return $_SESSION['guest_checkout'] ?? null;
}

/**
 * Store guest address in session
 */
function set_guest_address(array $address): void
{
    $_SESSION['guest_address'] = $address;
}

/**
 * Get guest address from session
 */
function get_guest_address(): ?array
{
    return $_SESSION['guest_address'] ?? null;
}

/**
 * Ensure users table has pin and temp_password_active columns (idempotent)
 */
function _guest_ensure_pin_columns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = get_db_connection();
        $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('pin', $cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN pin VARCHAR(255) NULL DEFAULT NULL AFTER password");
        }
        if (!in_array('temp_password_active', $cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN temp_password_active TINYINT(1) NOT NULL DEFAULT 0 AFTER pin");
        }
    } catch (\Throwable $e) {
        error_log("pin column migration failed: " . $e->getMessage());
    }
}

/**
 * Auto-create user account after successful guest checkout
 * Sets temp password = first 6 digits of phone number
 * Returns the new user ID or null on failure
 */
function auto_create_guest_account(string $name, string $email, string $phone): ?int
{
    _guest_ensure_pin_columns();
    try {
        $db = get_db_connection();
        
        // Check if user already exists by email or phone
        $existing = null;
        if (!empty($email)) {
            $existing = db_fetch('SELECT id FROM users WHERE email = ?', [$email]);
        }
        if (!$existing && !empty($phone)) {
            $existing = db_fetch('SELECT id FROM users WHERE phone = ?', [$phone]);
        }
        
        if ($existing) {
            // Account already exists — attach order to it but don't show PIN setup popup
            $_SESSION['just_created_account'] = null;
            unset($_SESSION['just_created_account']);
            return (int)$existing['id'];
        }
        
        // Temp password = first 6 digits of phone (or padded if phone is short)
        $tempPass  = substr(preg_replace('/\D/', '', $phone), 0, 6);
        if (strlen($tempPass) < 6) $tempPass = str_pad($tempPass, 6, '0');
        $hashedTemp = password_hash($tempPass, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            "INSERT INTO users (name, email, phone, password, temp_password_active, auth_method, is_guest, created_at)
             VALUES (?, ?, ?, ?, 1, 'guest', 1, NOW())"
        );
        $stmt->execute([$name, $email ?: null, $phone ?: null, $hashedTemp]);
        
        $newUserId = (int)$db->lastInsertId();

        // Flag for post-checkout popup
        $_SESSION['just_created_account'] = [
            'user_id'   => $newUserId,
            'phone'     => $phone,
            'temp_pass' => $tempPass,
            'is_new'    => true,
        ];
        
        // Log guest session
        try {
            $sessionId = session_id();
            $db->prepare("INSERT INTO guest_sessions (session_id, guest_name, guest_email, guest_phone, merged_user_id, merged_at) VALUES (?, ?, ?, ?, ?, NOW())")
               ->execute([$sessionId, $name, $email, $phone, $newUserId]);
        } catch (Exception $e) {
            error_log("Guest session logging failed: " . $e->getMessage());
        }
        
        return $newUserId;
        
    } catch (Exception $e) {
        error_log("Auto account creation failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Alias used by Razorpay & UPI payment handlers
 * Accepts array $guestInfo and optional $guestAddr
 */
function guest_auto_create_account(array $guestInfo, array $guestAddr = []): ?int
{
    return auto_create_guest_account(
        $guestInfo['name']  ?? 'Guest',
        $guestInfo['email'] ?? '',
        $guestInfo['phone'] ?? ''
    );
}

/**
 * Alias used by Razorpay & UPI payment handlers
 */
function guest_save_address(int $userId, array $address): void
{
    save_guest_address_to_user($userId, $address);
}

/**
 * Save guest address to user_addresses after account creation.
 * Prevents duplicates: if the user already has an address with the same
 * city AND state, that address is set as default and no new row is inserted.
 */
function save_guest_address_to_user(int $userId, array $address): void
{
    try {
        $addr1 = trim($address['address_line1'] ?? '');
        $city  = trim($address['city']  ?? '');
        $state = trim($address['state'] ?? '');
        $zip   = trim($address['zip_code'] ?? '');

        // Never insert an incomplete address — prevents blank rows
        if (empty($addr1) || empty($city) || empty($state) || empty($zip)) {
            return;
        }

        // Duplicate check: same city + state already on file?
        $existing = db_fetch(
            "SELECT id FROM user_addresses WHERE user_id = ? AND LOWER(city) = LOWER(?) AND LOWER(state) = LOWER(?) LIMIT 1",
            [$userId, $city, $state]
        );
        if ($existing) {
            // Promote the existing address to default, skip insert
            db_query("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
            db_query("UPDATE user_addresses SET is_default = 1 WHERE id = ?", [(int)$existing['id']]);
            return;
        }

        // No duplicate found — clear existing defaults and insert new address
        db_query("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
        db_query(
            "INSERT INTO user_addresses (user_id, type, address_line1, address_line2, city, state, zip_code, phone, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                $userId,
                $address['type']         ?? 'home',
                $addr1,
                $address['address_line2'] ?? '',
                $city,
                $state,
                $zip,
                $address['phone']        ?? '',
            ]
        );
    } catch (Exception $e) {
        error_log("Guest address save failed: " . $e->getMessage());
    }
}

/**
 * Merge guest order to newly created user account
 */
function merge_guest_order_to_user(int $orderId, int $userId): void
{
    try {
        db_query("UPDATE orders SET user_id = ? WHERE id = ? AND (user_id = 0 OR user_id IS NULL)", [$userId, $orderId]);
    } catch (Exception $e) {
        error_log("Guest order merge failed for order #$orderId: " . $e->getMessage());
    }
}

/**
 * Send account creation notification (optional)
 */
function send_guest_account_notification(int $userId, string $email, string $name): void
{
    try {
        if (!empty($email) && function_exists('send_welcome_email')) {
            require_once __DIR__ . '/order_emails.php';
            send_welcome_email($email, $name);
        }
    } catch (Exception $e) {
        error_log("Guest account notification failed: " . $e->getMessage());
    }
}

/**
 * Clear guest checkout session data
 */
function clear_guest_session(): void
{
    unset($_SESSION['guest_checkout']);
    unset($_SESSION['guest_address']);
}

/**
 * Validate guest checkout form fields
 */
function validate_guest_fields(array $post): array
{
    $errors = [];
    
    $name = trim($post['guest_name'] ?? '');
    $email = trim($post['guest_email'] ?? '');
    $phone = trim($post['guest_phone'] ?? '');
    
    if (strlen($name) < 2) {
        $errors['guest_name'] = 'Please enter your full name';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['guest_email'] = 'Please enter a valid email address';
    }
    
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors['guest_phone'] = 'Please enter a valid 10-digit phone number';
    }
    
    return $errors;
}

/**
 * Validate guest address fields
 */
function validate_guest_address(array $post): array
{
    $errors = [];
    
    if (empty(trim($post['address_line1'] ?? ''))) {
        $errors['address_line1'] = 'Address is required';
    }
    if (empty(trim($post['city'] ?? ''))) {
        $errors['city'] = 'City is required';
    }
    if (empty(trim($post['state'] ?? ''))) {
        $errors['state'] = 'State is required';
    }
    if (empty(trim($post['zip_code'] ?? '')) || !preg_match('/^[0-9]{6}$/', trim($post['zip_code'] ?? ''))) {
        $errors['zip_code'] = 'Please enter a valid 6-digit PIN code';
    }
    
    return $errors;
}
