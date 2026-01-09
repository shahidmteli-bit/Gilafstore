<?php
/**
 * Region Detection Service
 * Automatically detects user's country, language, and currency
 * Priority: User preference > Logged-in profile > IP geolocation > Browser language
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Detect user's country using multiple signals
 * @return array Country data with code, name, currency, language
 */
function detect_user_country() {
    // Priority 1: Check user preference (cookie or session)
    if (isset($_COOKIE['user_country_preference'])) {
        $countryCode = $_COOKIE['user_country_preference'];
        $countryData = get_country_data($countryCode);
        if ($countryData) {
            return $countryData;
        }
    }
    
    // Priority 2: Check logged-in user profile
    if (isset($_SESSION['user']['country'])) {
        $countryCode = $_SESSION['user']['country'];
        $countryData = get_country_data($countryCode);
        if ($countryData) {
            return $countryData;
        }
    }
    
    // Priority 3: IP-based geolocation
    $ipCountry = detect_country_by_ip();
    if ($ipCountry) {
        return $ipCountry;
    }
    
    // Priority 4: Browser language
    $browserCountry = detect_country_by_browser_language();
    if ($browserCountry) {
        return $browserCountry;
    }
    
    // Fallback: Default to India
    return get_country_data('IN');
}

/**
 * Detect country by IP address using free geolocation API
 * @return array|null Country data or null
 */
function detect_country_by_ip() {
    try {
        $ip = get_user_ip();
        
        // Skip detection for local/private IPs
        if (is_local_ip($ip)) {
            return null;
        }
        
        // Use free ipapi.co service (no API key needed, 1000 requests/day)
        $url = "https://ipapi.co/{$ip}/json/";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['country_code']) && !isset($data['error'])) {
                return get_country_data($data['country_code']);
            }
        }
    } catch (Exception $e) {
        // Silent fail, continue to next detection method
    }
    
    return null;
}

/**
 * Detect country by browser's Accept-Language header
 * @return array|null Country data or null
 */
function detect_country_by_browser_language() {
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return null;
    }
    
    $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $primaryLang = strtolower(substr($languages[0], 0, 2));
    
    // Map common languages to countries
    $langToCountry = [
        'en' => 'US',
        'hi' => 'IN',
        'fr' => 'FR',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
        'pt' => 'BR',
        'ja' => 'JP',
        'zh' => 'CN',
        'ar' => 'AE',
        'ru' => 'RU',
        'nl' => 'NL',
        'sv' => 'SE',
        'pl' => 'PL',
        'tr' => 'TR'
    ];
    
    if (isset($langToCountry[$primaryLang])) {
        return get_country_data($langToCountry[$primaryLang]);
    }
    
    return null;
}

/**
 * Get user's IP address
 * @return string IP address
 */
function get_user_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    return trim($ip);
}

/**
 * Check if IP is local/private
 * @param string $ip IP address
 * @return bool True if local IP
 */
function is_local_ip($ip) {
    $localRanges = ['127.', '192.168.', '10.', '172.16.', '::1', 'localhost'];
    
    foreach ($localRanges as $range) {
        if (strpos($ip, $range) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get country data by country code
 * @param string $countryCode ISO 2-letter country code
 * @return array|null Country data
 */
function get_country_data($countryCode) {
    $countries = get_supported_countries();
    $countryCode = strtoupper($countryCode);
    
    return $countries[$countryCode] ?? null;
}

/**
 * Get all supported countries with currency and language data
 * Now uses global countries database with exclusion filtering
 * @return array Countries data
 */
function get_supported_countries() {
    require_once __DIR__ . '/global_countries.php';
    
    $allCountries = get_all_countries();
    $excludedCountries = get_excluded_countries();
    
    // Filter out excluded countries
    $supportedCountries = array_filter($allCountries, function($code) use ($excludedCountries) {
        return !in_array($code, $excludedCountries);
    }, ARRAY_FILTER_USE_KEY);
    
    return $supportedCountries;
}

/**
 * Get excluded countries from database or default list
 * @return array List of excluded country codes
 */
function get_excluded_countries() {
    try {
        // Check if settings table exists
        $tableExists = db_fetch("SHOW TABLES LIKE 'system_settings'");
        if (!$tableExists) {
            create_system_settings_table();
        }
        
        // Get excluded countries from database
        $setting = db_fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'excluded_countries'");
        
        if ($setting && !empty($setting['setting_value'])) {
            return json_decode($setting['setting_value'], true) ?: [];
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    // Return default excluded countries
    require_once __DIR__ . '/global_countries.php';
    return get_default_excluded_countries();
}

/**
 * Save excluded countries to database
 * @param array $countryCodes Array of country codes to exclude
 * @return bool Success status
 */
function save_excluded_countries($countryCodes) {
    try {
        $json = json_encode($countryCodes);
        
        db_query(
            "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
             VALUES ('excluded_countries', ?, NOW()) 
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
            [$json, $json]
        );
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create system settings table
 */
function create_system_settings_table() {
    try {
        db_query("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME NOT NULL,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Save user's country preference
 * @param string $countryCode Country code
 * @param int $userId Optional user ID for logged-in users
 */
function save_country_preference($countryCode, $userId = null) {
    // Save to cookie (30 days)
    setcookie('user_country_preference', $countryCode, time() + (30 * 24 * 60 * 60), '/');
    
    // Save to session
    $_SESSION['user_country'] = $countryCode;
    
    // Save to user profile if logged in
    if ($userId) {
        try {
            db_query("UPDATE users SET country = ? WHERE id = ?", [$countryCode, $userId]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
}

/**
 * Check if user has confirmed auto-detection
 * @return bool True if confirmed
 */
function has_confirmed_auto_detection() {
    return isset($_COOKIE['region_detection_confirmed']) || isset($_SESSION['region_detection_confirmed']);
}

/**
 * Mark auto-detection as confirmed
 */
function confirm_auto_detection() {
    setcookie('region_detection_confirmed', '1', time() + (365 * 24 * 60 * 60), '/');
    $_SESSION['region_detection_confirmed'] = true;
}

/**
 * Get current user's region settings
 * @return array Region settings with country, currency, language
 */
function get_user_region_settings() {
    // Check if user has manually set preferences
    if (isset($_SESSION['user_country'])) {
        $country = get_country_data($_SESSION['user_country']);
        if ($country) {
            return [
                'country' => $country,
                'currency' => $country['currency'],
                'currency_symbol' => $country['currency_symbol'],
                'language' => $country['language'],
                'auto_detected' => false
            ];
        }
    }
    
    // Auto-detect
    $country = detect_user_country();
    
    return [
        'country' => $country,
        'currency' => $country['currency'],
        'currency_symbol' => $country['currency_symbol'],
        'language' => $country['language'],
        'auto_detected' => !has_confirmed_auto_detection()
    ];
}
