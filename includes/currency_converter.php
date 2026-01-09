<?php
/**
 * Currency Conversion Service
 * Handles real-time currency conversion with caching
 * Base currency: INR (Indian Rupee)
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Convert price from base currency (INR) to target currency
 * @param float $amount Amount in INR
 * @param string $targetCurrency Target currency code
 * @return float Converted amount
 */
function convert_currency($amount, $targetCurrency = 'INR') {
    if ($targetCurrency === 'INR') {
        return $amount;
    }
    
    $rate = get_exchange_rate('INR', $targetCurrency);
    return round($amount * $rate, 2);
}

/**
 * Format price with currency symbol
 * @param float $amount Amount
 * @param string $currency Currency code
 * @param string $currencySymbol Currency symbol
 * @return string Formatted price
 */
function format_price($amount, $currency = 'INR', $currencySymbol = '₹') {
    // Round to appropriate decimal places
    $decimals = in_array($currency, ['JPY', 'KRW']) ? 0 : 2;
    $formatted = number_format($amount, $decimals);
    
    // Symbol placement based on currency
    $symbolAfter = in_array($currency, ['EUR', 'SEK', 'NOK']);
    
    if ($symbolAfter) {
        return $formatted . ' ' . $currencySymbol;
    } else {
        return $currencySymbol . $formatted;
    }
}

/**
 * Get exchange rate between two currencies
 * @param string $from From currency code
 * @param string $to To currency code
 * @return float Exchange rate
 */
function get_exchange_rate($from, $to) {
    if ($from === $to) {
        return 1.0;
    }
    
    // Check cache first
    $cachedRate = get_cached_exchange_rate($from, $to);
    if ($cachedRate !== null) {
        return $cachedRate;
    }
    
    // Fetch fresh rate
    $rate = fetch_exchange_rate($from, $to);
    
    // Cache the rate
    cache_exchange_rate($from, $to, $rate);
    
    return $rate;
}

/**
 * Fetch exchange rate from API
 * @param string $from From currency
 * @param string $to To currency
 * @return float Exchange rate
 */
function fetch_exchange_rate($from, $to) {
    try {
        // Use exchangerate-api.com (free tier: 1500 requests/month)
        $url = "https://api.exchangerate-api.com/v4/latest/{$from}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['rates'][$to])) {
                return (float)$data['rates'][$to];
            }
        }
    } catch (Exception $e) {
        // Fall back to static rates
    }
    
    // Fallback to static rates if API fails
    return get_static_exchange_rate($from, $to);
}

/**
 * Get cached exchange rate from database
 * @param string $from From currency
 * @param string $to To currency
 * @return float|null Cached rate or null
 */
function get_cached_exchange_rate($from, $to) {
    try {
        // Check if rates table exists
        $tableExists = db_fetch("SHOW TABLES LIKE 'exchange_rates'");
        if (!$tableExists) {
            create_exchange_rates_table();
            return null;
        }
        
        $cacheKey = "{$from}_{$to}";
        $cached = db_fetch(
            "SELECT rate, updated_at FROM exchange_rates WHERE cache_key = ? AND updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$cacheKey]
        );
        
        if ($cached) {
            return (float)$cached['rate'];
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    return null;
}

/**
 * Cache exchange rate in database
 * @param string $from From currency
 * @param string $to To currency
 * @param float $rate Exchange rate
 */
function cache_exchange_rate($from, $to, $rate) {
    try {
        $cacheKey = "{$from}_{$to}";
        
        db_query(
            "INSERT INTO exchange_rates (cache_key, from_currency, to_currency, rate, updated_at) 
             VALUES (?, ?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE rate = ?, updated_at = NOW()",
            [$cacheKey, $from, $to, $rate, $rate]
        );
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Create exchange rates table
 */
function create_exchange_rates_table() {
    try {
        db_query("
            CREATE TABLE IF NOT EXISTS exchange_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(10) UNIQUE NOT NULL,
                from_currency VARCHAR(3) NOT NULL,
                to_currency VARCHAR(3) NOT NULL,
                rate DECIMAL(12, 6) NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_cache_key (cache_key),
                INDEX idx_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Static fallback exchange rates (approximate, updated periodically)
 * @param string $from From currency
 * @param string $to To currency
 * @return float Exchange rate
 */
function get_static_exchange_rate($from, $to) {
    // Base rates from INR (as of Jan 2026 - approximate)
    $rates = [
        'INR' => [
            'USD' => 0.012,   // 1 INR = 0.012 USD
            'EUR' => 0.011,   // 1 INR = 0.011 EUR
            'GBP' => 0.0095,  // 1 INR = 0.0095 GBP
            'AED' => 0.044,   // 1 INR = 0.044 AED
            'QAR' => 0.044,   // 1 INR = 0.044 QAR (Qatar Riyal)
            'SAR' => 0.045,   // 1 INR = 0.045 SAR (Saudi Riyal)
            'KWD' => 0.0037,  // 1 INR = 0.0037 KWD (Kuwaiti Dinar)
            'OMR' => 0.0046,  // 1 INR = 0.0046 OMR (Omani Rial)
            'BHD' => 0.0045,  // 1 INR = 0.0045 BHD (Bahraini Dinar)
            'CAD' => 0.016,   // 1 INR = 0.016 CAD
            'AUD' => 0.018,   // 1 INR = 0.018 AUD
            'SGD' => 0.016,   // 1 INR = 0.016 SGD
            'JPY' => 1.75,    // 1 INR = 1.75 JPY
            'BRL' => 0.060,   // 1 INR = 0.060 BRL
            'SEK' => 0.125,   // 1 INR = 0.125 SEK
            'CHF' => 0.011,   // 1 INR = 0.011 CHF (Swiss Franc)
            'CNY' => 0.086,   // 1 INR = 0.086 CNY (Chinese Yuan)
            'HKD' => 0.093,   // 1 INR = 0.093 HKD (Hong Kong Dollar)
            'NZD' => 0.020,   // 1 INR = 0.020 NZD (New Zealand Dollar)
            'MXN' => 0.24,    // 1 INR = 0.24 MXN (Mexican Peso)
            'ZAR' => 0.22,    // 1 INR = 0.22 ZAR (South African Rand)
            'RUB' => 1.15,    // 1 INR = 1.15 RUB (Russian Ruble)
            'TRY' => 0.41,    // 1 INR = 0.41 TRY (Turkish Lira)
        ]
    ];
    
    if ($from === 'INR' && isset($rates['INR'][$to])) {
        return $rates['INR'][$to];
    }
    
    // For reverse conversion
    if ($to === 'INR' && isset($rates['INR'][$from])) {
        return 1 / $rates['INR'][$from];
    }
    
    // Default fallback
    return 1.0;
}

/**
 * Convert and format price for display
 * @param float $priceINR Price in INR
 * @param string $targetCurrency Target currency code
 * @param string $currencySymbol Currency symbol
 * @return string Formatted price
 */
function display_price($priceINR, $targetCurrency = 'INR', $currencySymbol = '₹') {
    $converted = convert_currency($priceINR, $targetCurrency);
    return format_price($converted, $targetCurrency, $currencySymbol);
}

/**
 * Get all exchange rates for a base currency
 * @param string $baseCurrency Base currency code
 * @return array Exchange rates
 */
function get_all_exchange_rates($baseCurrency = 'INR') {
    $currencies = ['USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD', 'SGD', 'JPY', 'BRL', 'SEK'];
    $rates = [];
    
    foreach ($currencies as $currency) {
        if ($currency !== $baseCurrency) {
            $rates[$currency] = get_exchange_rate($baseCurrency, $currency);
        }
    }
    
    return $rates;
}

/**
 * Update all exchange rates (run daily via cron or manual trigger)
 */
function update_all_exchange_rates() {
    $currencies = ['USD', 'EUR', 'GBP', 'AED', 'CAD', 'AUD', 'SGD', 'JPY', 'BRL', 'SEK'];
    $updated = 0;
    
    foreach ($currencies as $currency) {
        $rate = fetch_exchange_rate('INR', $currency);
        cache_exchange_rate('INR', $currency, $rate);
        $updated++;
        
        // Small delay to avoid rate limiting
        usleep(100000); // 0.1 second
    }
    
    return $updated;
}
