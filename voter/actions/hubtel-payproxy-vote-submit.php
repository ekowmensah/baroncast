<?php
/**
 * Hubtel PayProxy Vote Submission Handler
 * Uses correct PayProxy API based on church management system
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/payproxy-vote-submission.log');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelPayProxyService.php';

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
        $transaction_ref = 'PAYPROXY_' . time() . '_' . rand(1000, 9999);
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'payproxy_checkout', 'pending', NOW())
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
        
        // Initialize Hubtel PayProxy service
        $payProxy = new HubtelPayProxyService();
        
        $description = "Vote for {$nominee['name']} in {$event['title']} ({$vote_count} vote" . ($vote_count > 1 ? 's' : '') . ")";
        
        $metadata = [
            'voter_name' => $voter_name,
            'email' => $email,
            'event_id' => $event_id,
            'nominee_id' => $nominee_id,
            'vote_count' => $vote_count,
            'transaction_db_id' => $transaction_db_id
        ];
        
        // Generate PayProxy checkout with timeout handling
        $start_time = microtime(true);
        
        try {
            $payment_result = $payProxy->generateVotingPayment(
                $total_amount,
                $phone_number,
                $description,
                $transaction_ref,
                $metadata
            );
            
            $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
            error_log("PayProxy checkout creation took: " . number_format($execution_time, 2) . " ms");
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            error_log("PayProxy checkout failed after: " . number_format($execution_time, 2) . " ms - Error: " . $e->getMessage());
            
            // Rollback transaction
            $pdo->rollBack();
            
            echo json_encode([
                'success' => false,
                'message' => 'Payment service temporarily unavailable. Please try again.',
                'error_code' => 'PAYPROXY_TIMEOUT',
                'execution_time' => number_format($execution_time, 2) . ' ms',
                'transaction_ref' => $transaction_ref
            ]);
            exit;
        }
        
        if ($payment_result['success']) {
            // Update transaction with payment details
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET hubtel_transaction_id = ?, 
                    payment_response = ?,
                    status = 'checkout_created'
                WHERE id = ?
            ");
            
            $stmt->execute([
                $payment_result['checkout_id'] ?? '',
                json_encode($payment_result),
                $transaction_db_id
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            // Log successful PayProxy checkout creation
            
            // Return success response with checkout details
            echo json_encode([
                'success' => true,
                'payment_method' => 'payproxy_checkout',
                'status' => 'checkout_created',
                'message' => 'Payment checkout created successfully',
                'transaction_ref' => $transaction_ref,
                'checkout_url' => $payment_result['checkout_url'],
                'checkout_id' => $payment_result['checkout_id'],
                'amount' => $total_amount,
                'nominee_name' => $nominee['name'],
                'vote_count' => $vote_count,
                'instructions' => $payment_result['instructions'],
                'expires_at' => $payment_result['expires_at'],
                'payment_details' => [
                    'method' => 'hubtel_payproxy',
                    'amount' => $total_amount,
                    'checkout_url' => $payment_result['checkout_url'],
                    'expires_at' => $payment_result['expires_at']
                ]
            ]);
            
        } else {
            // PayProxy checkout creation failed - rollback
            $pdo->rollBack();
            
            error_log("PayProxy checkout creation failed: " . $payment_result['message']);
            
            echo json_encode([
                'success' => false,
                'message' => $payment_result['message'] ?? 'Failed to create payment checkout',
                'error_code' => $payment_result['error_code'] ?? 'PAYPROXY_CREATION_FAILED',
                'transaction_ref' => $transaction_ref,
                'debug' => $payment_result['debug'] ?? null
            ]);
        }
        
    } catch (Exception $e) {
        // Database operation failed - rollback
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in hubtel-payproxy-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in hubtel-payproxy-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
