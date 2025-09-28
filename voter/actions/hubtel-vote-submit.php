<?php
/**
 * Hubtel Direct Receive Money Vote Submission Handler
 * Clean implementation using Hubtel Direct Receive Money API
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/vote-submission.log');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelReceiveMoneyService.php';

/**
 * Get vote cost from configurable settings with fallbacks
 * Supports both local (vote_cost) and live server (voting_fee) schemas
 */
function getVoteCost($event, $pdo) {
    // First, try to get from event-specific settings (supporting both schemas)
    $event_cost = $event['voting_fee'] ?? $event['vote_cost'] ?? null;
    
    if ($event_cost && $event_cost > 0) {
        return (float)$event_cost;
    }
    
    // Fallback to system default setting
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_vote_cost'");
        $stmt->execute();
        $default_cost = $stmt->fetchColumn();
        
        if ($default_cost && $default_cost > 0) {
            return (float)$default_cost;
        }
    } catch (Exception $e) {
        error_log("Error getting default vote cost: " . $e->getMessage());
    }
    
    // Final fallback
    return 1.00;
}

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
    
    // Check if Hubtel payments are enabled
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_hubtel_payments'");
    $stmt->execute();
    $hubtel_enabled = $stmt->fetchColumn();
    
    if ($hubtel_enabled !== '1') {
        echo json_encode([
            'success' => false, 
            'message' => 'Mobile money payments are currently disabled. Please contact support.'
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
    
    // Calculate total amount using configurable vote cost
    $vote_cost = getVoteCost($event, $pdo);
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
        // Generate unique transaction reference using full event + nominee abbreviation
        // Extract first letter of each word from event name
        $event_words = explode(' ', $event['title']);
        $event_abbr = '';
        foreach ($event_words as $word) {
            if (!empty($word)) {
                $event_abbr .= strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1));
            }
        }
        
        // Use full nominee name (cleaned)
        $nominee_clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nominee['name']));
        
        $transaction_ref = $event_abbr . $nominee_clean . '-' . date('mdHi') . '-' . rand(100, 999);
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'mobile_money', 'pending', NOW())
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
        
        // Don't commit yet - wait for Hubtel response
        // Initialize Hubtel payment
        $hubtel = new HubtelReceiveMoneyService();
        
        $description = "Vote for {$nominee['name']} in {$event['title']} ({$vote_count} vote" . ($vote_count > 1 ? 's' : '') . ")";
        
        $payment_result = $hubtel->initiatePayment(
            $total_amount,
            $phone_number,
            $description,
            $transaction_ref,
            $voter_name,
            $email
        );
        
        if ($payment_result['success']) {
            // Update transaction with Hubtel details before committing
            // Use a flexible approach that works with different table schemas
            try {
                // Check if Hubtel columns exist before updating
                $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_response'");
                $has_extended_columns = $stmt->rowCount() > 0;
                
                if ($has_extended_columns) {
                    // Full update with all Hubtel fields (live server schema)
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET hubtel_transaction_id = ?, 
                            payment_response = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $payment_result['transaction_id'] ?? '',
                        json_encode($payment_result),
                        $payment_result['status'] ?? 'pending',
                        $transaction_db_id
                    ]);
                } else {
                    // Basic update for minimal schema (local environment)
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET status = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $payment_result['status'] ?? 'pending',
                        $transaction_db_id
                    ]);
                }
            } catch (Exception $update_error) {
                // If update fails, log but don't break the transaction
                error_log("Transaction update warning: " . $update_error->getMessage());
            }
            
            // Now commit the transaction with all updates
            $pdo->commit();
            
            // Return success response
            echo json_encode([
                'success' => true,
                'status' => $payment_result['status'],
                'message' => $payment_result['message'] ?? 'Payment initiated successfully',
                'transaction_ref' => $transaction_ref,
                'amount' => $total_amount,
                'voter_name' => $voter_name,
                'nominee_name' => $nominee['name'],
                'vote_count' => $vote_count,
                'payment_details' => [
                    'hubtel_transaction_id' => $payment_result['transaction_id'] ?? '',
                    'amount' => $total_amount,
                    'charges' => $payment_result['charges'] ?? 0,
                    'amount_charged' => $payment_result['amount_charged'] ?? $total_amount
                ]
            ]);
            
        } else {
            // Payment initiation failed - rollback the transaction
            $pdo->rollBack();
            
            echo json_encode([
                'success' => false,
                'message' => $payment_result['message'] ?? 'Payment initiation failed',
                'transaction_ref' => $transaction_ref,
                'error_code' => $payment_result['code'] ?? 'PAYMENT_FAILED'
            ]);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in hubtel-vote-submit.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
} catch (Exception $e) {
    error_log("Error in hubtel-vote-submit.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your vote. Please try again.'
    ]);
}
?>