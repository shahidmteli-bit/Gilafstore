-- ============================================================
-- GilafStore CRM Tables Migration
-- Run this in your MySQL database to create all CRM tables
-- ============================================================

-- 1. crm_settings - Stores CRM configuration
CREATE TABLE IF NOT EXISTS crm_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO crm_settings (setting_key, setting_value, setting_type) VALUES
('crm_enabled', '0', 'boolean'),
('crm_api_url', 'http://localhost:3000', 'string'),
('crm_webhook_secret', '', 'string'),
('whatsapp_otp_enabled', '0', 'boolean'),
('whatsapp_otp_expiry', '300', 'integer'),
('whatsapp_otp_max_attempts', '5', 'integer'),
('whatsapp_otp_resend_cooldown', '60', 'integer'),
('whatsapp_otp_rate_limit', '10', 'integer'),
('cart_recovery_enabled', '0', 'boolean'),
('cart_recovery_delay_1', '15', 'integer'),
('cart_recovery_delay_2', '60', 'integer'),
('cart_recovery_delay_3', '1440', 'integer'),
('cart_recovery_discount', '', 'string')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 2. crm_api_keys - Stores API keys for WACRM authentication
CREATE TABLE IF NOT EXISTS crm_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(100) NOT NULL,
    api_secret VARCHAR(100) NOT NULL,
    permissions JSON,
    is_active TINYINT(1) DEFAULT 1,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. crm_event_queue - Event queue for async processing
CREATE TABLE IF NOT EXISTS crm_event_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload JSON,
    priority INT DEFAULT 5,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    locked_by VARCHAR(50) NULL,
    locked_at DATETIME NULL,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority),
    INDEX idx_locked (locked_by, status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. crm_whatsapp_otp - OTP storage
CREATE TABLE IF NOT EXISTS crm_whatsapp_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(50) DEFAULT 'login',
    ip_address VARCHAR(45),
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    whatsapp_message_id VARCHAR(100),
    expires_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_status (phone, status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. crm_otp_rate_limits - Rate limiting for OTP
CREATE TABLE IF NOT EXISTS crm_otp_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    identifier_type VARCHAR(20) NOT NULL,
    request_count INT DEFAULT 1,
    blocked_until DATETIME NULL,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_type (identifier, identifier_type, window_start),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. crm_customer_sync - Customer sync tracking
CREATE TABLE IF NOT EXISTS crm_customer_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_user_id INT NOT NULL,
    crm_contact_id VARCHAR(100),
    sync_status VARCHAR(20) DEFAULT 'pending',
    last_sync_at DATETIME NULL,
    sync_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_local_user (local_user_id),
    INDEX idx_sync_status (sync_status),
    INDEX idx_last_sync (last_sync_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. crm_webhook_logs - Webhook event logging
CREATE TABLE IF NOT EXISTS crm_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    direction VARCHAR(20) NOT NULL COMMENT 'incoming or outgoing',
    event_type VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255),
    payload JSON,
    response_code INT,
    response_body TEXT,
    status VARCHAR(20),
    duration_ms INT,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_direction_event (direction, event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. crm_activity_log - Activity logging
CREATE TABLE IF NOT EXISTS crm_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type VARCHAR(20) NOT NULL COMMENT 'admin, system, or user',
    actor_id VARCHAR(50),
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id VARCHAR(50),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. crm_abandoned_carts - Abandoned cart tracking
CREATE TABLE IF NOT EXISTS crm_abandoned_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(100),
    cart_data JSON,
    total_value DECIMAL(10,2),
    reminder_1_sent_at DATETIME NULL,
    reminder_2_sent_at DATETIME NULL,
    reminder_3_sent_at DATETIME NULL,
    recovered_at DATETIME NULL,
    recovery_order_id INT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_recovered (recovered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Migration complete - all CRM tables created
-- ============================================================
