-- Remove Arkesel and Hubtel settings from system_settings table
-- Replace with Paystack settings

-- Remove Arkesel settings
DELETE FROM system_settings WHERE setting_key IN (
    'arkesel_api_key',
    'arkesel_sender_id',
    'arkesel_ussd_shortcode',
    'arkesel_webhook_url'
);

-- Remove Hubtel settings
DELETE FROM system_settings WHERE setting_key IN (
    'hubtel_client_id',
    'hubtel_client_secret',
    'hubtel_sender_id',
    'hubtel_api_key'
);

-- Add Paystack settings
INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('paystack_public_key', '', 'Paystack Public Key for frontend integration', 'payment'),
('paystack_secret_key', '', 'Paystack Secret Key for API calls', 'payment'),
('paystack_webhook_secret', '', 'Paystack Webhook Secret for signature verification', 'payment'),
('payment_currency', 'GHS', 'Payment currency (Ghana Cedis)', 'payment'),
('payment_gateway', 'paystack', 'Primary payment gateway', 'payment')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description),
    category = VALUES(category);
