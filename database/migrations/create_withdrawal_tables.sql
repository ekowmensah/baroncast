-- Create withdrawal_requests table
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    withdrawal_method ENUM('mobile_money', 'bank') NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    bank_code VARCHAR(20) NULL,
    bank_name VARCHAR(100) NULL,
    mobile_network VARCHAR(50) NULL,
    status ENUM('pending', 'approved', 'processing', 'completed', 'rejected') DEFAULT 'pending',
    processed_by INT NULL,
    admin_notes TEXT NULL,
    transaction_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_organizer_id (organizer_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Create commission_settings table (if not exists)
CREATE TABLE IF NOT EXISTS commission_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commission_rate DECIMAL(5,2) DEFAULT 10.00,
    minimum_withdrawal DECIMAL(10,2) DEFAULT 10.00,
    withdrawal_fee DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default commission settings if table is empty
INSERT IGNORE INTO commission_settings (id, commission_rate, minimum_withdrawal, withdrawal_fee) 
VALUES (1, 10.00, 10.00, 0.00);

-- Insert default system settings for withdrawal system
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('commission_rate', '10', 'string', 'Platform commission rate percentage'),
('minimum_withdrawal', '10', 'string', 'Minimum withdrawal amount in GHS'),
('withdrawal_fee', '0', 'string', 'Fixed withdrawal processing fee in GHS');
