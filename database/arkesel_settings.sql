-- Add Arkesel USSD Payment Settings to System Settings
-- Run this SQL directly in phpMyAdmin

INSERT INTO system_settings (setting_key, setting_value, description, created_at) 
VALUES 
('arkesel_api_key', '', 'Arkesel API Key for USSD payments', NOW()),
('arkesel_api_secret', '', 'Arkesel API Secret for USSD payments', NOW()),
('enable_ussd_payments', '1', 'Enable USSD payment method for voting', NOW()),
('ussd_payment_provider', 'arkesel', 'USSD payment provider (arkesel, hubtel, etc.)', NOW()),
('ussd_fallback_code', '*170*456#', 'Fallback USSD code for development/testing', NOW())
ON DUPLICATE KEY UPDATE 
setting_value = VALUES(setting_value), 
description = VALUES(description);

-- Verify the settings were added
SELECT * FROM system_settings WHERE setting_key LIKE '%arkesel%' OR setting_key LIKE '%ussd%';
