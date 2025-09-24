<?php
/**
 * Hubtel API Credentials Configuration Template
 * Copy this file and rename to hubtel-credentials.php with your actual credentials
 * 
 * CRITICAL: You must obtain valid Hubtel API credentials for live server deployment
 */

return [
    // Hubtel SMS API Credentials
    'hubtel_client_id' => 'YOUR_HUBTEL_CLIENT_ID',
    'hubtel_client_secret' => 'YOUR_HUBTEL_CLIENT_SECRET', 
    'hubtel_api_key' => 'YOUR_HUBTEL_API_KEY',
    'hubtel_sender_id' => 'E-Cast', // Your approved sender ID
    
    // Hubtel Payment API Credentials  
    'hubtel_merchant_account' => 'YOUR_MERCHANT_ACCOUNT_NUMBER',
    'hubtel_checkout_client_id' => 'YOUR_CHECKOUT_CLIENT_ID',
    'hubtel_checkout_client_secret' => 'YOUR_CHECKOUT_CLIENT_SECRET',
    
    // Environment Settings
    'hubtel_environment' => 'production', // 'sandbox' or 'production'
    'enable_hubtel_sms' => true,
    'hubtel_sms_timeout' => 30,
    'hubtel_max_retries' => 3,
    
    // Callback URLs (update with your live domain)
    'callback_base_url' => 'https://yourdomain.com',
    'webhook_url' => 'https://yourdomain.com/webhooks/hubtel-webhook.php',
    'return_url' => 'https://yourdomain.com/voter/payment-success.php',
    'cancel_url' => 'https://yourdomain.com/voter/payment-cancelled.php'
];

/*
SETUP INSTRUCTIONS FOR LIVE SERVER:

1. Get Hubtel Account:
   - Visit https://hubtel.com
   - Create merchant account
   - Get API credentials from dashboard

2. Configure Credentials:
   - Copy this file to hubtel-credentials.php
   - Replace all YOUR_* placeholders with actual values
   - Set correct callback URLs with your domain

3. Test Integration:
   - Use sandbox environment first
   - Switch to production when ready
   - Verify SMS and payment flows work

4. Security:
   - Never commit actual credentials to version control
   - Use environment variables in production
   - Restrict API access by IP if possible
*/
?>
