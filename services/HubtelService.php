<?php
/**
 * Hubtel Complete Integration Service
 * Handles SMS, USSD, and Mobile Money payments for Ghana market
 */

class HubtelService {
    private $clientId;
    private $clientSecret;
    private $apiKey;
    private $environment;
    private $baseUrl;
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        $this->loadSettings();
        $this->setBaseUrl();
    }
    
    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $this->clientId = $settings['hubtel_client_id'] ?? '';
        $this->clientSecret = $settings['hubtel_client_secret'] ?? '';
        $this->apiKey = $settings['hubtel_api_key'] ?? '';
        $this->environment = $settings['hubtel_environment'] ?? 'sandbox';
    }
    
    private function setBaseUrl() {
        if ($this->environment === 'production') {
            $this->baseUrl = 'https://api.hubtel.com/v1';
        } else {
            $this->baseUrl = 'https://sandbox-api.hubtel.com/v1';
        }
    }
    
    /**
     * Send SMS via Hubtel
     */
    public function sendSMS($to, $message, $senderId = null) {
        $senderId = $senderId ?: $this->getSetting('hubtel_sender_id', 'E-Cast');
        
        $data = [
            'From' => $senderId,
            'To' => $this->formatPhoneNumber($to),
            'Content' => $message,
            'Type' => 0,
            'RegisteredDelivery' => true
        ];
        
        return $this->makeRequest('/messages/send', 'POST', $data);
    }
    
    /**
     * Send OTP SMS
     */
    public function sendOTP($phoneNumber, $otp) {
        $message = "Your E-Cast Voting verification code is: $otp. Valid for 5 minutes. Do not share this code.";
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Initialize Online Checkout Payment (New Hubtel API)
     */
    public function initializeOnlineCheckout($amount, $description, $reference, $payeeName = null, $payeeMobileNumber = null, $payeeEmail = null) {
        $merchantAccountNumber = $this->getSetting('hubtel_merchant_account', '');
        
        if (empty($merchantAccountNumber)) {
            return [
                'success' => false,
                'message' => 'Merchant Account Number not configured'
            ];
        }
        
        $data = [
            'totalAmount' => (float)$amount,
            'description' => $description,
            'callbackUrl' => $this->getCallbackUrl(),
            'returnUrl' => $this->getReturnUrl(),
            'merchantAccountNumber' => $merchantAccountNumber,
            'cancellationUrl' => $this->getCancellationUrl(),
            'clientReference' => $reference
        ];
        
        // Add optional payee information
        if ($payeeName) $data['payeeName'] = $payeeName;
        if ($payeeMobileNumber) $data['payeeMobileNumber'] = $this->formatPhoneNumber($payeeMobileNumber);
        if ($payeeEmail) $data['payeeEmail'] = $payeeEmail;
        
        return $this->makeCheckoutRequest('/items/initiate', 'POST', $data);
    }
    
    /**
     * Check Transaction Status
     */
    public function checkTransactionStatus($clientReference) {
        $merchantAccountNumber = $this->getSetting('hubtel_merchant_account', '');
        
        if (empty($merchantAccountNumber)) {
            return [
                'success' => false,
                'message' => 'Merchant Account Number not configured'
            ];
        }
        
        $endpoint = "/transactions/{$merchantAccountNumber}/status?clientReference=" . urlencode($clientReference);
        return $this->makeStatusRequest($endpoint, 'GET');
    }
    
    /**
     * Initialize USSD Payment
     */
    public function initializeUSSDPayment($amount, $phoneNumber, $description, $reference) {
        $data = [
            'Amount' => $amount,
            'PhoneNumber' => $this->formatPhoneNumber($phoneNumber),
            'Network' => $this->detectNetwork($phoneNumber),
            'Description' => $description,
            'CallbackUrl' => $this->getCallbackUrl(),
            'ClientReference' => $reference,
            'Channel' => 'ussd-gh'
        ];
        
        $response = $this->makeRequest('/merchantaccount/merchants/' . $this->clientId . '/receive/ussd', 'POST', $data);
        
        if ($response['success'] && isset($response['data']['ussd_code'])) {
            return [
                'success' => true,
                'ussd_code' => $response['data']['ussd_code'],
                'payment_token' => $response['data']['payment_token'] ?? $reference,
                'instructions' => $this->generateUSSDInstructions($response['data']['ussd_code']),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
            ];
        }
        
        return $response;
    }
    
    /**
     * Initialize Mobile Money Payment (Direct Deduction)
     */
    public function initializeMobileMoneyPayment($amount, $phoneNumber, $description, $reference) {
        $merchantAccountNumber = $this->getSetting('hubtel_merchant_account', '');
        
        if (empty($merchantAccountNumber)) {
            return [
                'success' => false,
                'message' => 'Merchant Account Number not configured'
            ];
        }
        
        $data = [
            'CustomerName' => 'Voter',
            'CustomerMsisdn' => $this->formatPhoneNumber($phoneNumber),
            'CustomerEmail' => '',
            'Channel' => 'momo-subscriber-' . $this->detectNetwork($phoneNumber),
            'Amount' => (float)$amount,
            'PrimaryCallbackUrl' => $this->getCallbackUrl(),
            'Description' => $description,
            'ClientReference' => $reference
        ];
        
        $endpoint = '/merchantaccount/merchants/' . $merchantAccountNumber . '/receive/mobilemoney';
        return $this->makeRequest($endpoint, 'POST', $data);
    }

    /**
     * Check Payment Status
     */
    public function checkPaymentStatus($paymentToken) {
        return $this->makeRequest('/merchantaccount/merchants/' . $this->clientId . '/transactions/' . $paymentToken, 'GET');
    }
    
    /**
     * Process Webhook Callback
     */
    public function processWebhook($payload) {
        // Verify webhook signature if available
        if (!$this->verifyWebhookSignature($payload)) {
            return ['success' => false, 'message' => 'Invalid webhook signature'];
        }
        
        $data = json_decode($payload, true);
        
        if (!$data || !isset($data['Data'])) {
            return ['success' => false, 'message' => 'Invalid webhook payload'];
        }
        
        $paymentData = $data['Data'];
        $reference = $paymentData['ClientReference'] ?? '';
        $status = $paymentData['Status'] ?? '';
        $amount = $paymentData['Amount'] ?? 0;
        
        // Update transaction in database
        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET payment_status = ?, amount = ?, updated_at = NOW() 
            WHERE reference = ?
        ");
        
        $paymentStatus = $this->mapHubtelStatus($status);
        $stmt->execute([$paymentStatus, $amount, $reference]);
        
        // If payment successful, create vote
        if ($paymentStatus === 'completed') {
            $this->createVoteFromTransaction($reference);
        }
        
        return [
            'success' => true,
            'status' => $paymentStatus,
            'reference' => $reference,
            'amount' => $amount
        ];
    }
    
    /**
     * Generate USSD Instructions
     */
    private function generateUSSDInstructions($ussdCode) {
        return [
            "Dial $ussdCode on your phone",
            "Follow the prompts to complete payment",
            "Enter your mobile money PIN when requested",
            "You will receive SMS confirmation when payment is complete"
        ];
    }
    
    /**
     * Detect Mobile Network from Phone Number
     */
    private function detectNetwork($phoneNumber) {
        $phone = $this->formatPhoneNumber($phoneNumber);
        
        // Ghana network prefixes
        $networks = [
            'mtn' => ['024', '025', '053', '054', '055', '059'],
            'vodafone' => ['020', '050'],
            'airteltigo' => ['027', '028', '057', '058', '026', '056']
        ];
        
        $prefix = substr($phone, -9, 3); // Get first 3 digits after country code
        
        foreach ($networks as $network => $prefixes) {
            if (in_array($prefix, $prefixes)) {
                return $network;
            }
        }
        
        return 'mtn'; // Default to MTN
    }
    
    /**
     * Format Phone Number for Ghana (+233)
     */
    private function formatPhoneNumber($phoneNumber) {
        $phone = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        if (substr($phone, 0, 1) === '0') {
            return '+233' . substr($phone, 1);
        } elseif (substr($phone, 0, 4) !== '+233') {
            return '+233' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Make HTTP Request to Hubtel API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/json'
        ];
        
        // Log request details for debugging
        error_log("Hubtel API Request: $method $url");
        if ($data) {
            error_log("Hubtel API Data: " . json_encode($data));
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => true
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log response for debugging
        error_log("Hubtel API Response Code: $httpCode");
        error_log("Hubtel API Response: " . $response);
        
        if ($error) {
            error_log("Hubtel API cURL Error: " . $error);
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        // Better error handling for API responses
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'data' => $decodedResponse,
                'message' => $decodedResponse['Message'] ?? 'Success'
            ];
        } else {
            // Handle error responses
            $errorMessage = 'Request failed';
            
            if ($decodedResponse) {
                if (isset($decodedResponse['Message'])) {
                    $errorMessage = $decodedResponse['Message'];
                } elseif (isset($decodedResponse['error'])) {
                    $errorMessage = $decodedResponse['error'];
                } elseif (isset($decodedResponse['errors']) && is_array($decodedResponse['errors'])) {
                    $errorMessage = implode(', ', $decodedResponse['errors']);
                }
            }
            
            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => $decodedResponse,
                'message' => $errorMessage
            ];
        }
    }
    
    /**
     * Make HTTP Request to Hubtel Checkout API
     */
    private function makeCheckoutRequest($endpoint, $method = 'GET', $data = null) {
        $url = 'https://payproxyapi.hubtel.com' . $endpoint;
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        error_log("Hubtel Checkout API Request: $method $url");
        if ($data) {
            error_log("Hubtel Checkout API Data: " . json_encode($data));
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Hubtel Checkout API Response Code: $httpCode");
        error_log("Hubtel Checkout API Response: " . $response);
        
        if ($error) {
            error_log("Hubtel Checkout API cURL Error: " . $error);
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'data' => $decodedResponse,
                'message' => $decodedResponse['status'] ?? 'Success'
            ];
        } else {
            $errorMessage = 'Request failed';
            if ($decodedResponse && isset($decodedResponse['message'])) {
                $errorMessage = $decodedResponse['message'];
            }
            
            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => $decodedResponse,
                'message' => $errorMessage
            ];
        }
    }
    
    /**
     * Make HTTP Request to Hubtel Status API
     */
    private function makeStatusRequest($endpoint, $method = 'GET') {
        $url = 'https://api-txnstatus.hubtel.com' . $endpoint;
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'Content-Type: application/json'
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
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'message' => $decodedResponse['message'] ?? ($httpCode >= 200 && $httpCode < 300 ? 'Success' : 'Request failed')
        ];
    }
    
    /**
     * Get Callback URL for webhooks
     */
    private function getCallbackUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/webhooks/hubtel-checkout-callback.php';
    }
    
    /**
     * Get Return URL after successful payment
     */
    private function getReturnUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/voter/payment-success.php?ref={ClientReference}&status={Status}';
    }
    
    /**
     * Get Cancellation URL after cancelled payment
     */
    private function getCancellationUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/voter/payment-cancelled.php?ref={ClientReference}';
    }
    
    /**
     * Verify Webhook Signature
     */
    private function verifyWebhookSignature($payload) {
        // Get signature from headers
        $signature = $_SERVER['HTTP_X_HUBTEL_SIGNATURE'] ?? '';
        
        if (empty($signature)) {
            error_log("Hubtel webhook: No signature provided");
            return false;
        }
        
        // Get webhook secret from settings
        $webhookSecret = $this->getSetting('hubtel_webhook_secret', '');
        
        if (empty($webhookSecret)) {
            error_log("Hubtel webhook: No webhook secret configured");
            // In development, allow webhooks without signature verification
            return $this->environment === 'sandbox';
        }
        
        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        // Compare signatures
        if (!hash_equals($expectedSignature, $signature)) {
            error_log("Hubtel webhook: Signature verification failed");
            return false;
        }
        
        return true;
    }
    
    /**
     * Map Hubtel status to internal status
     */
    private function mapHubtelStatus($hubtelStatus) {
        $statusMap = [
            'Success' => 'completed',
            'Paid' => 'completed',
            'Failed' => 'failed',
            'Cancelled' => 'cancelled',
            'Pending' => 'pending'
        ];
        
        return $statusMap[$hubtelStatus] ?? 'pending';
    }
    
    /**
     * Create vote from successful transaction
     */
    private function createVoteFromTransaction($reference) {
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE reference = ? AND payment_status = 'completed'
        ");
        $stmt->execute([$reference]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            $stmt = $this->db->prepare("
                INSERT INTO votes (event_id, nominee_id, voter_phone, payment_reference, payment_status, amount, voted_at)
                VALUES (?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $transaction['event_id'],
                $transaction['nominee_id'],
                $transaction['voter_phone'],
                $reference,
                $transaction['amount']
            ]);
        }
    }
    
    /**
     * Get setting value
     */
    private function getSetting($key, $default = '') {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }
}
?>
