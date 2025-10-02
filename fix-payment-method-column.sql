-- Fix payment_method column to allow 'hubtel_ussd'
-- Current value is probably too long for the column

-- Check current column definition
DESCRIBE transactions;

-- Update the payment_method column to allow longer values
ALTER TABLE transactions 
MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cash';

-- Or if it's an ENUM, add the new value
-- ALTER TABLE transactions 
-- MODIFY COLUMN payment_method ENUM('cash','mobile_money','bank_transfer','hubtel_ussd','hubtel_payproxy') NOT NULL DEFAULT 'cash';
