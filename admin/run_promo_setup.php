<?php
/**
 * PROMO TABLES SETUP
 * Creates all promotional system tables
 * DELETE THIS FILE AFTER RUNNING
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();
if (!$db) { die("DB connection failed"); }

$results = [];

$tables = [
    'promotions' => "CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promo_name VARCHAR(255) NOT NULL,
        promo_type ENUM('discount','free_shipping','combo','first_order','bundle','seasonal','best_seller','buy_more_save_more') NOT NULL,
        promo_message TEXT NOT NULL,
        promo_badge VARCHAR(100),
        discount_type ENUM('percentage','fixed','free_shipping') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) DEFAULT 0,
        min_order_value DECIMAL(10,2) DEFAULT 0,
        max_discount DECIMAL(10,2) DEFAULT NULL,
        coupon_code VARCHAR(50) DEFAULT NULL,
        target_type ENUM('all','category','product','new_users','returning_users') DEFAULT 'all',
        target_ids TEXT,
        show_on_homepage BOOLEAN DEFAULT FALSE,
        show_on_product_page BOOLEAN DEFAULT FALSE,
        show_on_cart BOOLEAN DEFAULT FALSE,
        show_on_checkout BOOLEAN DEFAULT FALSE,
        show_exit_intent BOOLEAN DEFAULT FALSE,
        show_sticky_mobile BOOLEAN DEFAULT FALSE,
        start_date DATETIME DEFAULT NULL,
        end_date DATETIME DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        priority INT DEFAULT 0,
        banner_color VARCHAR(20) DEFAULT '#FF6B6B',
        text_color VARCHAR(20) DEFAULT '#FFFFFF',
        icon VARCHAR(50) DEFAULT 'tag',
        show_countdown BOOLEAN DEFAULT FALSE,
        urgency_message VARCHAR(255) DEFAULT NULL,
        stock_threshold INT DEFAULT NULL,
        views INT DEFAULT 0,
        clicks INT DEFAULT 0,
        conversions INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active),
        INDEX idx_dates (start_date, end_date),
        INDEX idx_type (promo_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'exit_intent_popups' => "CREATE TABLE IF NOT EXISTS exit_intent_popups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        popup_name VARCHAR(255) NOT NULL,
        headline VARCHAR(255) NOT NULL,
        subheadline TEXT,
        offer_text TEXT NOT NULL,
        cta_text VARCHAR(100) DEFAULT 'Claim Offer',
        cta_link VARCHAR(255),
        auto_apply_coupon VARCHAR(50) DEFAULT NULL,
        discount_value DECIMAL(10,2) DEFAULT 0,
        show_on_pages ENUM('all','product','cart','checkout','homepage') DEFAULT 'all',
        trigger_delay INT DEFAULT 3,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'signup_incentives' => "CREATE TABLE IF NOT EXISTS signup_incentives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        incentive_name VARCHAR(255) NOT NULL,
        incentive_type ENUM('discount','free_shipping','points','gift') NOT NULL,
        headline VARCHAR(255) NOT NULL,
        description TEXT,
        discount_type ENUM('percentage','fixed') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) DEFAULT 0,
        coupon_code VARCHAR(50) DEFAULT NULL,
        auto_apply BOOLEAN DEFAULT TRUE,
        show_on_register_page BOOLEAN DEFAULT TRUE,
        show_on_checkout BOOLEAN DEFAULT TRUE,
        show_as_popup BOOLEAN DEFAULT FALSE,
        valid_days INT DEFAULT 30,
        min_order_value DECIMAL(10,2) DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        priority INT DEFAULT 0,
        signups_attributed INT DEFAULT 0,
        redemptions INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'homepage_banners' => "CREATE TABLE IF NOT EXISTS homepage_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        banner_name VARCHAR(255) NOT NULL,
        banner_type ENUM('hero','strip','card','floating') NOT NULL,
        headline VARCHAR(255) NOT NULL,
        subheadline TEXT,
        cta_text VARCHAR(100),
        cta_link VARCHAR(255),
        background_image VARCHAR(255) DEFAULT NULL,
        background_color VARCHAR(20) DEFAULT '#FF6B6B',
        text_color VARCHAR(20) DEFAULT '#FFFFFF',
        position ENUM('top','middle','bottom','floating') DEFAULT 'top',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'promo_analytics' => "CREATE TABLE IF NOT EXISTS promo_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promo_id INT NOT NULL,
        promo_type ENUM('promotion','exit_intent','signup_incentive','homepage_banner') NOT NULL,
        event_type ENUM('view','click','conversion','signup','redemption') NOT NULL,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'user_promo_interactions' => "CREATE TABLE IF NOT EXISTS user_promo_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_identifier VARCHAR(100) NOT NULL,
        promo_id INT NOT NULL,
        promo_type ENUM('exit_intent','signup_incentive','homepage_banner') NOT NULL,
        interaction_type ENUM('viewed','dismissed','converted') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_interaction (user_identifier, promo_id, promo_type),
        INDEX idx_user (user_identifier)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        $results[] = ['name' => $name, 'status' => 'success'];
    } catch (PDOException $e) {
        $results[] = ['name' => $name, 'status' => 'error', 'msg' => $e->getMessage()];
    }
}

// Insert sample data
try {
    $db->exec("INSERT IGNORE INTO promotions (promo_name, promo_type, promo_message, promo_badge, discount_type, discount_value, min_order_value, show_on_homepage, show_on_product_page, show_on_cart, is_active, priority, banner_color) VALUES ('Free Shipping Offer', 'free_shipping', 'Free Shipping on orders above Rs.999', 'FREE SHIPPING', 'free_shipping', 0, 999, TRUE, TRUE, TRUE, TRUE, 10, '#10B981'), ('First Order Discount', 'first_order', 'Get 10% OFF on your first order', 'FIRST ORDER', 'percentage', 10, 0, TRUE, TRUE, TRUE, TRUE, 9, '#8B5CF6')");
    $results[] = ['name' => 'sample_data', 'status' => 'success'];
} catch (PDOException $e) {
    $results[] = ['name' => 'sample_data', 'status' => 'error', 'msg' => $e->getMessage()];
}

$allSuccess = !array_filter($results, fn($r) => $r['status'] === 'error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Promo Setup Result</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f2f5;padding:30px}
.card{max-width:700px;margin:0 auto;background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
h1{font-size:24px;margin-bottom:5px}
.sub{color:#666;margin-bottom:25px}
.item{display:flex;align-items:center;gap:10px;padding:10px 14px;margin:6px 0;border-radius:8px}
.ok{background:#d4edda;color:#155724}
.err{background:#f8d7da;color:#721c24}
.warn{background:#fff3cd;color:#856404;padding:14px;border-radius:8px;margin:20px 0}
.btn{display:inline-block;padding:10px 22px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;margin:5px 5px 0 0}
.btn:hover{background:#2563eb}
</style>
</head>
<body>
<div class="card">
<h1><?= $allSuccess ? '&#10004; Setup Complete!' : '&#9888; Setup Partial' ?></h1>
<p class="sub">Promotional system database tables</p>

<?php foreach ($results as $r): ?>
<div class="item <?= $r['status']==='success'?'ok':'err' ?>">
    <strong><?= $r['status']==='success'?'&#10004;':'&#10008;' ?></strong>
    <span><strong><?= htmlspecialchars($r['name']) ?></strong><?= isset($r['msg'])?' — '.htmlspecialchars($r['msg']):'' ?></span>
</div>
<?php endforeach; ?>

<div class="warn">
    <strong>&#9888; Security:</strong> Delete this file (<code>run_promo_setup.php</code>) from the server now.
</div>

<div style="margin-top:20px">
    <a href="manage_promotions.php" class="btn">Manage Promotions</a>
    <a href="manage_promo_codes.php" class="btn">Manage Promo Codes</a>
    <a href="promo_system_debug.php" class="btn">Run Diagnostics</a>
</div>
</div>
</body>
</html>
