-- Fix transactions table to add nominee_id column and foreign key constraint
-- This addresses the foreign key constraint error for nominee_id

-- First, check if nominee_id column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'nominee_id'
);

-- Add nominee_id column if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN nominee_id INT NULL AFTER organizer_id',
    'SELECT "nominee_id column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add voter_phone column if it doesn't exist
SET @voter_phone_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'voter_phone'
);

SET @sql = IF(@voter_phone_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN voter_phone VARCHAR(20) NULL AFTER nominee_id',
    'SELECT "voter_phone column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vote_count column if it doesn't exist
SET @vote_count_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'vote_count'
);

SET @sql = IF(@vote_count_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN vote_count INT DEFAULT 1 AFTER voter_phone',
    'SELECT "vote_count column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add payment_method column if it doesn't exist
SET @payment_method_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'payment_method'
);

SET @sql = IF(@payment_method_exists = 0, 
    'ALTER TABLE transactions ADD COLUMN payment_method VARCHAR(50) NULL AFTER vote_count',
    'SELECT "payment_method column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint for nominee_id if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'e_cast_voting' 
    AND TABLE_NAME = 'transactions' 
    AND COLUMN_NAME = 'nominee_id'
    AND REFERENCED_TABLE_NAME = 'nominees'
);

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_nominee FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE',
    'SELECT "Foreign key constraint already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show final table structure
DESCRIBE transactions;
