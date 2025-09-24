<?php
/**
 * Hubtel Payment Status Checker
 * Checks payment status and creates votes when payment is completed
 */

session_start();
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/payment-status.log');

function logDebug($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    file_put_contents(__DIR__ . '/../../logs/payment-status.log', $logEntry . "\n", FILE_APPEND);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $transactionRef = $input['transaction_ref'] ?? '';
    
    if (!$transactionRef) {
        throw new Exception('Missing transaction reference');
    }
    
    // Load services
    require_once __DIR__ . '/../../services/HubtelService.php';
    require_once __DIR__ . '/../../config/database.php';
    
    $hubtel = new HubtelService();
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get transaction details
    $stmt = $pdo->prepare("
        SELECT t.*, n.name as nominee_name, e.title as event_title
        FROM transactions t
        JOIN nominees n ON t.nominee_id = n.id
        JOIN events e ON t.event_id = e.id
        WHERE t.reference = ?
    ");
    $stmt->execute([$transactionRef]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Check payment status with Hubtel
    $paymentToken = $transaction['payment_token'] ?? $transactionRef;
    $statusResult = $hubtel->checkPaymentStatus($paymentToken);
    
    logDebug("Payment status check", [
        'transaction_ref' => $transactionRef,
        'payment_token' => $paymentToken,
        'status_result' => $statusResult
    ]);
    
    if ($statusResult['success']) {
        $paymentData = $statusResult['data'];
        $hubtelStatus = $paymentData['Status'] ?? 'Pending';
        $internalStatus = mapHubtelStatus($hubtelStatus);
        
        // Update transaction status if changed
        if ($transaction['status'] !== $internalStatus) {
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = ?, updated_at = NOW()
                WHERE reference = ?
            ");
            $stmt->execute([$internalStatus, $transactionRef]);
            
            // If payment completed, create vote
            if ($internalStatus === 'completed') {
                createVote($pdo, $transaction);
                
                logDebug("Vote created successfully", [
                    'transaction_ref' => $transactionRef,
                    'nominee_id' => $transaction['nominee_id'],
                    'vote_count' => $transaction['vote_count']
                ]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'status' => $internalStatus,
            'hubtel_status' => $hubtelStatus,
            'transaction_ref' => $transactionRef,
            'amount' => $transaction['amount'],
            'message' => getStatusMessage($internalStatus)
        ]);
        
    } else {
        // If API call failed, return current database status
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'transaction_ref' => $transactionRef,
            'amount' => $transaction['amount'],
            'message' => getStatusMessage($transaction['status']),
            'note' => 'Status check failed, showing last known status'
        ]);
    }
    
} catch (Exception $e) {
    logDebug("Error in payment status check", ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function mapHubtelStatus($hubtelStatus) {
    $statusMap = [
        'Success' => 'completed',
        'Paid' => 'completed',
        'Failed' => 'failed',
        'Cancelled' => 'cancelled',
        'Pending' => 'pending',
        'InProgress' => 'processing'
    ];
    
    return $statusMap[$hubtelStatus] ?? 'pending';
}

function getStatusMessage($status) {
    $messages = [
        'pending' => 'Payment is being processed...',
        'processing' => 'Payment is in progress...',
        'completed' => 'Payment successful! Your votes have been recorded.',
        'failed' => 'Payment failed. Please try again.',
        'cancelled' => 'Payment was cancelled.'
    ];
    
    return $messages[$status] ?? 'Unknown payment status';
}

function createVote($pdo, $transaction) {
    // Check if vote already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM votes 
        WHERE payment_reference = ?
    ");
    $stmt->execute([$transaction['reference']]);
    
    if ($stmt->fetchColumn() > 0) {
        return; // Vote already exists
    }
    
    // Create vote record(s)
    for ($i = 0; $i < $transaction['vote_count']; $i++) {
        $stmt = $pdo->prepare("
            INSERT INTO votes (
                event_id, nominee_id, voter_phone, 
                payment_reference, payment_status, 
                amount, voted_at
            ) VALUES (?, ?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([
            $transaction['event_id'],
            $transaction['nominee_id'],
            $transaction['voter_phone'],
            $transaction['reference'],
            $transaction['amount'] / $transaction['vote_count'] // Amount per vote
        ]);
    }
}
?>
