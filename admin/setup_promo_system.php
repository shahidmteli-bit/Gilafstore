<?php
/**
 * ADVANCED PROMO + CRO SYSTEM - DATABASE SETUP
 * Creates all necessary tables for promotional system
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication
require_admin();

$db = get_db_connection();

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
            
            -- Targeting
            target_type ENUM('all', 'category', 'product', 'new_users', 'returning_users') DEFAULT 'all',
            target_ids TEXT COMMENT 'JSON array of category/product IDs',
            
            -- Display Settings
            show_on_homepage BOOLEAN DEFAULT FALSE,
            show_on_product_page BOOLEAN DEFAULT FALSE,
            show_on_cart BOOLEAN DEFAULT FALSE,
            show_on_checkout BOOLEAN DEFAULT FALSE,
            show_exit_intent BOOLEAN DEFAULT FALSE,
            show_sticky_mobile BOOLEAN DEFAULT FALSE,
            
            -- Scheduling
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            
            -- Priority & Design
            priority INT DEFAULT 0 COMMENT 'Higher priority shows first',
            banner_color VARCHAR(20) DEFAULT '#FF6B6B',
            text_color VARCHAR(20) DEFAULT '#FFFFFF',
            icon VARCHAR(50) DEFAULT 'tag',
            
            -- Urgency Settings
            show_countdown BOOLEAN DEFAULT FALSE,
            urgency_message VARCHAR(255) DEFAULT NULL,
            stock_threshold INT DEFAULT NULL COMMENT 'Show urgency when stock below this',
            
            -- Analytics
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
            
            -- Coupon Integration
            auto_apply_coupon VARCHAR(50) DEFAULT NULL,
            discount_value DECIMAL(10,2) DEFAULT 0,
            
            -- Targeting
            show_on_pages ENUM('all', 'product', 'cart', 'checkout', 'homepage') DEFAULT 'all',
            trigger_delay INT DEFAULT 3 COMMENT 'Seconds before showing',
            
            -- Design
            background_color VARCHAR(20) DEFAULT '#FFFFFF',
            overlay_color VARCHAR(20) DEFAULT 'rgba(0,0,0,0.7)',
            image_url VARCHAR(255) DEFAULT NULL,
            
            -- Behavior
            show_once_per_session BOOLEAN DEFAULT TRUE,
            show_once_per_user BOOLEAN DEFAULT FALSE,
            
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0,
            
            -- Analytics
            impressions INT DEFAULT 0,
            conversions INT DEFAULT 0,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 3. Account Creation Incentives
    $db->exec("
        CREATE TABLE IF NOT EXISTS signup_incentives (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incentive_name VARCHAR(255) NOT NULL,
            incentive_type ENUM('discount', 'free_shipping', 'points', 'gift') NOT NULL,
            headline VARCHAR(255) NOT NULL,
            description TEXT,
            
            -- Reward Details
            discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            discount_value DECIMAL(10,2) DEFAULT 0,
            coupon_code VARCHAR(50) DEFAULT NULL,
            auto_apply BOOLEAN DEFAULT TRUE,
            
            -- Display Settings
            show_on_register_page BOOLEAN DEFAULT TRUE,
            show_on_checkout BOOLEAN DEFAULT TRUE,
            show_as_popup BOOLEAN DEFAULT FALSE,
            
            -- Validity
            valid_days INT DEFAULT 30 COMMENT 'Days after signup',
            min_order_value DECIMAL(10,2) DEFAULT 0,
            
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0,
            
            -- Analytics
            signups_attributed INT DEFAULT 0,
            redemptions INT DEFAULT 0,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 4. Homepage Promotional Banners
    $db->exec("
        CREATE TABLE IF NOT EXISTS homepage_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            banner_name VARCHAR(255) NOT NULL,
            banner_type ENUM('hero', 'strip', 'card', 'floating') NOT NULL,
            
            -- Content
            headline VARCHAR(255) NOT NULL,
            subheadline TEXT,
            cta_text VARCHAR(100),
            cta_link VARCHAR(255),
            
            -- Design
            background_image VARCHAR(255) DEFAULT NULL,
            background_color VARCHAR(20) DEFAULT '#FF6B6B',
            text_color VARCHAR(20) DEFAULT '#FFFFFF',
            position ENUM('top', 'middle', 'bottom', 'floating') DEFAULT 'top',
            
            -- Scheduling
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            
            -- Display Settings
            show_desktop BOOLEAN DEFAULT TRUE,
            show_mobile BOOLEAN DEFAULT TRUE,
            show_countdown BOOLEAN DEFAULT FALSE,
            
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            
            -- Analytics
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 5. Promo Analytics Tracking
    $db->exec("
        CREATE TABLE IF NOT EXISTS promo_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            promo_id INT NOT NULL,
            promo_type ENUM('promotion', 'exit_intent', 'signup_incentive', 'homepage_banner') NOT NULL,
            event_type ENUM('view', 'click', 'conversion', 'signup', 'redemption') NOT NULL,
            
            user_id INT DEFAULT NULL,
            session_id VARCHAR(100),
            page_url VARCHAR(255),
            
            -- Conversion Details
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
    
    // 6. User Promo Interactions (prevent duplicate popups)
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
    
    // Insert default promotions
    $db->exec("
        INSERT INTO promotions (promo_name, promo_type, promo_message, promo_badge, discount_type, discount_value, min_order_value, show_on_homepage, show_on_product_page, show_on_cart, is_active, priority, banner_color)
        VALUES 
        ('Free Shipping Offer', 'free_shipping', 'Free Shipping on orders above ₹999', 'FREE SHIPPING', 'free_shipping', 0, 999, TRUE, TRUE, TRUE, TRUE, 10, '#10B981'),
        ('First Order Discount', 'first_order', 'Get 10% OFF on your first order', 'FIRST ORDER', 'percentage', 10, 0, TRUE, TRUE, TRUE, TRUE, 9, '#8B5CF6'),
        ('Limited Time Deal', 'seasonal', 'Limited Time Offer - Save Extra Today!', 'LIMITED TIME', 'percentage', 15, 499, TRUE, TRUE, FALSE, TRUE, 8, '#EF4444')
    ");
    
    // Insert default exit intent popup
    $db->exec("
        INSERT INTO exit_intent_popups (popup_name, headline, subheadline, offer_text, cta_text, auto_apply_coupon, discount_value, show_on_pages, is_active, priority)
        VALUES 
        ('Exit Discount Offer', 'Wait! Don\\'t Leave Empty Handed', 'Complete your order now and get exclusive savings', 'Get 10% OFF your order with code: SAVE10', 'Claim My Discount', 'SAVE10', 10, 'all', TRUE, 10)
    ");
    
    // Insert default signup incentive
    $db->exec("
        INSERT INTO signup_incentives (incentive_name, incentive_type, headline, description, discount_type, discount_value, coupon_code, show_on_register_page, show_on_checkout, is_active)
        VALUES 
        ('Welcome Discount', 'discount', 'Create Account & Save 10%', 'Sign up today to unlock exclusive member benefits and get 10% off your first order', 'percentage', 10, 'WELCOME10', TRUE, TRUE, TRUE)
    ");
    
    // Insert default homepage banner
    $db->exec("
        INSERT INTO homepage_banners (banner_name, banner_type, headline, subheadline, cta_text, cta_link, background_color, text_color, position, is_active, sort_order)
        VALUES 
        ('Top Promo Strip', 'strip', '🎉 Special Offer: Free Shipping on Orders Above ₹999', 'Shop Now & Save!', 'Shop Now', '/shop.php', '#10B981', '#FFFFFF', 'top', TRUE, 1)
    ");
    
    echo "✅ Promotional system database setup completed successfully!\n\n";
    echo "Tables created:\n";
    echo "- promotions\n";
    echo "- exit_intent_popups\n";
    echo "- signup_incentives\n";
    echo "- homepage_banners\n";
    echo "- promo_analytics\n";
    echo "- user_promo_interactions\n\n";
    echo "Default promotions, popups, and banners have been added.\n";
    echo "Access admin panel to manage promotions.\n";
    
} catch (PDOException $e) {
    echo "❌ Error setting up promotional system: " . $e->getMessage() . "\n";
}
