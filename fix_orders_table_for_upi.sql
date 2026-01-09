-- Fix orders table to support UPI payment system
-- This adds all necessary columns for payment tracking and verification

-- First, check current structure
DESCRIBE orders;

-- Add payment_method column
ALTER TABLE orders 
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cod' AFTER total_amount;

-- Add payment_status column
ALTER TABLE orders 
ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_method;

-- Add transaction_id column for UPI payments
ALTER TABLE orders 
ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_status;

-- Add shipping_address column
ALTER TABLE orders 
ADD COLUMN shipping_address TEXT NULL AFTER transaction_id;

-- Rename status to order_status for clarity
ALTER TABLE orders 
CHANGE COLUMN status order_status ENUM('pending', 'processing', 'accepted', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending';

-- Add verification tracking columns
ALTER TABLE orders 
ADD COLUMN verified_at DATETIME NULL AFTER order_status;

ALTER TABLE orders 
ADD COLUMN verified_by INT NULL AFTER verified_at;

ALTER TABLE orders 
ADD COLUMN admin_notes TEXT NULL AFTER verified_by;

-- Add foreign key for verified_by
ALTER TABLE orders 
ADD CONSTRAINT fk_orders_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id) 
ON DELETE SET NULL;

-- Add indexes for better performance
CREATE INDEX idx_payment_method ON orders(payment_method);
CREATE INDEX idx_payment_status ON orders(payment_status);
CREATE INDEX idx_transaction_id ON orders(transaction_id);
CREATE INDEX idx_order_status ON orders(order_status);

-- Show final structure
DESCRIBE orders;

-- Show success message
SELECT 'Orders table successfully updated for UPI payment system!' AS Status;
