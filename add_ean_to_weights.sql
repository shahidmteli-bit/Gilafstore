-- ============================================
-- Add EAN column to product_weights table
-- Run this in phpMyAdmin on BOTH local and Hostinger
-- ============================================

-- Add EAN column to product_weights table
ALTER TABLE `product_weights` 
ADD COLUMN `ean` VARCHAR(13) NULL AFTER `price`;

-- Success message
SELECT 'EAN column added to product_weights successfully!' AS Status;
