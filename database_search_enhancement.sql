-- Database Enhancement for Product Search Functionality
-- This script adds optional columns and indexes to improve search performance

-- Add SKU column if it doesn't exist (for product identification)
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS sku VARCHAR(100) UNIQUE AFTER id;

-- Add keywords/tags column if it doesn't exist (for better search matching)
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS keywords TEXT AFTER description;

-- Create indexes for better search performance
-- Index on product name for faster LIKE queries
CREATE INDEX IF NOT EXISTS idx_product_name ON products(name);

-- Index on SKU for faster lookups
CREATE INDEX IF NOT EXISTS idx_product_sku ON products(sku);

-- Full-text index for comprehensive text search (name, description, keywords)
-- Note: FULLTEXT indexes work best with InnoDB engine in MySQL 5.6+
ALTER TABLE products 
ADD FULLTEXT INDEX IF NOT EXISTS idx_fulltext_search (name, description, keywords);

-- Index on category_id for faster category filtering
CREATE INDEX IF NOT EXISTS idx_product_category ON products(category_id);

-- Index on category name for search
CREATE INDEX IF NOT EXISTS idx_category_name ON categories(name);

-- Composite index for common search + filter combinations
CREATE INDEX IF NOT EXISTS idx_product_search_combo ON products(category_id, name);

-- Update existing products with sample SKUs if they don't have them
-- This is optional and can be customized based on your SKU format
UPDATE products 
SET sku = CONCAT('GILAF-', LPAD(id, 6, '0'))
WHERE sku IS NULL OR sku = '';

-- Sample keywords for existing products (customize as needed)
-- These help with search discoverability
UPDATE products 
SET keywords = CASE
    WHEN LOWER(name) LIKE '%saffron%' THEN 'saffron, spice, premium, kashmiri, kesar, zafran, cooking, authentic'
    WHEN LOWER(name) LIKE '%almond%' THEN 'almond, nuts, badam, dry fruits, healthy, snack, kashmiri'
    WHEN LOWER(name) LIKE '%walnut%' THEN 'walnut, akhrot, nuts, dry fruits, brain food, kashmiri, organic'
    WHEN LOWER(name) LIKE '%honey%' THEN 'honey, natural, organic, sweet, pure, kashmiri, health'
    WHEN LOWER(name) LIKE '%apple%' THEN 'apple, fruit, kashmiri, fresh, organic, healthy'
    WHEN LOWER(name) LIKE '%chilli%' OR LOWER(name) LIKE '%chili%' THEN 'chilli, chili, spice, hot, red chilli, kashmiri mirch, cooking'
    WHEN LOWER(name) LIKE '%spice%' THEN 'spice, masala, seasoning, cooking, authentic, kashmiri'
    WHEN LOWER(name) LIKE '%tea%' OR LOWER(name) LIKE '%chai%' THEN 'tea, chai, kahwa, beverage, kashmiri, traditional'
    WHEN LOWER(name) LIKE '%rice%' THEN 'rice, grain, basmati, cooking, kashmiri, premium'
    WHEN LOWER(name) LIKE '%oil%' THEN 'oil, cooking oil, mustard oil, healthy, kashmiri, organic'
    ELSE CONCAT(LOWER(name), ', kashmiri, authentic, premium, organic')
END
WHERE keywords IS NULL OR keywords = '';

-- Verify the changes
SELECT 'Search enhancement completed successfully!' as Status;
SELECT COUNT(*) as total_products, 
       SUM(CASE WHEN sku IS NOT NULL AND sku != '' THEN 1 ELSE 0 END) as products_with_sku,
       SUM(CASE WHEN keywords IS NOT NULL AND keywords != '' THEN 1 ELSE 0 END) as products_with_keywords
FROM products;
