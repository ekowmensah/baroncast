-- Hubtel Schema Fixes (Corrected)
-- Add missing columns and indexes for proper Hubtel integration
-- This version doesn't assume payment_token column exists

-- Add checkout_url column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'checkout_url' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "checkout_url column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN checkout_url VARCHAR(500) NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add payment_token column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'payment_token' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "payment_token column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN payment_token VARCHAR(255) NULL'
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
    'ALTER TABLE transactions ADD COLUMN hubtel_transaction_id VARCHAR(100) NULL'
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
    'ALTER TABLE transactions ADD COLUMN payment_details TEXT NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_code column if it doesn't exist (for OTP verification)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'otp_code' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "otp_code column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN otp_code VARCHAR(10) NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_expires_at column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'otp_expires_at' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "otp_expires_at column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN otp_expires_at DATETIME NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_verified_at column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'transactions' 
     AND column_name = 'otp_verified_at' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "otp_verified_at column already exists" as message',
    'ALTER TABLE transactions ADD COLUMN otp_verified_at DATETIME NULL'
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
CREATE INDEX IF NOT EXISTS idx_transactions_payment_token ON transactions(payment_token);
