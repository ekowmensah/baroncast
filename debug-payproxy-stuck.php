<?php
/**
 * Debug PayProxy Stuck Issue
 * Test PayProxy API directly to identify bottlenecks
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>🔍 Debug PayProxy Stuck Issue</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Test 1: Basic connectivity
echo "<h3>1. BASIC CONNECTIVITY TEST:</h3>";
$start = microtime(true);

$ch = curl_init('https://payproxyapi.hubtel.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
$connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000;
$error = curl_error($ch);
curl_close($ch);

$end = microtime(true);
$phpTime = ($end - $start) * 1000;

echo "<strong>PayProxy Base URL:</strong><br>";
echo "HTTP Code: $httpCode<br>";
echo "Connect Time: " . number_format($connectTime, 2) . " ms<br>";
echo "Total Time: " . number_format($totalTime, 2) . " ms<br>";
echo "PHP Time: " . number_format($phpTime, 2) . " ms<br>";
if ($error) echo "Error: $error<br>";

if ($totalTime > 5000) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ <strong>SLOW CONNECTION:</strong> PayProxy API is responding slowly (" . number_format($totalTime, 2) . " ms)";
    echo "</div>";
}

echo "<br>";

// Test 2: Database connection speed
echo "<h3>2. DATABASE CONNECTION TEST:</h3>";
$start = microtime(true);

try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
    $count = $stmt->fetchColumn();
    
    $end = microtime(true);
    $dbTime = ($end - $start) * 1000;
    
    echo "<strong>Database Connection:</strong><br>";
    echo "Time: " . number_format($dbTime, 2) . " ms<br>";
    echo "Hubtel Settings: $count found<br>";
    
    if ($dbTime > 1000) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>SLOW DATABASE:</strong> Database queries are slow (" . number_format($dbTime, 2) . " ms)";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ Database Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br>";

// Test 3: PayProxy service initialization
echo "<h3>3. PAYPROXY SERVICE INITIALIZATION:</h3>";
$start = microtime(true);

try {
    require_once __DIR__ . '/services/HubtelPayProxyService.php';
    $payProxy = new HubtelPayProxyService();
    
    $end = microtime(true);
    $initTime = ($end - $start) * 1000;
    
    echo "<strong>Service Initialization:</strong><br>";
    echo "Time: " . number_format($initTime, 2) . " ms<br>";
    
    if ($initTime > 2000) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>SLOW INITIALIZATION:</strong> PayProxy service is slow to initialize (" . number_format($initTime, 2) . " ms)";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ Service Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br>";

// Test 4: Minimal PayProxy API call
echo "<h3>4. MINIMAL PAYPROXY API CALL:</h3>";
$start = microtime(true);

try {
    $testData = [
        'totalAmount' => 1.00,
        'description' => 'Debug test',
        'clientReference' => 'DEBUG_' . time(),
        'merchantAccountNumber' => '2031233', // Your POS ID
        'callbackUrl' => 'https://example.com/callback',
        'returnUrl' => 'https://example.com/return'
    ];
    
    $ch = curl_init('https://payproxyapi.hubtel.com/items/initiate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode('your_api_key:your_api_secret') // Replace with actual
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
    $error = curl_error($ch);
    curl_close($ch);
    
    $end = microtime(true);
    $apiTime = ($end - $start) * 1000;
    
    echo "<strong>PayProxy API Call:</strong><br>";
    echo "HTTP Code: $httpCode<br>";
    echo "API Time: " . number_format($totalTime, 2) . " ms<br>";
    echo "PHP Time: " . number_format($apiTime, 2) . " ms<br>";
    if ($error) echo "Error: $error<br>";
    
    if ($response) {
        $json = json_decode($response, true);
        if ($json) {
            echo "Response Code: " . ($json['responseCode'] ?? 'N/A') . "<br>";
            echo "Status: " . ($json['status'] ?? 'N/A') . "<br>";
        }
    }
    
    if ($apiTime > 10000) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "🐌 <strong>VERY SLOW API:</strong> PayProxy API is taking too long (" . number_format($apiTime, 2) . " ms)";
        echo "</div>";
    } elseif ($apiTime > 5000) {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>SLOW API:</strong> PayProxy API is slow (" . number_format($apiTime, 2) . " ms)";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ API Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br>";

// Recommendations
echo "<h3>5. RECOMMENDATIONS:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>🔧 If Stuck on 'Preparing Payment Page':</h4>";
echo "<ol>";
echo "<li><strong>Check Network:</strong> Ensure stable internet connection</li>";
echo "<li><strong>Verify Credentials:</strong> Make sure Hubtel API credentials are correct</li>";
echo "<li><strong>Test Timeout:</strong> Reduce timeout to fail faster</li>";
echo "<li><strong>Add Fallback:</strong> Provide alternative payment method</li>";
echo "<li><strong>User Feedback:</strong> Show timeout message after 20 seconds</li>";
echo "</ol>";
echo "</div>";

echo "<br>";

echo "<h3>6. IMMEDIATE FIXES APPLIED:</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h4>✅ Improvements Made:</h4>";
echo "<ul>";
echo "<li><strong>Frontend Timeout:</strong> 20-second timeout with user feedback</li>";
echo "<li><strong>Backend Timeout:</strong> 15-second API timeout</li>";
echo "<li><strong>Error Handling:</strong> Better error messages and logging</li>";
echo "<li><strong>Progress Indicators:</strong> Step-by-step loading feedback</li>";
echo "<li><strong>Abort Controller:</strong> Can cancel stuck requests</li>";
echo "</ul>";
echo "</div>";

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='voter/vote-form.php?event_id=1&nominee_id=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗳️ Test Vote Form Now</a>";
echo "</div>";
?>
