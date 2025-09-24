-- Add checkout_url column to transactions table for Hubtel online checkout
-- Run this migration to support online payment checkout URLs

ALTER TABLE transactions 
ADD COLUMN checkout_url VARCHAR(500) NULL AFTER payment_token,
ADD COLUMN hubtel_transaction_id VARCHAR(100) NULL AFTER checkout_url,
ADD COLUMN payment_details TEXT NULL AFTER hubtel_transaction_id;

-- Add index for better performance
CREATE INDEX idx_transactions_hubtel_id ON transactions(hubtel_transaction_id);
CREATE INDEX idx_transactions_checkout_url ON transactions(checkout_url(255));

-- Update existing transactions to have proper status values
UPDATE transactions 
SET status = 'pending' 
WHERE status IS NULL OR status = '';
