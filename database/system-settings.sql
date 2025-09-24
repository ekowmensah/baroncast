-- Create system_settings table for configurable vote costs
-- Run this SQL in phpMyAdmin or your database management tool

CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Insert default vote cost settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('default_vote_cost', '1.00', 'Default cost per vote when event has no custom fee')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('enable_event_custom_fee', '1', 'Allow events to set custom voting fees (1=enabled, 0=disabled)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('min_vote_cost', '0.50', 'Minimum allowed vote cost for custom event fees')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('max_vote_cost', '100.00', 'Maximum allowed vote cost for custom event fees')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Show current settings
SELECT * FROM system_settings WHERE setting_key LIKE '%vote%' OR setting_key LIKE '%fee%';