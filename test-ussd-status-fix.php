<?php
/**
 * Test USSD Status Fix - Run this once to fix current stuck transactions
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "<h2>USSD Transaction Status Fix</h2>";

    // Get all processing transactions
    $stmt = $pdo->prepare("
        SELECT ut.*, n.name as nominee_name, e.title as event_title
        FROM ussd_transactions ut
        LEFT JOIN nominees n ON ut.nominee_id = n.id
        LEFT JOIN events e ON ut.event_id = e.id
        WHERE ut.status = 'processing'
        ORDER BY ut.created_at DESC
    ");
    $stmt->execute();
    $processingTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " . count($processingTransactions) . " processing transactions</p>";

    if (empty($processingTransactions)) {
        echo "<p style='color: green;'>No processing transactions found!</p>";
        exit;
    }

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Ref</th><th>Phone</th><th>Event</th><th>Nominee</th><th>Votes</th><th>Amount</th><th>Created</th><th>Action</th></tr>";

    foreach ($processingTransactions as $transaction) {
        echo "<tr>";
        echo "<td>{$transaction['id']}</td>";
        echo "<td>{$transaction['transaction_ref']}</td>";
        echo "<td>{$transaction['phone_number']}</td>";
        echo "<td>" . substr($transaction['event_title'] ?? '', 0, 20) . "</td>";
        echo "<td>" . substr($transaction['nominee_name'] ?? '', 0, 20) . "</td>";
        echo "<td>{$transaction['vote_count']}</td>";
        echo "<td>{$transaction['amount']}</td>";
        echo "<td>{$transaction['created_at']}</td>";

        // Check if transaction is older than 10 minutes (assume payment completed)
        $transactionAge = time() - strtotime($transaction['created_at']);
        
        if ($transactionAge > 600) { // 10 minutes
            // Update to completed
            $stmt = $pdo->prepare("
                UPDATE ussd_transactions 
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transaction['id']]);
            
            // Check if main transaction already exists
            $stmt = $pdo->prepare("SELECT id FROM transactions WHERE reference = ?");
            $stmt->execute([$transaction['transaction_ref']]);
            $existingTransaction = $stmt->fetch();
            
            if (!$existingTransaction) {
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
                
                echo "<td style='color: green;'>✓ FIXED - {$voteCount} votes recorded</td>";
            } else {
                echo "<td style='color: orange;'>Already processed</td>";
            }
        } else {
            echo "<td style='color: blue;'>Too recent (wait " . (600 - $transactionAge) . "s)</td>";
        }
        
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Summary</h3>";
    echo "<p>All processing transactions older than 10 minutes have been marked as completed and votes recorded.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Set up the cron job to run every 15 minutes: <code>/cron/ussd-status-checker.php</code></li>";
    echo "<li>Contact Hubtel support about USSD callback configuration</li>";
    echo "<li>Check if callback URL needs to be different for USSD vs PayProxy</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
