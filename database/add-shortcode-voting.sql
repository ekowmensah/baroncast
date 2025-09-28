-- Add shortcode voting support to the database
-- This migration adds shortcode fields and creates necessary tables

-- Add shortcode field to nominees table
ALTER TABLE nominees ADD COLUMN short_code VARCHAR(10) UNIQUE AFTER name;
ALTER TABLE nominees ADD INDEX idx_short_code (short_code);

-- Create shortcode voting sessions table (without foreign keys initially)
CREATE TABLE IF NOT EXISTS shortcode_voting_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    current_step ENUM('welcome', 'select_event', 'select_category', 'select_nominee', 'enter_votes', 'confirm_payment', 'payment_processing', 'completed') DEFAULT 'welcome',
    event_id INT NULL,
    category_id INT NULL,
    nominee_id INT NULL,
    vote_count INT DEFAULT 1,
    amount DECIMAL(10,2) DEFAULT 0.00,
    session_data TEXT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_session_id (session_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_last_activity (last_activity),
    INDEX idx_expires_at (expires_at),
    INDEX idx_event_id (event_id),
    INDEX idx_category_id (category_id),
    INDEX idx_nominee_id (nominee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shortcode transactions table (without foreign keys initially)
CREATE TABLE IF NOT EXISTS shortcode_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(50) UNIQUE NOT NULL,
    session_id VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    event_id INT NOT NULL,
    nominee_id INT NOT NULL,
    vote_count INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'expired') DEFAULT 'pending',
    payment_method ENUM('ussd', 'mobile_money') DEFAULT 'ussd',
    hubtel_transaction_id VARCHAR(100) NULL,
    payment_data TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    INDEX idx_transaction_ref (transaction_ref),
    INDEX idx_session_id (session_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_event_id (event_id),
    INDEX idx_nominee_id (nominee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add shortcode voting settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('enable_shortcode_voting', '1', 'Enable shortcode/USSD voting'),
('shortcode_number', '*170*123#', 'USSD shortcode for voting'),
('shortcode_welcome_message', 'Welcome to E-Cast Voting! Choose an option:', 'Welcome message for shortcode voting'),
('shortcode_session_timeout', '600', 'Session timeout in seconds (10 minutes)'),
('shortcode_max_votes_per_session', '10', 'Maximum votes per shortcode session'),
('shortcode_payment_method', 'ussd', 'Default payment method for shortcode voting (ussd/mobile_money)');

-- Update existing nominees with sample shortcodes (if they don't have them)
-- This is just for existing data - new nominees will get shortcodes when created
UPDATE nominees SET short_code = CONCAT(
    UPPER(LEFT(REPLACE(REPLACE(name, ' ', ''), '-', ''), 3)),
    LPAD(id, 3, '0')
) WHERE short_code IS NULL OR short_code = '';
