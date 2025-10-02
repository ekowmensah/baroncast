-- Fix payment_method column size issue
-- This will allow 'hubtel' and other payment methods to fit properly

-- Fix transactions table
ALTER TABLE transactions 
MODIFY COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cash';

-- Fix votes table  
ALTER TABLE votes 
MODIFY COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cash';

-- Verify the changes
DESCRIBE transactions;
DESCRIBE votes;
