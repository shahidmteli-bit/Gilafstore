<?php
/**
 * Google Tag Helper Functions
 * Handles dynamic injection of Google Analytics/Ads tags based on admin settings
 */

/**
 * Get Google Tag settings from database
 */
function get_google_tag_settings() {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $conn = get_db_connection();
            if (!$conn) {
                return null;
            }
            
            // Check if table exists first
            $table_check = $conn->query("SHOW TABLES LIKE 'google_tags'");
            if ($table_check->num_rows === 0) {
                return null;
            }
            
            $result = $conn->query("SELECT * FROM google_tags WHERE id = 1");
            if ($result) {
                $settings = $result->fetch_assoc();
                if ($settings) {
                    $settings['page_conditions'] = json_decode($settings['page_conditions'] ?? '{}', true) ?: ['pages' => [], 'custom_urls' => []];
                }
            }
        } catch (Exception $e) {
            // Silently fail to prevent white page
            $settings = null;
        }
    }
    
    return $settings;
}

/**
 * Detect current page type based on URL
 */
function detect_current_page_type() {
    $url = $_SERVER['REQUEST_URI'] ?? '';
    $url = parse_url($url, PHP_URL_PATH);
    
    // Remove query string
    $url = explode('?', $url)[0];
    
    if ($url === '/' || $url === '/index.php' || $url === '') {
        return 'homepage';
    } elseif (strpos($url, '/product') === 0) {
        return 'product';
    } elseif (strpos($url, '/shop') === 0) {
        return 'shop';
    } elseif (strpos($url, '/cart') === 0) {
        return 'cart';
    } elseif (strpos($url, '/checkout') === 0) {
        return 'checkout';
    } elseif (strpos($url, '/thank') === 0 || strpos($url, '/order_success') === 0) {
        return 'thank_you';
    } elseif (strpos($url, '/offers') === 0) {
        return 'offers';
    } elseif (strpos($url, '/blog') === 0) {
        return 'blog';
    } elseif (strpos($url, '/contact') === 0) {
        return 'contact';
    }
    
    return 'other';
}

/**
 * Check if Google Tag should load on current page
 */
function should_load_google_tag($settings = null) {
    if ($settings === null) {
        $settings = get_google_tag_settings();
    }
    
    // Check if tag is enabled and script exists
    if (!$settings || !$settings['enabled'] || empty($settings['tag_script'])) {
        return false;
    }
    
    $page_conditions = $settings['page_conditions'];
    $pages = $page_conditions['pages'] ?? [];
    $custom_urls = $page_conditions['custom_urls'] ?? [];
    
    // Check if all pages selected
    if (in_array('all', $pages)) {
        return true;
    }
    
    // Check specific pages
    $current_page_type = detect_current_page_type();
    if (in_array($current_page_type, $pages)) {
        return true;
    }
    
    // Check custom URLs
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($custom_urls as $url) {
        if (!empty($url) && strpos($current_url, $url) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get Google Tag script for injection
 */
function get_google_tag_script($settings = null) {
    if ($settings === null) {
        $settings = get_google_tag_settings();
    }
    
    if (!should_load_google_tag($settings)) {
        return '';
    }
    
    $script = $settings['tag_script'] ?? '';
    
    // Sanitize script for safe output
    $script = trim($script);
    
    // Ensure script is properly formatted
    if (!empty($script)) {
        // Add comment for debugging
        $script = "<!-- Google Tag (Admin Configured) -->\n" . $script;
    }
    
    return $script;
}

/**
 * Inject Google Tag into HTML head
 */
function inject_google_tag() {
    try {
        $script = get_google_tag_script();
        
        if (!empty($script)) {
            echo $script . "\n";
        }
    } catch (Exception $e) {
        // Silently fail to prevent white page
        // Optionally log error for debugging
        // error_log("Google Tag injection error: " . $e->getMessage());
    }
}

/**
 * Get database connection (reusing existing connection if available)
 */
function get_db_connection() {
    global $conn;
    
    // If connection already exists, return it
    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }
    
    // Try to include db_connect if not already loaded
    if (!function_exists('db_connect')) {
        require_once __DIR__ . '/db_connect.php';
    }
    
    // Return the global connection (should be set by db_connect.php)
    return $conn;
}
?>
