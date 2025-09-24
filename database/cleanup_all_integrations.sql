-- Comprehensive Cleanup Script for Arkesel, Hubtel, and Paystack
-- This script removes all database traces of these payment integrations
-- Run this in phpMyAdmin or MySQL command line

-- =============================================================================
-- 1. REMOVE ARKESEL SETTINGS
-- =============================================================================

DELETE FROM system_settings WHERE setting_key IN (
    'arkesel_api_key',
    'arkesel_api_secret', 
    'arkesel_sender_id',
    'arkesel_ussd_shortcode',
    'arkesel_webhook_url',
    'enable_ussd_payments',
    'ussd_payment_provider',
    'ussd_fallback_code',
    'ussd_short_code',
    'ussd_app_name',
    'ussd_welcome_message',
    'enable_ussd_voting',
    'enable_ussd_sms',
    'ussd_session_timeout',
    'ussd_max_menu_items',
    'ussd_app_id',
    'ussd_app_status'
);

-- =============================================================================
-- 2. REMOVE HUBTEL SETTINGS  
-- =============================================================================

DELETE FROM system_settings WHERE setting_key IN (
    'hubtel_client_id',
    'hubtel_client_secret',
    'hubtel_api_key',
    'hubtel_sender_id',
    'hubtel_merchant_account',
    'enable_hubtel_sms',
    'hubtel_environment',
    'hubtel_sms_timeout',
    'hubtel_max_retries',
    'hubtel_webhook_url',
    'hubtel_webhook_secret',
    'enable_hubtel_payments'
);

-- =============================================================================
-- 3. REMOVE PAYSTACK SETTINGS
-- =============================================================================

DELETE FROM system_settings WHERE setting_key IN (
    'paystack_public_key',
    'paystack_secret_key',
    'paystack_webhook_secret',
    'payment_currency',
    'payment_gateway',
    'payment_environment',
    'payment_timeout',
    'enable_payment_logging',
    'enable_paystack'
);

-- =============================================================================
-- 4. CLEAN UP PAYMENT-RELATED SETTINGS
-- =============================================================================

DELETE FROM system_settings WHERE setting_key IN (
    'enable_card_payments',
    'enable_mobile_money',
    'vote_cost',
    'payment_methods_enabled'
);

-- =============================================================================
-- 5. REMOVE TRANSACTION RECORDS (OPTIONAL - COMMENT OUT IF YOU WANT TO KEEP)
-- =============================================================================

-- WARNING: This will delete all payment transaction records
-- Uncomment only if you want to completely reset payment history

-- DELETE FROM transactions WHERE payment_method IN ('ussd', 'mobile_money', 'card', 'paystack');
-- DELETE FROM votes WHERE payment_method IN ('ussd', 'mobile_money', 'card');

-- =============================================================================
-- 6. CLEAN UP USSD SESSIONS TABLE (IF EXISTS)
-- =============================================================================

DROP TABLE IF EXISTS ussd_sessions;

-- =============================================================================
-- 7. VERIFICATION QUERIES
-- =============================================================================

-- Run these to verify cleanup was successful:

SELECT 'Remaining Arkesel settings:' as check_type, COUNT(*) as count 
FROM system_settings 
WHERE setting_key LIKE '%arkesel%' OR setting_key LIKE '%ussd%';

SELECT 'Remaining Hubtel settings:' as check_type, COUNT(*) as count 
FROM system_settings 
WHERE setting_key LIKE '%hubtel%';

SELECT 'Remaining Paystack settings:' as check_type, COUNT(*) as count 
FROM system_settings 
WHERE setting_key LIKE '%paystack%';

SELECT 'All payment-related settings:' as check_type, setting_key, setting_value 
FROM system_settings 
WHERE setting_key LIKE '%payment%' 
   OR setting_key LIKE '%arkesel%' 
   OR setting_key LIKE '%hubtel%' 
   OR setting_key LIKE '%paystack%'
   OR setting_key LIKE '%ussd%';

-- =============================================================================
-- SCRIPT COMPLETED
-- =============================================================================