<?php
/**
 * Hubtel USSD Service - Fixed Version
 * Uses correct callback URL and tries multiple endpoints
 */

class HubtelUSSDService {
    private $posId;
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $environment;
    private $db;
    
    public function __construct() {
        $this->loadSettings();
        $this->setEnvironment();
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
        
        $this->posId = $settings['hubtel_pos_id'] ?? '';
        $this->apiKey = $settings['hubtel_api_key'] ?? '';
        $this->apiSecret = $settings['hubtel_api_secret'] ?? '';
        $this->environment = $settings['hubtel_environment'] ?? 'sandbox';
    }
    
    /**
     * Set API URLs based on environment
     */
    private function setEnvironment() {
        if ($this->environment === 'production') {
            $this->baseUrl = 'https://rmp.hubtel.com';
        } else {
            $this->baseUrl = 'https://sandbox.hubtel.com';
        }
    }
    
    /**
     * Generate USSD payment code for voting
     * Uses correct callback URL and tries multiple endpoints
     */
    public function generateUSSDPayment($amount, $phoneNumber, $description, $clientReference, $metadata = []) {
        try {
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);
            
            $payload = [
                'CustomerName' => $metadata['voter_name'] ?? 'E-Cast Voter',
                'CustomerMsisdn' => $formattedPhone,
                'CustomerEmail' => $metadata['email'] ?? '',
                'Amount' => (float)$amount,
                'PrimaryCallbackUrl' => $this->getCallbackUrl(),
                'Description' => $description,
                'ClientReference' => $clientReference,
                'Metadata' => json_encode($metadata)
            ];
            
            // Try different USSD endpoints based on actual Hubtel API structure
            $endpoints = [
                "/merchantaccount/merchants/{$this->posId}/receive/ussd",
                "/merchantaccount/merchants/{$this->posId}/ussd/receive", 
                "/ussd/receive",
                "/receive/ussd",
                "/merchantaccount/merchants/{$this->posId}/receive/mobilemoney" // Fallback to mobile money
            ];
            
            $response = null;
            $lastError = null;
            $workingEndpoint = null;
            
            foreach ($endpoints as $endpoint) {
                error_log("Trying USSD endpoint: {$this->baseUrl}$endpoint");
                $response = $this->makeRequest('POST', $endpoint, $payload);
                
                // Check if we got a successful response
                if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] === '0000') {
                    $workingEndpoint = $endpoint;
                    error_log("USSD endpoint working: $endpoint");
                    break;
                }
                
                // If we get a non-404 error, this endpoint exists but has other issues
                if ($response && (!isset($response['ResponseCode']) || $response['ResponseCode'] !== '404')) {
                    $lastError = $response;
                    $workingEndpoint = $endpoint;
                    error_log("USSD endpoint exists but failed: $endpoint - " . json_encode($response));
                    break;
                }
                
                $lastError = $response;
            }
            
            // Use the last response if we found a working endpoint
            if (!$response && $lastError) {
                $response = $lastError;
            }
            
            if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] === '0000') {
                return [
                    'success' => true,
                    'ussd_code' => $response['Data']['USSDCode'] ?? '',
                    'payment_token' => $response['Data']['CheckoutId'] ?? $clientReference,
                    'transaction_id' => $response['Data']['TransactionId'] ?? '',
                    'instructions' => $this->generateUSSDInstructions($response['Data']['USSDCode'] ?? ''),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                    'amount' => $amount,
                    'phone_number' => $formattedPhone,
                    'endpoint_used' => $workingEndpoint
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['Message'] ?? 'Failed to generate USSD payment code',
                    'error_code' => $response['ResponseCode'] ?? 'UNKNOWN_ERROR',
                    'endpoint_tried' => $workingEndpoint,
                    'full_response' => $response
                ];
            }
            
        } catch (Exception $e) {
            error_log("Hubtel USSD Payment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'USSD payment generation failed. Please try again.',
                'error_code' => 'SYSTEM_ERROR'
            ];
        }
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
     * Generate USSD payment instructions
     */
    private function generateUSSDInstructions($ussdCode) {
        if (empty($ussdCode)) {
            return [
                'Please complete the mobile money payment on your phone',
                'Follow the prompts to authorize the payment',
                'You will receive SMS confirmation when payment is complete'
            ];
        }
        
        return [
            "Dial {$ussdCode} on your phone",
            "Follow the prompts to complete payment",
            "Enter your mobile money PIN when requested",
            "You will receive SMS confirmation when payment is complete"
        ];
    }
    
    /**
     * Get callback URL for webhooks
     * For USSD payments, Hubtel uses their gateway service callback
     */
    private function getCallbackUrl() {
        // Use Hubtel's gateway service callback URL for USSD payments
        // Based on church management system implementation
        return 'https://gs-callback.hubtel.com/callback';
    }
    
    /**
     * Make HTTP request to Hubtel API
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret)
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log request for debugging
        error_log("Hubtel USSD API Request: $method $url");
        error_log("Hubtel USSD API Data: " . json_encode($data));
        error_log("Hubtel USSD API Response Code: $httpCode");
        error_log("Hubtel USSD API Response: $response");
        
        return json_decode($response, true);
    }
}
?>
