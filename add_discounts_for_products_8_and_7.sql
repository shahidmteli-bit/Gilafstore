-- Add discounts for your actual products
-- Product ID 8: Red Chilli - 15% OFF
-- Product ID 7: Raisens - 10% OFF

INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
VALUES 
(8, 'percentage', 15.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1),
(7, 'percentage', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1)
ON DUPLICATE KEY UPDATE 
    discount_value = VALUES(discount_value),
    is_active = VALUES(is_active);
