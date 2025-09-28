<?php
/**
 * Hubtel USSD Vote Submission Handler
 * Generates USSD payment codes for voting
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/ussd-vote-submission.log');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelUSSDService.php';

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
    
    // Check if Hubtel USSD is enabled
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'hubtel_ussd_enabled'");
    $stmt->execute();
    $ussdEnabled = $stmt->fetchColumn();
    
    if ($ussdEnabled !== '1') {
        echo json_encode([
            'success' => false, 
            'message' => 'USSD voting is currently disabled. Please contact support.'
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
        $transaction_ref = 'USSD_' . time() . '_' . rand(1000, 9999);
        
        // Create USSD transaction record
        $stmt = $pdo->prepare("
            INSERT INTO ussd_transactions (
                transaction_ref, phone_number, event_id, nominee_id,
                vote_count, amount, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $transaction_ref,
            $phone_number,
            $event_id,
            $nominee_id,
            $vote_count,
            $total_amount
        ]);
        
        $ussd_transaction_id = $pdo->lastInsertId();
        
        // Initialize Hubtel USSD service
        $hubtelUSSD = new HubtelUSSDService();
        
        $description = "Vote for {$nominee['name']} in {$event['title']} ({$vote_count} vote" . ($vote_count > 1 ? 's' : '') . ")";
        
        $metadata = [
            'voter_name' => $voter_name,
            'email' => $email,
            'event_id' => $event_id,
            'nominee_id' => $nominee_id,
            'vote_count' => $vote_count,
            'ussd_transaction_id' => $ussd_transaction_id
        ];
        
        // Generate USSD payment
        $payment_result = $hubtelUSSD->generateUSSDPayment(
            $total_amount,
            $phone_number,
            $description,
            $transaction_ref,
            $metadata
        );
        
        if ($payment_result['success']) {
            // Update USSD transaction with payment details
            $stmt = $pdo->prepare("
                UPDATE ussd_transactions 
                SET payment_token = ?, ussd_code = ?, hubtel_transaction_id = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $payment_result['payment_token'] ?? '',
                $payment_result['ussd_code'] ?? '',
                $payment_result['transaction_id'] ?? '',
                $ussd_transaction_id
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            // Log successful USSD generation
            error_log("USSD payment generated successfully: $transaction_ref - {$payment_result['ussd_code']}");
            
            // Return success response with USSD details
            echo json_encode([
                'success' => true,
                'payment_method' => 'ussd',
                'status' => 'ussd_generated',
                'message' => 'USSD payment code generated successfully',
                'transaction_ref' => $transaction_ref,
                'ussd_code' => $payment_result['ussd_code'],
                'amount' => $total_amount,
                'voter_name' => $voter_name,
                'nominee_name' => $nominee['name'],
                'vote_count' => $vote_count,
                'instructions' => $payment_result['instructions'],
                'expires_at' => $payment_result['expires_at'],
                'payment_details' => [
                    'method' => 'hubtel_ussd',
                    'amount' => $total_amount,
                    'ussd_code' => $payment_result['ussd_code'],
                    'expires_at' => $payment_result['expires_at']
                ]
            ]);
            
        } else {
            // USSD generation failed - rollback
            $pdo->rollBack();
            
            error_log("USSD payment generation failed: " . $payment_result['message']);
            
            echo json_encode([
                'success' => false,
                'message' => $payment_result['message'] ?? 'Failed to generate USSD payment code',
                'error_code' => $payment_result['error_code'] ?? 'USSD_GENERATION_FAILED',
                'transaction_ref' => $transaction_ref
            ]);
        }
        
    } catch (Exception $e) {
        // Database operation failed - rollback
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in hubtel-ussd-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in hubtel-ussd-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
