<?php
/**
 * Verify Hubtel Credentials and Test API Connectivity
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== HUBTEL CREDENTIALS VERIFICATION ===\n\n";
    
    // Get all Hubtel settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%' ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "1. CURRENT SETTINGS:\n";
    echo "-------------------\n";
    foreach ($settings as $key => $value) {
        if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
            $displayValue = $value ? str_repeat('*', min(strlen($value), 20)) . ' (' . strlen($value) . ' chars)' : 'EMPTY';
        } else {
            $displayValue = $value ?: 'EMPTY';
        }
        echo sprintf("%-25s: %s\n", $key, $displayValue);
    }
    
    echo "\n2. CREDENTIAL ANALYSIS:\n";
    echo "----------------------\n";
    
    $requiredFields = ['hubtel_pos_id', 'hubtel_api_key', 'hubtel_api_secret'];
    $missingFields = [];
    $suspiciousFields = [];
    
    foreach ($requiredFields as $field) {
        $value = $settings[$field] ?? '';
        
        if (empty($value)) {
            $missingFields[] = $field;
        } else {
            // Check for placeholder/test values
            $lowerValue = strtolower($value);
            if (strpos($lowerValue, 'test') !== false || 
                strpos($lowerValue, 'sandbox') !== false || 
                strpos($lowerValue, 'placeholder') !== false ||
                strpos($lowerValue, 'example') !== false ||
                $value === 'your_pos_id' ||
                $value === 'your_api_key' ||
                strlen($value) < 5) {
                $suspiciousFields[] = $field;
            }
        }
    }
    
    if (!empty($missingFields)) {
        echo "❌ MISSING FIELDS: " . implode(', ', $missingFields) . "\n";
    }
    
    if (!empty($suspiciousFields)) {
        echo "⚠️  SUSPICIOUS VALUES: " . implode(', ', $suspiciousFields) . " (may be placeholders)\n";
    }
    
    if (empty($missingFields) && empty($suspiciousFields)) {
        echo "✅ All required fields have values that appear to be real credentials\n";
    }
    
    echo "\n3. ENVIRONMENT CHECK:\n";
    echo "--------------------\n";
    $environment = $settings['hubtel_environment'] ?? 'not_set';
    $testMode = $settings['hubtel_test_mode'] ?? 'not_set';
    
    echo "Environment: $environment\n";
    echo "Test Mode: $testMode\n";
    
    if ($environment === 'production' && $testMode === '0') {
        echo "⚠️  PRODUCTION MODE - Real payments will be processed\n";
    } elseif ($environment === 'sandbox' || $testMode === '1') {
        echo "✅ SANDBOX/TEST MODE - Safe for testing\n";
    } else {
        echo "❓ UNCLEAR MODE - Check environment settings\n";
    }
    
    echo "\n4. API CONNECTIVITY TEST:\n";
    echo "------------------------\n";
    
    // Test basic API connectivity (without authentication)
    $testUrl = 'https://rmp.hubtel.com';
    
    echo "Testing connectivity to: $testUrl\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BaronCast-Test/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ CONNECTIVITY ERROR: $error\n";
    } elseif ($httpCode >= 200 && $httpCode < 400) {
        echo "✅ CONNECTIVITY OK: HTTP $httpCode\n";
    } else {
        echo "⚠️  CONNECTIVITY ISSUE: HTTP $httpCode\n";
    }
    
    echo "\n5. CREDENTIAL VALIDATION ATTEMPT:\n";
    echo "---------------------------------\n";
    
    $posId = $settings['hubtel_pos_id'] ?? '';
    $apiKey = $settings['hubtel_api_key'] ?? '';
    $apiSecret = $settings['hubtel_api_secret'] ?? '';
    
    if ($posId && $apiKey && $apiSecret && !in_array('hubtel_pos_id', $suspiciousFields)) {
        echo "Attempting to validate credentials with Hubtel API...\n";
        
        // Try a simple API call to validate credentials
        $testEndpoint = "https://rmp.hubtel.com/merchantaccount/merchants/$posId/receive/mobilemoney";
        
        $testPayload = [
            'CustomerName' => 'Test User',
            'CustomerMsisdn' => '233241234567',
            'CustomerEmail' => 'test@example.com',
            'Channel' => 'mtn-gh',
            'Amount' => 0.01,
            'PrimaryCallbackUrl' => 'https://example.com/callback',
            'Description' => 'Credential validation test',
            'ClientReference' => 'TEST_' . time()
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ API TEST ERROR: $error\n";
        } else {
            switch ($httpCode) {
                case 200:
                case 201:
                    echo "✅ CREDENTIALS VALID: API responded successfully (HTTP $httpCode)\n";
                    echo "Response preview: " . substr($response, 0, 200) . "...\n";
                    break;
                case 401:
                    echo "❌ CREDENTIALS INVALID: Authentication failed (HTTP $httpCode)\n";
                    break;
                case 403:
                    echo "⚠️  CREDENTIALS VALID BUT NO PERMISSION: Account may not have Direct Receive Money enabled (HTTP $httpCode)\n";
                    break;
                case 400:
                    echo "✅ CREDENTIALS VALID: Bad request format, but authentication passed (HTTP $httpCode)\n";
                    break;
                default:
                    echo "❓ UNCLEAR RESULT: HTTP $httpCode\n";
                    echo "Response: " . substr($response, 0, 300) . "\n";
            }
        }
    } else {
        echo "⚠️  SKIPPING API TEST: Credentials appear to be missing or placeholder values\n";
    }
    
    echo "\n6. RECOMMENDATIONS:\n";
    echo "------------------\n";
    
    if (!empty($missingFields) || !empty($suspiciousFields)) {
        echo "❌ CREDENTIALS NOT READY FOR PRODUCTION\n";
        echo "   • Contact Hubtel to get proper API credentials\n";
        echo "   • Update credentials in admin panel or database\n";
        echo "   • Use test mode for development\n";
    } else {
        echo "✅ CREDENTIALS APPEAR TO BE CONFIGURED\n";
        echo "   • Test with small amounts first\n";
        echo "   • Monitor transaction logs\n";
        echo "   • Verify callback URL is accessible\n";
    }
    
    echo "\n=== VERIFICATION COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
