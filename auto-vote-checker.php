<?php
/**
 * Automatic Vote Creation and Status Checker
 * This script should be run periodically to ensure all completed payments have corresponding votes
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/HubtelReceiveMoneyService.php';

function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message";
    echo $logEntry . "\n";
    error_log($logEntry);
}

try {
    logMessage("Starting automatic vote creation and status check");
    
    $database = new Database();
    $pdo = $database->getConnection();
    $hubtelService = new HubtelReceiveMoneyService();
    
    // Step 1: Check pending transactions older than 5 minutes
    logMessage("Checking pending transactions for status updates...");
    
    $stmt = $pdo->query("
        SELECT reference, transaction_id, created_at, vote_count, nominee_id
        FROM transactions 
        WHERE status = 'pending' 
        AND payment_method = 'mobile_money'
        AND created_at <= NOW() - INTERVAL 5 MINUTE
        ORDER BY created_at ASC
        LIMIT 20
    ");
    $pending_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $checked_count = 0;
    $updated_count = 0;
    
    foreach ($pending_transactions as $transaction) {
        $reference = $transaction['reference'] ?: $transaction['transaction_id'];
        
        try {
            logMessage("Checking status for transaction: $reference");
            
            // Check status with Hubtel
            $status_result = $hubtelService->checkTransactionStatus($reference);
            
            if ($status_result['success']) {
                $new_status = 'pending';
                
                if ($status_result['is_paid']) {
                    $new_status = 'completed';
                } elseif (isset($status_result['status']) && strtolower($status_result['status']) === 'failed') {
                    $new_status = 'failed';
                }
                
                if ($new_status !== 'pending') {
                    // Update transaction status
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET status = ?, 
                            external_transaction_id = ?,
                            payment_charges = ?,
                            updated_at = NOW()
                        WHERE reference = ? OR transaction_id = ?
                    ");
                    
                    $stmt->execute([
                        $new_status,
                        $status_result['external_transaction_id'] ?? '',
                        $status_result['charges'] ?? 0,
                        $reference,
                        $reference
                    ]);
                    
                    logMessage("Updated transaction $reference status to: $new_status");
                    $updated_count++;
                    
                    // If completed, create votes
                    if ($new_status === 'completed') {
                        $vote_result = $hubtelService->createVoteRecordsFromTransaction($reference);
                        if ($vote_result) {
                            logMessage("✓ Created votes for completed transaction: $reference");
                        } else {
                            logMessage("⚠ Warning: Could not create votes for: $reference", 'WARNING');
                        }
                    }
                }
            } else {
                logMessage("Could not check status for $reference: " . ($status_result['message'] ?? 'Unknown error'), 'WARNING');
            }
            
            $checked_count++;
            
        } catch (Exception $e) {
            logMessage("Error checking transaction $reference: " . $e->getMessage(), 'ERROR');
        }
        
        // Add small delay to avoid API rate limits
        usleep(500000); // 0.5 seconds
    }
    
    logMessage("Checked $checked_count pending transactions, updated $updated_count");
    
    // Step 2: Find and fix completed transactions without votes
    logMessage("Checking for completed transactions missing votes...");
    
    $stmt = $pdo->query("
        SELECT t.id, t.reference, t.transaction_id, t.vote_count, t.nominee_id, 
               t.voter_phone, t.amount, COUNT(v.id) as existing_votes
        FROM transactions t
        LEFT JOIN votes v ON (v.transaction_id = t.id OR v.payment_reference = t.reference)
        WHERE t.status = 'completed' 
        AND t.payment_method = 'mobile_money'
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        GROUP BY t.id
        HAVING t.vote_count > existing_votes OR existing_votes = 0
    ");
    
    $missing_vote_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fixed_votes = 0;
    
    foreach ($missing_vote_transactions as $tx) {
        try {
            $reference = $tx['reference'] ?: $tx['transaction_id'];
            $votes_needed = $tx['vote_count'] - $tx['existing_votes'];
            
            logMessage("Creating $votes_needed missing vote(s) for transaction: $reference");
            
            // Get event_id and category_id from the nominee
            $stmt = $pdo->prepare("
                SELECT n.category_id, c.event_id 
                FROM nominees n 
                JOIN categories c ON n.category_id = c.id 
                WHERE n.id = ?
            ");
            $stmt->execute([$tx['nominee_id']]);
            $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nominee_data) {
                logMessage("Could not find nominee data for transaction: $reference", 'ERROR');
                continue;
            }
            
            $vote_amount = $tx['amount'] / $tx['vote_count'];
            
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
                    $tx['nominee_id'],
                    $tx['voter_phone'],
                    $tx['id'],
                    $reference,
                    $vote_amount
                ]);
            }
            
            logMessage("✓ Created $votes_needed vote(s) for transaction: $reference");
            $fixed_votes += $votes_needed;
            
        } catch (Exception $e) {
            logMessage("Error creating votes for transaction {$tx['reference']}: " . $e->getMessage(), 'ERROR');
        }
    }
    
    logMessage("Created $fixed_votes missing vote records");
    
    // Step 3: Summary
    logMessage("=== SUMMARY ===");
    logMessage("• Pending transactions checked: $checked_count");
    logMessage("• Transactions status updated: $updated_count");
    logMessage("• Missing votes created: $fixed_votes");
    logMessage("Automatic vote creation check completed successfully");
    
} catch (Exception $e) {
    logMessage("Fatal error in automatic vote checker: " . $e->getMessage(), 'ERROR');
}
?>