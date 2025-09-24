-- EMERGENCY: Nuclear Cleanup of Hubtel Settings
-- This will completely wipe and rebuild your Hubtel configuration

-- Step 1: Show the chaos
SELECT 'Before cleanup' as status, COUNT(*) as total_settings 
FROM system_settings 
WHERE setting_key LIKE 'hubtel_%';

-- Step 2: NUCLEAR DELETE - Remove everything
DELETE FROM system_settings WHERE setting_key LIKE 'hubtel_%';

-- Step 3: Verify nuclear deletion worked
SELECT 'After deletion' as status, COUNT(*) as remaining_settings 
FROM system_settings 
WHERE setting_key LIKE 'hubtel_%';

-- Step 4: Fresh start - Insert ONLY what you need for Direct Receive Money API
INSERT INTO system_settings (setting_key, setting_value) VALUES
('hubtel_pos_id', '2031233'),
('hubtel_api_key', 'mGLrKNA'),
('hubtel_api_secret', 'zuihjmpr'),
('hubtel_environment', 'production'),
('hubtel_callback_url', 'https://baroncast.online/baroncas_voting/webhooks/hubtel-receive-money-callback.php'),
('enable_hubtel_payments', '1');

-- Step 5: Final verification - should be exactly 6 entries
SELECT 'Final result' as status, setting_key, setting_value 
FROM system_settings 
WHERE setting_key LIKE 'hubtel_%' 
ORDER BY setting_key;

-- Step 6: Count check - MUST be exactly 6
SELECT 'Final count' as status, COUNT(*) as should_be_six 
FROM system_settings 
WHERE setting_key LIKE 'hubtel_%';