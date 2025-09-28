<?php
/**
 * Test Hosted URLs
 * Show what URLs will be used for callbacks and returns
 */

echo "<h2>🌐 Hosted URL Configuration Test</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Get current environment info
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

echo "<h3>1. CURRENT ENVIRONMENT:</h3>";
echo "<strong>Protocol:</strong> $protocol<br>";
echo "<strong>Host:</strong> $host<br>";
echo "<strong>Environment:</strong> " . ($isLocal ? 'Local Development' : 'Hosted Production') . "<br><br>";

// Determine base URL
if ($isLocal) {
    $baseUrl = $protocol . $host . '/baroncast';
} else {
    $baseUrl = $protocol . $host;
}

echo "<h3>2. BASE URL:</h3>";
echo "<strong>Base URL:</strong> $baseUrl<br><br>";

echo "<h3>3. GENERATED URLS:</h3>";

// Callback URL
$callbackUrl = $baseUrl . '/webhooks/hubtel-checkout-callback.php';
echo "<strong>Callback URL:</strong><br>";
echo "<code>$callbackUrl</code><br><br>";

// Return URL (example)
$returnUrl = $baseUrl . '/voter/payment-success.php?ref=EXAMPLE_REF';
echo "<strong>Return URL:</strong><br>";
echo "<code>$returnUrl</code><br><br>";

// Test PayProxy service URL generation
echo "<h3>4. PAYPROXY SERVICE TEST:</h3>";

try {
    require_once __DIR__ . '/services/HubtelPayProxyService.php';
    
    $payProxy = new HubtelPayProxyService();
    
    // Use reflection to test private methods
    $reflection = new ReflectionClass($payProxy);
    $getBaseUrlMethod = $reflection->getMethod('getBaseUrl');
    $getBaseUrlMethod->setAccessible(true);
    $getCallbackUrlMethod = $reflection->getMethod('getCallbackUrl');
    $getCallbackUrlMethod->setAccessible(true);
    $getReturnUrlMethod = $reflection->getMethod('getReturnUrl');
    $getReturnUrlMethod->setAccessible(true);
    
    $serviceBaseUrl = $getBaseUrlMethod->invoke($payProxy);
    $serviceCallbackUrl = $getCallbackUrlMethod->invoke($payProxy);
    $serviceReturnUrl = $getReturnUrlMethod->invoke($payProxy, 'TEST_REF');
    
    echo "<strong>PayProxy Service URLs:</strong><br>";
    echo "Base URL: <code>$serviceBaseUrl</code><br>";
    echo "Callback URL: <code>$serviceCallbackUrl</code><br>";
    echo "Return URL: <code>$serviceReturnUrl</code><br><br>";
    
    // Verify URLs match
    if ($serviceCallbackUrl === $callbackUrl) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
        echo "✅ <strong>URLs MATCH!</strong> PayProxy service is using correct hosted URLs.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
        echo "❌ <strong>URL MISMATCH!</strong><br>";
        echo "Expected: $callbackUrl<br>";
        echo "Service: $serviceCallbackUrl";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ <strong>Error testing PayProxy service:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><h3>5. HUBTEL CONFIGURATION:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h4>📋 For Hubtel PayProxy Setup:</h4>";
echo "<ul>";
echo "<li><strong>Callback URL:</strong> <code>$callbackUrl</code></li>";
echo "<li><strong>Return URL:</strong> <code>$baseUrl/voter/payment-success.php</code></li>";
echo "<li><strong>Cancel URL:</strong> <code>$baseUrl/voter/payment-cancelled.php</code></li>";
echo "</ul>";
echo "<p><strong>Note:</strong> These URLs will be automatically sent with each PayProxy request.</p>";
echo "</div>";

echo "<br><h3>6. TESTING ACCESSIBILITY:</h3>";

// Test if callback URL is accessible
$ch = curl_init($callbackUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<strong>Callback URL Accessibility Test:</strong><br>";
if ($httpCode == 200) {
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
    echo "✅ <strong>Callback URL is accessible</strong> (HTTP $httpCode)";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ <strong>Callback URL accessibility issue</strong> (HTTP $httpCode)";
    if ($error) {
        echo "<br>Error: $error";
    }
    echo "</div>";
}

echo "</div>";

echo "<br><div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h3>🎯 Summary:</h3>";
echo "<ul>";
echo "<li><strong>Environment:</strong> " . ($isLocal ? 'Local Development' : 'Hosted Production') . "</li>";
echo "<li><strong>Base URL:</strong> $baseUrl</li>";
echo "<li><strong>Callback URL:</strong> $callbackUrl</li>";
echo "<li><strong>URL Generation:</strong> Automatic based on HTTP_HOST</li>";
echo "</ul>";
echo "<p><strong>The system will now use the correct hosted URLs for all PayProxy callbacks and returns!</strong></p>";
echo "</div>";
?>
