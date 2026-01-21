-- Add usage_limit_per_user column to promo_codes table
-- This allows limiting how many times a single user can use the same promo code

ALTER TABLE promo_codes 
ADD COLUMN usage_limit_per_user INT NULL DEFAULT NULL 
AFTER usage_limit;

-- Comment: NULL means unlimited usage per user
