<?php
/**
 * Debug Vote Callback Issue
 * Test to see what's happening with vote count processing
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html');

echo "<h2>🔍 Debug Vote Callback Issue</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h3>1. Recent Transactions with Vote Count > 1:</h3>";
    
    $stmt = $pdo->query("
        SELECT 
            id, reference, nominee_id, voter_phone, vote_count, amount, 
            status, payment_method, created_at
        FROM transactions 
        WHERE vote_count > 1 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p style='color: orange;'>⚠️ No transactions found with vote_count > 1</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #ddd;'>";
        echo "<th>ID</th><th>Reference</th><th>Nominee ID</th><th>Vote Count</th><th>Amount</th><th>Status</th><th>Created</th>";
        echo "</tr>";
        
        foreach ($transactions as $tx) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>{$tx['reference']}</td>";
            echo "<td>{$tx['nominee_id']}</td>";
            echo "<td style='color: blue; font-weight: bold;'>{$tx['vote_count']}</td>";
            echo "<td>GH₵{$tx['amount']}</td>";
            echo "<td>{$tx['status']}</td>";
            echo "<td>{$tx['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>2. Checking Votes Created for These Transactions:</h3>";
        
        foreach ($transactions as $tx) {
            echo "<h4>Transaction: {$tx['reference']} (Vote Count: {$tx['vote_count']})</h4>";
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as actual_votes_created, 
                       SUM(amount) as total_vote_amount
                FROM votes 
                WHERE payment_reference = ? OR transaction_id = ?
            ");
            $stmt->execute([$tx['reference'], $tx['id']]);
            $voteData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<div style='margin-left: 20px;'>";
            echo "<p><strong>Expected Votes:</strong> {$tx['vote_count']}</p>";
            echo "<p><strong>Actual Votes Created:</strong> <span style='color: " . 
                 ($voteData['actual_votes_created'] == $tx['vote_count'] ? 'green' : 'red') . 
                 "; font-weight: bold;'>{$voteData['actual_votes_created']}</span></p>";
            echo "<p><strong>Total Vote Amount:</strong> GH₵{$voteData['total_vote_amount']}</p>";
            
            if ($voteData['actual_votes_created'] != $tx['vote_count']) {
                echo "<p style='color: red;'>❌ <strong>MISMATCH DETECTED!</strong></p>";
                
                // Show individual votes
                $stmt = $pdo->prepare("
                    SELECT id, amount, voted_at, payment_status 
                    FROM votes 
                    WHERE payment_reference = ? OR transaction_id = ?
                    ORDER BY voted_at DESC
                ");
                $stmt->execute([$tx['reference'], $tx['id']]);
                $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<p><strong>Individual Votes:</strong></p>";
                echo "<ul>";
                foreach ($votes as $vote) {
                    echo "<li>Vote ID: {$vote['id']}, Amount: GH₵{$vote['amount']}, Status: {$vote['payment_status']}, Time: {$vote['voted_at']}</li>";
                }
                echo "</ul>";
            } else {
                echo "<p style='color: green;'>✅ Vote count matches!</p>";
            }
            echo "</div>";
        }
    }
    
    echo "<h3>3. Recent Callback Logs:</h3>";
    
    // Check if there are any callback logs
    $logFile = __DIR__ . '/logs/hubtel-callback.log';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $logLines = explode("\n", $logs);
        $recentLogs = array_slice(array_reverse($logLines), 0, 20);
        
        echo "<div style='background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        foreach ($recentLogs as $log) {
            if (trim($log)) {
                echo htmlspecialchars($log) . "<br>";
            }
        }
        echo "</div>";
    } else {
        echo "<p style='color: orange;'>⚠️ No callback log file found at: $logFile</p>";
    }
    
    echo "<h3>4. Test Vote Creation Logic:</h3>";
    
    // Simulate the callback vote creation logic
    if (!empty($transactions)) {
        $testTx = $transactions[0]; // Use the first transaction for testing
        
        echo "<h4>Testing with Transaction: {$testTx['reference']}</h4>";
        
        // Get nominee data
        $stmt = $pdo->prepare("
            SELECT n.*, c.id as category_id, e.id as event_id 
            FROM nominees n 
            LEFT JOIN categories c ON n.category_id = c.id 
            LEFT JOIN events e ON c.event_id = e.id 
            WHERE n.id = ?
        ");
        $stmt->execute([$testTx['nominee_id']]);
        $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nominee_data) {
            echo "<p><strong>Nominee Data Found:</strong></p>";
            echo "<ul>";
            echo "<li>Nominee: {$nominee_data['name']}</li>";
            echo "<li>Event ID: {$nominee_data['event_id']}</li>";
            echo "<li>Category ID: {$nominee_data['category_id']}</li>";
            echo "</ul>";
            
            $voteCount = (int)$testTx['vote_count'];
            $individualAmount = $testTx['amount'] / $voteCount;
            
            echo "<p><strong>Vote Creation Logic:</strong></p>";
            echo "<ul>";
            echo "<li>Vote Count: $voteCount</li>";
            echo "<li>Total Amount: GH₵{$testTx['amount']}</li>";
            echo "<li>Individual Vote Amount: GH₵$individualAmount</li>";
            echo "</ul>";
            
            echo "<p style='color: blue;'>✅ Logic appears correct. The issue might be in the callback execution.</p>";
        } else {
            echo "<p style='color: red;'>❌ Could not find nominee data for ID: {$testTx['nominee_id']}</p>";
        }
    }
    
    echo "<h3>5. Recommendations:</h3>";
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
    echo "<h4>To Fix the Vote Count Issue:</h4>";
    echo "<ol>";
    echo "<li><strong>Check Callback Execution:</strong> Ensure the webhook is being called by Hubtel</li>";
    echo "<li><strong>Verify Transaction Status:</strong> Make sure transactions are marked as 'completed'</li>";
    echo "<li><strong>Test Callback Manually:</strong> Use the test-callback-webhook.php to simulate payments</li>";
    echo "<li><strong>Check Error Logs:</strong> Look for PHP errors in the callback execution</li>";
    echo "<li><strong>Database Constraints:</strong> Ensure no database constraints are preventing vote creation</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='test-callback-webhook.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🧪 Test Callback</a>";
echo "<a href='voter/actions/hubtel-vote-submit.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>💳 Test Vote Submission</a>";
echo "</div>";
?>
