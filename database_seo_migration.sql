-- ============================================
-- SEO SYSTEM DATABASE MIGRATION
-- Gilaf Store — Full SEO Automation
-- Run in phpMyAdmin or MySQL CLI
-- ============================================

-- 1. Add SEO columns to products table
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(255) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `short_description` VARCHAR(500) NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `seo_title` VARCHAR(255) NULL AFTER `short_description`,
  ADD COLUMN IF NOT EXISTS `seo_description` VARCHAR(320) NULL AFTER `seo_title`,
  ADD COLUMN IF NOT EXISTS `og_image_url` VARCHAR(500) NULL AFTER `seo_description`,
  ADD COLUMN IF NOT EXISTS `canonical_override` VARCHAR(500) NULL AFTER `og_image_url`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Add unique index on slug (allow NULL for migration, enforce uniqueness on non-null)
DROP INDEX IF EXISTS `idx_product_slug` ON `products`;
ALTER TABLE `products` ADD UNIQUE INDEX `idx_product_slug` (`slug`);

-- 2. Add SEO columns to categories table
ALTER TABLE `categories`
  ADD COLUMN IF NOT EXISTS `slug` VARCHAR(255) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `description` TEXT NULL AFTER `slug`,
  ADD COLUMN IF NOT EXISTS `seo_title` VARCHAR(255) NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `seo_description` VARCHAR(320) NULL AFTER `seo_title`,
  ADD COLUMN IF NOT EXISTS `image` VARCHAR(255) NULL AFTER `seo_description`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

DROP INDEX IF EXISTS `idx_category_slug` ON `categories`;
ALTER TABLE `categories` ADD UNIQUE INDEX `idx_category_slug` (`slug`);

-- 3. Create SEO redirects table (for slug changes / URL migrations)
CREATE TABLE IF NOT EXISTS `seo_redirects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `old_path` VARCHAR(500) NOT NULL,
  `new_path` VARCHAR(500) NOT NULL,
  `status_code` SMALLINT NOT NULL DEFAULT 301,
  `hit_count` INT NOT NULL DEFAULT 0,
  `last_hit_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX `idx_old_path` (`old_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add alt_text to product images if product_images table exists
-- (product_weights already has variant_image; we add alt_text support)
ALTER TABLE `product_weights`
  ADD COLUMN IF NOT EXISTS `image_alt_text` VARCHAR(255) NULL AFTER `variant_image_back`;

-- 5. Auto-generate slugs for all existing products that don't have one
-- This uses MySQL string functions to create URL-friendly slugs
-- NOTE: Run the PHP migration script (seo_migrate_slugs.php) after this for proper slug generation with uniqueness handling

-- 6. Insert initial 301 redirects for old ?id= URLs -> new slug URLs
-- (Will be handled by the PHP migration script)

-- 7. Add stock_qty to product_weights if missing (for per-variant availability in schema)
ALTER TABLE `product_weights`
  ADD COLUMN IF NOT EXISTS `stock_qty` INT NOT NULL DEFAULT 0 AFTER `price`;

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'SEO migration completed!' AS Status;
SELECT COUNT(*) AS 'Products needing slugs' FROM products WHERE slug IS NULL;
