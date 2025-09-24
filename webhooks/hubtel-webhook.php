<?php
/**
 * Hubtel Webhook Handler
 * Processes payment callbacks from Hubtel API
 */

header('Content-Type: application/json');

// Log webhook for debugging
$logFile = __DIR__ . '/../logs/hubtel-webhook.log';
$payload = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook received: " . $payload . "\n", FILE_APPEND);

try {
    require_once __DIR__ . '/../services/HubtelService.php';
    
    $hubtel = new HubtelService();
    $result = $hubtel->processWebhook($payload);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Webhook processed successfully'
        ]);
        
        // Log success
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook processed successfully: " . json_encode($result) . "\n", FILE_APPEND);
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $result['message'] ?? 'Failed to process webhook'
        ]);
        
        // Log error
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook processing failed: " . $result['message'] . "\n", FILE_APPEND);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
    
    // Log exception
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
