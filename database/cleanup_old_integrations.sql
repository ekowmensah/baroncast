-- Cleanup Old Payment Integration Settings
-- Remove Arkesel and Paystack settings from system_settings table

-- Remove Arkesel settings
DELETE FROM system_settings WHERE setting_key LIKE 'arkesel_%';
DELETE FROM system_settings WHERE setting_key = 'enable_arkesel_ussd';

-- Remove Paystack settings
DELETE FROM system_settings WHERE setting_key LIKE 'paystack_%';
DELETE FROM system_settings WHERE setting_key = 'enable_paystack';

-- Remove JuniPay settings
DELETE FROM system_settings WHERE setting_key LIKE 'junipay_%';
DELETE FROM system_settings WHERE setting_key = 'enable_junipay';

-- Remove card payment settings (since we're Hubtel-only now)
DELETE FROM system_settings WHERE setting_key = 'enable_card_payments';

-- Add Hubtel-specific settings if they don't exist
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('hubtel_client_id', '', 'Hubtel API Client ID'),
('hubtel_client_secret', '', 'Hubtel API Client Secret'),
('hubtel_api_key', '', 'Hubtel API Key'),
('hubtel_sender_id', 'E-Cast', 'SMS Sender ID'),
('enable_hubtel_sms', '1', 'Enable Hubtel SMS integration'),
('hubtel_environment', 'sandbox', 'Hubtel environment (sandbox/production)'),
('hubtel_sms_timeout', '30', 'SMS timeout in seconds'),
('hubtel_max_retries', '3', 'Maximum retry attempts'),
('enable_hubtel_payments', '1', 'Enable Hubtel payment integration'),
('hubtel_webhook_url', '', 'Hubtel webhook callback URL');

-- Update payment method references in existing transactions
UPDATE transactions SET payment_method = 'hubtel_mobile_money' WHERE payment_method = 'mobile_money';
UPDATE transactions SET payment_method = 'hubtel_ussd' WHERE payment_method = 'ussd';

-- Clean up any orphaned payment references
UPDATE transactions SET payment_method = 'hubtel_mobile_money' WHERE payment_method IN ('arkesel', 'paystack', 'junipay');
