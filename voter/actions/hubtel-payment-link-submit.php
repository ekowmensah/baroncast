<?php
/**
 * Hubtel Payment Link Vote Submission Handler
 * Alternative to USSD - generates payment links for mobile money
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/payment-link-submission.log');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelReceiveMoneyService.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Set HTTP_HOST for proper database connection
    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get form data
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $nominee_id = isset($_POST['nominee_id']) ? (int)$_POST['nominee_id'] : 0;
    $vote_count = isset($_POST['vote_count']) ? (int)$_POST['vote_count'] : 1;
    $voter_name = isset($_POST['voter_name']) ? trim($_POST['voter_name']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Basic validation
    if (!$event_id || !$nominee_id || !$vote_count || !$voter_name || !$phone_number) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }
    
    if ($vote_count < 1 || $vote_count > 100) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid vote count. Must be between 1 and 100'
        ]);
        exit;
    }
    
    // Validate phone number format
    if (!preg_match('/^[0-9+]{10,15}$/', preg_replace('/\s/', '', $phone_number))) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please enter a valid phone number'
        ]);
        exit;
    }
    
    // Verify event exists and is active
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name
        FROM events e 
        JOIN users u ON e.organizer_id = u.id 
        WHERE e.id = ? AND e.status = 'active'
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode([
            'success' => false, 
            'message' => 'Event not found or has ended'
        ]);
        exit;
    }
    
    // Verify nominee exists and belongs to event
    $stmt = $pdo->prepare("
        SELECT n.*, c.name as category_name
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        WHERE n.id = ? AND c.event_id = ?
    ");
    $stmt->execute([$nominee_id, $event_id]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        echo json_encode([
            'success' => false, 
            'message' => 'Nominee not found'
        ]);
        exit;
    }
    
    // Calculate total amount
    $vote_cost = 1.00; // Default cost - can be made configurable
    $total_amount = $vote_cost * $vote_count;
    
    if ($total_amount <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid vote cost configuration'
        ]);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Generate unique transaction reference
        $transaction_ref = 'LINK_' . time() . '_' . rand(1000, 9999);
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'payment_link', 'pending', NOW())
        ");
        
        $stmt->execute([
            $transaction_ref,
            $transaction_ref, 
            $event_id,
            $event['organizer_id'],
            $nominee_id,
            $phone_number,
            $vote_count,
            $total_amount
        ]);
        
        $transaction_db_id = $pdo->lastInsertId();
        
        // Initialize Hubtel payment service
        $hubtel = new HubtelReceiveMoneyService();
        
        $description = "Vote for {$nominee['name']} in {$event['title']} ({$vote_count} vote" . ($vote_count > 1 ? 's' : '') . ")";
        
        // Use regular Hubtel Direct Receive Money API
        $payment_result = $hubtel->initiatePayment(
            $total_amount,
            $phone_number,
            $description,
            $transaction_ref,
            $voter_name,
            $email
        );
        
        if ($payment_result['success']) {
            // Update transaction with payment details
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET hubtel_transaction_id = ?, 
                    payment_response = ?,
                    status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $payment_result['transaction_id'] ?? '',
                json_encode($payment_result),
                $payment_result['status'] ?? 'pending',
                $transaction_db_id
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            // Log successful payment link generation
            error_log("Payment link generated successfully: $transaction_ref");
            
            // Generate mobile-friendly payment instructions
            $instructions = generateMobileInstructions($payment_result, $phone_number);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'payment_method' => 'payment_link',
                'status' => 'payment_initiated',
                'message' => 'Payment initiated successfully. Complete payment on your phone.',
                'transaction_ref' => $transaction_ref,
                'amount' => $total_amount,
                'voter_name' => $voter_name,
                'nominee_name' => $nominee['name'],
                'vote_count' => $vote_count,
                'instructions' => $instructions,
                'payment_details' => [
                    'method' => 'hubtel_mobile_money',
                    'amount' => $total_amount,
                    'status' => $payment_result['status'],
                    'transaction_id' => $payment_result['transaction_id'] ?? ''
                ]
            ]);
            
        } else {
            // Payment initiation failed - rollback
            $pdo->rollBack();
            
            error_log("Payment link generation failed: " . $payment_result['message']);
            
            echo json_encode([
                'success' => false,
                'message' => $payment_result['message'] ?? 'Failed to initiate payment',
                'error_code' => $payment_result['code'] ?? 'PAYMENT_INITIATION_FAILED',
                'transaction_ref' => $transaction_ref
            ]);
        }
        
    } catch (Exception $e) {
        // Database operation failed - rollback
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in hubtel-payment-link-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in hubtel-payment-link-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

/**
 * Generate mobile-friendly payment instructions
 */
function generateMobileInstructions($paymentResult, $phoneNumber) {
    $instructions = [
        "💳 Complete your mobile money payment:",
        "📱 Check your phone for payment prompt",
        "🔢 Enter your mobile money PIN when requested",
        "✅ You'll receive SMS confirmation when payment is complete"
    ];
    
    // Add network-specific instructions if possible
    $network = detectNetwork($phoneNumber);
    if ($network) {
        array_unshift($instructions, "📶 {$network} Mobile Money payment initiated");
    }
    
    return $instructions;
}

/**
 * Detect mobile network from phone number
 */
function detectNetwork($phoneNumber) {
    $phone = preg_replace('/\D/', '', $phoneNumber);
    
    // Remove country code if present
    if (strlen($phone) == 12 && substr($phone, 0, 3) == '233') {
        $phone = substr($phone, 3);
    } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
        $phone = substr($phone, 1);
    }
    
    if (strlen($phone) == 9) {
        $prefix = substr($phone, 0, 2);
        
        // MTN prefixes
        if (in_array($prefix, ['24', '25', '53', '54', '55', '59'])) {
            return 'MTN';
        }
        // Vodafone prefixes  
        elseif (in_array($prefix, ['20', '50', '23', '28', '29'])) {
            return 'Vodafone';
        }
        // AirtelTigo prefixes
        elseif (in_array($prefix, ['26', '27', '56', '57'])) {
            return 'AirtelTigo';
        }
    }
    
    return null;
}
?>
