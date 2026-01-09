-- Add columns for payment verification tracking to orders table

-- Add verified_at column if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL AFTER payment_status;

-- Add verified_by column if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS verified_by INT NULL AFTER verified_at;

-- Add admin_notes column if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL AFTER verified_by;

-- Add foreign key for verified_by
ALTER TABLE orders 
ADD CONSTRAINT fk_orders_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id) 
ON DELETE SET NULL;

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_payment_status ON orders(payment_status);
CREATE INDEX IF NOT EXISTS idx_payment_method ON orders(payment_method);
CREATE INDEX IF NOT EXISTS idx_transaction_id ON orders(transaction_id);
