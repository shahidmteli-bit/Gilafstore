-- Database Schema for Health & Cache Management System
-- Run this to create required tables

-- Cache logs table
CREATE TABLE IF NOT EXISTS cache_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    admin_id INT DEFAULT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cache statistics table
CREATE TABLE IF NOT EXISTS cache_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL,
    hit TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Error logs table
CREATE TABLE IF NOT EXISTS error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100),
    error_message TEXT,
    file_path VARCHAR(255),
    line_number INT,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_error_type (error_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API logs table
CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    status VARCHAR(20),
    response_time FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Query logs table
CREATE TABLE IF NOT EXISTS query_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_text TEXT,
    execution_time FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_execution_time (execution_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    success TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Promo usage logs table
CREATE TABLE IF NOT EXISTS promo_usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_code VARCHAR(50),
    user_id INT,
    status VARCHAR(20),
    order_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_promo_code (promo_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fix actions log table
CREATE TABLE IF NOT EXISTS fix_actions_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    admin_id INT DEFAULT NULL,
    success TINYINT(1) DEFAULT 1,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User cache logs table
CREATE TABLE IF NOT EXISTS user_cache_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    user_id INT DEFAULT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin alerts table
CREATE TABLE IF NOT EXISTS admin_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50),
    severity VARCHAR(20),
    title VARCHAR(255),
    message TEXT,
    read_status TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_read_status (read_status),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automation logs table
CREATE TABLE IF NOT EXISTS automation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User cache table
CREATE TABLE IF NOT EXISTS user_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    cache_key VARCHAR(255),
    cache_value TEXT,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add load_time column to page_views if it doesn't exist
ALTER TABLE page_views ADD COLUMN IF NOT EXISTS load_time FLOAT DEFAULT NULL;
