<?php
/**
 * Hubtel Direct Receive Money Service - Fixed Version
 * Implementation of Hubtel's Direct Receive Money API for mobile money payments
 */

class HubtelReceiveMoneyService {
    private $posId;
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $statusCheckUrl;
    private $environment;
    private $db;
    
    // Available mobile money channels
    const CHANNELS = [
        'mtn' => 'mtn-gh',
        'telecel' => 'vodafone-gh',
        'vodafone' => 'vodafone-gh', // Legacy support
        'airteltigo' => 'tigo-gh'
    ];
    
    // Response codes
    const SUCCESS = '0000';
    const PENDING = '0001';
    const FAILED = '2001';
    const VALIDATION_ERROR = '4000';
    const FEES_ERROR = '4070';
    const SETUP_ERROR = '4101';
    const PERMISSION_ERROR = '4103';
    
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        $this->loadSettings();
        $this->setUrls();
    }
    
    /**
     * Load Hubtel settings from database
     */
    private function loadSettings() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $this->posId = $settings['hubtel_pos_id'] ?? '';
            $this->apiKey = $settings['hubtel_api_key'] ?? '';
            $this->apiSecret = $settings['hubtel_api_secret'] ?? '';
            $this->environment = $settings['hubtel_environment'] ?? 'live';
            
        } catch (Exception $e) {
            error_log("Error loading Hubtel settings: " . $e->getMessage());
            $this->posId = '';
            $this->apiKey = '';
            $this->apiSecret = '';
            $this->environment = 'live';
        }
    }
    
    /**
     * Set API URLs based on environment
     */
    private function setUrls() {
        if ($this->environment === 'sandbox') {
            $this->baseUrl = 'https://rmp-sandbox.hubtel.com';
            $this->statusCheckUrl = 'https://rmp-sandbox.hubtel.com';
        } else {
            $this->baseUrl = 'https://rmp.hubtel.com';
            $this->statusCheckUrl = 'https://rmp.hubtel.com';
        }
    }
    
    /**
     * Initiate mobile money payment
     */
    public function initiatePayment($amount, $phone, $description, $clientReference, $customerName = '', $customerEmail = '') {
        try {
            // Validate request
            $validation = $this->validatePaymentRequest($amount, $phone, $description, $clientReference);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error']
                ];
            }
            
            // Format phone and detect channel
            $formattedPhone = $this->formatPhoneNumber($phone);
            $channel = $this->detectChannel($formattedPhone);
            
            if (!$channel) {
                return [
                    'success' => false,
                    'message' => 'Unsupported mobile network. Please use MTN, Telecel, or AirtelTigo.'
                ];
            }
            
            // Prepare API payload
            $payload = [
                'CustomerName' => $customerName ?: 'E-Cast Voter',
                'CustomerMsisdn' => $formattedPhone,
                'CustomerEmail' => $customerEmail ?: '',
                'Channel' => $channel,
                'Amount' => (float) $amount,
                'PrimaryCallbackUrl' => $this->getCallbackUrl(),
                'Description' => $description,
                'ClientReference' => $clientReference
            ];
            
            // Make API request
            $endpoint = "/merchantaccount/merchants/{$this->posId}/receive/mobilemoney";
            $response = $this->makeRequest('POST', $endpoint, $payload);
            
            return $this->parseResponse($response);
            
        } catch (Exception $e) {
            error_log("Hubtel payment initiation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment service temporarily unavailable. Please try again.'
            ];
        }
    }
    
    /**
     * Check payment status
     */
    public function checkPaymentStatus($clientReference) {
        try {
            $endpoint = "/merchantaccount/merchants/{$this->posId}/receive/mobilemoney/status";
            $queryParams = [
                'clientReference' => $clientReference
            ];
            
            $response = $this->makeStatusRequest('GET', $endpoint, $queryParams);
            
            if ($response['success'] && isset($response['data']['data'])) {
                $statusData = $response['data']['data'];
                
                return [
                    'success' => true,
                    'status' => strtolower($statusData['status'] ?? 'unknown'),
                    'transaction_id' => $statusData['transactionId'] ?? '',
                    'external_transaction_id' => $statusData['externalTransactionId'] ?? '',
                    'amount' => (float)($statusData['amount'] ?? 0),
                    'charges' => (float)($statusData['charges'] ?? 0),
                    'payment_date' => $statusData['date'] ?? '',
                    'is_paid' => strtolower($statusData['status'] ?? '') === 'paid'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Status check failed',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            error_log("Hubtel status check error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Status check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate payment request parameters
     */
    private function validatePaymentRequest($amount, $phone, $description, $clientReference) {
        if (empty($this->posId)) {
            return ['valid' => false, 'error' => 'Hubtel POS ID not configured'];
        }
        
        if (empty($this->apiKey)) {
            return ['valid' => false, 'error' => 'Hubtel API key not configured'];
        }
        
        if ($amount <= 0 || $amount > 10000) {
            return ['valid' => false, 'error' => 'Invalid amount. Must be between 0.01 and 10,000'];
        }
        
        if (strlen($clientReference) > 36) {
            return ['valid' => false, 'error' => 'Client reference too long (max 36 characters)'];
        }
        
        if (empty($description)) {
            return ['valid' => false, 'error' => 'Description is required'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert Ghana numbers to international format
        if (substr($phone, 0, 1) === '0') {
            $phone = '233' . substr($phone, 1);
        } elseif (substr($phone, 0, 4) !== '+233' && substr($phone, 0, 3) !== '233') {
            $phone = '233' . $phone;
        }
        
        // Remove + if present
        $phone = str_replace('+', '', $phone);
        
        return $phone;
    }
    
    /**
     * Detect mobile money channel from phone number
     */
    private function detectChannel($phone) {
        // Ghana mobile prefixes
        $prefixes = [
            'mtn-gh' => ['24', '25', '53', '54', '55', '59'],
            'vodafone-gh' => ['20', '50'], // Telecel Ghana
            'tigo-gh' => ['26', '27', '56', '57'] // AirtelTigo
        ];
        
        $phonePrefix = substr($phone, 3, 2); // Get prefix after 233
        
        foreach ($prefixes as $channel => $channelPrefixes) {
            if (in_array($phonePrefix, $channelPrefixes)) {
                return $channel;
            }
        }
        
        return null;
    }
    
    /**
     * Get callback URL for webhook
     */
    private function getCallbackUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/webhooks/hubtel-receive-money-callback.php";
    }
    
    /**
     * Make API request to Hubtel
     */
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        $auth = base64_encode($this->apiKey . ':' . $this->apiSecret);
        
        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json',
            'Cache-Control: no-cache'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        error_log("Hubtel API Request: $method $url");
        if ($data) {
            error_log("Hubtel API Data: " . json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Hubtel API Response Code: $httpCode");
        error_log("Hubtel API Response: " . $response);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => json_decode($response, true),
            'raw_response' => $response
        ];
    }
    
    /**
     * Make status check request
     */
    private function makeStatusRequest($method, $endpoint, $queryParams = []) {
        $url = $this->statusCheckUrl . $endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        $auth = base64_encode($this->apiKey . ':' . $this->apiSecret);
        
        $headers = [
            'Authorization: Basic ' . $auth,
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Status check cURL Error: " . $error);
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => json_decode($response, true),
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse API response
     */
    private function parseResponse($response) {
        if (!$response['success']) {
            $errorMessage = 'API request failed';
            
            // Provide more specific error messages based on HTTP code
            switch ($response['http_code']) {
                case 401:
                    $errorMessage = 'Authentication failed. Please check your Hubtel API credentials.';
                    break;
                case 403:
                    $errorMessage = 'Access denied. Your account may not have permission for mobile money services.';
                    break;
                case 404:
                    $errorMessage = 'Service not found. The mobile money service may not be available.';
                    break;
                case 500:
                    $errorMessage = 'Hubtel server error. Please try again later.';
                    break;
                case 0:
                    $errorMessage = 'Network connection failed. Please check your internet connection.';
                    break;
                default:
                    if ($response['http_code'] >= 400) {
                        $errorMessage = "Hubtel API error (HTTP {$response['http_code']}). Please contact support.";
                    }
            }
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'http_code' => $response['http_code'],
                'response' => $response['data']
            ];
        }
        
        $data = $response['data'];
        $responseCode = $data['ResponseCode'] ?? '';
        $message = $data['Message'] ?? '';
        $responseData = $data['Data'] ?? [];
        
        switch ($responseCode) {
            case self::SUCCESS:
                return [
                    'success' => true,
                    'status' => 'completed',
                    'message' => $message,
                    'transaction_id' => $responseData['TransactionId'] ?? '',
                    'client_reference' => $responseData['ClientReference'] ?? '',
                    'amount' => (float)($responseData['Amount'] ?? 0),
                    'charges' => (float)($responseData['Charges'] ?? 0),
                    'amount_charged' => (float)($responseData['AmountCharged'] ?? 0)
                ];
                
            case self::PENDING:
                return [
                    'success' => true,
                    'status' => 'pending',
                    'message' => $message,
                    'transaction_id' => $responseData['TransactionId'] ?? '',
                    'client_reference' => $responseData['ClientReference'] ?? '',
                    'amount' => (float)($responseData['Amount'] ?? 0),
                    'charges' => (float)($responseData['Charges'] ?? 0),
                    'amount_charged' => (float)($responseData['AmountCharged'] ?? 0)
                ];
                
            default:
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $message,
                    'code' => $responseCode,
                    'response' => $data
                ];
        }
    }
    
    /**
     * Process payment callback from Hubtel
     */
    public function processCallback($callbackData) {
        // Log raw callback data before any validation (as requested by Hubtel)
        error_log("=== HUBTEL CALLBACK RAW REQUEST ===");
        error_log("Timestamp: " . date('Y-m-d H:i:s'));
        error_log("Raw callback data: " . json_encode($callbackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        error_log("Request headers: " . json_encode(getallheaders(), JSON_PRETTY_PRINT));
        error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
        error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        error_log("User agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        error_log("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        error_log("=== END RAW REQUEST ===");
        
        try {
            // Extract callback information
            $orderInfo = $callbackData['OrderInfo'] ?? [];
            $status = $orderInfo['Status'] ?? '';
            $items = $orderInfo['Items'] ?? [];
            $payment = $orderInfo['Payment'] ?? [];
            
            // Get the first item (should be the USSD vote)
            $item = $items[0] ?? [];
            $itemName = $item['Name'] ?? '';
            
            // Extract USSD reference from item name
            if (preg_match('/Ref: (USSD_\d+_\d+)/', $itemName, $matches)) {
                $clientReference = $matches[1];
            } else {
                return [
                    'success' => false,
                    'message' => 'Could not extract USSD reference from callback'
                ];
            }
            
            // Check if payment was successful
            $isSuccess = (strtolower($status) === 'paid' && ($payment['IsSuccessful'] ?? false));
            
            if ($isSuccess) {
                // Find the USSD transaction
                $stmt = $this->db->prepare("
                    SELECT ut.*, n.name as nominee_name, e.title as event_title
                    FROM ussd_transactions ut
                    JOIN nominees n ON ut.nominee_id = n.id
                    JOIN events e ON ut.event_id = e.id
                    WHERE ut.transaction_ref = ?
                ");
                $stmt->execute([$clientReference]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$transaction) {
                    return [
                        'success' => false,
                        'message' => 'USSD transaction not found: ' . $clientReference
                    ];
                }
                
                // Start database transaction
                $this->db->beginTransaction();
                
                try {
                    // Update USSD transaction status
                    $stmt = $this->db->prepare("
                        UPDATE ussd_transactions 
                        SET status = 'completed', completed_at = NOW()
                        WHERE transaction_ref = ?
                    ");
                    $stmt->execute([$clientReference]);
                    
                    // Get organizer_id from event
                    $stmt = $this->db->prepare("SELECT organizer_id FROM events WHERE id = ?");
                    $stmt->execute([$transaction['event_id']]);
                    $organizerId = $stmt->fetchColumn();
                    
                    // Create main transaction record
                    $stmt = $this->db->prepare("
                        INSERT INTO transactions (
                            transaction_id, reference, event_id, organizer_id, nominee_id,
                            voter_phone, vote_count, amount, payment_method,
                            status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', NOW())
                    ");
                    $stmt->execute([
                        $clientReference,
                        $clientReference,
                        $transaction['event_id'],
                        $organizerId,
                        $transaction['nominee_id'],
                        $transaction['phone_number'],
                        $transaction['vote_count'],
                        $transaction['amount']
                    ]);
                    
                    $mainTransactionId = $this->db->lastInsertId();
                    
                    // Create individual votes
                    $voteCount = (int)$transaction['vote_count'];
                    $voteAmount = $transaction['amount'] / $voteCount;
                    
                    // Get category_id from nominee
                    $stmt = $this->db->prepare("SELECT category_id FROM nominees WHERE id = ?");
                    $stmt->execute([$transaction['nominee_id']]);
                    $categoryId = $stmt->fetchColumn();
                    
                    for ($i = 0; $i < $voteCount; $i++) {
                        $stmt = $this->db->prepare("
                            INSERT INTO votes (
                                event_id, category_id, nominee_id, voter_phone, 
                                transaction_id, payment_method, payment_reference, 
                                payment_status, amount, voted_at, created_at
                            ) VALUES (?, ?, ?, ?, ?, 'hubtel_ussd', ?, 'completed', ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $transaction['event_id'],
                            $categoryId,
                            $transaction['nominee_id'],
                            $transaction['phone_number'],
                            $mainTransactionId,
                            $clientReference,
                            $voteAmount
                        ]);
                    }
                    
                    // Commit transaction
                    $this->db->commit();
                    
                    // Send callback confirmation to Hubtel (as per church system)
                    $sessionId = $callbackData['SessionId'] ?? '';
                    $orderId = $callbackData['OrderId'] ?? '';
                    
                    $callback_payload = [
                        'SessionId' => $sessionId,
                        'OrderId' => $orderId,
                        'ServiceStatus' => 'success',
                        'MetaData' => null
                    ];
                    
                    $callback_url = 'https://gs-callback.hubtel.com/callback';
                    $callback_options = [
                        'http' => [
                            'header' => "Content-Type: application/json\r\n",
                            'method' => 'POST',
                            'content' => json_encode($callback_payload),
                            'timeout' => 10
                        ]
                    ];
                    
                    $callback_context = stream_context_create($callback_options);
                    $callback_result = @file_get_contents($callback_url, false, $callback_context);
                    
                    if ($callback_result !== false) {
                        error_log('Hubtel gs-callback sent successfully: ' . json_encode($callback_payload));
                    } else {
                        error_log('Failed to send Hubtel gs-callback: ' . json_encode($callback_payload));
                    }
                    
                    return [
                        'success' => true,
                        'status' => 'completed',
                        'processed' => true,
                        'client_reference' => $clientReference,
                        'votes_recorded' => $voteCount,
                        'nominee' => $transaction['nominee_name']
                    ];
                    
                } catch (Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }
                
            } else {
                // Payment failed - update status
                $stmt = $this->db->prepare("
                    UPDATE ussd_transactions 
                    SET status = 'failed'
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([$clientReference]);
                
                return [
                    'success' => true,
                    'status' => 'failed',
                    'processed' => true,
                    'client_reference' => $clientReference
                ];
            }
            
        } catch (Exception $e) {
            error_log("Callback processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Callback processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transactions pending status check
     */
    public function getPendingStatusChecks() {
        try {
            $stmt = $this->db->prepare("
                SELECT reference, transaction_id, created_at
                FROM transactions 
                WHERE status = 'pending' 
                AND payment_method = 'mobile_money'
                AND created_at <= NOW() - INTERVAL 5 MINUTE
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching pending status checks: " . $e->getMessage());
            return [];
        }
    }
}
?>
