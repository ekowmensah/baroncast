<?php
/**
 * Fix stuck USSD transactions and record votes
 * Run this to manually complete transactions that are stuck on "processing"
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Fixing Stuck USSD Transactions</h2>\n";
    
    // Get all processing transactions
    $stmt = $pdo->prepare("
        SELECT ut.*, n.name as nominee_name, e.title as event_title
        FROM ussd_transactions ut
        JOIN nominees n ON ut.nominee_id = n.id
        JOIN events e ON ut.event_id = e.id
        WHERE ut.status = 'processing'
        ORDER BY ut.created_at DESC
    ");
    $stmt->execute();
    $stuckTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($stuckTransactions) . " stuck transactions</p>\n";
    
    foreach ($stuckTransactions as $transaction) {
        echo "<h3>Processing Transaction: {$transaction['transaction_ref']}</h3>\n";
        echo "<p>Nominee: {$transaction['nominee_name']}</p>\n";
        echo "<p>Votes: {$transaction['vote_count']}</p>\n";
        echo "<p>Amount: GHS {$transaction['amount']}</p>\n";
        
        // Start database transaction
        $pdo->beginTransaction();
        
        try {
            // Update USSD transaction status
            $stmt = $pdo->prepare("
                UPDATE ussd_transactions 
                SET status = 'completed', completed_at = NOW()
                WHERE transaction_ref = ?
            ");
            $stmt->execute([$transaction['transaction_ref']]);
            
            // Get organizer_id from event
            $stmt = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
            $stmt->execute([$transaction['event_id']]);
            $organizerId = $stmt->fetchColumn();
            
            // Check if main transaction already exists
            $stmt = $pdo->prepare("SELECT id FROM transactions WHERE reference = ?");
            $stmt->execute([$transaction['transaction_ref']]);
            $existingTransaction = $stmt->fetchColumn();
            
            if (!$existingTransaction) {
                // Create main transaction record
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        transaction_id, reference, event_id, organizer_id, nominee_id,
                        voter_phone, vote_count, amount, payment_method,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', NOW())
                ");
                $stmt->execute([
                    $transaction['transaction_ref'],
                    $transaction['transaction_ref'],
                    $transaction['event_id'],
                    $organizerId,
                    $transaction['nominee_id'],
                    $transaction['phone_number'],
                    $transaction['vote_count'],
                    $transaction['amount']
                ]);
                
                $mainTransactionId = $pdo->lastInsertId();
                echo "<p>✅ Created main transaction record (ID: $mainTransactionId)</p>\n";
            } else {
                $mainTransactionId = $existingTransaction;
                echo "<p>ℹ️ Main transaction already exists (ID: $mainTransactionId)</p>\n";
            }
            
            // Check if votes already exist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE payment_reference = ?");
            $stmt->execute([$transaction['transaction_ref']]);
            $existingVotes = $stmt->fetchColumn();
            
            if ($existingVotes == 0) {
                // Create individual votes
                $voteCount = (int)$transaction['vote_count'];
                $voteAmount = $transaction['amount'] / $voteCount;
                
                // Get category_id from nominee
                $stmt = $pdo->prepare("SELECT category_id FROM nominees WHERE id = ?");
                $stmt->execute([$transaction['nominee_id']]);
                $categoryId = $stmt->fetchColumn();
                
                for ($i = 0; $i < $voteCount; $i++) {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (
                            event_id, category_id, nominee_id, voter_phone, 
                            transaction_id, payment_method, payment_reference, 
                            payment_status, amount, voted_at, created_at
                        ) VALUES (?, ?, ?, ?, ?, 'hubtel_ussd', ?, 'completed', ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $transaction['event_id'],
                        $categoryId,
                        $transaction['nominee_id'],
                        $transaction['phone_number'],
                        $mainTransactionId,
                        $transaction['transaction_ref'],
                        $voteAmount
                    ]);
                }
                
                echo "<p>✅ Created $voteCount individual votes</p>\n";
            } else {
                echo "<p>ℹ️ Votes already exist ($existingVotes votes found)</p>\n";
            }
            
            // Commit transaction
            $pdo->commit();
            echo "<p><strong>✅ Transaction {$transaction['transaction_ref']} completed successfully!</strong></p>\n";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p><strong>❌ Error processing transaction: " . $e->getMessage() . "</strong></p>\n";
        }
        
        echo "<hr>\n";
    }
    
    echo "<h3>Summary</h3>\n";
    echo "<p>All stuck transactions have been processed.</p>\n";
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Configure Payment Callback URL in Hubtel dashboard: <code>https://yourdomain.com/webhooks/payment-callback.php</code></li>\n";
    echo "<li>Test new payments to ensure callbacks are received</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
}
?>
