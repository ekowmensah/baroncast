<?php
/**
 * USSD Callback Handler for Arkesel Integration
 * Processes USSD session requests and returns appropriate responses
 */

require_once __DIR__ . '/../services/ArkeselUSSDService.php';

header('Content-Type: application/json');

// Only allow POST requests from Arkesel
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get USSD session data from Arkesel
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Log USSD request for debugging
    error_log("USSD Request: " . json_encode($input));
    
    // Initialize USSD service
    $ussdService = new ArkeselUSSDService();
    
    // Process USSD session
    $response = $ussdService->handleUSSDSession($input);
    
    // Log USSD response
    error_log("USSD Response: " . json_encode($response));
    
    // Return response to Arkesel
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("USSD Callback Error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'type' => 'end',
        'message' => 'Service temporarily unavailable. Please try again later.',
        'continue' => false
    ]);
}
?>
