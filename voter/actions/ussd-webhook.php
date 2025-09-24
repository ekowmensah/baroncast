<?php
/**
 * Arkesel USSD Payment Webhook Handler
 * Receives payment confirmations and updates vote status
 */

require_once __DIR__ . '/arkesel-ussd-payment.php';

// Set headers for webhook response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log webhook received
error_log("USSD Webhook received: " . file_get_contents('php://input'));

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Get webhook data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
    
    // Initialize Arkesel payment handler
    $arkesel = new ArkeselUSSDPayment();
    
    // Process the webhook
    $result = $arkesel->handleWebhook($data);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['message']]);
    }
    
} catch (Exception $e) {
    error_log("Webhook processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
