<?php
/**
 * Test Payment Callback Endpoint
 * Use this to verify Hubtel can reach your server
 */

// Log everything
$logFile = __DIR__ . '/logs/payment-callback-test.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

$input = file_get_contents('php://input');
$headers = getallheaders();
$timestamp = date('Y-m-d H:i:s');

$logEntry = "[$timestamp] TEST CALLBACK RECEIVED\n";
$logEntry .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$logEntry .= "Input: $input\n";
$logEntry .= "Headers: " . json_encode($headers) . "\n";
$logEntry .= "GET: " . json_encode($_GET) . "\n";
$logEntry .= "POST: " . json_encode($_POST) . "\n";
$logEntry .= "---\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Return success
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Test callback received',
    'timestamp' => $timestamp
]);
?>
