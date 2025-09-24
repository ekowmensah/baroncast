<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/HubtelService.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/hubtel-callback.log');

function logCallback($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $logEntry .= ' - ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Get raw POST data
    $rawInput = file_get_contents('php://input');
    $callbackData = json_decode($rawInput, true);
    
    logCallback('Hubtel callback received', $callbackData);
    
    if (!$callbackData) {
        throw new Exception('Invalid callback data received');
    }
    
    // Extract callback information
    $responseCode = $callbackData['ResponseCode'] ?? '';
    $status = $callbackData['Status'] ?? '';
    $data = $callbackData['Data'] ?? [];
    
    $checkoutId = $data['CheckoutId'] ?? '';
    $clientReference = $data['ClientReference'] ?? '';
    $paymentStatus = $data['Status'] ?? '';
    $amount = (float)($data['Amount'] ?? 0);
    $customerPhone = $data['CustomerPhoneNumber'] ?? '';
    $paymentDetails = $data['PaymentDetails'] ?? [];
    $description = $data['Description'] ?? '';
    
    if (!$clientReference) {
        throw new Exception('Client reference not found in callback data');
    }
    
    // Get database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Find the transaction
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$clientReference]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception("Transaction not found: {$clientReference}");
    }
    
    logCallback('Transaction found', ['transaction_id' => $transaction['id'], 'current_status' => $transaction['status']]);
    
    // Map Hubtel status to internal status
    $internalStatus = 'pending';
    if ($responseCode === '0000' && $paymentStatus === 'Success') {
        $internalStatus = 'completed';
    } elseif ($paymentStatus === 'Failed' || $paymentStatus === 'Cancelled') {
        $internalStatus = 'failed';
    }
    
    // Update transaction status
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = ?, payment_details = ?, hubtel_transaction_id = ?, updated_at = NOW()
        WHERE reference = ?
    ");
    $stmt->execute([
        $internalStatus,
        json_encode($paymentDetails),
        $checkoutId,
        $clientReference
    ]);
    
    logCallback('Transaction status updated', [
        'reference' => $clientReference,
        'status' => $internalStatus,
        'hubtel_status' => $paymentStatus
    ]);
    
    // If payment is successful, create votes
    if ($internalStatus === 'completed') {
        // Get event_id and category_id from the nominee
        $stmt = $pdo->prepare("
            SELECT n.category_id, c.event_id 
            FROM nominees n 
            JOIN categories c ON n.category_id = c.id 
            WHERE n.id = ?
        ");
        $stmt->execute([$transaction['nominee_id']]);
        $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nominee_data) {
            // Create votes based on vote count
            $voteCount = (int)$transaction['vote_count'];
            $nomineeId = (int)$transaction['nominee_id'];
            $voterPhone = $transaction['voter_phone'];
            
            for ($i = 0; $i < $voteCount; $i++) {
                $stmt = $pdo->prepare("
                    INSERT INTO votes (
                        event_id, category_id, nominee_id, voter_phone, 
                        transaction_id, payment_method, payment_reference, 
                        payment_status, amount, voted_at
                    ) VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, 'completed', ?, NOW())
                ");
                $stmt->execute([
                    $nominee_data['event_id'],
                    $nominee_data['category_id'],
                    $nomineeId, 
                    $voterPhone, 
                    $transaction['id'],
                    $clientReference,
                    $transaction['amount'] / $voteCount
                ]);
            }
            
            logCallback('Votes created successfully', [
                'vote_count' => $voteCount,
                'nominee_id' => $nomineeId,
                'transaction_id' => $transaction['id']
            ]);
        } else {
            logCallback('Could not find nominee data for vote creation', [
                'nominee_id' => $transaction['nominee_id']
            ]);
        }
        
        // Log successful payment
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (log_type, message, created_at) 
            VALUES ('payment_success', ?, NOW())
        ");
        $stmt->execute([
            "Payment completed: {$clientReference} - " . SiteSettings::getCurrencySymbol() . " {$amount} - {$voteCount} votes for nominee {$nomineeId}"
        ]);
    }
    
    // Send success response to Hubtel
    echo json_encode([
        'ResponseCode' => '0000',
        'Status' => 'Success',
        'Message' => 'Callback processed successfully'
    ]);
    
    logCallback('Callback processed successfully', ['status' => $internalStatus]);
    
} catch (Exception $e) {
    logCallback('Callback processing error', ['error' => $e->getMessage()]);
    
    // Send error response to Hubtel
    http_response_code(500);
    echo json_encode([
        'ResponseCode' => '1001',
        'Status' => 'Failed',
        'Message' => 'Callback processing failed: ' . $e->getMessage()
    ]);
}
?>
