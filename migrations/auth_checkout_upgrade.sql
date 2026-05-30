-- ============================================================
-- Gilaf Store: Auth & Checkout Upgrade Migration
-- Phase 1: Guest Checkout + Auto Account Creation
-- Phase 2: Phone OTP Login + SMS Config
-- Phase 3: Google OAuth Login
-- Phase 4: SMS Logging + Failover
-- ============================================================

-- 1. Allow nullable password for OTP/Google/Guest users
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL DEFAULT NULL;

-- 2. Add phone column if not exists, auth_method, google_id
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_method ENUM('password','otp','google','guest') DEFAULT 'password';
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_guest TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified TINYINT(1) DEFAULT 0;

-- 3. OTP Codes table
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    purpose ENUM('login','signup','verify','order') DEFAULT 'login',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    is_used TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_purpose (phone, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. SMS Providers configuration (encrypted credentials)
CREATE TABLE IF NOT EXISTS sms_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(100) NOT NULL,
    provider_slug VARCHAR(50) NOT NULL UNIQUE,
    api_key TEXT DEFAULT NULL,
    api_secret TEXT DEFAULT NULL,
    sender_id VARCHAR(20) DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    auth_token TEXT DEFAULT NULL,
    base_url VARCHAR(500) DEFAULT NULL,
    otp_template_id VARCHAR(100) DEFAULT NULL,
    dlt_template_id VARCHAR(100) DEFAULT NULL,
    dlt_entity_id VARCHAR(100) DEFAULT NULL,
    country_code VARCHAR(10) DEFAULT '+91',
    rate_limit_per_min INT DEFAULT 10,
    timeout_seconds INT DEFAULT 30,
    retry_attempts INT DEFAULT 2,
    is_active TINYINT(1) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    is_fallback TINYINT(1) DEFAULT 0,
    priority INT DEFAULT 0,
    extra_config JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SMS Logs
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    provider_id INT DEFAULT NULL,
    provider_name VARCHAR(100) DEFAULT NULL,
    message_type ENUM('otp','notification','marketing','test') DEFAULT 'otp',
    message_content TEXT DEFAULT NULL,
    status ENUM('sent','delivered','failed','pending') DEFAULT 'pending',
    failure_reason TEXT DEFAULT NULL,
    api_response TEXT DEFAULT NULL,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_verified TINYINT(1) DEFAULT 0,
    cost DECIMAL(8,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Google OAuth accounts (links Google IDs to users)
CREATE TABLE IF NOT EXISTS oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider ENUM('google','facebook') DEFAULT 'google',
    provider_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255) DEFAULT NULL,
    provider_name VARCHAR(255) DEFAULT NULL,
    provider_avatar VARCHAR(500) DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_provider_id (provider, provider_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Guest orders tracking (before account merge)
CREATE TABLE IF NOT EXISTS guest_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL,
    guest_name VARCHAR(120) DEFAULT NULL,
    guest_email VARCHAR(160) DEFAULT NULL,
    guest_phone VARCHAR(20) DEFAULT NULL,
    merged_user_id INT DEFAULT NULL,
    merged_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_email (guest_email),
    INDEX idx_phone (guest_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Add guest fields to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_name VARCHAR(120) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_email VARCHAR(160) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_session_id VARCHAR(128) DEFAULT NULL;

-- 9. Insert default SMS provider templates
INSERT IGNORE INTO sms_providers (provider_name, provider_slug, base_url, country_code, is_active, priority) VALUES
('Fast2SMS', 'fast2sms', 'https://www.fast2sms.com/dev/bulkV2', '+91', 0, 1),
('MSG91', 'msg91', 'https://control.msg91.com/api/v5/otp', '+91', 0, 2),
('Twilio', 'twilio', 'https://api.twilio.com/2010-04-01', '+91', 0, 3),
('Textlocal', 'textlocal', 'https://api.textlocal.in/send/', '+91', 0, 4);
