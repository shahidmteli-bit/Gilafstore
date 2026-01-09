-- Add discounts to your actual products (Red Chilli, Raisens, etc.)
-- Run this in phpMyAdmin after checking your product IDs

-- First, let's see what products you have:
-- SELECT id, name, price FROM products;

-- Then add discounts using the actual product names:

-- Red Chilli - 15% OFF
INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'percentage', 15.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1
FROM products p
WHERE p.name LIKE '%Red Chilli%' OR p.name LIKE '%red chilli%'
LIMIT 1
ON DUPLICATE KEY UPDATE discount_value = 15.00, is_active = 1;

-- Raisens - 10% OFF
INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'percentage', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1
FROM products p
WHERE p.name LIKE '%Raisens%' OR p.name LIKE '%raisens%' OR p.name LIKE '%Raisin%'
LIMIT 1
ON DUPLICATE KEY UPDATE discount_value = 10.00, is_active = 1;

-- If you want to add discounts to ALL products (for testing):
-- Uncomment the following:

/*
INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'percentage', 20.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1
FROM products p
WHERE NOT EXISTS (
    SELECT 1 FROM product_discounts pd WHERE pd.product_id = p.id
)
ON DUPLICATE KEY UPDATE discount_value = 20.00, is_active = 1;
*/
