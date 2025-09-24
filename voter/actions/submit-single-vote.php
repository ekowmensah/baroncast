<?php
/**
 * Single Vote Submission Handler
 * Processes individual vote submissions with payment integration
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelService.php';
require_once __DIR__ . '/../../config/development-config.php';
require_once __DIR__ . '/../../config/free-event-config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get form data
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $nominee_id = isset($_POST['nominee_id']) ? (int)$_POST['nominee_id'] : 0;
    $vote_count = isset($_POST['vote_count']) ? (int)$_POST['vote_count'] : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $network_type = isset($_POST['network_type']) ? trim($_POST['network_type']) : '';
    $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    
    // Validation
    if (!$event_id || !$nominee_id || !$vote_count || !$payment_method || !$phone_number || !$network_type || !$total_amount) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if ($vote_count < 1) {
        echo json_encode(['success' => false, 'message' => 'Vote count must be at least 1']);
        exit;
    }
    
    // Validate event exists and is active
    $event_query = "SELECT * FROM events WHERE id = ? AND status = 'active'";
    $event_stmt = $pdo->prepare($event_query);
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or not active']);
        exit;
    }
    
    // Validate nominee exists and belongs to this event
    $nominee_query = "SELECT n.*, c.event_id FROM nominees n 
                      JOIN categories c ON n.category_id = c.id 
                      WHERE n.id = ? AND c.event_id = ?";
    $nominee_stmt = $pdo->prepare($nominee_query);
    $nominee_stmt->execute([$nominee_id, $event_id]);
    $nominee = $nominee_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        echo json_encode(['success' => false, 'message' => 'Nominee not found for this event']);
        exit;
    }
    
    // Use default vote cost (vote_cost column doesn't exist in current schema)
    $vote_cost = 1.00;
    
    // Verify total amount calculation
    $expected_total = $vote_count * $vote_cost;
    if (abs($total_amount - $expected_total) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Invalid total amount calculation']);
        exit;
    }
    
    // Format phone number (remove spaces, ensure proper format)
    $phone_number = preg_replace('/\s+/', '', $phone_number);
    if (!preg_match('/^0[0-9]{9}$/', $phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use format: 0XXXXXXXXX']);
        exit;
    }
    
    // Convert to international format for payment processing
    $international_phone = '+233' . substr($phone_number, 1);
    
    // Generate unique transaction reference
    $transaction_ref = 'VOTE_' . time() . '_' . rand(1000, 9999);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create initial vote record with pending status
        // Insert vote record for each individual vote (single vote system)
        for ($i = 0; $i < $vote_count; $i++) {
            $vote_query = "INSERT INTO votes (event_id, category_id, nominee_id, voter_phone, 
                           payment_method, amount, payment_reference, payment_status, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
            $vote_stmt = $pdo->prepare($vote_query);
            $vote_stmt->execute([
                $event_id,
                $nominee['category_id'],
                $nominee_id,
                $phone_number,
                $payment_method,
                $vote_cost, // Individual vote cost
                $transaction_ref,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
        
        $vote_id = $pdo->lastInsertId();
        
        // Process payment based on method
        $payment_successful = false;
        $payment_response = null;
        
        if (DEVELOPMENT_MODE) {
            // Development mode - simulate successful payment
            $payment_successful = true;
            $payment_reference = 'DEV_' . $transaction_ref;
            $payment_status = 'completed';
            $payment_response = [
                'status' => 'success',
                'message' => 'Development mode - payment simulated',
                'transaction_id' => 'DEV_' . $transaction_ref
            ];
            
            // Log development payment
            error_log("DEVELOPMENT MODE: Payment simulated for transaction: " . $transaction_ref);
        } elseif (isEventFree($event_id)) {
            // Free event - no payment required
            $free_result = processFreeEventVote($transaction_ref);
            $payment_successful = $free_result['successful'];
            $payment_reference = $free_result['reference'];
            $payment_status = $free_result['status'];
            $payment_response = $free_result['response'];
            
            error_log("FREE EVENT: No payment required for transaction: " . $transaction_ref);
        } else {
            // Production mode - process real payment via Hubtel
            $hubtel = new HubtelService();
            
            switch ($payment_method) {
                case 'mobile_money':
                    try {
                        // Initialize mobile money payment via Hubtel
                        $payment_result = $hubtel->initializeMobileMoneyPayment(
                            $total_amount,
                            $international_phone,
                            "Vote for nominee in " . $event['title'],
                            $transaction_ref
                        );
                        
                        if ($payment_result['success']) {
                            $payment_reference = $payment_result['data']['TransactionId'] ?? $transaction_ref;
                            $payment_status = 'pending';
                            $payment_successful = false; // Don't mark as successful until payment is verified
                            
                            $payment_response = [
                                'status' => 'payment_initiated',
                                'message' => 'Payment request sent to your phone. Please approve to complete.',
                                'reference' => $payment_reference
                            ];
                        } else {
                            throw new Exception($payment_result['message'] ?? 'Failed to initialize payment');
                        }
                    } catch (Exception $e) {
                        error_log("Hubtel Mobile Money Payment Error: " . $e->getMessage());
                        $payment_successful = false;
                        $payment_response = ['status' => 'failed', 'message' => 'Payment processing failed'];
                    }
                    break;
                    
                case 'ussd':
                    try {
                        // Initialize USSD payment via Hubtel
                        $payment_result = $hubtel->initializeUSSDPayment(
                            $total_amount,
                            $international_phone,
                            "Vote for nominee in " . $event['title'],
                            $transaction_ref
                        );
                        
                        if ($payment_result['success']) {
                            $payment_reference = $payment_result['payment_token'];
                            $payment_status = 'pending';
                            $payment_successful = false;
                            
                            $payment_response = [
                                'status' => 'ussd_generated',
                                'message' => 'USSD code generated. Please dial to complete payment.',
                                'ussd_code' => $payment_result['ussd_code'],
                                'instructions' => $payment_result['instructions'],
                                'reference' => $payment_reference
                            ];
                        } else {
                            throw new Exception($payment_result['message'] ?? 'Failed to generate USSD payment');
                        }
                    } catch (Exception $e) {
                        error_log("Hubtel USSD Payment Error: " . $e->getMessage());
                        $payment_successful = false;
                        $payment_response = ['status' => 'failed', 'message' => 'USSD payment generation failed'];
                    }
                    break;
                    
                default:
                    $payment_response = ['status' => 'failed', 'message' => 'Invalid payment method. Only mobile_money and ussd are supported.'];
            }
        }
        
        if ($payment_successful) {
            // Update vote status to confirmed
            $update_query = "UPDATE votes SET status = 'confirmed', payment_response = ?, updated_at = NOW() 
                            WHERE id = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([json_encode($payment_response), $vote_id]);
            
            // Log successful payment
            if (!DEVELOPMENT_MODE) {
                error_log("HUBTEL PAYMENT: Vote confirmed for transaction: " . $transaction_ref);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Vote cast successfully!',
                'transaction_ref' => $transaction_ref,
                'vote_id' => $vote_id
            ]);
            
        } else {
            // Payment failed - rollback transaction
            $pdo->rollBack();
            
            echo json_encode([
                'success' => false,
                'message' => $payment_response['message'] ?? 'Payment processing failed'
            ]);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Vote submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    
} catch (Exception $e) {
    error_log("General error in vote submission: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
}
?>
