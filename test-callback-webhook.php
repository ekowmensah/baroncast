<?php
/**
 * Test Callback Webhook
 * Simulate Hubtel callback to test vote recording
 */

header('Content-Type: application/json');

echo "<h2>🔗 Test Callback Webhook</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Test callback URL - use proper hosted URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determine base URL based on environment
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $baseUrl = $protocol . $host . '/baroncast';
} else {
    $baseUrl = $protocol . $host;
}

$callbackUrl = $baseUrl . '/webhooks/hubtel-checkout-callback.php';

echo "<h3>1. CALLBACK URL:</h3>";
echo "URL: $callbackUrl<br><br>";

echo "<h3>2. TESTING CALLBACK:</h3>";

// Sample callback data (based on successful PayProxy payment)
$testCallbackData = [
    'ResponseCode' => '0000',
    'Status' => 'Success',
    'Data' => [
        'CheckoutId' => 'test_checkout_123',
        'ClientReference' => 'PAYPROXY_' . time() . '_TEST',
        'Status' => 'Success',
        'Amount' => 2.00,
        'CustomerPhoneNumber' => '233241234567',
        'PaymentDetails' => [
            'PaymentMethod' => 'Mobile Money',
            'Network' => 'MTN',
            'TransactionId' => 'TXN_' . time()
        ],
        'Description' => 'Test vote payment'
    ]
];

echo "<strong>Test Callback Data:</strong><br>";
echo "<pre>" . json_encode($testCallbackData, JSON_PRETTY_PRINT) . "</pre><br>";

// First, let's create a test transaction
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $clientRef = $testCallbackData['Data']['ClientReference'];
    
    // Check if test transaction exists
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$clientRef]);
    $existingTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingTransaction) {
        echo "<strong>Creating test transaction...</strong><br>";
        
        // Create a test transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, 1, 1, 1, '233241234567', 2, 2.00, 'payproxy_checkout', 'pending', NOW())
        ");
        
        $stmt->execute([
            $clientRef,
            $clientRef
        ]);
        
        echo "✅ Test transaction created with reference: $clientRef<br><br>";
    } else {
        echo "✅ Test transaction already exists: $clientRef<br><br>";
    }
    
    echo "<h3>3. SENDING CALLBACK:</h3>";
    
    // Send callback to webhook
    $ch = curl_init($callbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testCallbackData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<strong>Callback Response:</strong><br>";
    echo "HTTP Code: $httpCode<br>";
    if ($error) {
        echo "cURL Error: $error<br>";
    }
    echo "Response: $response<br><br>";
    
    echo "<h3>4. CHECKING RESULTS:</h3>";
    
    // Check if transaction was updated
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ?");
    $stmt->execute([$clientRef]);
    $updatedTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updatedTransaction) {
        echo "<strong>Transaction Status:</strong> {$updatedTransaction['status']}<br>";
        echo "<strong>Updated At:</strong> {$updatedTransaction['updated_at']}<br><br>";
    }
    
    // Check if votes were created
    $stmt = $pdo->prepare("SELECT * FROM votes WHERE payment_reference = ?");
    $stmt->execute([$clientRef]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Votes Created:</strong> " . count($votes) . "<br>";
    if (count($votes) > 0) {
        echo "<strong>Vote Details:</strong><br>";
        foreach ($votes as $vote) {
            echo "- Vote ID: {$vote['id']}, Nominee: {$vote['nominee_id']}, Status: {$vote['payment_status']}<br>";
        }
    }
    
    echo "<br><h3>5. SUMMARY:</h3>";
    if ($httpCode == 200 && count($votes) > 0) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "✅ <strong>CALLBACK WORKING!</strong><br>";
        echo "- Webhook responded successfully<br>";
        echo "- Transaction status updated<br>";
        echo "- Votes created correctly<br>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "❌ <strong>CALLBACK ISSUE DETECTED!</strong><br>";
        echo "- HTTP Code: $httpCode<br>";
        echo "- Votes Created: " . count($votes) . "<br>";
        echo "- Check the callback webhook code<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>ERROR:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

echo "<br><div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔧 If Callback Not Working:</h3>";
echo "<ol>";
echo "<li><strong>Check webhook URL:</strong> Make sure $callbackUrl is accessible</li>";
echo "<li><strong>Check database:</strong> Ensure transactions table has the test record</li>";
echo "<li><strong>Check logs:</strong> Look at logs/hubtel-callback.log for errors</li>";
echo "<li><strong>Test manually:</strong> Visit the webhook URL directly</li>";
echo "</ol>";
echo "</div>";
?>
