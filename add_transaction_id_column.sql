-- Add transaction_id column to orders table for UPI payment tracking

-- Add transaction_id column if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) NULL AFTER payment_status;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_transaction_id ON orders(transaction_id);

-- Show the updated table structure
DESCRIBE orders;
