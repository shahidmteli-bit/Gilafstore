<?php
/**
 * Input Validation Functions
 * Comprehensive validation for user inputs
 */

/**
 * Validate email address
 * @param string $email Email to validate
 * @param bool $checkDomain Whether to check if domain exists
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_email(string $email, bool $checkDomain = false): array
{
    $email = trim($email);
    
    if (empty($email)) {
        return ['valid' => false, 'error' => 'Email is required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Invalid email format'];
    }
    
    // Check for disposable email domains
    $disposableDomains = ['tempmail.com', 'throwaway.email', '10minutemail.com', 'guerrillamail.com'];
    $domain = substr(strrchr($email, "@"), 1);
    
    if (in_array(strtolower($domain), $disposableDomains)) {
        return ['valid' => false, 'error' => 'Disposable email addresses are not allowed'];
    }
    
    if ($checkDomain) {
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return ['valid' => false, 'error' => 'Email domain does not exist'];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @param int $minLength Minimum length (default 8)
 * @return array ['valid' => bool, 'error' => string|null, 'strength' => string]
 */
function validate_password(string $password, int $minLength = 8): array
{
    if (empty($password)) {
        return ['valid' => false, 'error' => 'Password is required', 'strength' => 'none'];
    }
    
    if (strlen($password) < $minLength) {
        return ['valid' => false, 'error' => "Password must be at least {$minLength} characters", 'strength' => 'weak'];
    }
    
    $hasLower = preg_match('/[a-z]/', $password);
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);
    
    $strength = 0;
    if ($hasLower) $strength++;
    if ($hasUpper) $strength++;
    if ($hasNumber) $strength++;
    if ($hasSpecial) $strength++;
    
    if ($strength < 3) {
        return [
            'valid' => false, 
            'error' => 'Password must contain at least 3 of: lowercase, uppercase, numbers, special characters',
            'strength' => 'weak'
        ];
    }
    
    $strengthLabel = $strength === 4 ? 'strong' : 'medium';
    
    return ['valid' => true, 'error' => null, 'strength' => $strengthLabel];
}

/**
 * Validate Indian phone number
 * @param string $phone Phone number to validate
 * @return array ['valid' => bool, 'error' => string|null, 'formatted' => string|null]
 */
function validate_phone(string $phone): array
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (empty($phone)) {
        return ['valid' => false, 'error' => 'Phone number is required', 'formatted' => null];
    }
    
    // Remove country code if present
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    
    if (strlen($phone) !== 10) {
        return ['valid' => false, 'error' => 'Phone number must be 10 digits', 'formatted' => null];
    }
    
    if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        return ['valid' => false, 'error' => 'Invalid Indian phone number format', 'formatted' => null];
    }
    
    return ['valid' => true, 'error' => null, 'formatted' => $phone];
}

/**
 * Validate Indian postal code
 * @param string $zipCode Postal code to validate
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_zip_code(string $zipCode): array
{
    $zipCode = preg_replace('/[^0-9]/', '', $zipCode);
    
    if (empty($zipCode)) {
        return ['valid' => false, 'error' => 'Postal code is required'];
    }
    
    if (strlen($zipCode) !== 6) {
        return ['valid' => false, 'error' => 'Postal code must be 6 digits'];
    }
    
    if (!preg_match('/^[1-9][0-9]{5}$/', $zipCode)) {
        return ['valid' => false, 'error' => 'Invalid postal code format'];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate name
 * @param string $name Name to validate
 * @param int $minLength Minimum length (default 2)
 * @param int $maxLength Maximum length (default 100)
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_name(string $name, int $minLength = 2, int $maxLength = 100): array
{
    $name = trim($name);
    
    if (empty($name)) {
        return ['valid' => false, 'error' => 'Name is required'];
    }
    
    if (strlen($name) < $minLength) {
        return ['valid' => false, 'error' => "Name must be at least {$minLength} characters"];
    }
    
    if (strlen($name) > $maxLength) {
        return ['valid' => false, 'error' => "Name must not exceed {$maxLength} characters"];
    }
    
    if (!preg_match('/^[a-zA-Z\s\'-]+$/', $name)) {
        return ['valid' => false, 'error' => 'Name can only contain letters, spaces, hyphens, and apostrophes'];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate address field
 * @param string $address Address to validate
 * @param int $minLength Minimum length
 * @param int $maxLength Maximum length
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_address(string $address, int $minLength = 5, int $maxLength = 255): array
{
    $address = trim($address);
    
    if (empty($address)) {
        return ['valid' => false, 'error' => 'Address is required'];
    }
    
    if (strlen($address) < $minLength) {
        return ['valid' => false, 'error' => "Address must be at least {$minLength} characters"];
    }
    
    if (strlen($address) > $maxLength) {
        return ['valid' => false, 'error' => "Address must not exceed {$maxLength} characters"];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate city name
 * @param string $city City name to validate
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_city(string $city): array
{
    $city = trim($city);
    
    if (empty($city)) {
        return ['valid' => false, 'error' => 'City is required'];
    }
    
    if (strlen($city) < 2 || strlen($city) > 100) {
        return ['valid' => false, 'error' => 'City name must be between 2 and 100 characters'];
    }
    
    if (!preg_match('/^[a-zA-Z\s\'-]+$/', $city)) {
        return ['valid' => false, 'error' => 'City name can only contain letters, spaces, hyphens, and apostrophes'];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Validate Indian state name
 * @param string $state State name to validate
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_state(string $state): array
{
    $validStates = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
        'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
        'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
        'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
        'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
        'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
    ];
    
    $state = trim($state);
    
    if (empty($state)) {
        return ['valid' => false, 'error' => 'State is required'];
    }
    
    if (!in_array($state, $validStates)) {
        return ['valid' => false, 'error' => 'Please select a valid Indian state'];
    }
    
    return ['valid' => true, 'error' => null];
}

/**
 * Sanitize input for safe display
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitize_output($data)
{
    if (is_array($data)) {
        return array_map('sanitize_output', $data);
    }
    
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Sanitize input for database storage
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitize_input_safe(string $data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Validate all address fields at once
 * @param array $data Address data
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_address_data(array $data): array
{
    $errors = [];
    
    // Validate address line 1
    $addressResult = validate_address($data['address_line1'] ?? '', 5, 255);
    if (!$addressResult['valid']) {
        $errors['address_line1'] = $addressResult['error'];
    }
    
    // Validate city
    $cityResult = validate_city($data['city'] ?? '');
    if (!$cityResult['valid']) {
        $errors['city'] = $cityResult['error'];
    }
    
    // Validate state
    $stateResult = validate_state($data['state'] ?? '');
    if (!$stateResult['valid']) {
        $errors['state'] = $stateResult['error'];
    }
    
    // Validate zip code
    $zipResult = validate_zip_code($data['zip_code'] ?? '');
    if (!$zipResult['valid']) {
        $errors['zip_code'] = $zipResult['error'];
    }
    
    // Validate phone if provided
    if (!empty($data['phone'])) {
        $phoneResult = validate_phone($data['phone']);
        if (!$phoneResult['valid']) {
            $errors['phone'] = $phoneResult['error'];
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
