<?php
/**
 * Test Paystack Connection API
 * Tests Paystack API connectivity and key validity
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['secret_key'])) {
    echo json_encode(['success' => false, 'message' => 'Secret key is required']);
    exit;
}

$secret_key = $input['secret_key'];
$public_key = $input['public_key'] ?? '';

// Validate key format
if (!preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $secret_key)) {
    echo json_encode(['success' => false, 'message' => 'Invalid secret key format']);
    exit;
}

try {
    // Test API connection by fetching banks
    $url = 'https://api.paystack.co/bank';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secret_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('Connection error: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = $error_data['message'] ?? 'HTTP ' . $http_code;
        throw new Exception($error_message);
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !$data['status']) {
        throw new Exception('Invalid API response');
    }
    
    // Determine environment
    $environment = strpos($secret_key, 'sk_test_') === 0 ? 'Test' : 'Live';
    $pub_environment = strpos($public_key, 'pk_test_') === 0 ? 'Test' : 'Live';
    
    // Check key consistency
    if (!empty($public_key) && $environment !== $pub_environment) {
        echo json_encode([
            'success' => false, 
            'message' => 'Key mismatch: Secret key is ' . $environment . ' but Public key is ' . $pub_environment
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful!',
        'environment' => $environment,
        'banks_count' => count($data['data'] ?? [])
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
