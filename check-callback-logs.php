<?php
/**
 * Check Callback Logs
 * View recent callback activity and debug issues
 */

echo "<h2>📋 Callback Logs & Debug</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Check if logs directory exists
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Logs directory not found:</strong> $logsDir<br>";
    echo "Creating logs directory...";
    mkdir($logsDir, 0777, true);
    echo " ✅ Created<br>";
    echo "</div><br>";
}

echo "<h3>1. CALLBACK LOG FILES:</h3>";

$logFiles = [
    'hubtel-callback.log' => 'General Hubtel callbacks',
    'payproxy-vote-submission.log' => 'PayProxy vote submissions',
    'ussd-vote-submission.log' => 'USSD vote submissions',
    'vote-submission.log' => 'General vote submissions'
];

foreach ($logFiles as $filename => $description) {
    $filepath = $logsDir . '/' . $filename;
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        $modified = date('Y-m-d H:i:s', filemtime($filepath));
        echo "✅ <strong>$filename</strong> - $description<br>";
        echo "   Size: " . number_format($size) . " bytes, Modified: $modified<br><br>";
    } else {
        echo "❌ <strong>$filename</strong> - Not found<br><br>";
    }
}

echo "<h3>2. RECENT CALLBACK ACTIVITY:</h3>";

$callbackLogFile = $logsDir . '/hubtel-callback.log';
if (file_exists($callbackLogFile)) {
    $lines = file($callbackLogFile);
    $recentLines = array_slice($lines, -20); // Last 20 lines
    
    if (count($recentLines) > 0) {
        echo "<strong>Last 20 log entries:</strong><br>";
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        foreach ($recentLines as $line) {
            echo htmlspecialchars($line) . "<br>";
        }
        echo "</div><br>";
    } else {
        echo "No callback activity found.<br><br>";
    }
} else {
    echo "No callback log file found. This means no callbacks have been received yet.<br><br>";
}

echo "<h3>3. RECENT TRANSACTIONS:</h3>";

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get recent transactions
    $stmt = $pdo->query("
        SELECT t.*, n.name as nominee_name, e.title as event_title
        FROM transactions t
        LEFT JOIN nominees n ON t.nominee_id = n.id
        LEFT JOIN events e ON t.event_id = e.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($transactions) > 0) {
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background: #e9ecef;'>";
        echo "<th style='border: 1px solid #ccc; padding: 8px;'>Reference</th>";
        echo "<th style='border: 1px solid #ccc; padding: 8px;'>Status</th>";
        echo "<th style='border: 1px solid #ccc; padding: 8px;'>Amount</th>";
        echo "<th style='border: 1px solid #ccc; padding: 8px;'>Votes</th>";
        echo "<th style='border: 1px solid #ccc; padding: 8px;'>Created</th>";
        echo "</tr>";
        
        foreach ($transactions as $tx) {
            // Count votes for this transaction
            $stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE payment_reference = ?");
            $stmt->execute([$tx['reference']]);
            $voteCount = $stmt->fetch()['vote_count'];
            
            $statusColor = $tx['status'] === 'completed' ? '#28a745' : ($tx['status'] === 'failed' ? '#dc3545' : '#ffc107');
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ccc; padding: 8px;'>{$tx['reference']}</td>";
            echo "<td style='border: 1px solid #ccc; padding: 8px; color: $statusColor; font-weight: bold;'>{$tx['status']}</td>";
            echo "<td style='border: 1px solid #ccc; padding: 8px;'>₵{$tx['amount']}</td>";
            echo "<td style='border: 1px solid #ccc; padding: 8px;'>$voteCount</td>";
            echo "<td style='border: 1px solid #ccc; padding: 8px;'>{$tx['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "No transactions found.<br><br>";
    }
    
    echo "<h3>4. CALLBACK URL STATUS:</h3>";
    
    // Get proper hosted callback URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine base URL based on environment
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $baseUrl = $protocol . $host . '/baroncast';
    } else {
        $baseUrl = $protocol . $host;
    }
    
    $callbackUrl = $baseUrl . '/webhooks/hubtel-checkout-callback.php';
    echo "<strong>Callback URL:</strong> $callbackUrl<br>";
    
    // Test if callback URL is accessible
    $ch = curl_init($callbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "✅ <strong>Callback URL is accessible</strong> (HTTP $httpCode)<br>";
    } else {
        echo "❌ <strong>Callback URL issue</strong> (HTTP $httpCode)<br>";
        if ($error) {
            echo "Error: $error<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Database Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><h3>5. DEBUGGING CHECKLIST:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>🔍 Common Issues & Solutions:</h4>";
echo "<ol>";
echo "<li><strong>No callbacks received:</strong> Check if PayProxy is using correct callback URL</li>";
echo "<li><strong>Callbacks received but votes not created:</strong> Check webhook processing logic</li>";
echo "<li><strong>Transaction status not updating:</strong> Check database connection in webhook</li>";
echo "<li><strong>Webhook errors:</strong> Check PHP error logs and webhook response</li>";
echo "</ol>";
echo "</div>";

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='test-callback-webhook.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Test Callback Manually</a>";
echo "</div>";
?>
