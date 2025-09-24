<?php
/**
 * Production Configuration
 * All features run in production mode with real API credentials
 */

// Development mode - disabled for production payment testing
define('DEVELOPMENT_MODE', false);

// Development helper functions
function isDevelopmentMode() {
    return DEVELOPMENT_MODE;
}

function shouldBypassSMS() {
    return DEVELOPMENT_MODE;
}

function shouldSimulatePayments() {
    return DEVELOPMENT_MODE;
}

/**
 * Log production payment activity
 */
if (!function_exists('logPaymentActivity')) {
    function logPaymentActivity($level, $action, $data = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'action' => $action,
            'data' => $data,
            'mode' => 'PRODUCTION'
        ];
        
        $logFile = __DIR__ . '/../logs/payments.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
?>
