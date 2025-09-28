<?php
/**
 * Shortcode Voting Webhook Handler
 * Handles incoming USSD/shortcode requests for voting
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../services/ShortcodeVotingService.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/shortcode-voting.log');

function logRequest($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $logEntry .= ' - ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Get the raw input
    $rawInput = file_get_contents('php://input');
    $requestData = json_decode($rawInput, true);
    
    logRequest('Shortcode voting webhook received', $requestData);
    
    if (!$requestData) {
        // Try to parse as form data if JSON fails
        $requestData = $_POST;
        if (empty($requestData)) {
            throw new Exception('No valid input data received');
        }
    }
    
    // Extract required fields (supporting multiple formats)
    $phoneNumber = $requestData['Mobile'] ?? $requestData['mobile'] ?? $requestData['phone_number'] ?? '';
    $input = $requestData['Message'] ?? $requestData['message'] ?? $requestData['input'] ?? '';
    $sessionId = $requestData['SessionId'] ?? $requestData['session_id'] ?? $requestData['sessionid'] ?? null;
    $sequence = $requestData['Sequence'] ?? $requestData['sequence'] ?? 1;
    
    // Clean the input
    $input = trim($input);
    
    // For first request (sequence 1), input might be the shortcode itself
    if ($sequence == 1 && (empty($input) || preg_match('/^\*\d+\*\d+#$/', $input))) {
        $input = ''; // Reset input for welcome message
    }
    
    logRequest('Processing shortcode request', [
        'phone' => $phoneNumber,
        'input' => $input,
        'session_id' => $sessionId,
        'sequence' => $sequence
    ]);
    
    if (empty($phoneNumber)) {
        throw new Exception('Phone number is required');
    }
    
    // Initialize shortcode voting service
    $votingService = new ShortcodeVotingService();
    
    // Process the shortcode request
    $response = $votingService->handleShortcodeRequest($phoneNumber, $input, $sessionId);
    
    logRequest('Shortcode voting response', $response);
    
    // Return response to Hubtel
    echo json_encode($response);
    
} catch (Exception $e) {
    logRequest('Shortcode voting webhook error', ['error' => $e->getMessage()]);
    
    // Return error response
    $errorResponse = [
        'Type' => 'Release',
        'Message' => 'Service temporarily unavailable. Please try again later.',
        'Mask' => 0
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
}

// Log the final response for debugging
if (isset($response)) {
    logRequest('Final response sent', $response);
} else {
    logRequest('Error response sent', $errorResponse ?? ['error' => 'Unknown error']);
}
?>
