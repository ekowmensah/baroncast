-- Fix database schema issues for live server deployment
-- Run this script to ensure all required columns exist

-- Add missing columns to transactions table if they don't exist
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10) NULL,
ADD COLUMN IF NOT EXISTS otp_expires_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(255) NULL;

-- Add unique index on transaction_id to prevent duplicates (ignore if exists)
CREATE UNIQUE INDEX IF NOT EXISTS idx_transactions_transaction_id ON transactions(transaction_id);

-- Ensure proper foreign key constraints exist
ALTER TABLE transactions 
ADD CONSTRAINT IF NOT EXISTS fk_transactions_event 
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE;

ALTER TABLE transactions 
ADD CONSTRAINT IF NOT EXISTS fk_transactions_organizer 
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE transactions 
ADD CONSTRAINT IF NOT EXISTS fk_transactions_nominee 
    FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE;

-- Add vote_cost column to events table if it doesn't exist
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS vote_cost DECIMAL(10,2) DEFAULT 1.00;

-- Update existing events to have default vote cost
UPDATE events SET vote_cost = 1.00 WHERE vote_cost IS NULL;
