-- ============================================
-- PROMO CODE SYSTEM - DATABASE SCHEMA
-- Complete table creation for checkout discount system
-- ============================================

-- 1. Main Promo Codes Table
CREATE TABLE IF NOT EXISTS `promo_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Promo code (uppercase)',
  `description` TEXT NULL COMMENT 'Internal description',
  
  -- Scope & Targeting
  `scope` ENUM('all', 'product', 'category') DEFAULT 'all' COMMENT 'Discount scope',
  `target_ids` JSON NULL COMMENT 'Product/category IDs if scoped',
  `promo_message` VARCHAR(255) NULL COMMENT 'Message shown to customer',
  
  -- User Eligibility (Enhanced)
  `eligibility_type` ENUM(
    'all_users',
    'new_users',
    'first_time',
    'second_time',
    'first_second_time',
    'third_time',
    'repeat_users',
    'returning_inactive',
    'all_existing',
    'custom'
  ) DEFAULT 'all_users' COMMENT 'User segment eligibility',
  `inactive_days` INT NULL DEFAULT NULL COMMENT 'Days required for returning_inactive type',
  `display_in_header` TINYINT(1) DEFAULT 0 COMMENT 'Show in website header',
  
  -- Discount Configuration
  `discount_type` ENUM('percentage', 'fixed') NOT NULL COMMENT 'Discount type',
  `discount_value` DECIMAL(10,2) NOT NULL COMMENT 'Percentage or fixed amount',
  `min_order_value` DECIMAL(10,2) DEFAULT 0 COMMENT 'Minimum cart value required',
  `max_discount` DECIMAL(10,2) NULL COMMENT 'Maximum discount cap (for percentage)',
  
  -- Usage Limits
  `usage_limit` INT NULL DEFAULT NULL COMMENT 'Total usage limit (NULL = unlimited)',
  `usage_limit_per_user` INT NULL DEFAULT NULL COMMENT 'Per-user usage limit (NULL = unlimited)',
  `used_count` INT DEFAULT 0 COMMENT 'Current usage count',
  
  -- Validity Period
  `valid_from` DATETIME NOT NULL COMMENT 'Start date/time',
  `valid_until` DATETIME NOT NULL COMMENT 'End date/time',
  
  -- Status
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Active status',
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_code (`code`),
  INDEX idx_active (`is_active`),
  INDEX idx_validity (`valid_from`, `valid_until`),
  INDEX idx_eligibility (`eligibility_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Promo Code Usage Tracking Table
CREATE TABLE IF NOT EXISTS `promo_code_usage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `promo_code_id` INT NOT NULL COMMENT 'FK to promo_codes',
  `user_id` INT NULL COMMENT 'User ID (NULL for guest)',
  `order_id` INT NULL COMMENT 'Order ID',
  
  -- User Details at Time of Use
  `user_email` VARCHAR(255) NULL COMMENT 'User email at time of use',
  `user_phone` VARCHAR(20) NULL COMMENT 'User phone at time of use',
  `order_count_at_use` INT DEFAULT 0 COMMENT 'Order count when code was applied',
  `user_type` VARCHAR(50) NULL COMMENT 'User segment label',
  
  -- Discount Applied
  `discount_amount` DECIMAL(10,2) NOT NULL COMMENT 'Actual discount given',
  `order_total` DECIMAL(10,2) NOT NULL COMMENT 'Order total before discount',
  
  -- Timestamps
  `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_promo_code (`promo_code_id`),
  INDEX idx_user (`user_id`),
  INDEX idx_order (`order_id`),
  INDEX idx_used_at (`used_at`),
  
  FOREIGN KEY (`promo_code_id`) REFERENCES `promo_codes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================

-- Example 1: Welcome discount for new users
INSERT INTO `promo_codes` (
  `code`, `description`, `eligibility_type`, `discount_type`, `discount_value`, 
  `min_order_value`, `max_discount`, `valid_from`, `valid_until`, `display_in_header`
) VALUES (
  'WELCOME20', 
  'Welcome discount for new users - 20% off up to ₹200', 
  'new_users', 
  'percentage', 
  20.00, 
  500.00, 
  200.00, 
  NOW(), 
  DATE_ADD(NOW(), INTERVAL 1 YEAR),
  1
);

-- Example 2: Returning customer win-back
INSERT INTO `promo_codes` (
  `code`, `description`, `eligibility_type`, `inactive_days`, `discount_type`, 
  `discount_value`, `min_order_value`, `valid_from`, `valid_until`, `display_in_header`
) VALUES (
  'COMEBACK50', 
  'Win-back offer for customers inactive 30+ days', 
  'returning_inactive', 
  30, 
  'fixed', 
  50.00, 
  300.00, 
  NOW(), 
  DATE_ADD(NOW(), INTERVAL 6 MONTH),
  1
);

-- Example 3: Loyalty reward for repeat customers
INSERT INTO `promo_codes` (
  `code`, `description`, `eligibility_type`, `discount_type`, `discount_value`, 
  `min_order_value`, `usage_limit`, `valid_from`, `valid_until`
) VALUES (
  'LOYAL100', 
  'Loyalty reward for repeat customers (4+ orders)', 
  'repeat_users', 
  'fixed', 
  100.00, 
  1000.00, 
  500, 
  NOW(), 
  DATE_ADD(NOW(), INTERVAL 1 YEAR)
);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check if tables were created
SHOW TABLES LIKE 'promo%';

-- View promo codes
SELECT * FROM promo_codes;

-- View usage tracking
SELECT * FROM promo_code_usage;
