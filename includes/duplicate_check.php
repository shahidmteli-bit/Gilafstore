<?php
/**
 * Duplicate Account Validation
 * Central uniqueness checks for email + phone across all entry points:
 * guest checkout, registration, profile update, admin-created accounts.
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Normalize phone: strip non-digits, remove +91 country code prefix
 */
function normalize_phone_number(string $phone): string
{
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    return $phone;
}

/**
 * Check if an email is already registered to a different account.
 * Case-insensitive comparison.
 *
 * @param string $email          Email to check
 * @param int    $excludeUserId  Exclude this user (0 = no exclusion, use for profile updates)
 * @return string|null Error message string if duplicate, null if unique
 */
function check_email_unique(string $email, int $excludeUserId = 0): ?string
{
    $email = strtolower(trim($email));
    if (empty($email)) return null;

    if ($excludeUserId > 0) {
        $row = db_fetch('SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1', [$email, $excludeUserId]);
    } else {
        $row = db_fetch('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1', [$email]);
    }

    if ($row) {
        return 'This email address is already registered with us. Please log in using your existing account.';
    }
    return null;
}

/**
 * Check if a phone number is already registered to a different account.
 * Normalizes the number before comparison.
 *
 * @param string $phone          Phone number to check
 * @param int    $excludeUserId  Exclude this user (0 = no exclusion, use for profile updates)
 * @return string|null Error message string if duplicate, null if unique
 */
function check_phone_unique(string $phone, int $excludeUserId = 0): ?string
{
    $phone = normalize_phone_number($phone);
    if (strlen($phone) !== 10) return null;

    if ($excludeUserId > 0) {
        $row = db_fetch('SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1', [$phone, $excludeUserId]);
    } else {
        $row = db_fetch('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone]);
    }

    if ($row) {
        return 'This phone number is already registered with us. Please log in using your existing account.';
    }
    return null;
}

/**
 * Check both email and phone uniqueness in one call.
 * Returns an associative array of field => error_message.
 * Empty array means both are unique.
 *
 * @param string $email
 * @param string $phone
 * @param int    $excludeUserId  Pass current user's ID when updating profile
 * @return array<string,string>  ['email' => '...'] and/or ['phone' => '...']
 */
function check_credentials_unique(string $email, string $phone, int $excludeUserId = 0): array
{
    $errors = [];

    $emailErr = check_email_unique($email, $excludeUserId);
    if ($emailErr !== null) {
        $errors['email'] = $emailErr;
    }

    $phoneErr = check_phone_unique($phone, $excludeUserId);
    if ($phoneErr !== null) {
        $errors['phone'] = $phoneErr;
    }

    return $errors;
}
