-- Hubtel Schema Fixes
-- Add missing columns and indexes for proper Hubtel integration
-- Run this directly in phpMyAdmin

-- Add checkout_url column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'checkout_url' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "checkout_url column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN checkout_url VARCHAR(500) NULL AFTER payment_token'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add hubtel_transaction_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'hubtel_transaction_id' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "hubtel_transaction_id column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN hubtel_transaction_id VARCHAR(100) NULL AFTER checkout_url'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add payment_details column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'payment_details' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "payment_details column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN payment_details TEXT NULL AFTER hubtel_transaction_id'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add hubtel_webhook_secret setting
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
VALUES ('hubtel_webhook_secret', '', 'Hubtel Webhook Secret for signature verification');

-- Update transactions with proper status values
UPDATE transactions 
SET status = 'pending' 
WHERE status IS NULL OR status = '';

-- Add indexes (ignore errors if they already exist)
CREATE INDEX IF NOT EXISTS idx_transactions_hubtel_id ON transactions(hubtel_transaction_id);
CREATE INDEX IF NOT EXISTS idx_transactions_checkout_url ON transactions(checkout_url(255));
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_reference ON transactions(reference);
