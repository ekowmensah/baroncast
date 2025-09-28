<?php
/**
 * Test PayProxy Speed & Performance
 * Diagnose slow payment processing issues
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>⚡ PayProxy Speed Test</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Test basic connectivity first
echo "<h3>1. BASIC CONNECTIVITY TEST:</h3>";

$startTime = microtime(true);

$ch = curl_init('https://payproxyapi.hubtel.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
$connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
$error = curl_error($ch);
curl_close($ch);

$endTime = microtime(true);
$phpTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

echo "<strong>PayProxy Base URL Test:</strong><br>";
echo "HTTP Code: $httpCode<br>";
echo "Connect Time: " . ($connectTime * 1000) . " ms<br>";
echo "Total Time: " . ($totalTime * 1000) . " ms<br>";
echo "PHP Execution Time: " . number_format($phpTime, 2) . " ms<br>";
if ($error) {
    echo "Error: $error<br>";
}
echo "<br>";

// Test the actual PayProxy service
echo "<h3>2. PAYPROXY SERVICE TEST:</h3>";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/HubtelPayProxyService.php';

try {
    $startTime = microtime(true);
    
    $payProxy = new HubtelPayProxyService();
    
    // Test data
    $testParams = [
        'amount' => 1.00,
        'description' => 'Speed test payment',
        'clientReference' => 'SPEED_TEST_' . time(),
        'customerName' => 'Speed Test User',
        'customerPhone' => '233241234567',
        'customerEmail' => 'test@example.com'
    ];
    
    echo "<strong>Testing PayProxy checkout creation...</strong><br>";
    echo "Start Time: " . date('H:i:s.') . substr(microtime(), 2, 3) . "<br>";
    
    $result = $payProxy->generateVotingPayment(
        $testParams['amount'],
        $testParams['customerPhone'],
        $testParams['description'],
        $testParams['clientReference'],
        [
            'voter_name' => $testParams['customerName'],
            'email' => $testParams['customerEmail']
        ]
    );
    
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "End Time: " . date('H:i:s.') . substr(microtime(), 2, 3) . "<br>";
    echo "<strong>Total Execution Time: " . number_format($executionTime, 2) . " ms</strong><br><br>";
    
    if ($result['success']) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>PayProxy Request SUCCESSFUL!</strong><br>";
        echo "Checkout URL: " . substr($result['checkout_url'], 0, 50) . "...<br>";
        echo "Checkout ID: {$result['checkout_id']}<br>";
        echo "Execution Time: " . number_format($executionTime, 2) . " ms<br>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "❌ <strong>PayProxy Request FAILED!</strong><br>";
        echo "Error: {$result['message']}<br>";
        echo "Execution Time: " . number_format($executionTime, 2) . " ms<br>";
        if (isset($result['debug'])) {
            echo "<details><summary>Debug Info</summary>";
            echo "<pre>" . json_encode($result['debug'], JSON_PRETTY_PRINT) . "</pre>";
            echo "</details>";
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Exception occurred!</strong><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Execution Time: " . number_format($executionTime, 2) . " ms<br>";
    echo "</div>";
}

echo "<br><h3>3. PERFORMANCE ANALYSIS:</h3>";

if (isset($executionTime)) {
    if ($executionTime < 2000) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "🚀 <strong>FAST:</strong> Payment processing is quick (" . number_format($executionTime, 2) . " ms)";
        echo "</div>";
    } elseif ($executionTime < 5000) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ <strong>MODERATE:</strong> Payment processing is acceptable (" . number_format($executionTime, 2) . " ms)";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "🐌 <strong>SLOW:</strong> Payment processing is too slow (" . number_format($executionTime, 2) . " ms)";
        echo "<br><br><strong>Possible causes:</strong>";
        echo "<ul>";
        echo "<li>Network connectivity issues</li>";
        echo "<li>Hubtel API server response time</li>";
        echo "<li>Database connection delays</li>";
        echo "<li>Server resource constraints</li>";
        echo "</ul>";
        echo "</div>";
    }
}

echo "<br><h3>4. OPTIMIZATION RECOMMENDATIONS:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>🔧 If Payment Processing is Slow:</h4>";
echo "<ol>";
echo "<li><strong>Reduce timeout:</strong> Already optimized to 15 seconds</li>";
echo "<li><strong>Add loading indicators:</strong> Show progress to users</li>";
echo "<li><strong>Async processing:</strong> Process payment in background</li>";
echo "<li><strong>Cache credentials:</strong> Avoid repeated database queries</li>";
echo "<li><strong>Error handling:</strong> Fail fast on network issues</li>";
echo "</ol>";
echo "</div>";

echo "<br><h3>5. QUICK FIXES:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h4>💡 Immediate Improvements:</h4>";
echo "<ul>";
echo "<li><strong>Frontend:</strong> Add better loading animation</li>";
echo "<li><strong>Timeout:</strong> Reduced from 30s to 15s</li>";
echo "<li><strong>Connection:</strong> Added 10s connection timeout</li>";
echo "<li><strong>User feedback:</strong> Show progress messages</li>";
echo "</ul>";
echo "</div>";

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='voter/vote-form.php?event_id=1&nominee_id=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗳️ Test Real Vote Form</a>";
echo "</div>";
?>
