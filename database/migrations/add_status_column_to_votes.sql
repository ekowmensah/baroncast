-- Add missing status column to votes table
-- This column is required for vote confirmation workflow

ALTER TABLE votes ADD COLUMN status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending' AFTER payment_status;

-- Add payment_response column to store API responses
ALTER TABLE votes ADD COLUMN payment_response TEXT AFTER status;

-- Add updated_at timestamp column
ALTER TABLE votes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER voted_at;
