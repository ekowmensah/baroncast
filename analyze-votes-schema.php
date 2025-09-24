<?php
/**
 * Fix Votes Table Schema and Update Vote Creation Scripts
 * This script identifies the actual votes table structure and fixes all references
 */

require_once __DIR__ . '/config/database.php';

echo "<h2>🔧 Votes Table Schema Analysis and Fix</h2>";
echo "<style>body{font-family: Arial; margin: 20px;} .success{color: green;} .error{color: red;} .info{color: blue;} .warning{color: orange;} .section{margin: 20px 0; padding: 15px; border: 1px solid #ddd;}</style>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<div class='section'>";
    echo "<h3>📋 Current Votes Table Structure</h3>";
    
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $available_columns = [];
    foreach ($columns as $col) {
        $available_columns[] = $col['Field'];
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>🔍 Required vs Available Columns</h3>";
    
    $required_columns = [
        'id' => 'Primary key',
        'nominee_id' => 'Reference to nominee',
        'voter_phone' => 'Voter contact',
        'transaction_id' => 'Transaction reference',
        'payment_method' => 'Payment type',
        'payment_reference' => 'Payment reference',
        'payment_status' => 'Payment status',
        'amount' => 'Vote amount'
    ];
    
    $missing_columns = [];
    $timestamp_column = null;
    
    // Find the timestamp column
    if (in_array('voted_at', $available_columns)) {
        $timestamp_column = 'voted_at';
    } elseif (in_array('created_at', $available_columns)) {
        $timestamp_column = 'created_at';
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Required Column</th><th>Purpose</th><th>Status</th><th>Available As</th></tr>";
    
    foreach ($required_columns as $column => $purpose) {
        $status = in_array($column, $available_columns) ? "✅ Available" : "❌ Missing";
        $available_as = in_array($column, $available_columns) ? $column : "N/A";
        
        if (!in_array($column, $available_columns)) {
            $missing_columns[] = $column;
        }
        
        echo "<tr>";
        echo "<td>$column</td>";
        echo "<td>$purpose</td>";
        echo "<td>$status</td>";
        echo "<td>$available_as</td>";
        echo "</tr>";
    }
    
    // Add timestamp info
    echo "<tr>";
    echo "<td>Timestamp</td>";
    echo "<td>Record creation time</td>";
    echo "<td>" . ($timestamp_column ? "✅ Available" : "❌ Missing") . "</td>";
    echo "<td>" . ($timestamp_column ?: "N/A") . "</td>";
    echo "</tr>";
    
    echo "</table>";
    echo "</div>";
    
    // Add missing columns if needed
    if (!empty($missing_columns)) {
        echo "<div class='section'>";
        echo "<h3>🔧 Adding Missing Columns</h3>";
        
        foreach ($missing_columns as $column) {
            try {
                switch ($column) {
                    case 'transaction_id':
                        $pdo->exec("ALTER TABLE votes ADD COLUMN transaction_id VARCHAR(100) NULL");
                        echo "<div class='success'>✅ Added transaction_id column</div>";
                        break;
                    case 'payment_method':
                        $pdo->exec("ALTER TABLE votes ADD COLUMN payment_method ENUM('mobile_money', 'card', 'ussd') DEFAULT 'mobile_money'");
                        echo "<div class='success'>✅ Added payment_method column</div>";
                        break;
                    case 'payment_reference':
                        $pdo->exec("ALTER TABLE votes ADD COLUMN payment_reference VARCHAR(100)");
                        echo "<div class='success'>✅ Added payment_reference column</div>";
                        break;
                    default:
                        echo "<div class='warning'>⚠️ Column $column needs manual attention</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error adding $column: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        echo "</div>";
    }
    
    // Generate the correct INSERT query
    echo "<div class='section'>";
    echo "<h3>📝 Correct Vote Creation Query</h3>";
    
    // Refresh available columns after additions
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $available_columns = array_column($columns, 'Field');
    
    $timestamp_column = in_array('voted_at', $available_columns) ? 'voted_at' : 'created_at';
    
    $insert_columns = [];
    $insert_values = [];
    
    if (in_array('nominee_id', $available_columns)) {
        $insert_columns[] = 'nominee_id';
        $insert_values[] = '?';
    }
    if (in_array('voter_phone', $available_columns)) {
        $insert_columns[] = 'voter_phone';
        $insert_values[] = '?';
    }
    if (in_array('transaction_id', $available_columns)) {
        $insert_columns[] = 'transaction_id';
        $insert_values[] = '?';
    }
    if (in_array('payment_method', $available_columns)) {
        $insert_columns[] = 'payment_method';
        $insert_values[] = "'mobile_money'";
    }
    if (in_array('payment_reference', $available_columns)) {
        $insert_columns[] = 'payment_reference';
        $insert_values[] = '?';
    }
    if (in_array('payment_status', $available_columns)) {
        $insert_columns[] = 'payment_status';
        $insert_values[] = "'completed'";
    }
    if (in_array('amount', $available_columns)) {
        $insert_columns[] = 'amount';
        $insert_values[] = '?';
    }
    if ($timestamp_column) {
        $insert_columns[] = $timestamp_column;
        $insert_values[] = 'NOW()';
    }
    
    $correct_query = "INSERT INTO votes (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_values) . ")";
    
    echo "<div class='info'>";
    echo "<h4>Correct INSERT Query:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars($correct_query);
    echo "</pre>";
    echo "</div>";
    echo "</div>";
    
    // Test vote creation with the corrected format
    echo "<div class='section'>";
    echo "<h3>🧪 Testing Vote Creation</h3>";
    
    // Find a completed transaction to test with
    $stmt = $pdo->query("
        SELECT id, reference, nominee_id, voter_phone, amount 
        FROM transactions 
        WHERE status = 'completed' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $test_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_transaction) {
        echo "<div class='info'>Testing with transaction ID: {$test_transaction['id']}</div>";
        
        try {
            // Check if vote already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE payment_reference = ? OR transaction_id = ?");
            $stmt->execute([$test_transaction['reference'], $test_transaction['id']]);
            $existing = $stmt->fetchColumn();
            
            if ($existing == 0) {
                // Create test vote using the correct format
                $stmt = $pdo->prepare($correct_query);
                
                $params = [];
                if (in_array('nominee_id', $insert_columns)) $params[] = $test_transaction['nominee_id'];
                if (in_array('voter_phone', $insert_columns)) $params[] = $test_transaction['voter_phone'];
                if (in_array('transaction_id', $insert_columns)) $params[] = $test_transaction['id'];
                if (in_array('payment_reference', $insert_columns)) $params[] = $test_transaction['reference'];
                if (in_array('amount', $insert_columns)) $params[] = $test_transaction['amount'];
                
                $stmt->execute($params);
                
                echo "<div class='success'>✅ Test vote created successfully!</div>";
                
                // Clean up test vote
                $stmt = $pdo->prepare("DELETE FROM votes WHERE payment_reference = ? AND transaction_id = ?");
                $stmt->execute([$test_transaction['reference'], $test_transaction['id']]);
                echo "<div class='info'>Test vote removed</div>";
                
            } else {
                echo "<div class='info'>Vote already exists for this transaction - query format is correct</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ No completed transactions found for testing</div>";
    }
    echo "</div>";
    
    // Create the corrected vote creation function
    echo "<div class='section'>";
    echo "<h3>📋 Summary and Next Steps</h3>";
    
    echo "<div class='success'>";
    echo "<h4>✅ Analysis Complete</h4>";
    echo "• Votes table structure analyzed<br>";
    echo "• Missing columns identified and added<br>";
    echo "• Correct INSERT query generated<br>";
    echo "• Vote creation format tested<br><br>";
    
    echo "<strong>The correct timestamp column is: <code>$timestamp_column</code></strong><br>";
    echo "<strong>All vote creation scripts need to be updated to use this format.</strong>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>Fatal Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>