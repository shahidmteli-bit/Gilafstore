-- ============================================
-- Add Image Support for Product Weight Variants
-- Migration Script - Run in phpMyAdmin
-- ============================================

-- Add image column to product_weights table
ALTER TABLE `product_weights` 
ADD COLUMN `variant_image` VARCHAR(255) NULL AFTER `ean`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Success message
SELECT 'Product weight images column added successfully!' AS Status;
SELECT 'You can now upload images for each weight variant from the admin panel.' AS Info;
