<?php
/**
 * Payment Gateway Configuration
 * Arkesel (SMS/USSD) & JuniPay (Mobile Money) Integration
 */

// Arkesel Configuration
define('ARKESEL_API_KEY', getenv('ARKESEL_API_KEY') ?: 'your_arkesel_api_key_here');
define('ARKESEL_BASE_URL', 'https://sms.arkesel.com/api/v2/');
define('ARKESEL_SENDER_ID', 'E-CAST'); // Your registered sender ID
define('ARKESEL_USSD_SHORTCODE', '*165*123#'); // Your USSD shortcode

// JuniPay Configuration  
define('JUNIPAY_API_KEY', getenv('JUNIPAY_API_KEY') ?: 'your_junipay_api_key_here');
define('JUNIPAY_SECRET_KEY', getenv('JUNIPAY_SECRET_KEY') ?: 'your_junipay_secret_key_here');
define('JUNIPAY_BASE_URL', 'https://api.junipayments.com/v1/');
define('JUNIPAY_WEBHOOK_SECRET', getenv('JUNIPAY_WEBHOOK_SECRET') ?: 'your_webhook_secret_here');

// Payment Configuration
define('PAYMENT_CURRENCY', 'GHS'); // Ghana Cedis
define('PAYMENT_TIMEOUT', 300); // 5 minutes timeout for payments
define('OTP_EXPIRY_TIME', 600); // 10 minutes OTP expiry
define('MAX_PAYMENT_RETRIES', 3);

// Supported Mobile Money Providers (Ghana)
define('SUPPORTED_MM_PROVIDERS', [
    'mtn' => [
        'name' => 'MTN Mobile Money',
        'prefix' => ['24', '25', '54', '55', '59'], // MTN Ghana prefixes
        'currency' => 'GHS',
        'logo' => '/assets/images/mtn-logo.png'
    ],
    'vodafone' => [
        'name' => 'Vodafone Cash',
        'prefix' => ['20', '50'], // Vodafone Ghana prefixes
        'currency' => 'GHS',
        'logo' => '/assets/images/vodafone-logo.png'
    ],
    'airteltigo' => [
        'name' => 'AirtelTigo Money',
        'prefix' => ['26', '27', '56', '57'], // AirtelTigo Ghana prefixes
        'currency' => 'GHS',
        'logo' => '/assets/images/airteltigo-logo.png'
    ]
]);

// Webhook URLs (update with your actual domain)
define('JUNIPAY_WEBHOOK_URL', 'https://yourdomain.com/webhooks/junipay-callback.php');
define('ARKESEL_WEBHOOK_URL', 'https://yourdomain.com/webhooks/arkesel-callback.php');

// Environment Configuration
define('PAYMENT_ENVIRONMENT', getenv('PAYMENT_ENVIRONMENT') ?: 'sandbox'); // sandbox or production

// Sandbox vs Production URLs
if (PAYMENT_ENVIRONMENT === 'sandbox') {
    // Use sandbox URLs for testing
    define('JUNIPAY_API_URL', 'https://sandbox.junipayments.com/v1/');
} else {
    // Use production URLs
    define('JUNIPAY_API_URL', JUNIPAY_BASE_URL);
}

// Logging Configuration
define('PAYMENT_LOG_FILE', __DIR__ . '/../logs/payment.log');
define('ENABLE_PAYMENT_LOGGING', true);

/**
 * Get mobile money provider based on phone number
 */
function getMobileMoneyProvider($phoneNumber) {
    $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Extract first 2 digits for Ghana numbers (after country code 233)
    if (strlen($cleanNumber) >= 12) {
        $prefix = substr($cleanNumber, -9, 2); // Get 2 digits after country code
        
        foreach (SUPPORTED_MM_PROVIDERS as $provider => $config) {
            if (in_array($prefix, $config['prefix'])) {
                return $provider;
            }
        }
    }
    
    return null;
}

/**
 * Format phone number for Ghana (+233)
 */
function formatGhanaPhoneNumber($phoneNumber) {
    $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Remove leading zeros
    $cleanNumber = ltrim($cleanNumber, '0');
    
    // Add Ghana country code if not present
    if (!str_starts_with($cleanNumber, '233')) {
        $cleanNumber = '233' . $cleanNumber;
    }
    
    return '+' . $cleanNumber;
}

/**
 * Format phone number (alias for backward compatibility)
 */
function formatUgandaPhoneNumber($phoneNumber) {
    return formatGhanaPhoneNumber($phoneNumber);
}

/**
 * Validate Ghana phone number format
 */
function isValidGhanaPhoneNumber($phoneNumber) {
    $formatted = formatGhanaPhoneNumber($phoneNumber);
    
    // Ghana phone numbers: +233 followed by 9 digits
    return preg_match('/^\+233[0-9]{9}$/', $formatted) && 
           getMobileMoneyProvider($formatted) !== null;
}

/**
 * Validate phone number format (alias for backward compatibility)
 */
function isValidUgandaPhoneNumber($phoneNumber) {
    return isValidGhanaPhoneNumber($phoneNumber);
}

/**
 * Log payment activity
 */
if (!function_exists('logPaymentActivity')) {
    function logPaymentActivity($level, $message, $data = []) {
    if (!ENABLE_PAYMENT_LOGGING) return;
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
    ];
    
    $logLine = json_encode($logEntry) . "\n";
    
    // Ensure log directory exists
    $logDir = dirname(PAYMENT_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(PAYMENT_LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
}
?>
