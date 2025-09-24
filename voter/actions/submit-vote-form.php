<?php
/**
 * Form-based Vote Submission Handler
 * Alternative endpoint that accepts form data instead of JSON
 * Bypasses ModSecurity content-type restrictions
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/PaystackService.php';
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
    
    // Get form data instead of JSON
    $nominee_id = (int)($_POST['nominee_id'] ?? 0);
    $vote_count = (int)($_POST['vote_count'] ?? 1);
    $payment_method = $_POST['payment_method'] ?? '';
    $phone_number = $_POST['phone_number'] ?? null;
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    
    // Validate required fields
    if (!$nominee_id || !$payment_method || $total_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Validate phone number for mobile money
    if ($payment_method === 'mobile_money' && empty($phone_number)) {
        echo json_encode(['success' => false, 'message' => 'Phone number required for mobile money']);
        exit;
    }
    
    // Validate nominee exists and get details
    $stmt = $pdo->prepare("
        SELECT n.*, c.description as category_name, e.title as event_title, e.status as event_status,
               c.event_id, e.organizer_id
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE n.id = ? AND n.status = 'active' AND e.status = 'active'
    ");
    $stmt->execute([$nominee_id]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        echo json_encode(['success' => false, 'message' => 'Nominee not found or voting not available']);
        exit;
    }
    
    // Format phone number for Ghana
    if ($phone_number) {
        $phone_number = preg_replace('/[^0-9+]/', '', $phone_number);
        if (substr($phone_number, 0, 1) === '0') {
            $phone_number = '+233' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 4) !== '+233') {
            $phone_number = '+233' . $phone_number;
        }
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
            $phone_number ?? '',
            $vote_count,
            $total_amount,
            $payment_method,
            $transaction_id
        ]);
        
        $transaction_db_id = $pdo->lastInsertId();
        
        // Initialize payment services
        $paystack = new PaystackService();
        
        // Commit transaction record first
        $pdo->commit();
        
        // Handle payment method
        if ($payment_method === 'mobile_money') {
            // Initialize transaction with Paystack
            $callback_url = 'https://' . $_SERVER['HTTP_HOST'] . 
                           dirname(dirname($_SERVER['REQUEST_URI'])) . '/payment-success.php';
            
            $payment_result = $paystack->initializeTransaction(
                $phone_number . '@ecast.com',
                $total_amount,
                $transaction_id,
                $callback_url,
                [
                    'transaction_db_id' => $transaction_db_id,
                    'nominee_id' => $nominee_id,
                    'event_id' => $nominee['event_id'],
                    'phone' => $phone_number,
                    'vote_count' => $vote_count
                ]
            );
            
            if ($payment_result['success']) {
                echo json_encode([
                    'success' => true,
                    'payment_method' => 'mobile_money',
                    'step' => 'redirect_required',
                    'message' => 'Redirecting to payment gateway...',
                    'transaction_id' => $transaction_id,
                    'authorization_url' => $payment_result['authorization_url'],
                    'reference' => $payment_result['reference'],
                    'amount' => $total_amount
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to initialize payment. Please try again.',
                    'transaction_id' => $transaction_id
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Payment method not supported'
            ]);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in submit-vote-form.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in submit-vote-form.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your vote']);
}
?>
