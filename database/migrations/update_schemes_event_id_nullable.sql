-- Migration to make event_id nullable in schemes table
-- This allows creating general schemes not tied to specific events

USE e_cast_voting;

-- Drop the existing foreign key constraint
ALTER TABLE schemes DROP FOREIGN KEY schemes_ibfk_1;

-- Modify the event_id column to allow NULL values
ALTER TABLE schemes MODIFY COLUMN event_id INT(11) NULL;

-- Re-add the foreign key constraint with NULL support
ALTER TABLE schemes 
ADD CONSTRAINT schemes_ibfk_1 
FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE;

-- Verify the change
DESCRIBE schemes;
