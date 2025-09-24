-- Create system_settings table for storing application configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
-- Site Information
('site_name', 'E-Cast Voting Platform', 'string', 'Name of the voting platform'),
('site_description', 'Secure and transparent online voting platform', 'string', 'Brief description of the platform'),
('admin_email', 'admin@example.com', 'string', 'Primary admin email address'),
('timezone', 'Africa/Accra', 'string', 'System timezone'),
('date_format', 'Y-m-d', 'string', 'Date display format'),
('time_format', 'H:i:s', 'string', 'Time display format'),

-- System Behavior
('maintenance_mode', '0', 'boolean', 'Enable maintenance mode'),
('user_registration', '1', 'boolean', 'Allow new user registration'),
('auto_approve_events', '0', 'boolean', 'Auto-approve new events'),

-- File Upload Settings
('max_file_size', '5', 'integer', 'Maximum file upload size in MB'),
('allowed_file_types', 'jpg,jpeg,png,gif', 'string', 'Allowed file extensions'),

-- Payment Settings
('payment_environment', 'sandbox', 'string', 'Payment environment (sandbox/production)'),
('payment_timeout', '300', 'integer', 'Payment timeout in seconds'),
('otp_expiry_time', '600', 'integer', 'OTP expiry time in seconds'),
('max_payment_retries', '3', 'integer', 'Maximum payment retry attempts'),
('enable_payment_logging', '1', 'boolean', 'Enable payment activity logging'),

-- Email Settings
('smtp_host', '', 'string', 'SMTP server hostname'),
('smtp_port', '587', 'integer', 'SMTP server port'),
('smtp_username', '', 'string', 'SMTP username'),
('smtp_password', '', 'string', 'SMTP password'),
('smtp_encryption', 'tls', 'string', 'SMTP encryption method'),
('mail_from_address', '', 'string', 'Default from email address'),
('mail_from_name', 'E-Cast Voting Platform', 'string', 'Default from name'),
('enable_email_notifications', '1', 'boolean', 'Enable email notifications'),
('notify_new_events', '1', 'boolean', 'Notify admin of new events'),
('notify_new_users', '1', 'boolean', 'Notify admin of new user registrations'),
('notify_payment_issues', '1', 'boolean', 'Notify admin of payment issues'),

-- Security Settings
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('password_min_length', '8', 'integer', 'Minimum password length'),
('enable_two_factor', '0', 'boolean', 'Enable two-factor authentication'),
('login_attempts_limit', '5', 'integer', 'Maximum login attempts before lockout'),
('lockout_duration', '900', 'integer', 'Account lockout duration in seconds'),

-- Analytics Settings
('enable_analytics', '1', 'boolean', 'Enable system analytics'),
('analytics_retention_days', '365', 'integer', 'Days to retain analytics data'),

-- Notification Settings
('enable_sms_notifications', '1', 'boolean', 'Enable SMS notifications'),
('enable_push_notifications', '0', 'boolean', 'Enable push notifications'),

-- Performance Settings
('cache_enabled', '1', 'boolean', 'Enable system caching'),
('cache_duration', '3600', 'integer', 'Cache duration in seconds'),
('max_concurrent_votes', '1000', 'integer', 'Maximum concurrent votes allowed'),

-- Backup Settings
('auto_backup_enabled', '1', 'boolean', 'Enable automatic database backups'),
('backup_frequency', 'daily', 'string', 'Backup frequency (daily/weekly/monthly)'),
('backup_retention_days', '30', 'integer', 'Days to retain backup files')

ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;
