<?php
/**
 * Check Payment Status via Hubtel Direct Receive Money API
 * Called via AJAX to check the status of pending payments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/HubtelReceiveMoneyService.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $transaction_ref = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : '';
    
    if (empty($transaction_ref)) {
        echo json_encode(['success' => false, 'message' => 'Transaction reference is required']);
        exit;
    }
    
    // Get transaction from database
    $stmt = $pdo->prepare("
        SELECT t.*, n.name as nominee_name, e.title as event_title
        FROM transactions t
        JOIN nominees n ON t.nominee_id = n.id
        JOIN events e ON t.event_id = e.id
        WHERE t.reference = ? OR t.transaction_id = ?
    ");
    $stmt->execute([$transaction_ref, $transaction_ref]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    // If transaction is already completed or failed, return current status
    if (in_array($transaction['status'], ['completed', 'failed'])) {
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'message' => 'Transaction status: ' . $transaction['status'],
            'transaction_ref' => $transaction_ref,
            'amount' => (float)$transaction['amount'],
            'vote_count' => (int)$transaction['vote_count'],
            'nominee_name' => $transaction['nominee_name']
        ]);
        exit;
    }
    
    // For pending transactions, check with Hubtel
    if ($transaction['status'] === 'pending') {
        $hubtel = new HubtelReceiveMoneyService();
        
        // Check transaction status with Hubtel
        $status_result = $hubtel->checkTransactionStatus($transaction_ref);
        
        if ($status_result['success']) {
            $new_status = 'pending'; // Default to pending
            
            if ($status_result['is_paid']) {
                $new_status = 'completed';
            } elseif (isset($status_result['status']) && strtolower($status_result['status']) === 'failed') {
                $new_status = 'failed';
            }
            
            // Update transaction status in database if it changed
            if ($new_status !== $transaction['status']) {
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET status = ?,
                        external_transaction_id = ?,
                        payment_charges = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $new_status,
                    $status_result['external_transaction_id'] ?? '',
                    $status_result['charges'] ?? 0,
                    $transaction['id']
                ]);
                
                // If payment is completed, create vote records (with duplicate prevention)
                if ($new_status === 'completed') {
                    // Check if votes already exist for this transaction
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM votes 
                        WHERE transaction_id = ? OR payment_reference = ?
                    ");
                    $stmt->execute([$transaction['id'], $transaction_ref]);
                    $existing_votes = $stmt->fetchColumn();
                    
                    $vote_count = (int)$transaction['vote_count'];
                    $votes_needed = $vote_count - $existing_votes;
                    
                    if ($votes_needed > 0) {
                        // Get event_id and category_id from the nominee
                        $stmt = $pdo->prepare("
                            SELECT n.category_id, c.event_id 
                            FROM nominees n 
                            JOIN categories c ON n.category_id = c.id 
                            WHERE n.id = ?
                        ");
                        $stmt->execute([$transaction['nominee_id']]);
                        $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($nominee_data) {
                            $nominee_id = $transaction['nominee_id'];
                            $voter_phone = $transaction['voter_phone'];
                            $transaction_id = $transaction['id'];
                            
                            for ($i = 0; $i < $votes_needed; $i++) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO votes (
                                        event_id, category_id, nominee_id, voter_phone, 
                                        transaction_id, payment_method, payment_reference, 
                                        payment_status, amount, voted_at
                                    ) VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, 'completed', ?, NOW())
                                ");
                                
                                $stmt->execute([
                                    $nominee_data['event_id'],
                                    $nominee_data['category_id'],
                                    $nominee_id,
                                    $voter_phone,
                                    $transaction_id,
                                    $transaction_ref,
                                    $transaction['amount'] / $vote_count // Amount per vote
                                ]);
                            }
                        } else {
                            error_log("Could not find nominee data for transaction: $transaction_ref");
                        }
                        
                        error_log("Created $votes_needed vote(s) for completed transaction: $transaction_ref");
                    } else {
                        error_log("Votes already exist for transaction: $transaction_ref");
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'status' => $new_status,
                'message' => 'Status updated from Hubtel',
                'transaction_ref' => $transaction_ref,
                'amount' => (float)$transaction['amount'],
                'vote_count' => (int)$transaction['vote_count'],
                'nominee_name' => $transaction['nominee_name'],
                'hubtel_status' => $status_result['status'] ?? 'unknown'
            ]);
            
        } else {
            // Hubtel status check failed, return current status
            echo json_encode([
                'success' => true,
                'status' => $transaction['status'],
                'message' => 'Could not check with payment provider, showing last known status',
                'transaction_ref' => $transaction_ref,
                'amount' => (float)$transaction['amount'],
                'vote_count' => (int)$transaction['vote_count'],
                'nominee_name' => $transaction['nominee_name']
            ]);
        }
    } else {
        // Transaction has unknown status
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'message' => 'Transaction status: ' . $transaction['status'],
            'transaction_ref' => $transaction_ref,
            'amount' => (float)$transaction['amount'],
            'vote_count' => (int)$transaction['vote_count'],
            'nominee_name' => $transaction['nominee_name']
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in check-payment-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in check-payment-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while checking payment status']);
}
?>