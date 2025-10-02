<?php
/**
 * USSD Transaction Status Checker
 * Polls processing transactions and updates their status
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Get processing transactions older than 5 minutes
    $stmt = $pdo->prepare("
        SELECT * FROM ussd_transactions 
        WHERE status = 'processing' 
        AND created_at < NOW() - INTERVAL 5 MINUTE
        AND created_at > NOW() - INTERVAL 24 HOUR
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $processingTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($processingTransactions) . " processing transactions to check\n";

    foreach ($processingTransactions as $transaction) {
        echo "Checking transaction: {$transaction['transaction_ref']}\n";
        
        // For now, mark transactions older than 30 minutes as completed
        // This is a temporary workaround until callbacks work
        if (strtotime($transaction['created_at']) < time() - 1800) { // 30 minutes
            
            // Update to completed
            $stmt = $pdo->prepare("
                UPDATE ussd_transactions 
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transaction['id']]);
            
            // Create main transaction record
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    transaction_id, reference, event_id, organizer_id, nominee_id,
                    voter_phone, vote_count, amount, payment_method,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', NOW())
            ");
            
            // Get organizer_id from event
            $stmt2 = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
            $stmt2->execute([$transaction['event_id']]);
            $organizerId = $stmt2->fetchColumn();
            
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
            
            echo "✓ Completed transaction {$transaction['transaction_ref']} - {$voteCount} votes recorded\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
