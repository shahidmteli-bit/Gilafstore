<?php
/**
 * SIMPLE PROMO TABLES SETUP - NO AUTH REQUIRED
 * Run this once to create promotional system tables
 * DELETE THIS FILE AFTER RUNNING FOR SECURITY
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct database connection
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();

if (!$db) {
    die("❌ Database connection failed");
}

$success = [];
$errors = [];

try {
    // 1. Promotions Master Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS promotions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            promo_name VARCHAR(255) NOT NULL,
            promo_type ENUM('discount', 'free_shipping', 'combo', 'first_order', 'bundle', 'seasonal', 'best_seller', 'buy_more_save_more') NOT NULL,
            promo_message TEXT NOT NULL,
            promo_badge VARCHAR(100),
            discount_type ENUM('percentage', 'fixed', 'free_shipping') DEFAULT 'percentage',
            discount_value DECIMAL(10,2) DEFAULT 0,
            min_order_value DECIMAL(10,2) DEFAULT 0,
            max_discount DECIMAL(10,2) DEFAULT NULL,
            coupon_code VARCHAR(50) DEFAULT NULL,
            target_type ENUM('all', 'category', 'product', 'new_users', 'returning_users') DEFAULT 'all',
            target_ids TEXT COMMENT 'JSON array of category/product IDs',
            show_on_homepage BOOLEAN DEFAULT FALSE,
            show_on_product_page BOOLEAN DEFAULT FALSE,
            show_on_cart BOOLEAN DEFAULT FALSE,
            show_on_checkout BOOLEAN DEFAULT FALSE,
            show_exit_intent BOOLEAN DEFAULT FALSE,
            show_sticky_mobile BOOLEAN DEFAULT FALSE,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0 COMMENT 'Higher priority shows first',
            banner_color VARCHAR(20) DEFAULT '#FF6B6B',
            text_color VARCHAR(20) DEFAULT '#FFFFFF',
            icon VARCHAR(50) DEFAULT 'tag',
            show_countdown BOOLEAN DEFAULT FALSE,
            urgency_message VARCHAR(255) DEFAULT NULL,
            stock_threshold INT DEFAULT NULL COMMENT 'Show urgency when stock below this',
            views INT DEFAULT 0,
            clicks INT DEFAULT 0,
            conversions INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_dates (start_date, end_date),
            INDEX idx_type (promo_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'promotions' created";
    
    // 2. Exit Intent Popups
    $db->exec("
        CREATE TABLE IF NOT EXISTS exit_intent_popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            popup_name VARCHAR(255) NOT NULL,
            headline VARCHAR(255) NOT NULL,
            subheadline TEXT,
            offer_text TEXT NOT NULL,
            cta_text VARCHAR(100) DEFAULT 'Claim Offer',
            cta_link VARCHAR(255),
            auto_apply_coupon VARCHAR(50) DEFAULT NULL,
            discount_value DECIMAL(10,2) DEFAULT 0,
            show_on_pages ENUM('all', 'product', 'cart', 'checkout', 'homepage') DEFAULT 'all',
            trigger_delay INT DEFAULT 3 COMMENT 'Seconds before showing',
            background_color VARCHAR(20) DEFAULT '#FFFFFF',
            overlay_color VARCHAR(20) DEFAULT 'rgba(0,0,0,0.7)',
            image_url VARCHAR(255) DEFAULT NULL,
            show_once_per_session BOOLEAN DEFAULT TRUE,
            show_once_per_user BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0,
            impressions INT DEFAULT 0,
            conversions INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'exit_intent_popups' created";
    
    // 3. Signup Incentives
    $db->exec("
        CREATE TABLE IF NOT EXISTS signup_incentives (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incentive_name VARCHAR(255) NOT NULL,
            incentive_type ENUM('discount', 'free_shipping', 'points', 'gift') NOT NULL,
            headline VARCHAR(255) NOT NULL,
            description TEXT,
            discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            discount_value DECIMAL(10,2) DEFAULT 0,
            coupon_code VARCHAR(50) DEFAULT NULL,
            auto_apply BOOLEAN DEFAULT TRUE,
            show_on_register_page BOOLEAN DEFAULT TRUE,
            show_on_checkout BOOLEAN DEFAULT TRUE,
            show_as_popup BOOLEAN DEFAULT FALSE,
            valid_days INT DEFAULT 30 COMMENT 'Days after signup',
            min_order_value DECIMAL(10,2) DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0,
            signups_attributed INT DEFAULT 0,
            redemptions INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'signup_incentives' created";
    
    // 4. Homepage Banners
    $db->exec("
        CREATE TABLE IF NOT EXISTS homepage_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            banner_name VARCHAR(255) NOT NULL,
            banner_type ENUM('hero', 'strip', 'card', 'floating') NOT NULL,
            headline VARCHAR(255) NOT NULL,
            subheadline TEXT,
            cta_text VARCHAR(100),
            cta_link VARCHAR(255),
            background_image VARCHAR(255) DEFAULT NULL,
            background_color VARCHAR(20) DEFAULT '#FF6B6B',
            text_color VARCHAR(20) DEFAULT '#FFFFFF',
            position ENUM('top', 'middle', 'bottom', 'floating') DEFAULT 'top',
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            show_desktop BOOLEAN DEFAULT TRUE,
            show_mobile BOOLEAN DEFAULT TRUE,
            show_countdown BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'homepage_banners' created";
    
    // 5. Promo Analytics
    $db->exec("
        CREATE TABLE IF NOT EXISTS promo_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            promo_id INT NOT NULL,
            promo_type ENUM('promotion', 'exit_intent', 'signup_incentive', 'homepage_banner') NOT NULL,
            event_type ENUM('view', 'click', 'conversion', 'signup', 'redemption') NOT NULL,
            user_id INT DEFAULT NULL,
            session_id VARCHAR(100),
            page_url VARCHAR(255),
            order_id INT DEFAULT NULL,
            order_value DECIMAL(10,2) DEFAULT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_promo (promo_id, promo_type),
            INDEX idx_event (event_type),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'promo_analytics' created";
    
    // 6. User Promo Interactions
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_promo_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_identifier VARCHAR(100) NOT NULL COMMENT 'User ID or session ID',
            promo_id INT NOT NULL,
            promo_type ENUM('exit_intent', 'signup_incentive', 'homepage_banner') NOT NULL,
            interaction_type ENUM('viewed', 'dismissed', 'converted') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_interaction (user_identifier, promo_id, promo_type),
            INDEX idx_user (user_identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✅ Table 'user_promo_interactions' created";
    
    // Insert sample data
    $db->exec("
        INSERT IGNORE INTO promotions (promo_name, promo_type, promo_message, promo_badge, discount_type, discount_value, min_order_value, show_on_homepage, show_on_product_page, show_on_cart, is_active, priority, banner_color)
        VALUES 
        ('Free Shipping Offer', 'free_shipping', 'Free Shipping on orders above ₹999', 'FREE SHIPPING', 'free_shipping', 0, 999, TRUE, TRUE, TRUE, TRUE, 10, '#10B981'),
        ('First Order Discount', 'first_order', 'Get 10% OFF on your first order', 'FIRST ORDER', 'percentage', 10, 0, TRUE, TRUE, TRUE, TRUE, 9, '#8B5CF6')
    ");
    $success[] = "✅ Sample promotions inserted";
    
} catch (PDOException $e) {
    $errors[] = "❌ Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Tables Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #7f8c8d; margin-bottom: 30px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; border-radius: 6px; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 6px; color: #721c24; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 6px; color: #856404; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px 0 0; transition: all 0.3s; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        ul { margin: 15px 0; padding-left: 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 Promo Tables Setup</h1>
        <p class="subtitle">Database table creation results</p>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <h3 style="margin-bottom: 10px;">✅ Success!</h3>
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <h3 style="margin-bottom: 10px;">❌ Errors</h3>
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ Security Notice:</strong> Please delete this file (<code>create_promo_tables_simple.php</code>) immediately after setup is complete for security reasons.
        </div>
        
        <div style="margin-top: 30px;">
            <a href="manage_promotions.php" class="btn">Manage Promotions</a>
            <a href="manage_promo_codes.php" class="btn">Manage Promo Codes</a>
            <a href="promo_system_debug.php" class="btn">Run Diagnostics</a>
        </div>
    </div>
</body>
</html>
