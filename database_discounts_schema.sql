-- ============================================
-- PRODUCT DISCOUNTS TABLE SCHEMA
-- ============================================
-- This table stores discount offers for products
-- Supports both percentage and flat amount discounts
-- Includes date range and active status validation

CREATE TABLE IF NOT EXISTS product_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    discount_type ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_active (product_id, is_active),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE DISCOUNT DATA
-- ============================================
-- Add some sample discounts for testing
-- Note: These use dynamic product IDs from existing products
-- Adjust dates to current/future dates for testing

-- Add discounts only if products exist
INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'percentage', 15.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1
FROM products p
WHERE p.name = 'Aurora Ceramic Vase'
LIMIT 1
ON DUPLICATE KEY UPDATE discount_value = VALUES(discount_value);

INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'flat', 20.00, '2026-01-01 00:00:00', '2026-06-30 23:59:59', 1
FROM products p
WHERE p.name = 'Pulse Wireless Headphones'
LIMIT 1
ON DUPLICATE KEY UPDATE discount_value = VALUES(discount_value);

INSERT INTO product_discounts (product_id, discount_type, discount_value, start_date, end_date, is_active)
SELECT p.id, 'percentage', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1
FROM products p
WHERE p.name = 'Nordic Throw Blanket'
LIMIT 1
ON DUPLICATE KEY UPDATE discount_value = VALUES(discount_value);

-- ============================================
-- VALIDATION NOTES
-- ============================================
-- 1. discount_type: 'percentage' (e.g., 15 = 15%) or 'flat' (e.g., 20 = $20 off)
-- 2. discount_value: Must be positive, validated in application logic
-- 3. start_date/end_date: Define the active period for the discount
-- 4. is_active: Manual override to enable/disable discount
-- 5. Percentage discounts should not exceed 100%
-- 6. Flat discounts should not exceed product price (validated in code)
