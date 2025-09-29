<?php
/**
 * Simple USSD Webhook Test
 * Use this to test if your webhook URL is properly configured
 */

header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/ussd-test.log');

function logTest($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $logEntry .= ' - ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Log all incoming data
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = file_get_contents('php://input');
    $headers = getallheaders();
    
    logTest('USSD Test Webhook Called', [
        'method' => $method,
        'raw_input' => $rawInput,
        'headers' => $headers,
        'get_params' => $_GET,
        'post_params' => $_POST
    ]);
    
    // Parse JSON if available
    $webhookData = json_decode($rawInput, true);
    
    if ($webhookData) {
        logTest('Parsed webhook data', $webhookData);
    }
    
    // Return a simple USSD response
    $response = [
        'Type' => 'Release',
        'Message' => 'USSD Webhook Test Successful! Your webhook is working correctly.'
    ];
    
    logTest('Sending response', $response);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logTest('Test webhook error', ['error' => $e->getMessage()]);
    
    echo json_encode([
        'Type' => 'Release',
        'Message' => 'Test webhook error occurred.'
    ]);
}
?>
