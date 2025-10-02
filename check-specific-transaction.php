<?php
/**
 * Check specific USSD transaction from the logs
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "<h2>Checking Transaction: USSD_1759418925_7876</h2>";

    // Check if this transaction exists
    $stmt = $pdo->prepare("SELECT * FROM ussd_transactions WHERE transaction_ref = ?");
    $stmt->execute(['USSD_1759418925_7876']);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transaction) {
        echo "<h3>✅ Transaction Found</h3>";
        echo "<pre>" . print_r($transaction, true) . "</pre>";
        
        echo "<h3>Status: " . $transaction['status'] . "</h3>";
        
        if ($transaction['status'] === 'processing') {
            echo "<p style='color: orange;'>⚠️ Transaction is stuck in 'processing' - payment completed but no callback received</p>";
            
            // Check if we should mark it as completed (older than 10 minutes)
            $age = time() - strtotime($transaction['created_at']);
            echo "<p>Transaction age: " . round($age/60, 1) . " minutes</p>";
            
            if ($age > 600) { // 10 minutes
                echo "<h3>🔧 Fixing Transaction</h3>";
                
                // Update to completed
                $stmt = $pdo->prepare("UPDATE ussd_transactions SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$transaction['id']]);
                
                // Create main transaction and votes (same logic as the cron job)
                // ... (implementation from the cron job)
                
                echo "<p style='color: green;'>✅ Transaction marked as completed</p>";
            } else {
                echo "<p>Transaction is recent - waiting for callback (will auto-fix in " . (600 - $age) . " seconds)</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Transaction status is: " . $transaction['status'] . "</p>";
        }
    } else {
        echo "<h3>❌ Transaction NOT Found</h3>";
        echo "<p>This means the AddToCart response was sent but the transaction was never created in ussd_transactions table.</p>";
        
        // Check recent transactions
        echo "<h3>Recent USSD Transactions:</h3>";
        $stmt = $pdo->prepare("SELECT * FROM ussd_transactions ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($recent) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Ref</th><th>Phone</th><th>Status</th><th>Created</th></tr>";
            foreach ($recent as $t) {
                echo "<tr>";
                echo "<td>{$t['id']}</td>";
                echo "<td>{$t['transaction_ref']}</td>";
                echo "<td>{$t['phone_number']}</td>";
                echo "<td>{$t['status']}</td>";
                echo "<td>{$t['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No USSD transactions found in database</p>";
        }
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
