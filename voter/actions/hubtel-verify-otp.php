<?php
/**
 * Hubtel OTP Verification and Mobile Money Payment
 * Verifies OTP and processes mobile money payment via Hubtel
 */

session_start();
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/otp-verification.log');

function logDebug($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    file_put_contents(__DIR__ . '/../../logs/otp-verification.log', $logEntry . "\n", FILE_APPEND);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    logDebug("OTP verification started", $input);
    
    $transactionRef = $input['transaction_ref'] ?? '';
    $otpCode = $input['otp_code'] ?? '';
    
    if (!$transactionRef || !$otpCode) {
        throw new Exception('Missing transaction reference or OTP code');
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
        WHERE t.reference = ? AND t.status = 'pending'
    ");
    $stmt->execute([$transactionRef]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found or already processed');
    }
    
    // Check OTP expiry
    if (strtotime($transaction['otp_expires_at']) < time()) {
        throw new Exception('OTP has expired. Please request a new one.');
    }
    
    // Verify OTP
    if ($transaction['otp_code'] !== $otpCode) {
        throw new Exception('Invalid OTP code');
    }
    
    logDebug("OTP verified successfully", ['transaction_ref' => $transactionRef]);
    
    // Initialize mobile money payment via Hubtel
    $description = "Vote for {$transaction['nominee_name']} - {$transaction['event_title']}";
    $paymentResult = $hubtel->initializeMobileMoneyPayment(
        $transaction['amount'],
        $transaction['voter_phone'],
        $description,
        $transactionRef
    );
    
    if ($paymentResult['success']) {
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'payment_initiated', 
                payment_token = ?, 
                otp_verified_at = NOW(),
                updated_at = NOW()
            WHERE reference = ?
        ");
        $paymentToken = $paymentResult['data']['TransactionId'] ?? $transactionRef;
        $stmt->execute([$paymentToken, $transactionRef]);
        
        logDebug("Mobile money payment initiated", [
            'transaction_ref' => $transactionRef,
            'payment_token' => $paymentToken
        ]);
        
        echo json_encode([
            'success' => true,
            'step' => 'payment_initiated',
            'message' => 'OTP verified! Payment request sent to your phone. Please approve on your mobile money app.',
            'payment_token' => $paymentToken,
            'transaction_ref' => $transactionRef,
            'amount' => $transaction['amount']
        ]);
        
    } else {
        throw new Exception('Failed to initiate mobile money payment: ' . $paymentResult['message']);
    }
    
} catch (Exception $e) {
    logDebug("Error in OTP verification", ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
