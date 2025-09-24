<?php
/**
 * Payment Status Checker
 * Checks Paystack payment status for votes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/PaystackService.php';
require_once __DIR__ . '/../../config/development-config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';

// Validate required fields
if (empty($reference)) {
    echo json_encode(['success' => false, 'message' => 'Payment reference is required']);
    exit;
}

try {
    // Initialize services
    $paystack = new PaystackService();
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verify transaction with Paystack
    $verification_result = $paystack->verifyTransaction($reference);
    
    if (!$verification_result['success']) {
        echo json_encode([
            'success' => false,
            'message' => $verification_result['message'] ?? 'Payment verification failed'
        ]);
        exit;
    }
    
    $transaction_data = $verification_result['data'];
    
    // Check if payment was successful
    if ($transaction_data['status'] !== 'success') {
        echo json_encode([
            'success' => false,
            'message' => 'Payment was not successful: ' . $transaction_data['status']
        ]);
        exit;
    }
    
    // Get vote information from metadata
    $metadata = $transaction_data['metadata'] ?? [];
    $vote_id = $metadata['vote_id'] ?? null;
    
    if (!$vote_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Vote ID not found in payment metadata'
        ]);
        exit;
    }
    
    // Update vote status to confirmed
    $stmt = $pdo->prepare("
        UPDATE votes 
        SET status = 'confirmed',
            payment_status = 'completed',
            payment_reference = ?,
            payment_response = ?,
            updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    
    $result = $stmt->execute([
        $reference,
        json_encode($transaction_data),
        $vote_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Vote not found or already confirmed'
        ]);
        exit;
    }
    
    if (!function_exists('logPaymentActivity')) {
        function logPaymentActivity($level, $action, $data = []) {
            error_log("PAYMENT LOG [{$level}] {$action}: " . json_encode($data));
        }
    }
    
    logPaymentActivity('INFO', 'Paystack payment completed', [
        'reference' => $reference,
        'vote_id' => $vote_id,
        'amount' => $transaction_data['amount'] / 100 // Convert from kobo to cedis
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment completed successfully! Your vote has been confirmed.',
        'reference' => $reference,
        'vote_id' => $vote_id,
        'amount' => $transaction_data['amount'] / 100,
        'status' => 'payment_completed'
    ]);
    
} catch (Exception $e) {
    if (!function_exists('logPaymentActivity')) {
        function logPaymentActivity($level, $action, $data = []) {
            error_log("PAYMENT LOG [{$level}] {$action}: " . json_encode($data));
        }
    }
    
    logPaymentActivity('ERROR', 'Payment verification exception', [
        'reference' => $reference,
        'error' => $e->getMessage()
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again.'
    ]);
}
?>
