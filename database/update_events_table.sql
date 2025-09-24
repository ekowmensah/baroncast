-- Update events table to support admin approval workflow
-- Run this SQL script in phpMyAdmin or MySQL command line

USE e_cast_voting;

-- Add new columns to events table for approval workflow
ALTER TABLE events 
ADD COLUMN vote_cost DECIMAL(10,2) DEFAULT 0.00 AFTER end_date,
ADD COLUMN max_votes_per_user INT DEFAULT 1 AFTER vote_cost,
ADD COLUMN event_type ENUM('public', 'private') DEFAULT 'public' AFTER max_votes_per_user,
ADD COLUMN voting_method ENUM('single', 'multiple') DEFAULT 'single' AFTER event_type,
ADD COLUMN admin_notes TEXT AFTER status,
ADD COLUMN approved_by INT AFTER admin_notes,
ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by;

-- Update status enum to include new values
ALTER TABLE events 
MODIFY COLUMN status ENUM('pending', 'active', 'ended', 'cancelled', 'rejected') DEFAULT 'pending';

-- Add foreign key constraint for approved_by
ALTER TABLE events 
ADD CONSTRAINT fk_events_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Remove old voting_fee column if it exists
ALTER TABLE events DROP COLUMN IF EXISTS voting_fee;

-- Update existing events to have pending status if they are currently draft
UPDATE events SET status = 'pending' WHERE status = 'draft';
