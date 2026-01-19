-- ============================================
-- PHASE 1: Product Multiple Weights System
-- Migration Script - Run in phpMyAdmin
-- ============================================

-- Create product_weights table
CREATE TABLE IF NOT EXISTS `product_weights` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `weight_value` DECIMAL(10,2) NOT NULL,
  `weight_unit` VARCHAR(10) NOT NULL DEFAULT 'g',
  `display_weight` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_weights` (`product_id`),
  CONSTRAINT `fk_product_weights_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing product weights to new table
-- This will copy net_weight from products table to product_weights table
INSERT INTO `product_weights` (`product_id`, `weight_value`, `weight_unit`, `display_weight`, `is_default`, `sort_order`)
SELECT 
    `id` as product_id,
    CAST(REGEXP_REPLACE(`net_weight`, '[^0-9.]', '') AS DECIMAL(10,2)) as weight_value,
    CASE 
        WHEN `net_weight` LIKE '%kg%' THEN 'kg'
        WHEN `net_weight` LIKE '%g%' THEN 'g'
        ELSE 'g'
    END as weight_unit,
    `net_weight` as display_weight,
    1 as is_default,
    0 as sort_order
FROM `products`
WHERE `net_weight` IS NOT NULL AND `net_weight` != '';

-- Add weight_id column to batch_codes table (for future Phase 2)
ALTER TABLE `batch_codes` 
ADD COLUMN `weight_id` INT(11) NULL AFTER `product_id`,
ADD KEY `idx_batch_weight` (`weight_id`);

-- Success message
SELECT 'Product weights migration completed successfully!' AS Status;
SELECT COUNT(*) as 'Total Weights Migrated' FROM `product_weights`;
