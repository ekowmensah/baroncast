<?php
/**
 * Test Different Authentication Methods for PayProxy API
 * Try various auth approaches to find the correct one
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for proper database connection
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>🔐 PayProxy API Authentication Testing</h2>";
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
    
    echo "<h3>1. TESTING DIFFERENT AUTHENTICATION METHODS:</h3>";
    
    $testData = [
        'CustomerName' => 'Test User',
        'CustomerMsisdn' => '233241234567',
        'CustomerEmail' => 'test@example.com',
        'Amount' => 1.00,
        'PrimaryCallbackUrl' => 'https://gs-callback.hubtel.com/callback',
        'Description' => 'Auth test',
        'ClientReference' => 'AUTH_TEST_' . time()
    ];
    
    $authMethods = [
        'Basic Auth (Current)' => [
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret)
            ]
        ],
        'API Key Header' => [
            'headers' => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
                'X-API-Secret: ' . $apiSecret
            ]
        ],
        'Bearer Token' => [
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]
        ],
        'Hubtel-Auth Header' => [
            'headers' => [
                'Content-Type: application/json',
                'Hubtel-ClientId: ' . $apiKey,
                'Hubtel-ClientSecret: ' . $apiSecret
            ]
        ],
        'POS-ID in Header' => [
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                'X-POS-ID: ' . $posId
            ]
        ],
        'Form Data (not JSON)' => [
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret)
            ],
            'data_format' => 'form'
        ]
    ];
    
    foreach ($authMethods as $methodName => $config) {
        echo "<strong>Testing: $methodName</strong><br>";
        
        $result = testPayProxyAuth('https://payproxyapi.hubtel.com/items/initiate', $testData, $config);
        
        echo "Result: " . getAuthResultMessage($result) . "<br>";
        
        if ($result['http_code'] !== 401 && $result['http_code'] !== 404) {
            echo "🎉 <strong>POTENTIAL SUCCESS!</strong> HTTP {$result['http_code']}<br>";
            if ($result['response']) {
                echo "Response preview: " . substr($result['response'], 0, 200) . "...<br>";
            }
        }
        
        echo "<br>";
    }
    
    echo "<h3>2. TESTING ALTERNATIVE PAYPROXY STRUCTURES:</h3>";
    
    // Test with POS ID in URL
    $alternativeUrls = [
        "https://payproxyapi.hubtel.com/merchants/$posId/items/initiate",
        "https://payproxyapi.hubtel.com/v1/items/initiate",
        "https://payproxyapi.hubtel.com/api/items/initiate",
        "https://payproxyapi.hubtel.com/payment/initiate",
        "https://payproxyapi.hubtel.com/ussd/initiate"
    ];
    
    foreach ($alternativeUrls as $url) {
        echo "<strong>Testing URL: $url</strong><br>";
        
        $result = testPayProxyAuth($url, $testData, $authMethods['Basic Auth (Current)']);
        echo "Result: " . getAuthResultMessage($result) . "<br>";
        
        if ($result['http_code'] !== 401 && $result['http_code'] !== 404) {
            echo "🎉 <strong>WORKING URL FOUND!</strong><br>";
            if ($result['response']) {
                echo "Response: " . substr($result['response'], 0, 300) . "<br>";
            }
        }
        
        echo "<br>";
    }
    
    echo "<h3>3. RECOMMENDATIONS:</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    
    $foundWorking = false;
    // Check if any method worked (you'd implement this logic based on results)
    
    if (!$foundWorking) {
        echo "<h4>🔧 Next Steps:</h4>";
        echo "<ol>";
        echo "<li><strong>Contact Hubtel Support:</strong> Ask for PayProxy API documentation and correct authentication method</li>";
        echo "<li><strong>Request API Access:</strong> PayProxy API may require special activation</li>";
        echo "<li><strong>Check Church System:</strong> Review the exact authentication method used in the working church management system</li>";
        echo "<li><strong>Alternative Solution:</strong> Use the working Direct Receive Money API with payment links instead of USSD codes</li>";
        echo "</ol>";
        
        echo "<h4>💡 Immediate Workaround:</h4>";
        echo "<p>Since the Direct Receive Money API works (from your existing system), we can:</p>";
        echo "<ul>";
        echo "<li>Generate payment links instead of USSD codes</li>";
        echo "<li>Send SMS with payment instructions</li>";
        echo "<li>Create QR codes for mobile money apps</li>";
        echo "</ul>";
    }
    
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Auth Testing Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

function testPayProxyAuth($url, $data, $config) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $config['headers']);
    curl_setopt($ch, CURLOPT_POST, true);
    
    if (isset($config['data_format']) && $config['data_format'] === 'form') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } else {
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

function getAuthResultMessage($result) {
    if ($result['success']) {
        return "✅ Success (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 401) {
        return "❌ Unauthorized - Wrong auth method (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 403) {
        return "❌ Forbidden - No permission (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 404) {
        return "❌ Not Found - Wrong URL (HTTP {$result['http_code']})";
    } elseif ($result['http_code'] == 400) {
        return "⚠️  Bad Request - Wrong data format (HTTP {$result['http_code']})";
    } else {
        return "❓ HTTP {$result['http_code']} - " . ($result['error'] ?: 'Unknown');
    }
}
?>
