<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo "ERROR: Unauthorized access";
    exit;
}

header('Content-Type: text/plain');

try {
    echo "=== HUBTEL WEBHOOK TEST ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Sample Hubtel callback data
    $test_callback_data = [
        'ResponseCode' => '0000',
        'Message' => 'Payment successful',
        'Data' => [
            'ClientReference' => 'TEST_WEBHOOK_' . time(),
            'TransactionId' => 'HUB_' . time() . '_' . rand(1000, 9999),
            'ExternalTransactionId' => 'EXT_' . time(),
            'Amount' => 5.00,
            'Charges' => 0.05,
            'AmountCharged' => 5.05,
            'PaymentDate' => date('Y-m-d H:i:s'),
            'CustomerMsisdn' => '233200000000',
            'CustomerName' => 'Test User'
        ]
    ];
    
    echo "Sample callback data to send:\n";
    echo json_encode($test_callback_data, JSON_PRETTY_PRINT) . "\n\n";
    
    // Determine webhook URL
    $webhook_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . 
                   $_SERVER['HTTP_HOST'] . '/webhooks/hubtel-receive-money-callback.php';
    
    echo "Webhook URL: $webhook_url\n\n";
    echo "Sending test webhook...\n\n";
    
    // Send POST request to webhook
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_callback_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: HubtelTestWebhook/1.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "=== WEBHOOK RESPONSE ===\n";
    echo "HTTP Status Code: $http_code\n";
    
    if ($curl_error) {
        echo "CURL Error: $curl_error\n";
    } else {
        echo "Response Body:\n$response\n\n";
        
        // Try to decode JSON response
        $json_response = json_decode($response, true);
        if ($json_response !== null) {
            echo "=== PARSED RESPONSE ===\n";
            echo json_encode($json_response, JSON_PRETTY_PRINT) . "\n\n";
        }
        
        echo "=== RESULT ANALYSIS ===\n";
        if ($http_code === 200) {
            echo "✓ Webhook endpoint is reachable and responded successfully\n";
            
            if (strpos($response, 'success') !== false || strpos($response, 'processed') !== false) {
                echo "✓ Webhook appears to have processed the callback data\n";
            } else {
                echo "⚠ Webhook responded but processing status unclear\n";
            }
        } elseif ($http_code === 405) {
            echo "⚠ Method not allowed - webhook might only accept POST from Hubtel\n";
        } elseif ($http_code === 404) {
            echo "❌ Webhook endpoint not found - check file path\n";
        } elseif ($http_code >= 500) {
            echo "❌ Server error in webhook processing\n";
        } else {
            echo "⚠ Unexpected HTTP status code: $http_code\n";
        }
    }
    
    // Check if any transaction was created (shouldn't happen with test data)
    echo "\n=== DATABASE CHECK ===\n";
    $database = new Database();
    $pdo = $database->getConnection();
    
    $test_ref = $test_callback_data['Data']['ClientReference'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reference = ? OR transaction_id = ?");
    $stmt->execute([$test_ref, $test_ref]);
    $transaction_count = $stmt->fetchColumn();
    
    if ($transaction_count > 0) {
        echo "⚠ Test transaction was created in database (this should not happen with test data)\n";
    } else {
        echo "✓ No test transactions were created in database\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>