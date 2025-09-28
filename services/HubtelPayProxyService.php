<?php
/**
 * Hubtel PayProxy Service - Corrected Version
 * Uses the correct payproxyapi.hubtel.com/items/initiate endpoint with proper syntax
 */

class HubtelPayProxyService {
    private $apiKey;
    private $apiSecret;
    private $merchantAccount;
    private $baseUrl;
    private $db;
    
    public function __construct() {
        $this->loadSettings();
        $this->baseUrl = 'https://payproxyapi.hubtel.com';
    }
    
    /**
     * Load Hubtel settings from database
     */
    private function loadSettings() {
        require_once __DIR__ . '/../config/database.php';
        
        // Set HTTP_HOST for proper database connection
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }
        
        $database = new Database();
        $this->db = $database->getConnection();
        
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $this->apiKey = $settings['hubtel_api_key'] ?? '';
        $this->apiSecret = $settings['hubtel_api_secret'] ?? '';
        $this->merchantAccount = $settings['hubtel_pos_id'] ?? ''; // Using POS ID as merchant account
    }
    
    /**
     * Create Hubtel checkout using PayProxy API (based on church system)
     * 
     * @param array $params Parameters for the checkout
     * @return array Response with success status and checkout details
     */
    public function createCheckout($params) {
        try {
            // Validate required credentials
            if (!$this->apiKey || !$this->apiSecret || !$this->merchantAccount) {
                return [
                    'success' => false,
                    'error' => 'Hubtel API credentials not configured',
                    'debug' => [
                        'api_key_set' => !empty($this->apiKey),
                        'api_secret_set' => !empty($this->apiSecret),
                        'merchant_account_set' => !empty($this->merchantAccount)
                    ]
                ];
            }
            
            $url = $this->baseUrl . '/items/initiate';
            
            // Map parameters to PayProxy API format (based on church system)
            $data = [
                'totalAmount' => (float) $params['amount'],
                'description' => $params['description'],
                'callbackUrl' => $params['callbackUrl'] ?? $this->getCallbackUrl(),
                'returnUrl' => $params['returnUrl'] ?? $this->getReturnUrl($params['clientReference']),
                'merchantAccountNumber' => $this->merchantAccount,
                'cancellationUrl' => $params['cancellationUrl'] ?? $this->getReturnUrl($params['clientReference']),
                'clientReference' => $params['clientReference']
            ];
            
            // Add optional customer details
            if (!empty($params['customerName'])) {
                $data['payeeName'] = $params['customerName'];
            }
            
            if (!empty($params['customerPhone'])) {
                $data['payeeMobileNumber'] = $this->formatPhoneNumber($params['customerPhone']);
            }
            
            if (!empty($params['customerEmail'])) {
                $data['payeeEmail'] = $params['customerEmail'];
            }
            
            // Make the API request
            $response = $this->makeRequest('POST', '/items/initiate', $data);
            
            // Process response - Handle Hubtel's response format correctly
            if ($response['success'] && $response['http_code'] === 200 && $response['body']) {
                $json = json_decode($response['body'], true);
                
                // Check for success response (Hubtel format: responseCode "0000" means success)
                if (isset($json['responseCode']) && $json['responseCode'] === '0000' && isset($json['data'])) {
                    $responseData = $json['data'];
                    return [
                        'success' => true,
                        'checkoutUrl' => $responseData['checkoutUrl'],
                        'checkoutDirectUrl' => $responseData['checkoutDirectUrl'] ?? null,
                        'checkoutId' => $responseData['checkoutId'] ?? null,
                        'clientReference' => $responseData['clientReference'] ?? $params['clientReference'],
                        'payment_method' => 'payproxy_checkout',
                        'message' => $responseData['message'] ?? 'Checkout created successfully'
                    ];
                }
                
                // Handle error responses
                if (isset($json['responseCode']) && $json['responseCode'] !== '0000') {
                    return [
                        'success' => false,
                        'error' => $json['message'] ?? 'PayProxy API error',
                        'response_code' => $json['responseCode'],
                        'debug' => $json
                    ];
                }
            }
            
            // Return error details if request failed
            return [
                'success' => false,
                'error' => 'PayProxy API request failed',
                'debug' => [
                    'http_code' => $response['http_code'],
                    'response' => $response['body'] ? json_decode($response['body'], true) : null,
                    'curl_error' => $response['curl_error']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Hubtel PayProxy Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'PayProxy service error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate voting payment using PayProxy API
     */
    public function generateVotingPayment($amount, $phoneNumber, $description, $clientReference, $metadata = []) {
        $params = [
            'amount' => $amount,
            'description' => $description,
            'clientReference' => $clientReference,
            'customerName' => $metadata['voter_name'] ?? 'E-Cast Voter',
            'customerPhone' => $phoneNumber,
            'customerEmail' => $metadata['email'] ?? '',
            'callbackUrl' => $this->getCallbackUrl(),
            'returnUrl' => $this->getReturnUrl($clientReference)
        ];
        
        $result = $this->createCheckout($params);
        
        if ($result['success']) {
            return [
                'success' => true,
                'checkout_url' => $result['checkoutUrl'],
                'checkout_direct_url' => $result['checkoutDirectUrl'],
                'checkout_id' => $result['checkoutId'],
                'payment_token' => $result['checkoutId'],
                'transaction_id' => $result['clientReference'],
                'instructions' => $this->generatePaymentInstructions($result['checkoutUrl']),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'amount' => $amount,
                'phone_number' => $this->formatPhoneNumber($phoneNumber)
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create payment checkout',
                'error_code' => 'PAYPROXY_FAILED',
                'debug' => $result['debug'] ?? null
            ];
        }
    }
    
    /**
     * Make HTTP request to PayProxy API
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        $request_body = json_encode($data);
        
        // Set headers (based on church system)
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret)
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reduced timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $raw_headers = $response !== false ? substr($response, 0, (int)$header_size) : '';
        $body = $response !== false ? substr($response, (int)$header_size) : '';
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Log request and response for debugging
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'request' => $data,
            'response_headers' => $raw_headers,
            'response' => $body ? json_decode($body, true) : null,
            'http_code' => $http_code,
            'curl_error' => $curl_error
        ];
        
        error_log("Hubtel PayProxy API Request: " . json_encode($log_data));
        
        return [
            'success' => $http_code === 200 && !$curl_error,
            'http_code' => $http_code,
            'headers' => $raw_headers,
            'body' => $body,
            'curl_error' => $curl_error
        ];
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            return '233' . substr($phone, 1);
        } elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '233') {
            return $phone;
        } elseif (strlen($phone) == 9) {
            return '233' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Generate payment instructions
     */
    private function generatePaymentInstructions($checkoutUrl) {
        return [
            "🎯 PAYMENT CHECKOUT CREATED",
            "💳 Click the payment link to complete your payment",
            "📱 You can pay with Mobile Money, Bank Card, or other methods",
            "🔒 Secure payment powered by Hubtel",
            "",
            "📋 PAYMENT STEPS:",
            "1️⃣ Click the checkout link below",
            "2️⃣ Choose your payment method (Mobile Money, Card, etc.)",
            "3️⃣ Complete the payment process",
            "4️⃣ Your votes will be recorded automatically",
            "",
            "🔗 Payment Link: " . $checkoutUrl
        ];
    }
    
    /**
     * Get callback URL for payment notifications
     */
    private function getCallbackUrl() {
        // Use the actual hosted domain
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/webhooks/hubtel-checkout-callback.php';
    }

    /**
     * Get return URL for payment completion
     */
    private function getReturnUrl($clientReference) {
        // Use the actual hosted domain
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/voter/payment-success.php?ref=' . $clientReference;
    }

    /**
     * Get the base URL for the hosted system
     */
    private function getBaseUrl() {
        // For hosted system, use the actual domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // If it's localhost, assume it's development
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return $protocol . $host . '/baroncast';
        }
        
        // For production/hosted environment, use the full domain
        return $protocol . $host;
    }
}
?>
