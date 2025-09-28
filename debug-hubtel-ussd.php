<?php
/**
 * Debug Hubtel USSD API Capabilities
 * Check what USSD services are available with current credentials
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for proper database connection
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>🔍 Hubtel USSD API Debug</h2>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";
    
    // Get Hubtel settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $posId = $settings['hubtel_pos_id'] ?? '';
    $apiKey = $settings['hubtel_api_key'] ?? '';
    $apiSecret = $settings['hubtel_api_secret'] ?? '';
    $environment = $settings['hubtel_environment'] ?? 'sandbox';
    
    echo "<h3>1. CURRENT HUBTEL CONFIGURATION:</h3>";
    echo "POS ID: $posId<br>";
    echo "API Key: " . str_repeat('*', strlen($apiKey)) . " (" . strlen($apiKey) . " chars)<br>";
    echo "Environment: $environment<br>";
    echo "Base URL: " . ($environment === 'production' ? 'https://rmp.hubtel.com' : 'https://sandbox.hubtel.com') . "<br><br>";
    
    $baseUrl = $environment === 'production' ? 'https://rmp.hubtel.com' : 'https://sandbox.hubtel.com';
    
    echo "<h3>2. TESTING HUBTEL API ENDPOINTS:</h3>";
    
    // Test 1: Basic connectivity
    echo "<strong>Test 1: Basic API Connectivity</strong><br>";
    $testUrl = $baseUrl;
    $result = testEndpoint($testUrl, 'GET', null, $apiKey, $apiSecret);
    echo "URL: $testUrl<br>";
    echo "Result: " . ($result['success'] ? "✅ Connected (HTTP {$result['http_code']})" : "❌ Failed (HTTP {$result['http_code']})") . "<br>";
    if (!$result['success']) {
        echo "Error: {$result['error']}<br>";
    }
    echo "<br>";
    
    // Test 2: Account info endpoint
    echo "<strong>Test 2: Account Information</strong><br>";
    $testUrl = "$baseUrl/merchantaccount/merchants/$posId";
    $result = testEndpoint($testUrl, 'GET', null, $apiKey, $apiSecret);
    echo "URL: $testUrl<br>";
    echo "Result: " . ($result['success'] ? "✅ Account accessible (HTTP {$result['http_code']})" : "❌ Account not accessible (HTTP {$result['http_code']})") . "<br>";
    if ($result['response']) {
        echo "Response preview: " . substr($result['response'], 0, 200) . "...<br>";
    }
    echo "<br>";
    
    // Test 3: Direct Receive Money endpoint (known working)
    echo "<strong>Test 3: Direct Receive Money API</strong><br>";
    $testUrl = "$baseUrl/merchantaccount/merchants/$posId/receive/mobilemoney";
    $testData = [
        'CustomerName' => 'Test User',
        'CustomerMsisdn' => '233241234567',
        'CustomerEmail' => 'test@example.com',
        'Channel' => 'mtn-gh',
        'Amount' => 0.01,
        'PrimaryCallbackUrl' => 'https://example.com/callback',
        'Description' => 'API test',
        'ClientReference' => 'TEST_' . time()
    ];
    $result = testEndpoint($testUrl, 'POST', $testData, $apiKey, $apiSecret);
    echo "URL: $testUrl<br>";
    echo "Result: " . getResultMessage($result) . "<br>";
    if ($result['response']) {
        echo "Response preview: " . substr($result['response'], 0, 300) . "...<br>";
    }
    echo "<br>";
    
    // Test 4: USSD endpoint (the failing one)
    echo "<strong>Test 4: USSD Payment API</strong><br>";
    $testUrl = "$baseUrl/merchantaccount/merchants/$posId/receive/ussd";
    $testData = [
        'CustomerName' => 'Test User',
        'CustomerMsisdn' => '233241234567',
        'CustomerEmail' => 'test@example.com',
        'Channel' => 'ussd-gh',
        'Amount' => 0.01,
        'PrimaryCallbackUrl' => 'https://example.com/callback',
        'Description' => 'USSD API test',
        'ClientReference' => 'USSD_TEST_' . time()
    ];
    $result = testEndpoint($testUrl, 'POST', $testData, $apiKey, $apiSecret);
    echo "URL: $testUrl<br>";
    echo "Result: " . getResultMessage($result) . "<br>";
    if ($result['response']) {
        echo "Response: " . $result['response'] . "<br>";
    }
    echo "<br>";
    
    // Test 5: Alternative USSD endpoints
    echo "<strong>Test 5: Alternative USSD Endpoints</strong><br>";
    $alternativeEndpoints = [
        "$baseUrl/ussd/send",
        "$baseUrl/messaging/ussd/send",
        "$baseUrl/ussd/applications",
        "$baseUrl/merchantaccount/merchants/$posId/ussd"
    ];
    
    foreach ($alternativeEndpoints as $endpoint) {
        $result = testEndpoint($endpoint, 'GET', null, $apiKey, $apiSecret);
        echo "URL: $endpoint<br>";
        echo "Result: " . getResultMessage($result) . "<br><br>";
    }
    
    echo "</div>";
    
    echo "<h3>🎯 ANALYSIS & RECOMMENDATIONS:</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    // Analyze results and provide recommendations
    if ($result['http_code'] == 403) {
        echo "<h4>❌ Issue Identified: 403 Forbidden</h4>";
        echo "<p><strong>Problem:</strong> Your Hubtel account doesn't have permission for USSD services.</p>";
        echo "<h4>🔧 Solutions:</h4>";
        echo "<ol>";
        echo "<li><strong>Contact Hubtel Support:</strong> Request USSD service activation for your account (POS ID: $posId)</li>";
        echo "<li><strong>Alternative Approach:</strong> Use Hubtel's SMS API to send USSD-like instructions</li>";
        echo "<li><strong>Hybrid Solution:</strong> Generate payment links instead of USSD codes</li>";
        echo "<li><strong>Third-party USSD:</strong> Consider using a dedicated USSD provider like Arkesel</li>";
        echo "</ol>";
    } elseif ($result['http_code'] == 404) {
        echo "<h4>❌ Issue Identified: 404 Not Found</h4>";
        echo "<p><strong>Problem:</strong> The USSD endpoint doesn't exist in Hubtel's API.</p>";
        echo "<h4>🔧 Solutions:</h4>";
        echo "<ol>";
        echo "<li><strong>Check Hubtel Documentation:</strong> Verify the correct USSD API endpoints</li>";
        echo "<li><strong>Use Alternative Methods:</strong> Implement payment links or QR codes</li>";
        echo "<li><strong>Contact Hubtel:</strong> Ask about USSD API availability</li>";
        echo "</ol>";
    } else {
        echo "<h4>⚠️ Other Issues Detected</h4>";
        echo "<p>HTTP Code: {$result['http_code']}</p>";
        echo "<p>Check the test results above for specific error details.</p>";
    }
    
    echo "</div>";
    
    echo "<h3>🚀 IMMEDIATE WORKAROUNDS:</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Option 1: Payment Links (Recommended)</h4>";
    echo "<p>Instead of USSD codes, generate payment links that users can click to pay via mobile money.</p>";
    echo "<h4>Option 2: QR Code Payments</h4>";
    echo "<p>Generate QR codes that users can scan with their mobile money apps.</p>";
    echo "<h4>Option 3: SMS Instructions</h4>";
    echo "<p>Send SMS with payment instructions and reference numbers.</p>";
    echo "<h4>Option 4: Switch to Arkesel USSD</h4>";
    echo "<p>Use Arkesel's USSD service which was partially implemented in your system.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Debug Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

function testEndpoint($url, $method, $data, $apiKey, $apiSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
        'User-Agent: BaronCast-Debug/1.0'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 400 && !$error,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

function getResultMessage($result) {
    if ($result['success']) {
        return "✅ Success (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 403) {
        return "❌ Forbidden - No permission (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 404) {
        return "❌ Not Found - Endpoint doesn't exist (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 401) {
        return "❌ Unauthorized - Invalid credentials (HTTP {$result['http_code']})";
    } else {
        return "❌ Failed (HTTP {$result['http_code']})";
    }
}
?>
