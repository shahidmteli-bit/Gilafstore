<?php
/**
 * Automatic Cache Clearing for User Actions
 * Clears user-specific cache on logout, language change, currency change, order placement
 */

require_once __DIR__ . '/cache_manager.php';

/**
 * Clear user-specific cache on logout
 */
function clearCacheOnLogout($userId = null) {
    // Clear user session cache
    if (isset($_SESSION['user_cache'])) {
        unset($_SESSION['user_cache']);
    }
    
    // Clear user-specific cookies
    $cookiesToClear = [
        'user_language_preference',
        'user_country_preference',
        'cart_cache',
        'wishlist_cache'
    ];
    
    foreach ($cookiesToClear as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 3600, '/');
        }
    }
    
    // Log the action
    logCacheAction('user_logout_cache_clear', $userId);
    
    return true;
}

/**
 * Clear cache when language is changed
 */
function clearCacheOnLanguageChange($newLanguage, $userId = null) {
    // Clear language-specific cache
    if (isset($_SESSION['language_cache'])) {
        unset($_SESSION['language_cache']);
    }
    
    // Clear translated content cache
    if (isset($_SESSION['translated_content'])) {
        unset($_SESSION['translated_content']);
    }
    
    // Log the action
    logCacheAction('language_change_cache_clear', $userId, "Changed to: $newLanguage");
    
    return true;
}

/**
 * Clear cache when currency is changed
 */
function clearCacheOnCurrencyChange($newCurrency, $userId = null) {
    // Clear currency-specific cache
    if (isset($_SESSION['currency_cache'])) {
        unset($_SESSION['currency_cache']);
    }
    
    // Clear price cache
    if (isset($_SESSION['price_cache'])) {
        unset($_SESSION['price_cache']);
    }
    
    // Clear cart price calculations
    if (isset($_SESSION['cart_totals'])) {
        unset($_SESSION['cart_totals']);
    }
    
    // Log the action
    logCacheAction('currency_change_cache_clear', $userId, "Changed to: $newCurrency");
    
    return true;
}

/**
 * Clear cache when order is placed
 */
function clearCacheOnOrderPlacement($orderId, $userId = null) {
    // Clear cart cache
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    
    if (isset($_SESSION['cart_cache'])) {
        unset($_SESSION['cart_cache']);
    }
    
    // Clear checkout cache
    if (isset($_SESSION['checkout_data'])) {
        unset($_SESSION['checkout_data']);
    }
    
    // Clear promo code cache for this user
    if (isset($_SESSION['applied_promo'])) {
        unset($_SESSION['applied_promo']);
    }
    
    // Log the action
    logCacheAction('order_placement_cache_clear', $userId, "Order ID: $orderId");
    
    return true;
}

/**
 * Soft refresh content for users (no system cache access)
 */
function softRefreshUserContent() {
    // Clear only user-facing session data, not system cache
    $userSafeClearItems = [
        'page_cache',
        'product_list_cache',
        'category_cache',
        'search_results_cache'
    ];
    
    foreach ($userSafeClearItems as $item) {
        if (isset($_SESSION[$item])) {
            unset($_SESSION[$item]);
        }
    }
    
    return [
        'success' => true,
        'message' => 'Content refreshed successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Log cache clearing actions
 */
function logCacheAction($action, $userId, $details = '') {
    try {
        db_query(
            "INSERT INTO user_cache_logs (action, user_id, details, created_at) VALUES (?, ?, ?, NOW())",
            [$action, $userId, $details]
        );
    } catch (Exception $e) {
        // Silent fail if table doesn't exist
        error_log("Cache log error: " . $e->getMessage());
    }
}

/**
 * Auto-clear expired user sessions
 */
function autoCleanExpiredUserSessions() {
    // This runs as a background task
    try {
        // Clear sessions older than 24 hours
        db_query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Clear expired cache entries
        db_query("DELETE FROM user_cache WHERE expires_at < NOW()");
        
        return true;
    } catch (Exception $e) {
        error_log("Auto-clean error: " . $e->getMessage());
        return false;
    }
}
