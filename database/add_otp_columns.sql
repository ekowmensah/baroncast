-- Add OTP-related columns to transactions table for Hubtel SMS OTP functionality

-- Add otp_code column if it doesn't exist
SET @otp_code_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'otp_code'
);

SET @sql = IF(@otp_code_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN otp_code VARCHAR(10) NULL AFTER payment_method',
    'SELECT "otp_code column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_expires_at column if it doesn't exist
SET @otp_expires_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'otp_expires_at'
);

SET @sql = IF(@otp_expires_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN otp_expires_at TIMESTAMP NULL AFTER otp_code',
    'SELECT "otp_expires_at column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add otp_verified column if it doesn't exist
SET @otp_verified_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'otp_verified'
);

SET @sql = IF(@otp_verified_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN otp_verified BOOLEAN DEFAULT FALSE AFTER otp_expires_at',
    'SELECT "otp_verified column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show final table structure
DESCRIBE transactions;
