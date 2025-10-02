<?php
/**
 * Test Callback URL Accessibility
 */

echo "<h2>Testing Callback URL Accessibility</h2>";

// Test different possible paths
$testUrls = [
    'https://baroncast.online/webhooks/hubtel-receive-money-callback.php',
    'https://baroncast.online/voter/webhooks/hubtel-receive-money-callback.php',
    'https://baroncast.online/webhooks/hubtel-ussd-callback.php',
    'https://baroncast.online/voter/webhooks/hubtel-ussd-callback.php'
];

foreach ($testUrls as $url) {
    echo "<h3>Testing: $url</h3>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>❌ Error: $error</p>";
    } else {
        if ($httpCode == 200 || $httpCode == 405) { // 405 = Method Not Allowed (but file exists)
            echo "<p style='color: green;'>✅ Accessible (HTTP $httpCode)</p>";
        } else {
            echo "<p style='color: red;'>❌ Not accessible (HTTP $httpCode)</p>";
        }
    }
}

echo "<h3>Current File Locations</h3>";
$webhookFiles = [
    'hubtel-receive-money-callback.php' => __DIR__ . '/webhooks/hubtel-receive-money-callback.php',
    'hubtel-ussd-callback.php' => __DIR__ . '/webhooks/hubtel-ussd-callback.php'
];

foreach ($webhookFiles as $name => $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✅ $name exists at: $path</p>";
    } else {
        echo "<p style='color: red;'>❌ $name NOT found at: $path</p>";
    }
}

echo "<h3>Recommended Actions</h3>";
echo "<ol>";
echo "<li><strong>Update Hubtel Service Configuration:</strong> Change callback URL to the working path</li>";
echo "<li><strong>Or create a redirect:</strong> Make the configured URL redirect to the working file</li>";
echo "<li><strong>Test payment:</strong> After fixing, make a USSD payment to test</li>";
echo "</ol>";
?>
