<?php
/**
 * Hubtel USSD Service
 * Handles USSD payments and interactive USSD applications for voting
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
     * 
     * @param float $amount Payment amount
     * @param string $phoneNumber Customer phone number
     * @param string $description Payment description
     * @param string $clientReference Unique transaction reference
     * @param array $metadata Additional data (event_id, nominee_id, etc.)
     * @return array Payment response with USSD code
     */
    public function generateUSSDPayment($amount, $phoneNumber, $description, $clientReference, $metadata = []) {
        try {
            $formattedPhone = $this->formatPhoneNumber($phoneNumber);
            
            $payload = [
                'CustomerName' => $metadata['voter_name'] ?? 'E-Cast Voter',
                'CustomerMsisdn' => $formattedPhone,
                'CustomerEmail' => $metadata['email'] ?? '',
                'Channel' => 'ussd-gh',
                'Amount' => (float)$amount,
                'PrimaryCallbackUrl' => $this->getCallbackUrl(),
                'Description' => $description,
                'ClientReference' => $clientReference,
                'Metadata' => json_encode($metadata)
            ];
            
            $endpoint = "/merchantaccount/merchants/{$this->posId}/receive/ussd";
            $response = $this->makeRequest('POST', $endpoint, $payload);
            
            if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] === '0000') {
                return [
                    'success' => true,
                    'ussd_code' => $response['Data']['USSDCode'] ?? '',
                    'payment_token' => $response['Data']['CheckoutId'] ?? $clientReference,
                    'transaction_id' => $response['Data']['TransactionId'] ?? '',
                    'instructions' => $this->generateUSSDInstructions($response['Data']['USSDCode'] ?? ''),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                    'amount' => $amount,
                    'phone_number' => $formattedPhone
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['Message'] ?? 'Failed to generate USSD payment code',
                    'error_code' => $response['ResponseCode'] ?? 'UNKNOWN_ERROR'
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
     * Create interactive USSD application for voting
     * 
     * @param string $shortCode USSD short code (e.g., *123*456#)
     * @param string $appName Application name
     * @param string $webhookUrl Webhook URL for USSD interactions
     * @return array Application creation response
     */
    public function createUSSDApplication($shortCode, $appName, $webhookUrl) {
        try {
            $payload = [
                'Name' => $appName,
                'ShortCode' => $shortCode,
                'WebhookUrl' => $webhookUrl,
                'Description' => 'E-Cast Voting USSD Application',
                'Status' => 'Active'
            ];
            
            $endpoint = "/ussd/applications";
            $response = $this->makeRequest('POST', $endpoint, $payload);
            
            if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] === '0000') {
                // Store application details in database
                $this->storeUSSDApplication($shortCode, $appName, $webhookUrl, $response['Data']['ApplicationId'] ?? '');
                
                return [
                    'success' => true,
                    'application_id' => $response['Data']['ApplicationId'] ?? '',
                    'short_code' => $shortCode,
                    'webhook_url' => $webhookUrl,
                    'status' => 'active'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['Message'] ?? 'Failed to create USSD application',
                    'error_code' => $response['ResponseCode'] ?? 'UNKNOWN_ERROR'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Hubtel USSD Application Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'USSD application creation failed. Please try again.',
                'error_code' => 'SYSTEM_ERROR'
            ];
        }
    }
    
    /**
     * Handle USSD session interactions
     * 
     * @param array $ussdData USSD session data from Hubtel
     * @return array USSD response
     */
    public function handleUSSDSession($ussdData) {
        try {
            $sessionId = $ussdData['SessionId'] ?? '';
            $phoneNumber = $ussdData['Mobile'] ?? '';
            $userInput = $ussdData['Message'] ?? '';
            $sequence = $ussdData['Sequence'] ?? 1;
            
            // Get or create session
            $session = $this->getUSSDSession($sessionId, $phoneNumber);
            
            if ($sequence == 1) {
                // First interaction - show main menu
                return $this->showMainMenu($sessionId, $phoneNumber);
            } else {
                // Process user input based on current menu
                return $this->processUserInput($session, $userInput);
            }
            
        } catch (Exception $e) {
            error_log("USSD Session Error: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => 'Service temporarily unavailable. Please try again later.'
            ];
        }
    }
    
    /**
     * Show main USSD menu
     */
    private function showMainMenu($sessionId, $phoneNumber) {
        $this->updateUSSDSession($sessionId, $phoneNumber, 'main_menu', []);
        
        $menu = "Welcome to BaronCast Voting\n\n";
        $menu .= "1. Vote in Event\n";
        $menu .= "2. Check Results\n";
        $menu .= "3. Vote History\n";
        $menu .= "4. Help\n";
        $menu .= "0. Exit";
        
        return [
            'Type' => 'Response',
            'Message' => $menu
        ];
    }
    
    /**
     * Process user input based on current menu
     */
    private function processUserInput($session, $userInput) {
        $currentMenu = $session['current_menu'] ?? 'main_menu';
        $sessionData = json_decode($session['session_data'] ?? '{}', true);
        
        switch ($currentMenu) {
            case 'main_menu':
                return $this->handleMainMenuInput($session['session_id'], $session['phone_number'], $userInput);
                
            case 'event_selection':
                return $this->handleEventSelection($session['session_id'], $session['phone_number'], $userInput, $sessionData);
                
            case 'category_selection':
                return $this->handleCategorySelection($session['session_id'], $session['phone_number'], $userInput, $sessionData);
                
            case 'nominee_selection':
                return $this->handleNomineeSelection($session['session_id'], $session['phone_number'], $userInput, $sessionData);
                
            case 'vote_count':
                return $this->handleVoteCount($session['session_id'], $session['phone_number'], $userInput, $sessionData);
                
            case 'payment_confirmation':
                return $this->handlePaymentConfirmation($session['session_id'], $session['phone_number'], $userInput, $sessionData);
                
            default:
                return $this->showMainMenu($session['session_id'], $session['phone_number']);
        }
    }
    
    /**
     * Handle main menu input
     */
    private function handleMainMenuInput($sessionId, $phoneNumber, $input) {
        switch ($input) {
            case '1':
                return $this->showEventSelection($sessionId, $phoneNumber);
            case '2':
                return $this->showResults($sessionId, $phoneNumber);
            case '3':
                return $this->showVoteHistory($sessionId, $phoneNumber);
            case '4':
                return $this->showHelp($sessionId, $phoneNumber);
            case '0':
                return [
                    'Type' => 'Release',
                    'Message' => 'Thank you for using BaronCast Voting!'
                ];
            default:
                return [
                    'Type' => 'Response',
                    'Message' => 'Invalid option. Please try again.\n\n' . $this->getMainMenuText()
                ];
        }
    }
    
    /**
     * Show active events for voting
     */
    private function showEventSelection($sessionId, $phoneNumber) {
        $stmt = $this->db->query("SELECT id, title FROM events WHERE status = 'active' ORDER BY created_at DESC LIMIT 9");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            return [
                'Type' => 'Release',
                'Message' => 'No active voting events available at the moment.'
            ];
        }
        
        $this->updateUSSDSession($sessionId, $phoneNumber, 'event_selection', ['events' => $events]);
        
        $menu = "Select Event to Vote:\n\n";
        foreach ($events as $index => $event) {
            $menu .= ($index + 1) . ". " . substr($event['title'], 0, 30) . "\n";
        }
        $menu .= "0. Back to Main Menu";
        
        return [
            'Type' => 'Response',
            'Message' => $menu
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
     */
    private function getCallbackUrl() {
        $baseUrl = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $baseUrl .= $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $baseUrl . '/baroncast/webhooks/hubtel-ussd-callback.php';
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
    
    /**
     * Get or create USSD session
     */
    private function getUSSDSession($sessionId, $phoneNumber) {
        $stmt = $this->db->prepare("SELECT * FROM ussd_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            // Create new session
            $stmt = $this->db->prepare("
                INSERT INTO ussd_sessions (session_id, phone_number, current_menu, session_data, expires_at) 
                VALUES (?, ?, 'main_menu', '{}', DATE_ADD(NOW(), INTERVAL 5 MINUTE))
            ");
            $stmt->execute([$sessionId, $phoneNumber]);
            
            return [
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
                'current_menu' => 'main_menu',
                'session_data' => '{}'
            ];
        }
        
        return $session;
    }
    
    /**
     * Update USSD session
     */
    private function updateUSSDSession($sessionId, $phoneNumber, $currentMenu, $sessionData) {
        $stmt = $this->db->prepare("
            UPDATE ussd_sessions 
            SET current_menu = ?, session_data = ?, expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE session_id = ?
        ");
        $stmt->execute([$currentMenu, json_encode($sessionData), $sessionId]);
    }
    
    /**
     * Store USSD application details
     */
    private function storeUSSDApplication($shortCode, $appName, $webhookUrl, $applicationId) {
        $stmt = $this->db->prepare("
            INSERT INTO ussd_applications (app_name, short_code, webhook_url, application_id, status) 
            VALUES (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE 
            app_name = VALUES(app_name), 
            webhook_url = VALUES(webhook_url),
            application_id = VALUES(application_id),
            status = VALUES(status)
        ");
        $stmt->execute([$appName, $shortCode, $webhookUrl, $applicationId]);
    }
    
    /**
     * Get main menu text
     */
    private function getMainMenuText() {
        return "Welcome to BaronCast Voting\n\n1. Vote in Event\n2. Check Results\n3. Vote History\n4. Help\n0. Exit";
    }
}
?>
