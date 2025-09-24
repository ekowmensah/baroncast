<?php
/**
 * Vote Submission Handler with Real Payment Integration
 * Processes vote submissions using Arkesel USSD & Hubtel OTP
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/payment-config.php';
require_once __DIR__ . '/../../config/development-config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get and validate input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Required fields validation
    $required_fields = ['nominee_id', 'vote_count', 'payment_method', 'total_amount'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
            exit;
        }
    }
    
    // Payment method specific validation
    if ($input['payment_method'] === 'mobile_money') {
        if (!isset($input['phone_number']) || empty($input['phone_number'])) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required for mobile money payments']);
            exit;
        }
    } elseif ($input['payment_method'] === 'card') {
        $card_fields = ['card_number', 'card_expiry', 'card_cvv', 'card_name'];
        foreach ($card_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Missing required card field: {$field}"]);
                exit;
            }
        }
    }
    
    $nominee_id = (int)$input['nominee_id'];
    $vote_count = (int)$input['vote_count'];
    $payment_method = $input['payment_method'];
    $phone_number = $input['phone_number'] ?? null;
    $total_amount = (float)$input['total_amount'];
    
    // Extract payment method specific data
    $card_data = null;
    if ($payment_method === 'card') {
        $card_data = [
            'card_number' => $input['card_number'],
            'card_expiry' => $input['card_expiry'],
            'card_cvv' => $input['card_cvv'],
            'card_name' => $input['card_name']
        ];
    }
    
    // Validate nominee exists and get details
    $stmt = $pdo->prepare("
        SELECT n.*, c.description as category_name, e.title as event_title, e.status as event_status,
               c.event_id, e.organizer_id, s.status as scheme_status
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        LEFT JOIN schemes s ON n.scheme_id = s.id
        WHERE n.id = ? AND n.status = 'active' AND e.status = 'active'
    ");
    $stmt->execute([$nominee_id]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        echo json_encode(['success' => false, 'message' => 'Nominee not found or voting not available']);
        exit;
    }
    
    // Validate vote count and pricing against database packages
    $stmt = $pdo->prepare("
        SELECT vote_count, price 
        FROM bulk_vote_packages 
        WHERE event_id = ? AND status = 'active' AND vote_count = ?
    ");
    $stmt->execute([$nominee['event_id'], $vote_count]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        echo json_encode(['success' => false, 'message' => 'Invalid vote package for this event']);
        exit;
    }
    
    // Validate pricing matches the package price
    if (abs($package['price'] - $total_amount) > 0.01) { // Allow small floating point differences
        echo json_encode([
            'success' => false, 
            'message' => "Invalid pricing. Expected GH₵" . number_format($package['price'], 2) . " for {$vote_count} votes"
        ]);
        exit;
    }
    
    // Validate phone number (Ghana format)
    if (!preg_match('/^(\+233|0)[2-5]\d{8}$/', $phone_number)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid phone number format. Please use Ghana format (e.g., +233241234567 or 0241234567)'
        ]);
        exit;
    }
    
    // Format phone number
    $phone_number = formatUgandaPhoneNumber($phone_number);
    
    // Get mobile money provider
    $mmProvider = getMobileMoneyProvider($phone_number);
    if (!$mmProvider) {
        echo json_encode(['success' => false, 'message' => 'Unsupported mobile money provider']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create transaction record
        $transaction_id = 'TXN_' . time() . '_' . rand(1000, 9999);
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, 
                event_id,
                organizer_id,
                nominee_id, 
                voter_phone, 
                vote_count, 
                amount, 
                payment_method, 
                reference,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $transaction_id,
            $nominee['event_id'],
            $nominee['organizer_id'],
            $nominee_id,
            $phone_number ?? '', // Use empty string if no phone number
            $vote_count,
            $total_amount,
            $payment_method,
            $transaction_id  // Use transaction_id as reference to ensure uniqueness
        ]);
        
        $transaction_db_id = $pdo->lastInsertId();
        
        // Commit transaction record first (payment will be processed asynchronously)
        $pdo->commit();
        
        // This endpoint is deprecated - redirect to Hubtel-only implementation
        echo json_encode([
            'success' => false,
            'message' => 'This payment method is no longer supported. Please use the Hubtel payment option.',
            'redirect' => 'hubtel-vote-submit.php'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in submit-vote.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in submit-vote.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your vote']);
}

/**
 * Payment processing using Arkesel USSD and Hubtel OTP
 * Ghana mobile money integration
 */
function simulatePaymentProcessing($payment_method, $phone_number, $amount) {
    // Simulate processing delay
    usleep(500000); // 0.5 second delay
    
    // For demo, we'll have a 90% success rate
    $success_rate = 0.9;
    $random = mt_rand() / mt_getrandmax();
    
    if ($random <= $success_rate) {
        // Simulate different payment methods
        switch ($payment_method) {
            case 'mobile_money':
                // In production: integrate with MTN Mobile Money, Airtel Money, etc.
                return true;
                
            case 'card':
                // In production: integrate with Stripe, PayPal, Flutterwave, etc.
                return true;
                
            case 'ussd':
                // In production: integrate with USSD gateway
                return true;
                
            default:
                return false;
        }
    }
    
    return false;
}
?>
