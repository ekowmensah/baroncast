<?php
/**
 * Auto-Fix stuck USSD transactions and record votes
 * Runs automatically every 5 minutes to handle stuck transactions
 */

require_once __DIR__ . '/../config/database.php';

// Check if running via web or CLI
$isWeb = isset($_SERVER['HTTP_HOST']);
$isAutoRun = isset($_GET['auto']) || (php_sapi_name() === 'cli');

// Auto-run mode: process silently and schedule next run
if ($isAutoRun) {
    // Set up logging
    $logFile = __DIR__ . '/../logs/auto-fix-transactions.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    function logMessage($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
    
    logMessage("Auto-fix script started");
} else {
    function logMessage($message) {
        echo "<p>$message</p>\n";
    }
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$isAutoRun) {
        echo "<h2>Fixing Stuck USSD Transactions</h2>\n";
        echo "<p><a href='?auto=1'>Enable Auto-Run Mode</a></p>\n";
    }
    
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
    
    logMessage("Found " . count($stuckTransactions) . " stuck transactions");
    
    $processedCount = 0;
    foreach ($stuckTransactions as $transaction) {
        if (!$isAutoRun) {
            echo "<h3>Processing Transaction: {$transaction['transaction_ref']}</h3>\n";
            echo "<p>Nominee: {$transaction['nominee_name']}</p>\n";
            echo "<p>Votes: {$transaction['vote_count']}</p>\n";
            echo "<p>Amount: GHS {$transaction['amount']}</p>\n";
        }
        
        logMessage("Processing transaction: {$transaction['transaction_ref']} - {$transaction['nominee_name']} - {$transaction['vote_count']} votes");
        
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
                if (!$isAutoRun) echo "<p>✅ Created main transaction record (ID: $mainTransactionId)</p>\n";
                logMessage("Created main transaction record (ID: $mainTransactionId)");
            } else {
                $mainTransactionId = $existingTransaction;
                if (!$isAutoRun) echo "<p>ℹ️ Main transaction already exists (ID: $mainTransactionId)</p>\n";
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
                
                if (!$isAutoRun) echo "<p>✅ Created $voteCount individual votes</p>\n";
                logMessage("Created $voteCount individual votes");
            } else {
                if (!$isAutoRun) echo "<p>ℹ️ Votes already exist ($existingVotes votes found)</p>\n";
            }
            
            // Commit transaction
            $pdo->commit();
            if (!$isAutoRun) echo "<p><strong>✅ Transaction {$transaction['transaction_ref']} completed successfully!</strong></p>\n";
            logMessage("SUCCESS: Transaction {$transaction['transaction_ref']} completed - {$transaction['vote_count']} votes recorded");
            $processedCount++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            if (!$isAutoRun) echo "<p><strong>❌ Error processing transaction: " . $e->getMessage() . "</strong></p>\n";
            logMessage("ERROR: Failed to process transaction {$transaction['transaction_ref']}: " . $e->getMessage());
        }
        
        if (!$isAutoRun) echo "<hr>\n";
    }
    
    logMessage("Auto-fix completed: Processed $processedCount transactions");
    
    if (!$isAutoRun) {
        echo "<h3>Summary</h3>\n";
        echo "<p>Processed $processedCount stuck transactions.</p>\n";
        echo "<p><strong>Next steps:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Configure Payment Callback URL in Hubtel dashboard: <code>https://yourdomain.com/webhooks/payment-callback.php</code></li>\n";
        echo "<li>Test new payments to ensure callbacks are received</li>\n";
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    if ($isAutoRun) {
        logMessage($errorMsg);
    } else {
        echo "<p><strong>$errorMsg</strong></p>\n";
    }
}

// Auto-run mode: Schedule next execution
if ($isAutoRun && $isWeb) {
    // Use JavaScript to reload the page every 5 minutes
    echo "<html><head><title>Auto-Fix Running</title></head><body>";
    echo "<h2>Auto-Fix Transaction Service</h2>";
    echo "<p>Service is running... Page will refresh every 1 minute.</p>";
    echo "<p>Last run: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p><a href='?'>Stop Auto-Run</a></p>";
    echo "<script>setTimeout(function(){ window.location.reload(); }, 60000);</script>"; // 1 minute
    echo "</body></html>";
}
?>
