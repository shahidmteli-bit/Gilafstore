-- ============================================
-- BATCH LIFECYCLE MANAGEMENT SYSTEM - SAFE MIGRATION
-- Only adds columns/tables that don't exist
-- ============================================

-- Add columns to batch_codes table (only if they don't exist)
-- Run each ALTER TABLE separately to avoid errors

-- Add status column if not exists
SET @dbname = DATABASE();
SET @tablename = 'batch_codes';
SET @columnname = 'status';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `status` ENUM('production', 'quality_testing', 'quality_approved', 'rejected', 'released_for_sale', 'in_distribution', 'sold_out', 'expired', 'recalled', 'blocked', 'archived') DEFAULT 'production' AFTER `is_active`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add quality_approved column if not exists
SET @columnname = 'quality_approved';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `quality_approved` TINYINT(1) DEFAULT 0 AFTER `status`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add quality_approver_id column if not exists
SET @columnname = 'quality_approver_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `quality_approver_id` INT NULL AFTER `quality_approved`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add quality_approved_at column if not exists
SET @columnname = 'quality_approved_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `quality_approved_at` DATETIME NULL AFTER `quality_approver_id`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add quality_notes column if not exists
SET @columnname = 'quality_notes';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `quality_notes` TEXT NULL AFTER `quality_approved_at`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add released_for_sale column if not exists
SET @columnname = 'released_for_sale';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `released_for_sale` TINYINT(1) DEFAULT 0 AFTER `quality_notes`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add releaser_id column if not exists
SET @columnname = 'releaser_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `releaser_id` INT NULL AFTER `released_for_sale`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add released_at column if not exists
SET @columnname = 'released_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `released_at` DATETIME NULL AFTER `releaser_id`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add recalled_quantity column if not exists
SET @columnname = 'recalled_quantity';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `recalled_quantity` INT DEFAULT 0 AFTER `released_at`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add recall_reason column if not exists
SET @columnname = 'recall_reason';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `recall_reason` TEXT NULL AFTER `recalled_quantity`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add recalled_by column if not exists
SET @columnname = 'recalled_by';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `recalled_by` INT NULL AFTER `recall_reason`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add recalled_at column if not exists
SET @columnname = 'recalled_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `recalled_at` DATETIME NULL AFTER `recalled_by`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add blocked_reason column if not exists
SET @columnname = 'blocked_reason';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `blocked_reason` TEXT NULL AFTER `recalled_at`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add blocked_by column if not exists
SET @columnname = 'blocked_by';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `blocked_by` INT NULL AFTER `blocked_reason`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add blocked_at column if not exists
SET @columnname = 'blocked_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `blocked_at` DATETIME NULL AFTER `blocked_by`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add archived_at column if not exists
SET @columnname = 'archived_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `archived_at` DATETIME NULL AFTER `blocked_at`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add total_units_manufactured column if not exists
SET @columnname = 'total_units_manufactured';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `total_units_manufactured` INT DEFAULT 0 AFTER `archived_at`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add units_sold column if not exists
SET @columnname = 'units_sold';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `units_sold` INT DEFAULT 0 AFTER `total_units_manufactured`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add units_remaining column if not exists
SET @columnname = 'units_remaining';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `units_remaining` INT DEFAULT 0 AFTER `units_sold`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add category_id column if not exists
SET @columnname = 'category_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `category_id` INT NULL AFTER `units_remaining`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add is_lab_tested column if not exists
SET @columnname = 'is_lab_tested';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `is_lab_tested` TINYINT(1) DEFAULT 0 AFTER `category_id`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add is_organic column if not exists
SET @columnname = 'is_organic';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `is_organic` TINYINT(1) DEFAULT 0 AFTER `is_lab_tested`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add auto_expired column if not exists
SET @columnname = 'auto_expired';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `auto_expired` TINYINT(1) DEFAULT 0 AFTER `is_organic`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add last_status_change column if not exists
SET @columnname = 'last_status_change';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `last_status_change` DATETIME NULL AFTER `auto_expired`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add status_change_reason column if not exists
SET @columnname = 'status_change_reason';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `status_change_reason` TEXT NULL AFTER `last_status_change`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create batch verification tracking table
CREATE TABLE IF NOT EXISTS `batch_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_code_id` INT NOT NULL,
  `batch_code` VARCHAR(100) NOT NULL,
  `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `device_fingerprint` VARCHAR(255) NULL,
  `country` VARCHAR(100) NULL,
  `city` VARCHAR(100) NULL,
  `latitude` DECIMAL(10, 8) NULL,
  `longitude` DECIMAL(11, 8) NULL,
  `verification_method` ENUM('qr_scan', 'manual_entry', 'whatsapp', 'api') DEFAULT 'manual_entry',
  `language` VARCHAR(10) DEFAULT 'en',
  `is_suspicious` TINYINT(1) DEFAULT 0,
  `suspicion_reason` TEXT NULL,
  INDEX idx_batch_code (batch_code),
  INDEX idx_ip_address (ip_address),
  INDEX idx_verified_at (verified_at),
  INDEX idx_suspicious (is_suspicious)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create admin alerts table
CREATE TABLE IF NOT EXISTS `batch_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `alert_type` ENUM('expiry_30_days', 'expiry_60_days', 'stock_low', 'recalled_verified', 'repeated_verification', 'suspicious_activity') NOT NULL,
  `batch_code_id` INT NOT NULL,
  `batch_code` VARCHAR(100) NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  `message` TEXT NOT NULL,
  `alert_data` JSON NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_resolved` TINYINT(1) DEFAULT 0,
  `resolved_by` INT NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alert_type (alert_type),
  INDEX idx_is_read (is_read),
  INDEX idx_severity (severity),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create batch audit trail table
CREATE TABLE IF NOT EXISTS `batch_audit_trail` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_code_id` INT NOT NULL,
  `batch_code` VARCHAR(100) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NULL,
  `performed_by` INT NOT NULL,
  `performer_name` VARCHAR(255) NOT NULL,
  `action_details` JSON NULL,
  `notes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_batch_code (batch_code),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create batch stock threshold settings table
CREATE TABLE IF NOT EXISTS `batch_stock_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `low_stock_threshold` INT DEFAULT 10,
  `critical_stock_threshold` INT DEFAULT 5,
  `auto_reorder_enabled` TINYINT(1) DEFAULT 0,
  `reorder_quantity` INT DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data for testing (optional)
INSERT IGNORE INTO `batch_stock_settings` (`product_id`, `low_stock_threshold`, `critical_stock_threshold`) 
SELECT id, 10, 5 FROM products LIMIT 10;
