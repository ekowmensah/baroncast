<?php
/**
 * Hubtel Direct Receive Money Callback Handler
 * Processes payment confirmations from Hubtel API
 * 
 * This endpoint receives HTTP POST callbacks from Hubtel when:
 * - Payment is successfully completed
 * - Payment fails or is declined
 * - Payment times out
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/hubtel-callback.log');

function logCallback($level, $message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message";
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_SLASHES);
    }
    error_log($logEntry);
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        logCallback('ERROR', 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
        exit;
    }
    
    // Get raw callback data
    $rawInput = file_get_contents('php://input');
    logCallback('INFO', 'Callback received', ['raw_input' => $rawInput]);
    
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty callback data']);
        logCallback('ERROR', 'Empty callback payload received');
        exit;
    }
    
    // Parse JSON data
    $callbackData = json_decode($rawInput, true);
    if (!$callbackData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        logCallback('ERROR', 'Invalid JSON in callback', ['raw_input' => $rawInput]);
        exit;
    }
    
    logCallback('INFO', 'Callback data parsed successfully', $callbackData);
    
    // Load Hubtel service
    require_once __DIR__ . '/../services/HubtelReceiveMoneyService.php';
    $hubtelService = new HubtelReceiveMoneyService();
    
    // Process the callback
    $result = $hubtelService->processCallback($callbackData);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Callback processed successfully',
            'processed' => $result['processed'],
            'payment_status' => $result['status']
        ]);
        
        logCallback('SUCCESS', 'Callback processed successfully', [
            'client_reference' => $result['client_reference'] ?? 'unknown',
            'status' => $result['status']
        ]);
        
        // Optional: Send confirmation SMS or email here
        if ($result['status'] === 'completed') {
            sendPaymentConfirmation($callbackData);
        }
        
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $result['message'] ?? 'Callback processing failed'
        ]);
        
        logCallback('ERROR', 'Callback processing failed', $result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
    
    logCallback('CRITICAL', 'Exception in callback processing', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

/**
 * Send payment confirmation (optional)
 */
function sendPaymentConfirmation($callbackData) {
    try {
        $data = $callbackData['Data'] ?? [];
        $clientReference = $data['ClientReference'] ?? '';
        $amount = $data['Amount'] ?? 0;
        
        // Log confirmation sent
        logCallback('INFO', 'Payment confirmation triggered', [
            'reference' => $clientReference,
            'amount' => $amount
        ]);
        
        // Here you could:
        // 1. Send SMS confirmation (if SMS service available)
        // 2. Send email notification
        // 3. Update user notifications
        // 4. Trigger real-time updates via WebSocket
        
    } catch (Exception $e) {
        logCallback('WARNING', 'Confirmation sending failed', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Store callback for debugging and audit trail
 */
function storeCallbackForAudit($callbackData) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        $data = $callbackData['Data'] ?? [];
        $clientReference = $data['ClientReference'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO hubtel_transaction_logs 
            (transaction_reference, log_type, log_data, created_at) 
            VALUES (?, 'callback', ?, NOW())
        ");
        
        $stmt->execute([$clientReference, json_encode($callbackData)]);
        
        logCallback('INFO', 'Callback stored for audit', ['reference' => $clientReference]);
        
    } catch (Exception $e) {
        logCallback('WARNING', 'Failed to store callback for audit', [
            'error' => $e->getMessage()
        ]);
    }
}

// Store callback for audit trail (optional)
if (isset($callbackData)) {
    storeCallbackForAudit($callbackData);
}
?>