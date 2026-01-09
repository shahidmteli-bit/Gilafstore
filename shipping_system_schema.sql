-- ============================================
-- COMPLETE SHIPPING MANAGEMENT SYSTEM
-- ============================================
-- Comprehensive shipping module for international eCommerce
-- Supports zones, methods, weight-based pricing, COD, and tracking

USE ecommerce_db;

-- ============================================
-- 1. SHIPPING ZONES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_zones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `zone_name` VARCHAR(100) NOT NULL,
    `zone_type` ENUM('local', 'national', 'regional', 'international', 'remote') NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_zone_type` (`zone_type`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. ZONE COUNTRIES/REGIONS MAPPING
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_zone_locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT NOT NULL,
    `country_code` VARCHAR(2) NOT NULL,
    `country_name` VARCHAR(100) NOT NULL,
    `state_province` VARCHAR(100) NULL,
    `postal_code_pattern` VARCHAR(50) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE CASCADE,
    INDEX `idx_country` (`country_code`),
    INDEX `idx_zone_id` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. SHIPPING METHODS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `method_name` VARCHAR(100) NOT NULL,
    `method_code` VARCHAR(50) NOT NULL UNIQUE,
    `method_type` ENUM('standard', 'express', 'local_pickup', 'overnight', 'economy') NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `icon` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_method_code` (`method_code`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. WEIGHT SLABS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_weight_slabs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slab_name` VARCHAR(100) NOT NULL,
    `min_weight` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_weight` DECIMAL(10,2) NOT NULL,
    `weight_unit` ENUM('g', 'kg', 'lb', 'oz') DEFAULT 'kg',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_weight_range` (`min_weight`, `max_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. SHIPPING RATES TABLE (Zone + Method + Weight)
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_rates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT NOT NULL,
    `method_id` INT NOT NULL,
    `weight_slab_id` INT NULL,
    `base_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `per_kg_cost` DECIMAL(10,2) DEFAULT 0.00,
    `min_delivery_days` INT DEFAULT 3,
    `max_delivery_days` INT DEFAULT 7,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`method_id`) REFERENCES `shipping_methods`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`weight_slab_id`) REFERENCES `shipping_weight_slabs`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_rate` (`zone_id`, `method_id`, `weight_slab_id`),
    INDEX `idx_zone_method` (`zone_id`, `method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. FREE SHIPPING RULES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_free_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rule_name` VARCHAR(100) NOT NULL,
    `zone_id` INT NULL,
    `min_order_value` DECIMAL(10,2) NOT NULL,
    `applicable_methods` VARCHAR(255) NULL,
    `exclude_international` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `priority` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE CASCADE,
    INDEX `idx_min_value` (`min_order_value`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. COD SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_cod_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT NULL,
    `is_enabled` TINYINT(1) DEFAULT 1,
    `cod_charge` DECIMAL(10,2) DEFAULT 0.00,
    `cod_charge_type` ENUM('fixed', 'percentage') DEFAULT 'fixed',
    `max_cod_amount` DECIMAL(10,2) NULL,
    `exclude_international` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE CASCADE,
    INDEX `idx_zone_enabled` (`zone_id`, `is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. ORDER SHIPPING DETAILS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `order_shipping_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `zone_id` INT NULL,
    `method_id` INT NULL,
    `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cod_charge` DECIMAL(10,2) DEFAULT 0.00,
    `total_weight` DECIMAL(10,2) DEFAULT 0.00,
    `tracking_number` VARCHAR(100) NULL,
    `courier_company` VARCHAR(100) NULL,
    `shipping_status` ENUM('pending', 'processing', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned') DEFAULT 'pending',
    `estimated_delivery_date` DATE NULL,
    `actual_delivery_date` DATE NULL,
    `shipped_at` TIMESTAMP NULL,
    `delivered_at` TIMESTAMP NULL,
    `shipping_address` TEXT NOT NULL,
    `shipping_country` VARCHAR(100) NOT NULL,
    `shipping_postal_code` VARCHAR(20) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`method_id`) REFERENCES `shipping_methods`(`id`) ON DELETE SET NULL,
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_tracking` (`tracking_number`),
    INDEX `idx_status` (`shipping_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. SHIPPING STATUS HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_shipping_id` INT NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `location` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_shipping_id`) REFERENCES `order_shipping_details`(`id`) ON DELETE CASCADE,
    INDEX `idx_order_shipping` (`order_shipping_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. GLOBAL SHIPPING SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `shipping_global_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `setting_type` ENUM('boolean', 'number', 'string', 'json') DEFAULT 'string',
    `description` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default Shipping Zones
INSERT INTO `shipping_zones` (`zone_name`, `zone_type`, `description`, `is_active`, `display_order`) VALUES
('Local (Same City)', 'local', 'Delivery within the same city', 1, 1),
('National (India)', 'national', 'Delivery across India', 1, 2),
('International (Asia)', 'regional', 'Delivery to Asian countries', 1, 3),
('International (Europe)', 'international', 'Delivery to European countries', 1, 4),
('International (Americas)', 'international', 'Delivery to North & South America', 1, 5),
('Remote Areas', 'remote', 'Hard-to-reach locations', 1, 6);

-- Default Shipping Methods
INSERT INTO `shipping_methods` (`method_name`, `method_code`, `method_type`, `description`, `is_active`, `display_order`) VALUES
('Standard Shipping', 'standard', 'standard', 'Regular delivery 5-7 business days', 1, 1),
('Express Shipping', 'express', 'express', 'Fast delivery 2-3 business days', 1, 2),
('Overnight Delivery', 'overnight', 'overnight', 'Next day delivery', 1, 3),
('Local Pickup', 'local_pickup', 'local_pickup', 'Pick up from store', 1, 4);

-- Default Weight Slabs
INSERT INTO `shipping_weight_slabs` (`slab_name`, `min_weight`, `max_weight`, `weight_unit`, `is_active`) VALUES
('0-500g', 0.00, 0.50, 'kg', 1),
('500g-1kg', 0.50, 1.00, 'kg', 1),
('1-2kg', 1.00, 2.00, 'kg', 1),
('2-5kg', 2.00, 5.00, 'kg', 1),
('5-10kg', 5.00, 10.00, 'kg', 1),
('10kg+', 10.00, 999.00, 'kg', 1);

-- Sample Shipping Rates (Local Zone + Standard Method)
INSERT INTO `shipping_rates` (`zone_id`, `method_id`, `weight_slab_id`, `base_cost`, `per_kg_cost`, `min_delivery_days`, `max_delivery_days`) VALUES
(1, 1, 1, 50.00, 0.00, 1, 2),
(1, 1, 2, 70.00, 0.00, 1, 2),
(1, 1, 3, 100.00, 0.00, 1, 2),
(1, 2, 1, 100.00, 0.00, 1, 1),
(1, 2, 2, 150.00, 0.00, 1, 1);

-- Default Free Shipping Rule
INSERT INTO `shipping_free_rules` (`rule_name`, `zone_id`, `min_order_value`, `exclude_international`, `is_active`, `priority`) VALUES
('Free Shipping on Orders Above ₹500', 1, 500.00, 1, 1, 1);

-- Default COD Settings
INSERT INTO `shipping_cod_settings` (`zone_id`, `is_enabled`, `cod_charge`, `cod_charge_type`, `exclude_international`) VALUES
(1, 1, 50.00, 'fixed', 1),
(2, 1, 75.00, 'fixed', 1);

-- Global Settings
INSERT INTO `shipping_global_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('shipping_enabled', '1', 'boolean', 'Enable/disable shipping globally'),
('default_weight_unit', 'kg', 'string', 'Default weight unit (kg, g, lb, oz)'),
('show_delivery_estimate', '1', 'boolean', 'Show estimated delivery time on product pages'),
('auto_calculate_weight', '1', 'boolean', 'Auto-calculate cart weight from products'),
('require_postal_code', '1', 'boolean', 'Require postal code for shipping calculation');

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

SELECT 'Shipping system tables created successfully!' as message;
SELECT COUNT(*) as total_zones FROM shipping_zones;
SELECT COUNT(*) as total_methods FROM shipping_methods;
SELECT COUNT(*) as total_weight_slabs FROM shipping_weight_slabs;
SELECT COUNT(*) as total_rates FROM shipping_rates;
