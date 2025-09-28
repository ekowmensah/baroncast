<?php
/**
 * Hubtel USSD Alternative - Mobile Money Payment Links
 * Creates USSD-like experience using working Hubtel Direct Receive Money API
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/ussd-alternative-submission.log');

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
    
    // Verify event and nominee exist
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as organizer_name,
               n.name as nominee_name, c.name as category_name
        FROM events e 
        JOIN users u ON e.organizer_id = u.id 
        JOIN nominees n ON n.id = ?
        JOIN categories c ON n.category_id = c.id
        WHERE e.id = ? AND e.status = 'active' AND c.event_id = e.id
    ");
    $stmt->execute([$nominee_id, $event_id]);
    $eventData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$eventData) {
        echo json_encode([
            'success' => false, 
            'message' => 'Event or nominee not found'
        ]);
        exit;
    }
    
    // Calculate total amount
    $vote_cost = 1.00;
    $total_amount = $vote_cost * $vote_count;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Generate unique transaction reference
        $transaction_ref = 'USSD_ALT_' . time() . '_' . rand(1000, 9999);
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ussd_alternative', 'pending', NOW())
        ");
        
        $stmt->execute([
            $transaction_ref,
            $transaction_ref, 
            $event_id,
            $eventData['organizer_id'],
            $nominee_id,
            $phone_number,
            $vote_count,
            $total_amount
        ]);
        
        $transaction_db_id = $pdo->lastInsertId();
        
        // Initialize Hubtel payment service (using working API)
        $hubtel = new HubtelReceiveMoneyService();
        
        $description = "Vote for {$eventData['nominee_name']} in {$eventData['title']} ({$vote_count} vote" . ($vote_count > 1 ? 's' : '') . ")";
        
        // Use working Hubtel Direct Receive Money API
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
            
            // Generate USSD-like instructions
            $ussdInstructions = generateUSSDLikeInstructions($phone_number, $total_amount, $eventData['nominee_name']);
            
            // Log successful payment initiation
            error_log("USSD Alternative payment initiated: $transaction_ref");
            
            // Return success response with USSD-like experience
            echo json_encode([
                'success' => true,
                'payment_method' => 'ussd_alternative',
                'status' => 'payment_initiated',
                'message' => 'Mobile Money payment initiated successfully',
                'transaction_ref' => $transaction_ref,
                'amount' => $total_amount,
                'voter_name' => $voter_name,
                'nominee_name' => $eventData['nominee_name'],
                'vote_count' => $vote_count,
                'ussd_experience' => true,
                'instructions' => $ussdInstructions,
                'payment_details' => [
                    'method' => 'mobile_money_direct',
                    'amount' => $total_amount,
                    'status' => $payment_result['status'],
                    'transaction_id' => $payment_result['transaction_id'] ?? '',
                    'network' => detectMobileNetwork($phone_number)
                ]
            ]);
            
        } else {
            // Payment initiation failed - rollback
            $pdo->rollBack();
            
            error_log("USSD Alternative payment failed: " . $payment_result['message']);
            
            echo json_encode([
                'success' => false,
                'message' => $payment_result['message'] ?? 'Failed to initiate mobile money payment',
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
    error_log("Database error in hubtel-ussd-alternative.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in hubtel-ussd-alternative.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

/**
 * Generate USSD-like instructions for mobile money payment
 */
function generateUSSDLikeInstructions($phoneNumber, $amount, $nomineeName) {
    $network = detectMobileNetwork($phoneNumber);
    $networkName = $network['name'] ?? 'Mobile Money';
    $ussdCode = $network['ussd'] ?? '*XXX#';
    
    return [
        "🎯 VOTE PAYMENT INITIATED",
        "📱 Check your phone for {$networkName} payment prompt",
        "💰 Amount: GHS " . number_format($amount, 2),
        "🗳️ Voting for: {$nomineeName}",
        "",
        "📋 PAYMENT STEPS:",
        "1️⃣ You should receive a payment request on your phone",
        "2️⃣ Enter your {$networkName} PIN to authorize",
        "3️⃣ You'll get SMS confirmation when payment is complete",
        "4️⃣ Your votes will be recorded automatically",
        "",
        "⏰ Payment expires in 15 minutes",
        "❓ No payment prompt? Try dialing {$ussdCode} and check for pending transactions"
    ];
}

/**
 * Detect mobile network and provide USSD codes
 */
function detectMobileNetwork($phoneNumber) {
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
            return [
                'name' => 'MTN Mobile Money',
                'ussd' => '*170#',
                'code' => 'mtn'
            ];
        }
        // Vodafone prefixes  
        elseif (in_array($prefix, ['20', '50', '23', '28', '29'])) {
            return [
                'name' => 'Vodafone Cash',
                'ussd' => '*110#',
                'code' => 'vodafone'
            ];
        }
        // AirtelTigo prefixes
        elseif (in_array($prefix, ['26', '27', '56', '57'])) {
            return [
                'name' => 'AirtelTigo Money',
                'ussd' => '*110#',
                'code' => 'airteltigo'
            ];
        }
    }
    
    return [
        'name' => 'Mobile Money',
        'ussd' => '*XXX#',
        'code' => 'unknown'
    ];
}
?>
