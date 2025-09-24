-- Create USSD sessions table for storing session data
CREATE TABLE IF NOT EXISTS ussd_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    session_data TEXT,
    phone_number VARCHAR(20),
    current_level INT DEFAULT 0,
    current_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 MINUTE),
    INDEX idx_session_id (session_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_expires_at (expires_at)
);

-- Create USSD transactions table for tracking USSD-initiated payments
CREATE TABLE IF NOT EXISTS ussd_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    transaction_ref VARCHAR(100) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NOT NULL,
    event_id INT,
    nominee_id INT,
    vote_count INT DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    paystack_reference VARCHAR(100),
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transaction_ref (transaction_ref),
    INDEX idx_session_id (session_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_status (status),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE
);
