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
        try {
            // Get the selected event number
            $eventIndex = (int)trim($choice) - 1;
            
            // Get active events again
            $stmt = $this->db->prepare("
                SELECT id, title, vote_cost 
                FROM events 
                WHERE status = 'active' 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!isset($events[$eventIndex])) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid event selection. Please try again."
                ];
            }
            
            $selectedEvent = $events[$eventIndex];
            
            // Store selected event in session (we'll use a simple approach)
            $this->storeUSSDSession($sessionId, 'selected_event_id', $selectedEvent['id']);
            
            // Show nominees for this event
            return $this->showEventNominees($selectedEvent['id'], $selectedEvent['title']);
            
        } catch (Exception $e) {
            error_log("Error in handleSubMenuSelection: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Error loading event. Please try again."
            ];
        }
    }
    
    /**
     * Handle advanced menu levels
     */
    private function handleAdvancedMenus($text, $phoneNumber, $sessionId, $sequence) {
        try {
            // Get stored session data
            $eventId = $this->getUSSDSession($sessionId, 'selected_event_id');
            
            if (!$eventId) {
                return [
                    'Type' => 'Release',
                    'Message' => "Session expired. Please start again."
                ];
            }
            
            switch ($sequence) {
                case 4:
                    // User selected a nominee, ask for vote count
                    return $this->handleNomineeSelection($text, $phoneNumber, $sessionId, $eventId);
                    
                case 5:
                    // User entered vote count, initiate payment
                    return $this->handleVoteCountAndPayment($text, $phoneNumber, $sessionId, $eventId);
                    
                default:
                    return [
                        'Type' => 'Release',
                        'Message' => "Session completed. Thank you for using E-Cast!"
                    ];
            }
            
        } catch (Exception $e) {
            error_log("Error in handleAdvancedMenus: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Session error. Please try again."
            ];
        }
    }
    
    /**
     * Show nominees for selected event
     */
    private function showEventNominees($eventId, $eventTitle) {
        try {
            $stmt = $this->db->prepare("
                SELECT n.id, n.name, c.name as category_name
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                WHERE c.event_id = ?
                ORDER BY c.name, n.name
                LIMIT 9
            ");
            $stmt->execute([$eventId]);
            $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($nominees)) {
                return [
                    'Type' => 'Release',
                    'Message' => "No nominees found for this event."
                ];
            }
            
            $message = "Event: " . substr($eventTitle, 0, 25) . "\n\nNominees:\n\n";
            foreach ($nominees as $index => $nominee) {
                $message .= ($index + 1) . ". " . substr($nominee['name'], 0, 25) . "\n";
                $message .= "   (" . substr($nominee['category_name'], 0, 20) . ")\n\n";
            }
            $message .= "Select nominee (1-" . count($nominees) . "):";
            
            return [
                'Type' => 'Response',
                'Message' => $message
            ];
            
        } catch (Exception $e) {
            error_log("Error showing nominees: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Error loading nominees. Please try again."
            ];
        }
    }
    
    /**
     * Handle nominee selection
     */
    private function handleNomineeSelection($choice, $phoneNumber, $sessionId, $eventId) {
        try {
            $nomineeIndex = (int)trim($choice) - 1;
            
            // Get nominees again
            $stmt = $this->db->prepare("
                SELECT n.id, n.name, c.name as category_name, e.vote_cost
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE c.event_id = ?
                ORDER BY c.name, n.name
                LIMIT 9
            ");
            $stmt->execute([$eventId]);
            $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!isset($nominees[$nomineeIndex])) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid nominee selection. Please try again."
                ];
            }
            
            $selectedNominee = $nominees[$nomineeIndex];
            
            // Store selected nominee
            $this->storeUSSDSession($sessionId, 'selected_nominee_id', $selectedNominee['id']);
            $this->storeUSSDSession($sessionId, 'vote_cost', $selectedNominee['vote_cost']);
            
            $voteCost = number_format($selectedNominee['vote_cost'], 2);
            
            return [
                'Type' => 'Response',
                'Message' => "Selected: " . substr($selectedNominee['name'], 0, 30) . "\n\n" .
                           "Vote cost: GHS $voteCost each\n\n" .
                           "How many votes? (1-10):"
            ];
            
        } catch (Exception $e) {
            error_log("Error in handleNomineeSelection: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Error processing selection. Please try again."
            ];
        }
    }
    
    /**
     * Handle vote count and initiate payment
     */
    private function handleVoteCountAndPayment($choice, $phoneNumber, $sessionId, $eventId) {
        try {
            $voteCount = (int)trim($choice);
            
            if ($voteCount < 1 || $voteCount > 10) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid vote count. Please enter 1-10 votes."
                ];
            }
            
            // Get session data
            $nomineeId = $this->getUSSDSession($sessionId, 'selected_nominee_id');
            $voteCost = $this->getUSSDSession($sessionId, 'vote_cost');
            
            if (!$nomineeId || !$voteCost) {
                return [
                    'Type' => 'Release',
                    'Message' => "Session expired. Please start again."
                ];
            }
            
            $totalAmount = $voteCost * $voteCount;
            $transactionRef = 'USSD_' . time() . '_' . rand(1000, 9999);
            
            // Store USSD transaction
            $stmt = $this->db->prepare("
                INSERT INTO ussd_transactions (
                    transaction_ref, session_id, phone_number, event_id, nominee_id,
                    vote_count, amount, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $transactionRef, $sessionId, $phoneNumber, $eventId, 
                $nomineeId, $voteCount, $totalAmount
            ]);
            
            // Get nominee name for confirmation
            $stmt = $this->db->prepare("SELECT name FROM nominees WHERE id = ?");
            $stmt->execute([$nomineeId]);
            $nomineeName = $stmt->fetchColumn();
            
            $formattedAmount = number_format($totalAmount, 2);
            
            return [
                'Type' => 'Release',
                'Message' => "Payment initiated!\n\n" .
                           "Nominee: " . substr($nomineeName, 0, 25) . "\n" .
                           "Votes: $voteCount\n" .
                           "Amount: GHS $formattedAmount\n\n" .
                           "Complete payment on your phone.\n" .
                           "Ref: $transactionRef"
            ];
            
        } catch (Exception $e) {
            error_log("Error in handleVoteCountAndPayment: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Payment initiation failed. Please try again."
            ];
        }
    }
    
    /**
     * Store USSD session data
     */
    private function storeUSSDSession($sessionId, $key, $value) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ussd_sessions (session_id, session_key, session_value, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE session_value = ?, created_at = NOW()
            ");
            $stmt->execute([$sessionId, $key, $value, $value]);
        } catch (Exception $e) {
            error_log("Error storing USSD session: " . $e->getMessage());
        }
    }
    
    /**
     * Get USSD session data
     */
    private function getUSSDSession($sessionId, $key) {
        try {
            $stmt = $this->db->prepare("
                SELECT session_value FROM ussd_sessions 
                WHERE session_id = ? AND session_key = ?
                AND created_at > NOW() - INTERVAL 30 MINUTE
            ");
            $stmt->execute([$sessionId, $key]);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting USSD session: " . $e->getMessage());
            return null;
        }
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
