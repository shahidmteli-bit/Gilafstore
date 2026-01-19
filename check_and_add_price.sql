-- ============================================
-- Check if price column exists and add if missing
-- SIMPLE VERSION - Just run this in SQL tab after selecting database
-- ============================================

-- Add price column if it doesn't exist
ALTER TABLE `product_weights` 
ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `display_weight`;

-- Update existing weights with product price
UPDATE `product_weights` pw
INNER JOIN `products` p ON pw.product_id = p.id
SET pw.price = p.price
WHERE pw.price = 0.00 OR pw.price IS NULL;

-- Show results
SELECT 'Price column added/updated successfully!' AS Status;
SELECT * FROM `product_weights` LIMIT 5;
