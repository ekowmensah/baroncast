-- Create USSD sessions table for storing session data between interactions
CREATE TABLE IF NOT EXISTS ussd_sessions (
    session_id VARCHAR(100) NOT NULL,
    session_key VARCHAR(50) NOT NULL,
    session_value TEXT,
    session_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, session_key),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

-- Create USSD transactions table if it doesn't exist
CREATE TABLE IF NOT EXISTS ussd_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(100) UNIQUE NOT NULL,
    session_id VARCHAR(100),
    phone_number VARCHAR(20),
    event_id INT,
    nominee_id INT,
    vote_count INT,
    amount DECIMAL(10,2),
    status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
    hubtel_transaction_id VARCHAR(100),
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_ref (transaction_ref),
    INDEX idx_session_id (session_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create USSD webhook logs table for debugging
CREATE TABLE IF NOT EXISTS ussd_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    phone_number VARCHAR(20),
    request_data JSON,
    response_data JSON,
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

-- Clean up old session data (older than 2 hours)
DELETE FROM ussd_sessions WHERE created_at < NOW() - INTERVAL 2 HOUR;

-- Add cleanup procedure for old sessions
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_old_ussd_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM ussd_sessions WHERE created_at < NOW() - INTERVAL 2 HOUR;
    DELETE FROM ussd_webhook_logs WHERE created_at < NOW() - INTERVAL 7 DAY;
END;
//
DELIMITER ;

-- Enable event scheduler if not already enabled
SET GLOBAL event_scheduler = ON;
