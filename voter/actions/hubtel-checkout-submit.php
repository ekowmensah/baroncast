<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelService.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/vote-submission.log');

function logVoteSubmission($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $logEntry .= ' - ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get input data - handle both JSON and form data
    $inputData = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Handle JSON input
        $jsonInput = file_get_contents('php://input');
        $inputData = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
    } else {
        // Handle form data
        $inputData = $_POST;
    }
    
    logVoteSubmission('Hubtel Checkout vote submission started', $inputData);
    
    // Get and validate input data
    $nomineeId = (int)($inputData['nominee_id'] ?? 0);
    $voteCount = (int)($inputData['vote_count'] ?? 1);
    $phoneNumber = trim($inputData['phone_number'] ?? '');
    $payerName = trim($inputData['voter_name'] ?? $inputData['payer_name'] ?? '');
    $payerEmail = trim($inputData['email'] ?? $inputData['payer_email'] ?? '');
    
    if (!$nomineeId || !$phoneNumber) {
        throw new Exception('Missing required data: nominee_id and phone_number are required');
    }
    
    if ($voteCount < 1 || $voteCount > 100) {
        throw new Exception('Invalid vote count. Must be between 1 and 100');
    }
    
    // Get database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get nominee and event details
    $stmt = $pdo->prepare("
        SELECT n.*, c.name as category_name, e.title as event_title, e.organizer_id, e.id as event_id
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE n.id = ? AND e.status = 'active'
    ");
    $stmt->execute([$nomineeId]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        throw new Exception('Nominee not found or event is not active');
    }
    
    // Get vote price from system settings (default 1.00 GHS)
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_vote_cost'");
    $stmt->execute();
    $votePrice = (float)($stmt->fetchColumn() ?: 1.00);
    
    // Calculate total amount
    $totalAmount = $votePrice * $voteCount;
    
    // Generate unique transaction reference
    $transactionRef = 'ECAST_' . time() . '_' . uniqid() . '_' . $nomineeId;
    
    logVoteSubmission('Transaction details calculated', [
        'nominee_id' => $nomineeId,
        'vote_count' => $voteCount,
        'vote_price' => $votePrice,
        'total_amount' => $totalAmount,
        'transaction_ref' => $transactionRef
    ]);
    
    // Create transaction record
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            transaction_id, reference, event_id, organizer_id, nominee_id, voter_phone, 
            vote_count, amount, payment_method, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $transactionRef,
        $transactionRef, 
        $nominee['event_id'], 
        $nominee['organizer_id'], 
        $nomineeId, 
        $phoneNumber, 
        $voteCount, 
        $totalAmount,
        'hubtel_checkout'
    ]);
    
    logVoteSubmission('Transaction record created', ['reference' => $transactionRef]);
    
    // Initialize Hubtel service
    $hubtel = new HubtelService($pdo);
    
    // Create payment description
    $description = "Vote for {$nominee['name']} in {$nominee['category_name']} - {$nominee['event_title']}";
    
    // Initialize Hubtel Online Checkout
    $checkoutResult = $hubtel->initializeOnlineCheckout(
        $totalAmount,
        $description,
        $transactionRef,
        $payerName,
        $phoneNumber,
        $payerEmail
    );
    
    logVoteSubmission('Hubtel checkout initialization result', $checkoutResult);
    
    if ($checkoutResult['success']) {
        // Update transaction with checkout details
        $checkoutData = $checkoutResult['data']['data'] ?? [];
        $checkoutId = $checkoutData['checkoutId'] ?? '';
        $checkoutUrl = $checkoutData['checkoutUrl'] ?? '';
        $checkoutDirectUrl = $checkoutData['checkoutDirectUrl'] ?? '';
        
        if ($checkoutId) {
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET payment_token = ?, checkout_url = ?, status = 'checkout_initialized', updated_at = NOW()
                WHERE reference = ?
            ");
            $stmt->execute([$checkoutId, $checkoutUrl, $transactionRef]);
        }
        
        echo json_encode([
            'success' => true,
            'step' => 'checkout_ready',
            'message' => 'Payment checkout initialized successfully',
            'data' => [
                'transaction_ref' => $transactionRef,
                'checkout_id' => $checkoutId,
                'checkout_url' => $checkoutUrl,
                'checkout_direct_url' => $checkoutDirectUrl,
                'amount' => $totalAmount,
                'vote_count' => $voteCount,
                'nominee_name' => $nominee['name'],
                'category_name' => $nominee['category_name'],
                'event_title' => $nominee['event_title']
            ]
        ]);
        
    } else {
        throw new Exception('Failed to initialize payment checkout: ' . $checkoutResult['message']);
    }
    
} catch (Exception $e) {
    logVoteSubmission('Error in vote submission', ['error' => $e->getMessage()]);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
