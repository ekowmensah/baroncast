<?php
/**
 * Check Payment Callback Logs
 */

echo "<h2>Hubtel Payment Callback Logs</h2>";

$logFile = __DIR__ . '/logs/hubtel-callback.log';

if (file_exists($logFile)) {
    echo "<h3>✅ Log file exists: $logFile</h3>";
    
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    
    echo "<p><strong>Total log lines:</strong> " . count($lines) . "</p>";
    echo "<p><strong>File size:</strong> " . filesize($logFile) . " bytes</p>";
    echo "<p><strong>Last modified:</strong> " . date('Y-m-d H:i:s', filemtime($logFile)) . "</p>";
    
    // Show recent logs
    echo "<h3>Recent Logs (last 50 lines):</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: scroll;'>";
    
    $recentLines = array_slice($lines, -50);
    foreach ($recentLines as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
    
    // Check for recent activity
    $now = time();
    $fileAge = $now - filemtime($logFile);
    
    if ($fileAge < 3600) { // Less than 1 hour
        echo "<p style='color: green;'>✅ Log file was updated recently (" . round($fileAge/60) . " minutes ago)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Log file is old (" . round($fileAge/3600, 1) . " hours ago)</p>";
    }
    
} else {
    echo "<h3>❌ Log file does not exist: $logFile</h3>";
    echo "<p>This means the payment callback endpoint has never been called.</p>";
    
    // Check if logs directory exists
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        echo "<p style='color: red;'>❌ Logs directory doesn't exist: $logsDir</p>";
        echo "<p>Creating logs directory...</p>";
        if (mkdir($logsDir, 0755, true)) {
            echo "<p style='color: green;'>✅ Created logs directory</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create logs directory</p>";
        }
    } else {
        echo "<p>✅ Logs directory exists: $logsDir</p>";
    }
}

// Test the payment callback endpoint
echo "<h3>Testing Payment Callback Endpoint</h3>";
$callbackUrl = 'https://baroncast.online/webhooks/hubtel-receive-money-callback.php';
echo "<p><strong>Callback URL:</strong> <a href='$callbackUrl' target='_blank'>$callbackUrl</a></p>";

// Check if endpoint is accessible
$headers = @get_headers($callbackUrl);
if ($headers) {
    echo "<p style='color: green;'>✅ Endpoint is accessible</p>";
    echo "<p>Response: " . $headers[0] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Endpoint is not accessible</p>";
}

echo "<h3>Next Steps</h3>";
echo "<ul>";
echo "<li>Make a test USSD payment</li>";
echo "<li>Check if this log file gets updated</li>";
echo "<li>If no logs appear, the payment callbacks are not reaching this endpoint</li>";
echo "</ul>";
?>
