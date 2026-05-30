-- Migration: Add HSN code and shipping dimensions to products table
-- Run on production: mysql -u u237768108_gilafstore -p u237768108_gilafstore < this_file.sql

ALTER TABLE products 
  ADD COLUMN hsn_code VARCHAR(20) DEFAULT NULL AFTER ean,
  ADD COLUMN shipping_length DECIMAL(8,2) DEFAULT NULL AFTER hsn_code,
  ADD COLUMN shipping_width DECIMAL(8,2) DEFAULT NULL AFTER shipping_length,
  ADD COLUMN shipping_height DECIMAL(8,2) DEFAULT NULL AFTER shipping_width,
  ADD COLUMN shipping_weight DECIMAL(8,3) DEFAULT NULL AFTER shipping_height,
  ADD COLUMN gst_rate DECIMAL(5,2) DEFAULT 5.00 AFTER shipping_weight;
