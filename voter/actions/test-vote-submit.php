<?php
/**
 * Test Vote Submission Handler - Mock Payment Mode
 * Simulates successful payment for testing purposes
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/test-vote-submission.log');

require_once __DIR__ . '/../../config/database.php';

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
    $vote_cost = 1.00; // Default cost for testing
    $total_amount = $vote_cost * $vote_count;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Generate unique transaction reference
        $transaction_ref = 'TEST_' . time() . '_' . rand(1000, 9999);
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'test_payment', 'completed', NOW())
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
        
        // Create votes immediately (simulating successful payment)
        for ($i = 0; $i < $vote_count; $i++) {
            $stmt = $pdo->prepare("
                INSERT INTO votes (
                    event_id, category_id, nominee_id, voter_phone, 
                    transaction_id, payment_method, payment_reference, 
                    payment_status, amount, voted_at, created_at
                ) VALUES (?, ?, ?, ?, ?, 'test_payment', ?, 'completed', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $event_id,
                $nominee['category_id'],
                $nominee_id, 
                $phone_number, 
                $transaction_db_id,
                $transaction_ref,
                $total_amount / $vote_count
            ]);
        }
        
        // Commit the transaction
        $pdo->commit();
        
        // Log successful test vote
        error_log("Test vote successful: $transaction_ref - $vote_count votes for nominee $nominee_id");
        
        // Return success response
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => 'Test vote completed successfully! (No real payment processed)',
            'transaction_ref' => $transaction_ref,
            'amount' => $total_amount,
            'voter_name' => $voter_name,
            'nominee_name' => $nominee['name'],
            'vote_count' => $vote_count,
            'payment_details' => [
                'method' => 'test_payment',
                'amount' => $total_amount,
                'charges' => 0,
                'amount_charged' => $total_amount
            ],
            'test_mode' => true
        ]);
        
    } catch (Exception $e) {
        // Payment/vote creation failed - rollback the transaction
        $pdo->rollBack();
        
        error_log("Test vote failed: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Vote creation failed: ' . $e->getMessage(),
            'transaction_ref' => $transaction_ref ?? 'N/A'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in test-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in test-vote-submit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
