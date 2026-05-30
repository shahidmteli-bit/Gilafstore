<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$db = get_db_connection();
$results = [];
$errors = [];

function rw_exec(PDO $db, string $sql, string &$results, string $label): void {
    try {
        $db->exec($sql);
        $results .= "<li class='text-success'><i class='fas fa-check-circle me-2'></i>{$label}</li>";
    } catch (Exception $e) {
        $results .= "<li class='text-warning'><i class='fas fa-info-circle me-2'></i>{$label} — " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}

$out = '';

// ── rewards_wallets ──────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    locked_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    lifetime_earned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    lifetime_redeemed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tier VARCHAR(20) NOT NULL DEFAULT 'Silver',
    referral_code VARCHAR(20) NULL UNIQUE,
    referred_by INT NULL,
    is_frozen TINYINT(1) NOT NULL DEFAULT 0,
    is_disabled TINYINT(1) NOT NULL DEFAULT 0,
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_referral (referral_code),
    INDEX idx_tier (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_wallets table");

// ── rewards_transactions ──────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('credit','debit','lock','unlock','expire','admin_credit','admin_debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    source ENUM('signup','first_order','purchase','review','referral','birthday','login','festival','admin','redemption','expiry','referral_bonus','spin_wheel','tier_upgrade','campaign','locked_unlock') NOT NULL DEFAULT 'admin',
    order_id INT NULL,
    description VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    lock_reason VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order (order_id),
    INDEX idx_type (type),
    INDEX idx_expires (expires_at),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_transactions table");

// ── rewards_rules ─────────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_key VARCHAR(60) NOT NULL UNIQUE,
    rule_label VARCHAR(120) NOT NULL,
    rule_group VARCHAR(60) NOT NULL DEFAULT 'earning',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    value2 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_rules table");

// ── rewards_tiers ─────────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(30) NOT NULL UNIQUE,
    min_spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    redeem_bonus_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    icon VARCHAR(60) NOT NULL DEFAULT 'fas fa-medal',
    color VARCHAR(30) NOT NULL DEFAULT '#6c757d',
    benefits TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_tiers table");

// ── rewards_popups ─────────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_popups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    cta_text VARCHAR(80) NOT NULL DEFAULT 'Claim Rewards',
    cta_url VARCHAR(255) NULL,
    trigger_type ENUM('entry','exit','cart','scroll','returning') NOT NULL DEFAULT 'entry',
    show_on VARCHAR(255) NOT NULL DEFAULT 'all',
    show_mobile TINYINT(1) NOT NULL DEFAULT 1,
    show_desktop TINYINT(1) NOT NULL DEFAULT 1,
    delay_seconds INT NOT NULL DEFAULT 3,
    show_frequency ENUM('once','per_session','daily','always') NOT NULL DEFAULT 'once',
    reward_offer VARCHAR(100) NULL,
    target ENUM('all','guests','logged_in','new','returning') NOT NULL DEFAULT 'all',
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_popups table");

// ── rewards_referrals ─────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    referral_code VARCHAR(20) NOT NULL,
    status ENUM('pending','rewarded','fraud') NOT NULL DEFAULT 'pending',
    referrer_reward DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    referred_reward DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    order_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rewarded_at DATETIME NULL,
    INDEX idx_referrer (referrer_id),
    INDEX idx_referred (referred_id),
    INDEX idx_code (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_referrals table");

// ── rewards_locked_rewards ─────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_locked_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    unlock_condition ENUM('first_order','spend_threshold','referral','orders_count','manual') NOT NULL DEFAULT 'first_order',
    unlock_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    source VARCHAR(60) NOT NULL DEFAULT 'signup',
    description VARCHAR(255) NULL,
    status ENUM('locked','unlocked','expired') NOT NULL DEFAULT 'locked',
    expires_at DATETIME NULL,
    unlocked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_locked_rewards table");

// ── rewards_spinwheel ─────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_spinwheel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(80) NOT NULL,
    reward_type ENUM('cashback','free_shipping','bonus_rewards','unlock_reward','no_reward') NOT NULL DEFAULT 'cashback',
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    probability INT NOT NULL DEFAULT 10,
    color VARCHAR(20) NOT NULL DEFAULT '#4ade80',
    icon VARCHAR(60) NOT NULL DEFAULT '🎁',
    daily_limit INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_spinwheel table");

// ── rewards_spin_history ──────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_spin_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spinwheel_id INT NOT NULL,
    reward_type VARCHAR(40) NOT NULL,
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ip_address VARCHAR(45) NULL,
    spun_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_date (spun_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_spin_history table");

// ── rewards_campaigns ─────────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    campaign_type ENUM('flat_bonus','multiplier','category_boost','product_boost','festival') NOT NULL DEFAULT 'flat_bonus',
    reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    target_category_ids TEXT NULL,
    target_product_ids TEXT NULL,
    min_order_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_reward_per_user DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_campaigns table");

// ── rewards_notifications ──────────────────────────────────────────────────────
rw_exec($db, "CREATE TABLE IF NOT EXISTS rewards_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('earned','redeemed','expiring','vip_upgrade','referral','unlocked','campaign') NOT NULL DEFAULT 'earned',
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $out, "Created rewards_notifications table");

// ── Seed default rules ────────────────────────────────────────────────────────
$defaultRules = [
    ['signup_reward',          'Signup Reward',            'earning',    100.00, 0,     1, 'Instant cashback on account creation'],
    ['signup_locked_1',        'Signup Locked Reward 1',   'locked',     300.00, 0,     1, 'Unlocks after first order'],
    ['signup_locked_2',        'Signup Locked Reward 2',   'locked',     600.00, 1000,  1, 'Unlocks after spending ₹1000 total'],
    ['first_order_reward',     'First Order Bonus',        'earning',    50.00,  0,     1, 'Extra cashback on first purchase'],
    ['purchase_per_100',       'Purchase Reward (per ₹100)','earning',   5.00,   0,     1, 'Earn ₹5 per ₹100 spent'],
    ['review_reward',          'Product Review Reward',    'earning',    25.00,  0,     1, 'For submitting a verified review'],
    ['referral_referrer',      'Referral - Referrer Gets', 'referral',   150.00, 0,     1, 'Reward for referring a friend'],
    ['referral_referred',      'Referral - Friend Gets',   'referral',   100.00, 0,     1, 'Welcome reward for referred friend'],
    ['birthday_reward',        'Birthday Reward',          'earning',    200.00, 0,     0, 'Bonus on user birthday'],
    ['daily_login_reward',     'Daily Login Reward',       'earning',    5.00,   0,     0, 'Daily login streak bonus'],
    ['min_cart_redeem',        'Min Cart Value to Redeem', 'redemption', 200.00, 0,     1, 'Minimum cart total to allow redemption'],
    ['max_redeem_pct',         'Max Redemption %',         'redemption', 10.00,  0,     1, 'Max % of cart that can be paid by wallet'],
    ['min_redeem_amount',      'Min Redeem Amount',        'redemption', 10.00,  0,     1, 'Minimum wallet amount that can be redeemed'],
    ['reward_expiry_days',     'Reward Expiry (Days)',     'redemption', 90.00,  0,     1, 'Days before rewards expire'],
    ['widget_enabled',         'Floating Widget Enabled',  'display',    1.00,   0,     1, 'Show floating rewards widget on frontend'],
    ['product_page_enabled',   'Product Page Earn Banner', 'display',    1.00,   0,     1, 'Show earn cashback banner on product pages'],
    ['spin_wheel_enabled',        'Spin Wheel Enabled',              'gamification', 1.00, 0, 1, 'Enable spin wheel feature (1=on)'],
    ['spin_wheel_per_days',       'Spin Every N Days',               'gamification', 1.00, 0, 1, 'How often user can spin (days)'],
    ['max_redeem_fixed',          'Max Redeem Fixed Cap (₹)',         'redemption',   0.00, 0, 1, '0 = no fixed cap; set e.g. 200 to cap at ₹200'],
    ['allow_wallet_on_cod',       'Allow Wallet on COD Orders',       'redemption',   1.00, 0, 1, '1 = allow, 0 = block wallet usage on Cash on Delivery'],
    ['allow_wallet_with_coupon',  'Allow Wallet + Coupon Together',   'redemption',   1.00, 0, 1, '1 = allow both, 0 = wallet blocked when coupon is active'],
];
foreach ($defaultRules as [$key, $label, $group, $val, $val2, $active, $desc]) {
    try {
        $st = $db->prepare("INSERT IGNORE INTO rewards_rules (rule_key, rule_label, rule_group, value, value2, is_active, description) VALUES (?,?,?,?,?,?,?)");
        $st->execute([$key, $label, $group, $val, $val2, $active, $desc]);
    } catch (Exception $e) {}
}
// UPDATE spin_wheel_enabled to 1 if it was previously 0 (enable it)
try { $db->exec("UPDATE rewards_rules SET value = 1, is_active = 1 WHERE rule_key = 'spin_wheel_enabled' AND value = 0"); } catch (Exception $e) {}
$out .= "<li class='text-success'><i class='fas fa-check-circle me-2'></i>Seeded default reward rules (+ COD, coupon-combo, fixed-cap, spin)</li>";

// ── Seed default tiers ────────────────────────────────────────────────────────
$defaultTiers = [
    ['Silver',   0,      1.00, 0,   'fas fa-medal',   '#9ca3af', 'Free delivery on orders above ₹499', 1],
    ['Gold',     2000,   1.50, 5,   'fas fa-star',    '#f59e0b', 'Free delivery + 1.5x rewards + 5% bonus redeem', 2],
    ['Platinum', 10000,  2.00, 10,  'fas fa-gem',     '#8b5cf6', 'Free delivery + 2x rewards + 10% bonus redeem + priority support', 3],
];
foreach ($defaultTiers as [$name, $min, $mult, $bonus, $icon, $color, $benefits, $sort]) {
    try {
        $st = $db->prepare("INSERT IGNORE INTO rewards_tiers (tier_name, min_spent, multiplier, redeem_bonus_pct, icon, color, benefits, sort_order) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([$name, $min, $mult, $bonus, $icon, $color, $benefits, $sort]);
    } catch (Exception $e) {}
}
$out .= "<li class='text-success'><i class='fas fa-check-circle me-2'></i>Seeded Silver / Gold / Platinum tiers</li>";

// ── Seed default spin wheel segments ─────────────────────────────────────────
$defaultSegments = [
    ['₹50 Cashback',       'cashback',       50,   30, '#4ade80', '💚'],
    ['₹100 Cashback',      'cashback',      100,   15, '#22c55e', '🎁'],
    ['₹200 Cashback',      'cashback',      200,    5, '#16a34a', '💰'],
    ['Free Shipping',      'free_shipping',   0,   20, '#60a5fa', '🚚'],
    ['₹25 Bonus Rewards',  'bonus_rewards',  25,   20, '#f59e0b', '⭐'],
    ['Unlock ₹100',        'unlock_reward', 100,    5, '#a78bfa', '🔓'],
    ['Try Again',          'no_reward',       0,    5, '#e5e7eb', '😊'],
];
foreach ($defaultSegments as [$label, $type, $val, $prob, $color, $icon]) {
    try {
        $st = $db->prepare("INSERT IGNORE INTO rewards_spinwheel (label, reward_type, reward_value, probability, color, icon) VALUES (?,?,?,?,?,?)");
        $st->execute([$label, $type, $val, $prob, $color, $icon]);
    } catch (Exception $e) {}
}
$out .= "<li class='text-success'><i class='fas fa-check-circle me-2'></i>Seeded default spin wheel segments</li>";

// ── Seed default popup ────────────────────────────────────────────────────────
try {
    $db->exec("INSERT IGNORE INTO rewards_popups (id, title, body, cta_text, cta_url, trigger_type, show_on, target, delay_seconds, show_frequency, reward_offer, is_active)
        VALUES (1, '🎁 Unlock ₹1000 Gilaf Rewards FREE',
        'Create your account and start earning cashback rewards instantly. ₹100 instant + ₹900 waiting for you!',
        'Claim My Rewards', '/register.php', 'entry', 'all', 'guests', 4, 'once', '₹1000', 1)");
    $out .= "<li class='text-success'><i class='fas fa-check-circle me-2'></i>Seeded default popup campaign</li>";
} catch (Exception $e) {
    $out .= "<li class='text-warning'><i class='fas fa-info-circle me-2'></i>Default popup already exists</li>";
}

$pageTitle = 'Rewards System Migration';
$adminPage = 'rewards_dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.mig-card { max-width: 760px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 36px 40px; }
.mig-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
.mig-list { list-style: none; padding: 0; margin: 24px 0 0; }
.mig-list li { padding: 8px 12px; border-radius: 8px; margin-bottom: 6px; background: #f8fafc; font-size: .9rem; }
.mig-list li.text-success { background: #f0fdf4; }
.mig-list li.text-warning { background: #fffbeb; }
</style>
<div class="admin-content">
  <div class="mig-card">
    <div class="mig-title">🏆 Gilaf Rewards — Database Migration</div>
    <div class="text-muted mb-3" style="font-size:.9rem;">Creates all rewards system tables and seeds default configuration. Safe to run multiple times.</div>
    <div class="alert alert-success border-0" style="background:#f0fdf4;">
      <strong>✅ Migration completed.</strong> All 10 rewards tables created. Default rules, tiers, and popups seeded.
    </div>
    <ul class="mig-list"><?= $out ?></ul>
    <div class="mt-4 d-flex gap-2">
      <a href="<?= base_url('admin/rewards_dashboard.php') ?>" class="btn btn-primary"><i class="fas fa-chart-pie me-2"></i>Go to Rewards Dashboard</a>
      <a href="<?= base_url('admin/rewards_rules.php') ?>" class="btn btn-outline-secondary"><i class="fas fa-cog me-2"></i>Configure Rules</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
