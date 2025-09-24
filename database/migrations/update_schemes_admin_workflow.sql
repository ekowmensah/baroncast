-- Migration to update schemes table for admin-centric workflow
-- Adds organizer_id, admin_percentage, and organizer_percentage columns

USE e_cast_voting;

-- Add organizer_id column (required - admin assigns schemes to organizers)
ALTER TABLE schemes 
ADD COLUMN organizer_id INT(11) NOT NULL AFTER id;

-- Add admin_percentage column (V. Charges - admin commission)
ALTER TABLE schemes 
ADD COLUMN admin_percentage DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER vote_price;

-- Add organizer_percentage column (organizer's share)
ALTER TABLE schemes 
ADD COLUMN organizer_percentage DECIMAL(5,2) NOT NULL DEFAULT 90.00 AFTER admin_percentage;

-- Add foreign key constraint for organizer_id
ALTER TABLE schemes 
ADD CONSTRAINT schemes_organizer_fk 
FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE;

-- Verify the changes
DESCRIBE schemes;
