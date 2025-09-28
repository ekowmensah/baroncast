<?php
/**
 * Debug Direct Mobile Money API Issues
 * Test Hubtel Direct Receive Money API connectivity and authentication
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>📱 Debug Direct Mobile Money API</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Test 1: Check Hubtel settings
echo "<h3>1. HUBTEL SETTINGS CHECK:</h3>";

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo "<strong>Hubtel Configuration:</strong><br>";
    echo "API Key: " . (isset($settings['hubtel_api_key']) && !empty($settings['hubtel_api_key']) ? 'Set (' . substr($settings['hubtel_api_key'], 0, 8) . '...)' : 'Not Set') . "<br>";
    echo "API Secret: " . (isset($settings['hubtel_api_secret']) && !empty($settings['hubtel_api_secret']) ? 'Set (' . substr($settings['hubtel_api_secret'], 0, 8) . '...)' : 'Not Set') . "<br>";
    echo "POS ID: " . ($settings['hubtel_pos_id'] ?? 'Not Set') . "<br>";
    echo "Payments Enabled: " . ($settings['enable_hubtel_payments'] ?? 'Not Set') . "<br>";
    
    if (empty($settings['hubtel_api_key']) || empty($settings['hubtel_api_secret']) || empty($settings['hubtel_pos_id'])) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>MISSING CREDENTIALS:</strong> Some Hubtel credentials are not configured.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ Database Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br>";

// Test 2: Test API connectivity
echo "<h3>2. HUBTEL API CONNECTIVITY TEST:</h3>";

$apiKey = $settings['hubtel_api_key'] ?? '';
$apiSecret = $settings['hubtel_api_secret'] ?? '';
$posId = $settings['hubtel_pos_id'] ?? '';

if ($apiKey && $apiSecret && $posId) {
    $testUrl = "https://rmp.hubtel.com/merchantaccount/merchants/{$posId}/receive/mobilemoney";
    
    echo "<strong>Testing API Endpoint:</strong><br>";
    echo "URL: $testUrl<br>";
    
    $testData = [
        'CustomerName' => 'Test User',
        'CustomerMsisdn' => '233241234567',
        'CustomerEmail' => 'test@example.com',
        'Channel' => 'mtn-gh',
        'Amount' => 1.00,
        'PrimaryCallbackUrl' => 'https://example.com/callback',
        'Description' => 'API Test',
        'ClientReference' => 'TEST_' . time()
    ];
    
    $auth = base64_encode($apiKey . ':' . $apiSecret);
    
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($testData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json',
            'Cache-Control: no-cache'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $end = microtime(true);
    
    $executionTime = ($end - $start) * 1000;
    
    echo "<strong>API Test Results:</strong><br>";
    echo "HTTP Code: $httpCode<br>";
    echo "Execution Time: " . number_format($executionTime, 2) . " ms<br>";
    if ($error) {
        echo "cURL Error: $error<br>";
    }
    
    if ($response) {
        $json = json_decode($response, true);
        if ($json) {
            echo "Response Code: " . ($json['ResponseCode'] ?? 'N/A') . "<br>";
            echo "Message: " . ($json['Message'] ?? 'N/A') . "<br>";
            
            if (isset($json['Data'])) {
                echo "Transaction ID: " . ($json['Data']['TransactionId'] ?? 'N/A') . "<br>";
            }
        }
        
        echo "<details><summary>Full Response</summary>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "</details>";
    }
    
    // Analyze the response
    if ($httpCode == 401) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>AUTHENTICATION FAILED (401):</strong><br>";
        echo "- Check if API Key and Secret are correct<br>";
        echo "- Verify credentials are properly encoded<br>";
        echo "- Ensure account has mobile money permissions<br>";
        echo "</div>";
    } elseif ($httpCode == 403) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>ACCESS DENIED (403):</strong><br>";
        echo "- Account may not have mobile money service activated<br>";
        echo "- Contact Hubtel to enable mobile money for your account<br>";
        echo "- POS ID may be incorrect<br>";
        echo "</div>";
    } elseif ($httpCode == 404) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ <strong>SERVICE NOT FOUND (404):</strong><br>";
        echo "- Mobile money service may not be available<br>";
        echo "- Check if POS ID is correct<br>";
        echo "- Verify API endpoint URL<br>";
        echo "</div>";
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ <strong>API CONNECTION SUCCESSFUL!</strong><br>";
        echo "The direct mobile money API is working correctly.";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>UNEXPECTED RESPONSE (HTTP $httpCode):</strong><br>";
        echo "Check the response details above for more information.";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ <strong>Cannot test API:</strong> Missing credentials (API Key, Secret, or POS ID)";
    echo "</div>";
}

echo "<br>";

// Test 3: Service initialization
echo "<h3>3. SERVICE INITIALIZATION TEST:</h3>";

try {
    require_once __DIR__ . '/services/HubtelReceiveMoneyService.php';
    
    $start = microtime(true);
    $service = new HubtelReceiveMoneyService();
    $end = microtime(true);
    
    $initTime = ($end - $start) * 1000;
    
    echo "<strong>Service Initialization:</strong><br>";
    echo "Time: " . number_format($initTime, 2) . " ms<br>";
    
    if ($initTime > 2000) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ <strong>SLOW INITIALIZATION:</strong> Service is slow to initialize (" . number_format($initTime, 2) . " ms)";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✅ <strong>Service initialized successfully</strong> (" . number_format($initTime, 2) . " ms)";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ Service Error: " . $e->getMessage();
    echo "</div>";
}

echo "<br>";

// Recommendations
echo "<h3>4. TROUBLESHOOTING GUIDE:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>🔧 Common Issues & Solutions:</h4>";
echo "<ol>";
echo "<li><strong>401 Unauthorized:</strong> Check API credentials in system settings</li>";
echo "<li><strong>403 Forbidden:</strong> Contact Hubtel to activate mobile money service</li>";
echo "<li><strong>404 Not Found:</strong> Verify POS ID and API endpoint</li>";
echo "<li><strong>Network Errors:</strong> Check internet connection and firewall</li>";
echo "<li><strong>Slow Response:</strong> Hubtel server may be experiencing delays</li>";
echo "</ol>";
echo "</div>";

echo "<br>";

echo "<h3>5. REFERENCE FORMAT UPDATE:</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h4>✅ Transaction Reference Format Updated:</h4>";
echo "<ul>";
echo "<li><strong>Old Format:</strong> ECAST_timestamp_random</li>";
echo "<li><strong>New Format:</strong> EVENTABBR + NOMINEEABBR_MDHI_random</li>";
echo "<li><strong>Example:</strong> AWARGALA_1228_456 (Awards Gala for nominee starting with 'GALA')</li>";
echo "</ul>";
echo "<p><strong>Both PayProxy and Direct Mobile Money now use the same reference format!</strong></p>";
echo "</div>";

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='voter/vote-form.php?event_id=1&nominee_id=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗳️ Test Vote Form</a>";
echo "</div>";
?>
