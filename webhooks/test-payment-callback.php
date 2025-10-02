<?php
/**
 * Simple test for payment callback endpoint
 */

header('Content-Type: application/json');

// Basic error handling
try {
    // Log that we received a request
    $logFile = __DIR__ . '/../logs/payment-callback-test.log';
    
    // Ensure logs directory exists
    $logsDir = dirname($logFile);
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    // Get request data
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $rawInput = file_get_contents('php://input');
    $timestamp = date('Y-m-d H:i:s');
    
    // Log the request
    $logEntry = "[$timestamp] Method: $method, Input: $rawInput\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Try to get headers safely
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Fallback for servers without getallheaders()
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[str_replace('HTTP_', '', $key)] = $value;
            }
        }
    }
    
    file_put_contents($logFile, "[$timestamp] Headers: " . json_encode($headers) . "\n", FILE_APPEND | LOCK_EX);
    
    // Parse JSON if present
    $data = null;
    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
        file_put_contents($logFile, "[$timestamp] Parsed data: " . json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    // Always respond with success for testing
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Test callback received',
        'timestamp' => $timestamp,
        'method' => $method,
        'data_received' => !empty($rawInput)
    ]);
    
} catch (Exception $e) {
    // Log error
    $errorLog = __DIR__ . '/../logs/payment-callback-error.log';
    file_put_contents($errorLog, date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?>
