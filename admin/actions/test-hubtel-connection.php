<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'test_connection') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$posId = $_POST['hubtel_pos_id'] ?? '';
$apiKey = $_POST['hubtel_api_key'] ?? '';
$apiSecret = $_POST['hubtel_api_secret'] ?? '';
$environment = $_POST['hubtel_environment'] ?? 'sandbox';

// Validate required fields
if (empty($posId) || empty($apiKey) || empty($apiSecret)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (POS ID, API Key, API Secret)']);
    exit;
}

try {
    // Set API URLs based on environment
    if ($environment === 'production') {
        $baseUrl = 'https://rmp.hubtel.com';
        $statusCheckUrl = 'https://api-txnstatus.hubtel.com';
    } else {
        // Sandbox URLs
        $baseUrl = 'https://rmp.hubtel.com';
        $statusCheckUrl = 'https://api-txnstatus.hubtel.com';
    }
    
    // Test authentication by making a simple API call
    $testEndpoint = "/merchantaccount/merchants/{$posId}/receive/mobilemoney";
    $testUrl = $baseUrl . $testEndpoint;
    
    // Prepare test payment data (this won't actually charge anyone)
    $testData = [
        'CustomerName' => 'Test Connection',
        'CustomerMsisdn' => '233200000000', // Test number
        'CustomerEmail' => 'test@example.com',
        'Channel' => 'mtn-gh',
        'Amount' => 1.00, // Minimum test amount
        'PrimaryCallbackUrl' => 'https://example.com/callback',
        'Description' => 'Connection Test - Do Not Process',
        'ClientReference' => 'TEST_' . time()
    ];
    
    $auth = base64_encode($apiKey . ':' . $apiSecret);
    
    $headers = [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
        'Accept: application/json',
        'Cache-Control: no-cache'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($testData),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo json_encode([
            'success' => false, 
            'message' => 'Connection failed: ' . $curlError
        ]);
        exit;
    }
    
    $responseData = json_decode($response, true);
    
    // Analyze response
    if ($httpCode === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'Connection successful! API credentials are valid and Hubtel service is reachable.',
            'details' => [
                'environment' => $environment,
                'http_code' => $httpCode,
                'response_code' => $responseData['ResponseCode'] ?? 'N/A'
            ]
        ]);
    } elseif ($httpCode === 401) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed. Please check your API Key and API Secret.'
        ]);
    } elseif ($httpCode === 403) {
        echo json_encode([
            'success' => false,
            'message' => 'Access forbidden. Please verify your POS Sales ID and API permissions.'
        ]);
    } elseif ($httpCode === 404) {
        echo json_encode([
            'success' => false,
            'message' => 'API endpoint not found. Please verify your POS Sales ID.'
        ]);
    } elseif ($httpCode >= 400 && $httpCode < 500) {
        $errorMessage = 'Client error occurred';
        if (isset($responseData['Message'])) {
            $errorMessage = $responseData['Message'];
        } elseif (isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'API Error: ' . $errorMessage,
            'details' => [
                'http_code' => $httpCode,
                'response' => $responseData
            ]
        ]);
    } elseif ($httpCode >= 500) {
        echo json_encode([
            'success' => false,
            'message' => 'Hubtel server error. Please try again later or contact Hubtel support.',
            'details' => [
                'http_code' => $httpCode
            ]
        ]);
    } else {
        // For any other response, consider it partially successful if we got a response
        echo json_encode([
            'success' => true,
            'message' => 'Connection established but received unexpected response. Please verify your settings.',
            'details' => [
                'http_code' => $httpCode,
                'response' => $responseData
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Test failed: ' . $e->getMessage()
    ]);
}
?>