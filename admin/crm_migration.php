<?php
/**
 * WhatsApp CRM Integration — Database Migration
 * Creates all required tables for GilafStore ↔ WACRM integration.
 * Safe to run multiple times (uses IF NOT EXISTS).
 */

// Enable error display for debugging (remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../includes/db_connect.php';
    require_once __DIR__ . '/../includes/auth.php';
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Auth check
require_admin();

$results = [];

$queries = [
    // 1. CRM Integration Settings
    "CREATE TABLE IF NOT EXISTS crm_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type ENUM('string','json','boolean','integer') DEFAULT 'string',
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 2. API Keys for secure communication
    "CREATE TABLE IF NOT EXISTS crm_api_keys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(100) NOT NULL,
        api_key VARCHAR(128) NOT NULL UNIQUE,
        api_secret VARCHAR(128) NOT NULL,
        permissions JSON,
        is_active TINYINT(1) DEFAULT 1,
        last_used_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_key (api_key),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 3. Webhook delivery log
    "CREATE TABLE IF NOT EXISTS crm_webhook_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        direction ENUM('outgoing','incoming') NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        endpoint VARCHAR(500),
        payload JSON,
        response_code INT,
        response_body TEXT,
        status ENUM('pending','sent','delivered','failed','retrying') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 3,
        next_retry_at TIMESTAMP NULL,
        error_message TEXT,
        duration_ms INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        INDEX idx_status (status),
        INDEX idx_event_type (event_type),
        INDEX idx_created_at (created_at),
        INDEX idx_next_retry (next_retry_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 4. Event queue for background processing
    "CREATE TABLE IF NOT EXISTS crm_event_queue (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(100) NOT NULL,
        payload JSON NOT NULL,
        priority TINYINT DEFAULT 5,
        status ENUM('pending','processing','completed','failed','dead') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 3,
        locked_by VARCHAR(50) NULL,
        locked_at TIMESTAMP NULL,
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        INDEX idx_status_priority (status, priority),
        INDEX idx_event_type (event_type),
        INDEX idx_locked (locked_by, locked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 5. Customer CRM sync mapping
    "CREATE TABLE IF NOT EXISTS crm_customer_sync (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        local_user_id INT UNSIGNED NOT NULL,
        crm_contact_id VARCHAR(36),
        supabase_user_id VARCHAR(36),
        phone VARCHAR(20),
        email VARCHAR(255),
        sync_status ENUM('pending','synced','failed','conflict') DEFAULT 'pending',
        last_synced_at TIMESTAMP NULL,
        sync_hash VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_local_user (local_user_id),
        INDEX idx_crm_contact (crm_contact_id),
        INDEX idx_phone (phone),
        INDEX idx_sync_status (sync_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 6. WhatsApp OTP authentication
    "CREATE TABLE IF NOT EXISTS crm_whatsapp_otp (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(20) NOT NULL,
        otp_hash VARCHAR(128) NOT NULL,
        purpose ENUM('login','verify','reset') DEFAULT 'login',
        status ENUM('pending','sent','verified','expired','failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 5,
        ip_address VARCHAR(45),
        user_agent TEXT,
        device_fingerprint VARCHAR(128),
        whatsapp_message_id VARCHAR(100),
        expires_at TIMESTAMP NOT NULL,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone_status (phone, status),
        INDEX idx_expires (expires_at),
        INDEX idx_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 7. OTP rate limiting
    "CREATE TABLE IF NOT EXISTS crm_otp_rate_limits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(100) NOT NULL,
        identifier_type ENUM('phone','ip','device') DEFAULT 'phone',
        request_count INT DEFAULT 1,
        window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        blocked_until TIMESTAMP NULL,
        INDEX idx_identifier (identifier, identifier_type),
        INDEX idx_window (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 8. Abandoned cart tracking
    "CREATE TABLE IF NOT EXISTS crm_abandoned_carts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED,
        session_id VARCHAR(128),
        phone VARCHAR(20),
        email VARCHAR(255),
        cart_data JSON,
        cart_total DECIMAL(10,2) DEFAULT 0,
        item_count INT DEFAULT 0,
        recovery_status ENUM('active','reminded','recovered','expired','excluded') DEFAULT 'active',
        reminder_stage INT DEFAULT 0,
        last_reminder_at TIMESTAMP NULL,
        recovery_discount VARCHAR(50),
        recovery_link VARCHAR(500),
        recovered_order_id INT UNSIGNED,
        abandoned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        recovered_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        INDEX idx_user (user_id),
        INDEX idx_phone (phone),
        INDEX idx_status (recovery_status),
        INDEX idx_reminder (reminder_stage, last_reminder_at),
        INDEX idx_abandoned (abandoned_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 9. Order lifecycle notification tracking
    "CREATE TABLE IF NOT EXISTS crm_order_notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED,
        phone VARCHAR(20),
        event_type VARCHAR(50) NOT NULL,
        template_name VARCHAR(100),
        message_id VARCHAR(100),
        status ENUM('pending','sent','delivered','read','failed') DEFAULT 'pending',
        sent_at TIMESTAMP NULL,
        delivered_at TIMESTAMP NULL,
        read_at TIMESTAMP NULL,
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id),
        INDEX idx_event (event_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 10. Notification templates
    "CREATE TABLE IF NOT EXISTS crm_notification_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(100) NOT NULL UNIQUE,
        template_name VARCHAR(200) NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        channel ENUM('whatsapp','email','sms','push') DEFAULT 'whatsapp',
        whatsapp_template_name VARCHAR(100),
        whatsapp_template_lang VARCHAR(10) DEFAULT 'en',
        subject VARCHAR(255),
        body_template TEXT,
        variables JSON,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_channel (channel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 11. Activity/audit log
    "CREATE TABLE IF NOT EXISTS crm_activity_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_type ENUM('system','admin','user','webhook','cron') DEFAULT 'system',
        actor_id VARCHAR(50),
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50),
        entity_id VARCHAR(50),
        details JSON,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// Run all migrations
foreach ($queries as $i => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['index' => $i + 1, 'status' => 'success', 'message' => 'Table created/verified'];
    } catch (PDOException $e) {
        $results[] = ['index' => $i + 1, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// Seed default settings
$defaults = [
    ['crm_enabled', '0', 'boolean', 'Enable/disable CRM integration'],
    ['crm_api_url', 'http://localhost:3000', 'string', 'WACRM API base URL'],
    ['crm_webhook_secret', '', 'string', 'Shared webhook signing secret'],
    ['whatsapp_otp_enabled', '0', 'boolean', 'Enable WhatsApp OTP login'],
    ['whatsapp_otp_expiry', '300', 'integer', 'OTP expiry in seconds (default 5 min)'],
    ['whatsapp_otp_max_attempts', '5', 'integer', 'Max OTP verification attempts'],
    ['whatsapp_otp_resend_cooldown', '60', 'integer', 'Resend cooldown in seconds'],
    ['whatsapp_otp_rate_limit', '10', 'integer', 'Max OTPs per phone per hour'],
    ['cart_recovery_enabled', '0', 'boolean', 'Enable abandoned cart recovery'],
    ['cart_recovery_delay_1', '15', 'integer', 'First reminder delay in minutes'],
    ['cart_recovery_delay_2', '60', 'integer', 'Second reminder delay in minutes'],
    ['cart_recovery_delay_3', '1440', 'integer', 'Third reminder delay in minutes'],
    ['cart_recovery_discount', '', 'string', 'Discount code for cart recovery'],
    ['order_notifications_enabled', '0', 'boolean', 'Enable order lifecycle notifications'],
    ['customer_sync_enabled', '0', 'boolean', 'Enable auto customer sync to CRM'],
    ['customer_sync_interval', '300', 'integer', 'Sync interval in seconds'],
];

foreach ($defaults as $d) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO crm_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute($d);
    } catch (PDOException $e) {
        // Ignore duplicates
    }
}

// Seed default notification templates
$templates = [
    ['order_placed', 'Order Placed', 'order', 'whatsapp', 'order_confirmation', 'en', 'Order Confirmed', 'Hi {{customer_name}}, your order #{{order_id}} has been placed successfully! Total: ₹{{order_total}}. Track: {{tracking_url}}', '["customer_name","order_id","order_total","tracking_url"]'],
    ['payment_success', 'Payment Successful', 'order', 'whatsapp', 'payment_success', 'en', 'Payment Received', 'Hi {{customer_name}}, payment of ₹{{amount}} received for order #{{order_id}}. Thank you!', '["customer_name","amount","order_id"]'],
    ['order_shipped', 'Order Shipped', 'order', 'whatsapp', 'order_shipped', 'en', 'Order Shipped', 'Hi {{customer_name}}, your order #{{order_id}} has been shipped! Tracking: {{tracking_number}}. Track here: {{tracking_url}}', '["customer_name","order_id","tracking_number","tracking_url"]'],
    ['order_delivered', 'Order Delivered', 'order', 'whatsapp', 'order_delivered', 'en', 'Order Delivered', 'Hi {{customer_name}}, your order #{{order_id}} has been delivered! We hope you enjoy it. Leave a review: {{review_url}}', '["customer_name","order_id","review_url"]'],
    ['cart_reminder_1', 'Cart Reminder (15 min)', 'cart', 'whatsapp', 'cart_reminder', 'en', 'Forgot Something?', 'Hi {{customer_name}}, you left {{item_count}} item(s) in your cart worth ₹{{cart_total}}. Complete your order: {{checkout_url}}', '["customer_name","item_count","cart_total","checkout_url"]'],
    ['cart_reminder_2', 'Cart Reminder (1 hour)', 'cart', 'whatsapp', 'cart_reminder_urgent', 'en', 'Items Selling Fast!', 'Hi {{customer_name}}, your cart items are selling fast! {{product_names}}. Grab them before they\'re gone: {{checkout_url}}', '["customer_name","product_names","checkout_url"]'],
    ['cart_reminder_3', 'Cart Reminder (24 hours)', 'cart', 'whatsapp', 'cart_reminder_discount', 'en', 'Special Offer Inside!', 'Hi {{customer_name}}, complete your order and get {{discount}}% off! Use code: {{discount_code}}. Shop now: {{checkout_url}}', '["customer_name","discount","discount_code","checkout_url"]'],
    ['whatsapp_otp', 'WhatsApp OTP', 'auth', 'whatsapp', 'otp_verification', 'en', 'Your Login OTP', 'Your Gilaf Store verification code is: {{otp_code}}. Valid for {{expiry_minutes}} minutes. Do not share this code.', '["otp_code","expiry_minutes"]'],
    ['welcome_message', 'Welcome Message', 'engagement', 'whatsapp', 'welcome_new_customer', 'en', 'Welcome!', 'Welcome to Gilaf Store, {{customer_name}}! 🎉 Thank you for joining us. Explore our products: {{shop_url}}', '["customer_name","shop_url"]'],
    ['order_packed', 'Order Packed', 'order', 'whatsapp', 'order_packed', 'en', 'Order Packed', 'Hi {{customer_name}}, your order #{{order_id}} has been packed and is ready for dispatch!', '["customer_name","order_id"]'],
    ['out_for_delivery', 'Out for Delivery', 'order', 'whatsapp', 'out_for_delivery', 'en', 'Out for Delivery', 'Hi {{customer_name}}, your order #{{order_id}} is out for delivery! Expected today.', '["customer_name","order_id"]'],
    ['refund_initiated', 'Refund Initiated', 'order', 'whatsapp', 'refund_initiated', 'en', 'Refund Processing', 'Hi {{customer_name}}, refund of ₹{{refund_amount}} for order #{{order_id}} has been initiated. Expected in 5-7 business days.', '["customer_name","refund_amount","order_id"]'],
    ['cod_confirmation', 'COD Confirmation', 'order', 'whatsapp', 'cod_confirm', 'en', 'COD Order Confirmation', 'Hi {{customer_name}}, your COD order #{{order_id}} for ₹{{order_total}} has been confirmed. Please keep exact change ready.', '["customer_name","order_id","order_total"]'],
    ['payment_failed', 'Payment Failed', 'order', 'whatsapp', 'payment_failed', 'en', 'Payment Failed', 'Hi {{customer_name}}, payment for order #{{order_id}} failed. Please retry: {{retry_url}}', '["customer_name","order_id","retry_url"]'],
    ['order_cancelled', 'Order Cancelled', 'order', 'whatsapp', 'order_cancelled', 'en', 'Order Cancelled', 'Hi {{customer_name}}, your order #{{order_id}} has been cancelled. If you have questions, please contact support.', '["customer_name","order_id"]'],
];

foreach ($templates as $t) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO crm_notification_templates 
            (template_key, template_name, category, channel, whatsapp_template_name, whatsapp_template_lang, subject, body_template, variables) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($t);
    } catch (PDOException $e) {
        // Ignore duplicates
    }
}

// 11. Broadcasts table
$broadcastTables = [
    "CREATE TABLE IF NOT EXISTS crm_broadcasts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        template_key VARCHAR(100) NOT NULL,
        segment VARCHAR(50) DEFAULT 'all',
        recipient_count INT UNSIGNED DEFAULT 0,
        sent_count INT UNSIGNED DEFAULT 0,
        status ENUM('pending','scheduled','sending','completed','failed') DEFAULT 'pending',
        scheduled_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS crm_broadcast_recipients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        broadcast_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        phone VARCHAR(20) NOT NULL,
        status ENUM('pending','sent','delivered','failed') DEFAULT 'pending',
        sent_at DATETIME NULL,
        delivered_at DATETIME NULL,
        error_message TEXT NULL,
        INDEX idx_broadcast (broadcast_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($broadcastTables as $i => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['index' => '11.' . ($i + 1), 'status' => 'success', 'message' => 'Broadcast table created/verified'];
    } catch (PDOException $e) {
        $results[] = ['index' => '11.' . ($i + 1), 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// Generate initial API key if none exists
$existingKeys = $pdo->query("SELECT COUNT(*) FROM crm_api_keys")->fetchColumn();
if ($existingKeys == 0) {
    $apiKey = 'gcrm_' . bin2hex(random_bytes(24));
    $apiSecret = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO crm_api_keys (key_name, api_key, api_secret, permissions) VALUES (?, ?, ?, ?)")
        ->execute(['Default Integration Key', $apiKey, $apiSecret, json_encode(['*'])]);
    $results[] = ['index' => 'API', 'status' => 'success', 'message' => "API Key generated: $apiKey"];
}

// Output results
$pageTitle = 'CRM Migration — Gilaf Admin';
$adminPage = 'crm_integration';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="container-fluid">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:linear-gradient(135deg,#25D366,#128C7E);">
            <i class="fab fa-whatsapp text-white" style="font-size:24px;"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">WhatsApp CRM — Database Migration</h4>
            <small class="text-muted">Setting up integration tables and default configuration</small>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>#</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= $r['index'] ?></td>
                        <td>
                            <?php if ($r['status'] === 'success'): ?>
                                <span class="badge bg-success">✓ Success</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Error</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($r['message']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-3">
                <a href="<?= base_url('admin/crm_integration.php') ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-1"></i> Go to CRM Integration Panel
                </a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
