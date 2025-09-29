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
            $this->baseUrl = 'https://payproxyapi.hubtel.com';
        } else {
            $this->baseUrl = 'https://payproxyapi.hubtel.com'; // Same for sandbox
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
            
            // Use the correct Hubtel PayProxy API endpoint
            $endpoints = [
                "/items/initiate", // Primary endpoint from church management system
                "/items/ussd/initiate", // Alternative USSD-specific endpoint
                "/ussd/initiate", // Another possible USSD endpoint
                "/receive/ussd", // Fallback endpoint
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
     * Handle USSD session interactions
     * This method processes USSD menu interactions when users dial your shortcode
     */
    public function handleUSSDSession($webhookData) {
        try {
            // Extract USSD session data
            $sessionId = $webhookData['SessionId'] ?? '';
            $serviceCode = $webhookData['ServiceCode'] ?? '';
            $phoneNumber = $webhookData['Mobile'] ?? '';
            $text = $webhookData['Text'] ?? '';
            $sequence = $webhookData['Sequence'] ?? 1;
            $type = $webhookData['Type'] ?? 'initiation';
            
            error_log("USSD Session - Phone: $phoneNumber, Text: '$text', Sequence: $sequence, Type: $type");
            
            // Handle different USSD menu levels
            switch ($sequence) {
                case 1:
                    // First interaction - show main menu
                    return $this->showMainMenu();
                    
                case 2:
                    // User selected an option from main menu
                    return $this->handleMainMenuSelection($text, $phoneNumber);
                    
                case 3:
                    // Handle sub-menu selections
                    return $this->handleSubMenuSelection($text, $phoneNumber, $sessionId);
                    
                default:
                    // Handle deeper menu levels or end session
                    return $this->handleAdvancedMenus($text, $phoneNumber, $sessionId, $sequence);
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
    private function showMainMenu() {
        return [
            'Type' => 'Response',
            'Message' => "Welcome to E-Cast Voting!\n\n1. Vote for Nominee\n2. Check Event Status\n3. Help\n\nEnter your choice:"
        ];
    }
    
    /**
     * Handle main menu selection
     */
    private function handleMainMenuSelection($choice, $phoneNumber) {
        switch (trim($choice)) {
            case '1':
                return $this->showActiveEvents();
                
            case '2':
                return [
                    'Type' => 'Response',
                    'Message' => "Enter event ID to check status:"
                ];
                
            case '3':
                return [
                    'Type' => 'Release',
                    'Message' => "E-Cast Voting Help:\n- Dial this code to vote\n- Follow menu prompts\n- Standard rates apply\n\nThank you!"
                ];
                
            default:
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid selection. Please try again."
                ];
        }
    }
    
    /**
     * Show active events for voting
     */
    private function showActiveEvents() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, title, vote_cost 
                FROM events 
                WHERE status = 'active' 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($events)) {
                return [
                    'Type' => 'Release',
                    'Message' => "No active voting events at the moment. Please check back later."
                ];
            }
            
            $message = "Active Events:\n\n";
            foreach ($events as $index => $event) {
                $cost = number_format($event['vote_cost'], 2);
                $message .= ($index + 1) . ". " . substr($event['title'], 0, 30) . "\n   (GHS $cost per vote)\n\n";
            }
            $message .= "Enter event number:";
            
            return [
                'Type' => 'Response',
                'Message' => $message
            ];
            
        } catch (Exception $e) {
            error_log("Error fetching events: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Unable to load events. Please try again later."
            ];
        }
    }
    
    /**
     * Handle sub-menu selections
     */
    private function handleSubMenuSelection($choice, $phoneNumber, $sessionId) {
        // This would handle event selection and show nominees
        // For now, provide a simple response
        return [
            'Type' => 'Release',
            'Message' => "Feature coming soon! Please use our web platform at your event organizer's website to vote."
        ];
    }
    
    /**
     * Handle advanced menu levels
     */
    private function handleAdvancedMenus($text, $phoneNumber, $sessionId, $sequence) {
        return [
            'Type' => 'Release',
            'Message' => "Session completed. Thank you for using E-Cast!"
        ];
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
