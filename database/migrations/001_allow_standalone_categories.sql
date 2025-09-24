-- Migration to allow standalone categories (event_id can be NULL)
-- This enables creating categories that are not tied to specific events

-- Drop the existing foreign key constraint
ALTER TABLE categories DROP FOREIGN KEY categories_ibfk_1;

-- Modify event_id column to allow NULL values
ALTER TABLE categories MODIFY COLUMN event_id INT NULL;

-- Add the foreign key constraint back with NULL support
ALTER TABLE categories ADD CONSTRAINT categories_ibfk_1 
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE;

-- Add index for better performance on event_id queries
CREATE INDEX idx_categories_event_id ON categories(event_id);

-- Add index for standalone categories (where event_id IS NULL)
CREATE INDEX idx_categories_standalone ON categories(event_id, status) WHERE event_id IS NULL;
