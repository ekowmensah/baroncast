<?php
/**
 * Hubtel OTP Verification and Payment Processing
 * Verifies OTP via Hubtel and processes payment via Arkesel USSD
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/PaystackService.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required_fields = ['transaction_id', 'requestId', 'prefix', 'otp_code'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$transaction_id = $input['transaction_id'];
$requestId = $input['requestId'];
$prefix = $input['prefix'];
$otp_code = $input['otp_code'];

try {
    // Initialize services
    $hubtel = new HubtelService();
    $arkesel = new ArkeselService();
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Verify OTP with Hubtel
    $otpVerification = $hubtel->verifyOTP($requestId, $prefix, $otp_code);
    
    if (!$otpVerification['success']) {
        echo json_encode([
            'success' => false,
            'message' => $otpVerification['message']
        ]);
        exit;
    }
    
    // Get transaction details
    $stmt = $pdo->prepare("
        SELECT t.*, n.name as nominee_name, e.title as event_title 
        FROM transactions t
        LEFT JOIN nominees n ON t.nominee_id = n.id
        LEFT JOIN events e ON t.event_id = e.id
        WHERE t.transaction_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found or already processed'
        ]);
        exit;
    }
    
    // Generate USSD payment via Arkesel
    $ussdPayment = $arkesel->generateUSSDPayment(
        $transaction['amount'],
        $transaction['phone_number'],
        "Vote payment for {$transaction['nominee_name']}",
        $transaction_id
    );
    
    if ($ussdPayment['success']) {
        // Update transaction with USSD details
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET payment_reference = ?, payment_status = 'ussd_generated', updated_at = NOW()
            WHERE transaction_id = ?
        ");
        $stmt->execute([$ussdPayment['payment_id'], $transaction_id]);
        
        echo json_encode([
            'success' => true,
            'step' => 'ussd_generated',
            'message' => 'OTP verified! Please dial the USSD code to complete payment.',
            'ussd_code' => $ussdPayment['ussd_code'],
            'payment_id' => $ussdPayment['payment_id'],
            'instructions' => $ussdPayment['instructions'],
            'amount' => $transaction['amount'],
            'transaction_id' => $transaction_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'OTP verified but failed to generate USSD payment. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in verify-hubtel-otp.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during verification'
    ]);
}
?>
