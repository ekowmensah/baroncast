-- Hubtel Direct Receive Money Integration Schema Updates (Simplified)
-- Run this to add Hubtel-specific columns and settings

-- Add Hubtel-specific columns to transactions table (without IF NOT EXISTS)
ALTER TABLE transactions 
ADD COLUMN hubtel_transaction_id VARCHAR(100) NULL COMMENT 'Hubtel transaction ID',
ADD COLUMN external_transaction_id VARCHAR(100) NULL COMMENT 'Telco transaction ID',
ADD COLUMN payment_charges DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Hubtel payment charges',
ADD COLUMN amount_charged DECIMAL(10,2) NULL COMMENT 'Total amount charged to customer';

-- Add indexes for better performance
CREATE INDEX idx_transactions_hubtel_id ON transactions(hubtel_transaction_id);
CREATE INDEX idx_transactions_external_id ON transactions(external_transaction_id);

-- Insert Hubtel system settings
INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('hubtel_pos_id', '', 'Hubtel POS Sales ID for Direct Receive Money API', 'payment'),
('hubtel_api_key', '', 'Hubtel API Key for authentication', 'payment'),
('hubtel_api_secret', '', 'Hubtel API Secret for authentication', 'payment'),
('hubtel_environment', 'sandbox', 'Hubtel environment (sandbox/production)', 'payment'),
('hubtel_callback_url', '', 'Webhook callback URL for payment confirmations', 'payment'),
('hubtel_ip_whitelist', '', 'Comma-separated list of whitelisted IPs', 'payment'),
('enable_hubtel_payments', '1', 'Enable Hubtel mobile money payments', 'payment'),
('hubtel_timeout', '30', 'API request timeout in seconds', 'payment'),
('hubtel_max_retries', '3', 'Maximum retry attempts for failed requests', 'payment'),
('hubtel_test_mode', '1', 'Enable test mode for development', 'payment')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description),
    category = VALUES(category);

-- Create Hubtel transaction log table for debugging
CREATE TABLE hubtel_transaction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_reference VARCHAR(100) NOT NULL,
    log_type ENUM('request', 'response', 'callback', 'status_check', 'error') NOT NULL,
    log_data JSON NOT NULL,
    http_code INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reference (transaction_reference),
    INDEX idx_type_date (log_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create view for Hubtel payment analytics
CREATE VIEW hubtel_payment_summary AS
SELECT 
    DATE(created_at) as payment_date,
    COUNT(*) as total_transactions,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN status = 'completed' THEN payment_charges ELSE 0 END) as total_charges,
    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_amount
FROM transactions 
WHERE payment_method = 'mobile_money'
GROUP BY DATE(created_at)
ORDER BY payment_date DESC;