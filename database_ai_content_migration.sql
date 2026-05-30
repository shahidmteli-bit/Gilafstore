-- ============================================
-- AI CONTENT GENERATION — DATABASE MIGRATION
-- Gilaf Store — AI SEO & Content Workflow
-- Run in phpMyAdmin or MySQL CLI
-- ============================================

-- 1. AI Generation Logs (tracks every AI call for debugging/monitoring)
CREATE TABLE IF NOT EXISTS `ai_generation_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `mode` ENUM('seo','description','featured','all') NOT NULL,
  `request_json` JSON NULL,
  `ai_raw_response` LONGTEXT NULL,
  `validated_status` ENUM('PASS','WARN','FAIL') NULL,
  `issues_json` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ai_log_product` (`product_id`),
  INDEX `idx_ai_log_mode` (`mode`),
  INDEX `idx_ai_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Featured product fields on products table
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `featured_tagline` VARCHAR(60) NULL AFTER `og_image_url`,
  ADD COLUMN IF NOT EXISTS `featured_bullets` TEXT NULL AFTER `featured_tagline`,
  ADD COLUMN IF NOT EXISTS `featured_badge` VARCHAR(30) NULL AFTER `featured_bullets`;

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'AI Content migration completed!' AS Status;
DESCRIBE ai_generation_logs;
