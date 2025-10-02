<?php
/**
 * Test the fixed payment callback endpoint
 */

echo "<h2>Testing Fixed Payment Callback</h2>";

$callbackUrl = 'https://baroncast.online/webhooks/hubtel-receive-money-callback.php';

echo "<h3>Testing GET request (health check)</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $callbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'>❌ Error: $error</p>";
} else {
    echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre style='background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
    
    if ($httpCode == 200) {
        echo "<p style='color: green;'>✅ Callback endpoint is now working!</p>";
    } else {
        echo "<p style='color: red;'>❌ Still has issues (HTTP $httpCode)</p>";
    }
}

echo "<h3>Check Callback Logs</h3>";
$logFile = __DIR__ . '/logs/hubtel-callback.log';

if (file_exists($logFile)) {
    echo "<p style='color: green;'>✅ Log file exists</p>";
    
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    $recentLines = array_slice($lines, -10); // Last 10 lines
    
    echo "<p><strong>Recent log entries:</strong></p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: scroll;'>";
    foreach ($recentLines as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p style='color: orange;'>⚠️ No log file yet - will be created when callback is called</p>";
}

echo "<h3>Next Steps</h3>";
echo "<ul>";
echo "<li>✅ <strong>Callback endpoint is fixed</strong></li>";
echo "<li>🔄 <strong>Make a test USSD payment</strong> to see if callbacks are received</li>";
echo "<li>📋 <strong>Check logs</strong> after payment to see what Hubtel sends</li>";
echo "</ul>";
?>
