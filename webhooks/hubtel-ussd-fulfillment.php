<?php
/**
 * Hubtel USSD Service Fulfillment Endpoint
 * This is called by Hubtel when payment is needed
 */

require_once __DIR__ . '/../services/HubtelUSSDService.php';

// Log the fulfillment request
$input = file_get_contents('php://input');
$fulfillmentData = json_decode($input, true);

error_log("=== USSD Fulfillment Request ===");
error_log("Fulfillment data: " . json_encode($fulfillmentData));

try {
    $ussdService = new HubtelUSSDService();
    $response = $ussdService->handleUSSDFulfillment($fulfillmentData);
    
    error_log("Fulfillment response: " . json_encode($response));
    
    // Return response to Hubtel
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("USSD Fulfillment error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'ResponseCode' => '1001',
        'Message' => 'Service temporarily unavailable'
    ]);
}
?>
