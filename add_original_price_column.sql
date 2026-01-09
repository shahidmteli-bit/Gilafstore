-- Add original_price column to products table for discount functionality
-- Run this SQL in phpMyAdmin to enable discount calculations

-- Add original_price column to products table
ALTER TABLE `products` 
ADD COLUMN `original_price` DECIMAL(10,2) NULL DEFAULT NULL AFTER `price`;

-- Update existing products: set original_price = price (no discount initially)
UPDATE `products` 
SET `original_price` = `price` 
WHERE `original_price` IS NULL;

-- Add comment for clarity
ALTER TABLE `products` 
MODIFY COLUMN `original_price` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Original listing price before discount';

SELECT 'original_price column added successfully to products table!' AS Status;
