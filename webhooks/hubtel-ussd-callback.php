<?php
/**
 * Hubtel USSD Webhook Handler
 * Processes USSD session interactions and payment callbacks
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../services/HubtelUSSDService.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/hubtel-ussd-webhook.log');

function logWebhook($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $logEntry .= ' - ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Get raw POST data
    $rawInput = file_get_contents('php://input');
    $webhookData = json_decode($rawInput, true);
    
    logWebhook('Hubtel USSD webhook received', $webhookData);
    
    if (!$webhookData) {
        throw new Exception('Invalid JSON input');
    }
    
    // Determine webhook type
    $webhookType = $webhookData['Type'] ?? $webhookData['type'] ?? 'unknown';
    
    switch (strtolower($webhookType)) {
        case 'ussd':
        case 'ussd_session':
            // Handle USSD session interaction
            $response = handleUSSDSession($webhookData);
            break;
            
        case 'payment':
        case 'payment_callback':
            // Handle USSD payment callback
            $response = handleUSSDPaymentCallback($webhookData);
            break;
            
        default:
            // Try to handle as USSD session if it has required fields
            if (isset($webhookData['SessionId']) || isset($webhookData['Mobile'])) {
                $response = handleUSSDSession($webhookData);
            } else {
                throw new Exception('Unknown webhook type: ' . $webhookType);
            }
    }
    
    logWebhook('Hubtel USSD webhook response', $response);
    
    // Return response to Hubtel
    echo json_encode($response);
    
} catch (Exception $e) {
    logWebhook('Hubtel USSD webhook error', ['error' => $e->getMessage()]);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'Type' => 'Release',
        'Message' => 'Service temporarily unavailable. Please try again later.'
    ]);
}

/**
 * Handle USSD session interactions
 */
function handleUSSDSession($webhookData) {
    try {
        $startTime = microtime(true);
        
        // Initialize USSD service
        $ussdService = new HubtelUSSDService();
        
        // Process USSD session
        $response = $ussdService->handleUSSDSession($webhookData);
        
        $processingTime = round((microtime(true) - $startTime) * 1000);
        
        // Log the interaction
        logUSSDInteraction($webhookData, $response, $processingTime);
        
        return $response;
        
    } catch (Exception $e) {
        logWebhook('USSD session handling error', ['error' => $e->getMessage()]);
        
        return [
            'Type' => 'Release',
            'Message' => 'Service error. Please try again later.'
        ];
    }
}

/**
 * Handle USSD payment callbacks
 */
function handleUSSDPaymentCallback($webhookData) {
    try {
        require_once __DIR__ . '/../config/database.php';
        
        // Set HTTP_HOST for proper database connection
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }
        
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Extract payment data
        $transactionId = $webhookData['TransactionId'] ?? '';
        $clientReference = $webhookData['ClientReference'] ?? '';
        $responseCode = $webhookData['ResponseCode'] ?? '';
        $status = $webhookData['Status'] ?? '';
        $amount = $webhookData['Amount'] ?? 0;
        $phoneNumber = $webhookData['CustomerMsisdn'] ?? '';
        
        logWebhook('Processing USSD payment callback', [
            'transaction_id' => $transactionId,
            'client_reference' => $clientReference,
            'response_code' => $responseCode,
            'status' => $status,
            'amount' => $amount
        ]);
        
        // Map Hubtel status to internal status
        $internalStatus = 'pending';
        if ($responseCode === '0000' && strtolower($status) === 'success') {
            $internalStatus = 'completed';
        } elseif (in_array(strtolower($status), ['failed', 'cancelled', 'declined'])) {
            $internalStatus = 'failed';
        }
        
        // Update USSD transaction
        $stmt = $pdo->prepare("
            UPDATE ussd_transactions 
            SET status = ?, hubtel_transaction_id = ?, completed_at = NOW()
            WHERE transaction_ref = ?
        ");
        $stmt->execute([$internalStatus, $transactionId, $clientReference]);
        
        // Get USSD transaction details
        $stmt = $pdo->prepare("SELECT * FROM ussd_transactions WHERE transaction_ref = ?");
        $stmt->execute([$clientReference]);
        $ussdTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ussdTransaction && $internalStatus === 'completed') {
            // Create main transaction record
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    transaction_id, reference, event_id, organizer_id, nominee_id,
                    voter_phone, vote_count, amount, payment_method,
                    status, hubtel_transaction_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', ?, NOW())
            ");
            
            // Get organizer_id from event
            $stmt2 = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
            $stmt2->execute([$ussdTransaction['event_id']]);
            $organizerId = $stmt2->fetchColumn();
            
            $stmt->execute([
                $clientReference,
                $clientReference,
                $ussdTransaction['event_id'],
                $organizerId,
                $ussdTransaction['nominee_id'],
                $ussdTransaction['phone_number'],
                $ussdTransaction['vote_count'],
                $ussdTransaction['amount'],
                $transactionId
            ]);
            
            $mainTransactionId = $pdo->lastInsertId();
            
            // Create individual votes
            $voteCount = (int)$ussdTransaction['vote_count'];
            $voteAmount = $ussdTransaction['amount'] / $voteCount;
            
            // Get category_id from nominee
            $stmt = $pdo->prepare("SELECT category_id FROM nominees WHERE id = ?");
            $stmt->execute([$ussdTransaction['nominee_id']]);
            $categoryId = $stmt->fetchColumn();
            
            for ($i = 0; $i < $voteCount; $i++) {
                $stmt = $pdo->prepare("
                    INSERT INTO votes (
                        event_id, category_id, nominee_id, voter_phone, 
                        transaction_id, payment_method, payment_reference, 
                        payment_status, amount, voted_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'hubtel_ussd', ?, 'completed', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $ussdTransaction['event_id'],
                    $categoryId,
                    $ussdTransaction['nominee_id'],
                    $ussdTransaction['phone_number'],
                    $mainTransactionId,
                    $clientReference,
                    $voteAmount
                ]);
            }
            
            logWebhook('USSD payment completed successfully', [
                'transaction_ref' => $clientReference,
                'votes_created' => $voteCount,
                'amount' => $ussdTransaction['amount']
            ]);
            
            // Send SMS confirmation (if SMS service is available)
            sendUSSDPaymentConfirmation($ussdTransaction, $transactionId);
        }
        
        return [
            'success' => true,
            'message' => 'Payment callback processed successfully'
        ];
        
    } catch (Exception $e) {
        logWebhook('USSD payment callback error', ['error' => $e->getMessage()]);
        
        return [
            'success' => false,
            'message' => 'Payment callback processing failed'
        ];
    }
}

/**
 * Log USSD interaction to database
 */
function logUSSDInteraction($request, $response, $processingTime) {
    try {
        require_once __DIR__ . '/../config/database.php';
        
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }
        
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO ussd_webhook_logs (session_id, phone_number, request_data, response_data, processing_time_ms) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $request['SessionId'] ?? '',
            $request['Mobile'] ?? '',
            json_encode($request),
            json_encode($response),
            $processingTime
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log USSD interaction: " . $e->getMessage());
    }
}

/**
 * Send SMS confirmation for USSD payment
 */
function sendUSSDPaymentConfirmation($transaction, $hubtelTransactionId) {
    try {
        // This would integrate with your SMS service
        // For now, just log the confirmation
        logWebhook('USSD payment confirmation', [
            'phone' => $transaction['phone_number'],
            'amount' => $transaction['amount'],
            'votes' => $transaction['vote_count'],
            'transaction_id' => $hubtelTransactionId
        ]);
        
        // TODO: Implement actual SMS sending
        // $smsService = new HubtelSMSService();
        // $message = "Payment successful! {$transaction['vote_count']} votes recorded. Ref: {$hubtelTransactionId}";
        // $smsService->sendSMS($transaction['phone_number'], $message);
        
    } catch (Exception $e) {
        logWebhook('SMS confirmation error', ['error' => $e->getMessage()]);
    }
}
?>
