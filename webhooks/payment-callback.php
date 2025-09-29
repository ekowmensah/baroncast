<?php
/**
 * Dedicated Hubtel Payment Callback Handler
 * Handles payment success/failure notifications
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Log all incoming data
$input = file_get_contents('php://input');
$headers = getallheaders();

error_log("=== PAYMENT CALLBACK RECEIVED ===");
error_log("Raw input: " . $input);
error_log("Headers: " . json_encode($headers));
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));

try {
    // Parse callback data (try both JSON and form data)
    $callbackData = json_decode($input, true);
    if (!$callbackData) {
        parse_str($input, $callbackData);
    }
    
    error_log("Parsed callback data: " . json_encode($callbackData));
    
    // Set HTTP_HOST for database connection
    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Extract payment information (try different field names)
    $transactionId = $callbackData['TransactionId'] ?? $callbackData['transaction_id'] ?? $callbackData['Data']['TransactionId'] ?? '';
    $clientReference = $callbackData['ClientReference'] ?? $callbackData['client_reference'] ?? $callbackData['Data']['ClientReference'] ?? '';
    $responseCode = $callbackData['ResponseCode'] ?? $callbackData['response_code'] ?? '';
    $status = $callbackData['Status'] ?? $callbackData['status'] ?? '';
    $amount = $callbackData['Amount'] ?? $callbackData['amount'] ?? 0;
    
    error_log("Extracted - TransactionId: $transactionId, ClientRef: $clientReference, ResponseCode: $responseCode, Status: $status, Amount: $amount");
    
    // Check if this is a USSD voting transaction
    if (strpos($clientReference, 'USSD_') === 0) {
        error_log("Processing USSD voting payment callback");
        
        // Get transaction from database
        $stmt = $pdo->prepare("
            SELECT ut.*, n.name as nominee_name, e.title as event_title
            FROM ussd_transactions ut
            JOIN nominees n ON ut.nominee_id = n.id
            JOIN events e ON ut.event_id = e.id
            WHERE ut.transaction_ref = ?
        ");
        $stmt->execute([$clientReference]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            error_log("Transaction not found for ref: $clientReference");
            echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
            exit;
        }
        
        error_log("Found transaction: " . json_encode($transaction));
        
        // Determine if payment was successful
        $isSuccess = false;
        if ($responseCode === '0000' || $responseCode === '00' || strtolower($status) === 'success' || strtolower($status) === 'paid') {
            $isSuccess = true;
        }
        
        error_log("Payment success status: " . ($isSuccess ? 'YES' : 'NO'));
        
        if ($isSuccess) {
            error_log("Recording votes for successful payment");
            
            // Start database transaction
            $pdo->beginTransaction();
            
            try {
                // Update USSD transaction status
                $stmt = $pdo->prepare("
                    UPDATE ussd_transactions 
                    SET status = 'completed', hubtel_transaction_id = ?, completed_at = NOW()
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([$transactionId, $clientReference]);
                
                // Get organizer_id from event
                $stmt = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
                $stmt->execute([$transaction['event_id']]);
                $organizerId = $stmt->fetchColumn();
                
                // Create main transaction record
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        transaction_id, reference, event_id, organizer_id, nominee_id,
                        voter_phone, vote_count, amount, payment_method,
                        status, hubtel_transaction_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', ?, NOW())
                ");
                $stmt->execute([
                    $clientReference,
                    $clientReference,
                    $transaction['event_id'],
                    $organizerId,
                    $transaction['nominee_id'],
                    $transaction['phone_number'],
                    $transaction['vote_count'],
                    $transaction['amount'],
                    $transactionId
                ]);
                
                $mainTransactionId = $pdo->lastInsertId();
                
                // Create individual votes
                $voteCount = (int)$transaction['vote_count'];
                $voteAmount = $transaction['amount'] / $voteCount;
                
                // Get category_id from nominee
                $stmt = $pdo->prepare("SELECT category_id FROM nominees WHERE id = ?");
                $stmt->execute([$transaction['nominee_id']]);
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
                        $transaction['event_id'],
                        $categoryId,
                        $transaction['nominee_id'],
                        $transaction['phone_number'],
                        $mainTransactionId,
                        $clientReference,
                        $voteAmount
                    ]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                error_log("SUCCESS: Recorded $voteCount votes for {$transaction['nominee_name']}");
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Votes recorded successfully',
                    'votes_added' => $voteCount,
                    'nominee' => $transaction['nominee_name']
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error recording votes: " . $e->getMessage());
                throw $e;
            }
            
        } else {
            // Payment failed
            error_log("Payment failed - updating transaction status");
            
            $stmt = $pdo->prepare("
                UPDATE ussd_transactions 
                SET status = 'failed', hubtel_transaction_id = ?
                WHERE transaction_ref = ?
            ");
            $stmt->execute([$transactionId, $clientReference]);
            
            echo json_encode([
                'status' => 'failed',
                'message' => 'Payment failed - no votes recorded'
            ]);
        }
        
    } else {
        error_log("Not a USSD voting transaction: $clientReference");
        echo json_encode(['status' => 'ignored', 'message' => 'Not a voting transaction']);
    }
    
} catch (Exception $e) {
    error_log("Payment callback error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Callback processing failed'
    ]);
}
?>
