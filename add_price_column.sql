-- ============================================
-- Add price column to product_weights table
-- Run this in phpMyAdmin on BOTH local and Hostinger
-- ============================================

-- IMPORTANT: Replace 'your_database_name' with your actual database name
-- For localhost: Usually 'gilaf_store' or check phpMyAdmin left sidebar
-- For Hostinger: Check your database name in Hostinger panel

-- USE your_database_name;

-- Add price column to existing product_weights table
ALTER TABLE `product_weights` 
ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `display_weight`;

-- Update existing weights with product price
UPDATE `product_weights` pw
INNER JOIN `products` p ON pw.product_id = p.id
SET pw.price = p.price
WHERE pw.price = 0.00;

-- Success message
SELECT 'Price column added successfully!' AS Status;
SELECT COUNT(*) as 'Total Weights Updated' FROM `product_weights` WHERE price > 0;
