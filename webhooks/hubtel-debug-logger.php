<?php
/**
 * Hubtel Debug Logger - Logs ALL requests to see what Hubtel sends
 */

header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/hubtel-debug.log');

// Get ALL request data
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$requestHeaders = function_exists('getallheaders') ? getallheaders() : [];
$rawInput = file_get_contents('php://input');
$getParams = $_GET;
$postParams = $_POST;
$serverVars = $_SERVER;

// Create comprehensive log entry
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $requestMethod,
    'url' => $serverVars['REQUEST_URI'] ?? 'unknown',
    'headers' => $requestHeaders,
    'get_params' => $getParams,
    'post_params' => $postParams,
    'raw_input' => $rawInput,
    'raw_input_length' => strlen($rawInput),
    'content_type' => $serverVars['CONTENT_TYPE'] ?? 'not set',
    'user_agent' => $serverVars['HTTP_USER_AGENT'] ?? 'not set',
    'remote_addr' => $serverVars['REMOTE_ADDR'] ?? 'unknown',
    'query_string' => $serverVars['QUERY_STRING'] ?? '',
];

// Log to file
error_log("=== HUBTEL DEBUG REQUEST ===");
error_log(json_encode($logData, JSON_PRETTY_PRINT));
error_log("=== END DEBUG REQUEST ===");

// Try to parse the data
$parsedData = null;
if (!empty($rawInput)) {
    // Try JSON first
    $parsedData = json_decode($rawInput, true);
    
    if (!$parsedData) {
        // Try form data
        parse_str($rawInput, $formData);
        if (!empty($formData)) {
            $parsedData = $formData;
        }
    }
}

error_log("Parsed data: " . json_encode($parsedData));

// Always respond with success to keep Hubtel happy
echo json_encode([
    'status' => 'success',
    'message' => 'Request logged successfully',
    'received_at' => date('Y-m-d H:i:s'),
    'method' => $requestMethod,
    'data_received' => !empty($rawInput),
    'parsed_successfully' => $parsedData !== null
]);
?>
