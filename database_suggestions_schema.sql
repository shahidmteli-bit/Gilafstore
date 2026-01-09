-- ========================================
-- Idea & Suggestion Center - Database Schema
-- Centralized Improvement & Innovation Management System
-- ========================================

-- Drop tables if they exist (for clean installation)
DROP TABLE IF EXISTS suggestion_rewards;
DROP TABLE IF EXISTS suggestion_audit_log;
DROP TABLE IF EXISTS suggestions;

-- Main Suggestions Table
CREATE TABLE suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id VARCHAR(20) UNIQUE NOT NULL COMMENT 'Unique tracking ID (e.g., SUG-2024-0001)',
    
    -- Submission Details
    subject VARCHAR(255) NOT NULL,
    category ENUM('UI/UX', 'Performance', 'Features', 'Payments', 'Security', 'Content', 'Other') NOT NULL,
    description TEXT NOT NULL,
    
    -- User Information
    user_id INT DEFAULT NULL COMMENT 'NULL for guest users',
    user_email VARCHAR(255) DEFAULT NULL,
    user_name VARCHAR(100) DEFAULT NULL,
    is_guest BOOLEAN DEFAULT FALSE,
    
    -- Status & Workflow
    status ENUM('new', 'under_review', 'accepted', 'rejected', 'implemented') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    is_best_suggestion BOOLEAN DEFAULT FALSE,
    is_business_impact BOOLEAN DEFAULT FALSE,
    
    -- Review Details
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    
    -- Implementation Tracking
    implementation_status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    implementation_date DATE DEFAULT NULL,
    
    -- Metadata
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'website' COMMENT 'chatbot, footer, help, direct',
    
    -- Timestamps
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_status (status),
    KEY idx_category (category),
    KEY idx_user_id (user_id),
    KEY idx_user_email (user_email),
    KEY idx_submitted_at (submitted_at),
    KEY idx_is_best (is_best_suggestion),
    KEY idx_submission_id (submission_id),
    
    -- Foreign Keys
    CONSTRAINT fk_suggestion_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_suggestion_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suggestion Rewards Table
CREATE TABLE suggestion_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    suggestion_id INT NOT NULL,
    
    -- Reward Details
    reward_type ENUM('coupon', 'cashback', 'voucher', 'physical_gift', 'discount', 'points', 'other') NOT NULL,
    reward_value DECIMAL(10,2) DEFAULT 0.00,
    reward_description TEXT DEFAULT NULL,
    reward_code VARCHAR(50) DEFAULT NULL COMMENT 'Coupon/voucher code',
    
    -- Status
    status ENUM('pending', 'issued', 'claimed', 'expired') DEFAULT 'pending',
    issued_at DATETIME DEFAULT NULL,
    claimed_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    
    -- Assignment
    assigned_by INT DEFAULT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Metadata
    notes TEXT DEFAULT NULL,
    
    -- Indexes
    KEY idx_suggestion_id (suggestion_id),
    KEY idx_status (status),
    KEY idx_reward_code (reward_code),
    
    -- Foreign Keys
    CONSTRAINT fk_reward_suggestion FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_reward_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suggestion Audit Log Table
CREATE TABLE suggestion_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    suggestion_id INT NOT NULL,
    
    -- Action Details
    action_type ENUM('created', 'status_changed', 'reviewed', 'rewarded', 'updated', 'email_sent') NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    
    -- Actor
    performed_by INT DEFAULT NULL,
    performed_by_name VARCHAR(100) DEFAULT NULL,
    
    -- Metadata
    ip_address VARCHAR(45) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_suggestion_id (suggestion_id),
    KEY idx_action_type (action_type),
    KEY idx_performed_at (performed_at),
    
    -- Foreign Keys
    CONSTRAINT fk_audit_suggestion FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Notification Queue Table (for tracking sent emails)
CREATE TABLE suggestion_email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    suggestion_id INT NOT NULL,
    
    -- Email Details
    email_type ENUM('acknowledgment', 'accepted', 'rejected', 'reward', 'reminder') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    
    -- Status
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    
    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_suggestion_id (suggestion_id),
    KEY idx_email_type (email_type),
    KEY idx_status (status),
    
    -- Foreign Keys
    CONSTRAINT fk_email_suggestion FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories configuration (optional settings table)
CREATE TABLE suggestion_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO suggestion_settings (setting_key, setting_value, setting_type, description) VALUES
('enable_guest_submissions', 'true', 'boolean', 'Allow guest users to submit suggestions'),
('require_email', 'true', 'boolean', 'Require email for guest submissions'),
('auto_send_acknowledgment', 'true', 'boolean', 'Automatically send acknowledgment email'),
('auto_send_status_updates', 'true', 'boolean', 'Send email on status changes'),
('min_description_length', '50', 'number', 'Minimum characters for description'),
('max_submissions_per_day', '5', 'number', 'Max submissions per user/IP per day'),
('reward_points_enabled', 'true', 'boolean', 'Enable reward points system'),
('default_reward_points', '100', 'number', 'Default points for accepted suggestions');

-- Create indexes for performance
CREATE INDEX idx_suggestions_analytics ON suggestions(status, category, submitted_at);
CREATE INDEX idx_suggestions_best ON suggestions(is_best_suggestion, is_business_impact, submitted_at);

-- Success message
SELECT 'Idea & Suggestion Center database schema created successfully!' AS Status,
       '4 tables created: suggestions, suggestion_rewards, suggestion_audit_log, suggestion_email_log' AS Info;
