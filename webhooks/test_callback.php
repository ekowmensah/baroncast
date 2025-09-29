<?php
/**
 * Callback Detector - Logs all incoming requests
 */

$log_file = __DIR__ . '/../logs/callback-detector.log';
$timestamp = date('c');
$input = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$headers = json_encode(getallheaders());

$log_entry = "[$timestamp] $method $uri\n";
$log_entry .= "Headers: $headers\n";
$log_entry .= "Body: $input\n";
$log_entry .= "--- END REQUEST ---\n\n";

file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// Respond like a successful callback
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Callback detector received request',
    'endpoint' => 'callback-detector.php'
]);
?>
