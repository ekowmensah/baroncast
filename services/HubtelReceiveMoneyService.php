<?php
/**
 * Hubtel Direct Receive Money Service
 * Implementation of Hubtel's Direct Receive Money API for mobile money payments
 * 
 * Features:
 * - Direct mobile money collection from MTN, Telecel, AirtelTigo
 * - Asynchronous payment processing with callbacks
 * - Mandatory transaction status checking
 * - Comprehensive error handling
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
    private function setUrls() {
        if ($this->environment === 'production') {
            $this->baseUrl = 'https://rmp.hubtel.com';
            $this->statusCheckUrl = 'https://api-txnstatus.hubtel.com';
        } else {
            // Sandbox URLs (if different)
            $this->baseUrl = 'https://rmp.hubtel.com';
            $this->statusCheckUrl = 'https://api-txnstatus.hubtel.com';
        }
    }
    
    /**
     * Initiate a direct mobile money payment
     * 
     * @param float $amount Payment amount (2 decimal places)
     * @param string $phone Customer phone number (international format)
     * @param string $description Payment description
     * @param string $clientReference Unique client reference (max 36 chars)
     * @param string $customerName Optional customer name
     * @param string $customerEmail Optional customer email
     * @return array API response
     */
    public function initiatePayment($amount, $phone, $description, $clientReference, $customerName = '', $customerEmail = '') {
        try {
            // Validate inputs
            $validation = $this->validatePaymentRequest($amount, $phone, $description, $clientReference);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Format phone number and detect channel
            $formattedPhone = $this->formatPhoneNumber($phone);
            $channel = $this->detectChannel($formattedPhone);
            
            if (!$channel) {
                return [
                    'success' => false,
                    'message' => 'Unsupported mobile network. Please use MTN, Telecel, or AirtelTigo.',
                    'code' => 'INVALID_CHANNEL'
                ];
            }
            
            // Prepare request payload
            $payload = [
                'CustomerName' => $customerName ?: 'E-Cast Voter',
                'CustomerMsisdn' => $formattedPhone,
                'CustomerEmail' => $customerEmail,
                'Channel' => $channel,
                'Amount' => (float)$amount,
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
                'message' => 'Payment initiation failed. Please try again.',
                'code' => 'SYSTEM_ERROR'
            ];
        }
    }
    
    /**
     * Process webhook callback from Hubtel
     * 
     * @param array $callbackData Raw callback data from Hubtel
     * @return array Processing result
     */
    public function processCallback($callbackData) {
        try {
            error_log("Hubtel callback received: " . json_encode($callbackData));
            
            $responseCode = $callbackData['ResponseCode'] ?? '';
            $message = $callbackData['Message'] ?? '';
            $data = $callbackData['Data'] ?? [];
            
            $clientReference = $data['ClientReference'] ?? '';
            $hubtelTransactionId = $data['TransactionId'] ?? '';
            $externalTransactionId = $data['ExternalTransactionId'] ?? '';
            $amount = (float)($data['Amount'] ?? 0);
            $charges = (float)($data['Charges'] ?? 0);
            $amountCharged = (float)($data['AmountCharged'] ?? 0);
            $paymentDate = $data['PaymentDate'] ?? date('Y-m-d H:i:s');
            
            // Update transaction in database
            $updateResult = $this->updateTransactionFromCallback(
                $clientReference,
                $responseCode,
                $hubtelTransactionId,
                $externalTransactionId,
                $amount,
                $charges,
                $amountCharged,
                $message,
                $callbackData
            );
            
            return [
                'success' => true,
                'processed' => $updateResult,
                'status' => $responseCode === self::SUCCESS ? 'completed' : 'failed',
                'client_reference' => $clientReference
            ];
            
        } catch (Exception $e) {
            error_log("Hubtel callback processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Callback processing failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check transaction status (mandatory after 5+ minutes)
     * 
     * @param string $clientReference Client reference to check
     * @return array Status check result
     */
    public function checkTransactionStatus($clientReference) {
        try {
            $endpoint = "/transactions/{$this->posId}/status";
            $queryParams = ['clientReference' => $clientReference];
            
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
            return [
                'success' => false,
                'message' => 'API request failed',
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
     * Update transaction from callback
     */
    private function updateTransactionFromCallback($clientReference, $responseCode, $hubtelTransactionId, 
                                                  $externalTransactionId, $amount, $charges, $amountCharged, 
                                                  $message, $fullCallback) {
        try {
            $status = $responseCode === self::SUCCESS ? 'completed' : 'failed';
            
            $stmt = $this->db->prepare("
                UPDATE transactions 
                SET 
                    status = ?,
                    hubtel_transaction_id = ?,
                    external_transaction_id = ?,
                    payment_charges = ?,
                    amount_charged = ?,
                    payment_response = ?,
                    updated_at = NOW()
                WHERE reference = ? OR transaction_id = ?
            ");
            
            $result = $stmt->execute([
                $status,
                $hubtelTransactionId,
                $externalTransactionId,
                $charges,
                $amountCharged,
                json_encode($fullCallback),
                $clientReference,
                $clientReference
            ]);
            
            // If payment successful, create vote records
            if ($status === 'completed' && $result) {
                $this->createVoteRecordsFromTransactionInternal($clientReference);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Transaction update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create vote records for successful payment (public wrapper)
     * 
     * @param string $clientReference Transaction reference
     * @return bool Success status
     */
    public function createVoteRecordsFromTransaction($clientReference) {
        return $this->createVoteRecordsFromTransactionInternal($clientReference);
    }
    
    /**
     * Create vote records for successful payment
     */
    private function createVoteRecordsFromTransactionInternal($clientReference) {
        try {
            // Get transaction details
            $stmt = $this->db->prepare("
                SELECT * FROM transactions 
                WHERE (reference = ? OR transaction_id = ?) AND status = 'completed'
            ");
            $stmt->execute([$clientReference, $clientReference]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                error_log("Transaction not found for vote creation: " . $clientReference);
                return false;
            }
            
            // Check how many votes already exist for this transaction
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM votes 
                WHERE transaction_id = ? OR payment_reference = ?
            ");
            $stmt->execute([$transaction['id'], $clientReference]);
            $existing_votes = $stmt->fetchColumn();
            
            $voteCount = (int)$transaction['vote_count'];
            $votes_needed = $voteCount - $existing_votes;
            
            if ($votes_needed <= 0) {
                error_log("Votes already exist for transaction: " . $clientReference . " (existing: $existing_votes, needed: $voteCount)");
                return true; // Not an error - votes already created
            }
            
            // Get event_id and category_id from the nominee
            $stmt = $this->db->prepare("
                SELECT n.category_id, c.event_id 
                FROM nominees n 
                JOIN categories c ON n.category_id = c.id 
                WHERE n.id = ?
            ");
            $stmt->execute([$transaction['nominee_id']]);
            $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nominee_data) {
                error_log("Could not find nominee data for transaction: " . $clientReference);
                return false;
            }
            
            // Create the needed vote records
            $nomineeId = $transaction['nominee_id'];
            $voterPhone = $transaction['voter_phone'];
            $transactionId = $transaction['id'];
            
            for ($i = 0; $i < $votes_needed; $i++) {
                $stmt = $this->db->prepare("
                    INSERT INTO votes (
                        event_id, category_id, nominee_id, voter_phone, 
                        transaction_id, payment_method, payment_reference, 
                        payment_status, amount, voted_at
                    ) VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, 'completed', ?, NOW())
                ");
                
                $stmt->execute([
                    $nominee_data['event_id'],
                    $nominee_data['category_id'],
                    $nomineeId,
                    $voterPhone,
                    $transactionId,
                    $clientReference,
                    $transaction['amount'] / $voteCount // Amount per vote
                ]);
            }
            
            error_log("Created $votes_needed vote(s) for transaction: " . $clientReference . " (total votes now: $voteCount)");
            return true;
            
        } catch (Exception $e) {
            error_log("Vote creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get transactions pending status check (older than 5 minutes)
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