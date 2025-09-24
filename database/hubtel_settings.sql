-- Hubtel SMS Settings Migration
-- Import this file into phpMyAdmin to add Hubtel SMS integration settings

-- Insert Hubtel SMS settings into system_settings table
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('hubtel_client_id', '', 'Hubtel SMS Client ID'),
('hubtel_client_secret', '', 'Hubtel SMS Client Secret'),
('hubtel_api_key', '', 'Hubtel SMS API Key'),
('hubtel_sender_id', 'E-Cast', 'Hubtel SMS Sender ID'),
('enable_hubtel_sms', '0', 'Enable Hubtel SMS Integration'),
('hubtel_environment', 'sandbox', 'Hubtel Environment (sandbox/production)'),
('hubtel_sms_timeout', '30', 'Hubtel SMS Timeout in seconds'),
('hubtel_max_retries', '3', 'Maximum SMS retry attempts')
ON DUPLICATE KEY UPDATE 
setting_value = IF(setting_value = '', VALUES(setting_value), setting_value),
description = VALUES(description);

-- Verify the settings were added
SELECT setting_key, setting_value, description 
FROM system_settings 
WHERE setting_key LIKE 'hubtel_%' OR setting_key = 'enable_hubtel_sms'
ORDER BY setting_key;
