-- ============================================
-- Sales Portal Pricing Enhancement Migration
-- Adds: Retail pricing, GST columns, Offline MRP
-- Fixes: Independent pricing storage
-- ============================================

-- Add Retail pricing column
ALTER TABLE `product_weights` 
ADD COLUMN IF NOT EXISTS `retail_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `franchise_price`;

-- Add GST columns for each pricing tier
ALTER TABLE `product_weights` 
ADD COLUMN IF NOT EXISTS `wholesale_gst` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `wholesale_price`,
ADD COLUMN IF NOT EXISTS `distributor_gst` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `distributor_price`,
ADD COLUMN IF NOT EXISTS `franchise_gst` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `franchise_price`,
ADD COLUMN IF NOT EXISTS `retail_gst` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `retail_price`;

-- Add Offline MRP (separate from website MRP/price)
ALTER TABLE `product_weights` 
ADD COLUMN IF NOT EXISTS `offline_mrp` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `retail_gst`;

-- Add index for faster pricing queries
ALTER TABLE `product_weights`
ADD INDEX IF NOT EXISTS `idx_pricing` (`wholesale_price`, `distributor_price`, `franchise_price`, `retail_price`);

-- Verification
SELECT 'Sales Portal pricing enhancement migration completed!' AS Status;
SELECT 
    COUNT(*) as 'Total Product Weights',
    SUM(CASE WHEN wholesale_price > 0 THEN 1 ELSE 0 END) as 'With Wholesale Price',
    SUM(CASE WHEN distributor_price > 0 THEN 1 ELSE 0 END) as 'With Distributor Price',
    SUM(CASE WHEN franchise_price > 0 THEN 1 ELSE 0 END) as 'With Franchise Price',
    SUM(CASE WHEN retail_price > 0 THEN 1 ELSE 0 END) as 'With Retail Price'
FROM `product_weights`;
