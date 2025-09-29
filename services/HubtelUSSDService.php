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
            // Extract USSD session data - try multiple field name variations
            $sessionId = $webhookData['SessionId'] ?? $webhookData['sessionId'] ?? '';
            $serviceCode = $webhookData['ServiceCode'] ?? $webhookData['serviceCode'] ?? '';
            $phoneNumber = $webhookData['Mobile'] ?? $webhookData['mobile'] ?? $webhookData['phoneNumber'] ?? '';
            $text = $webhookData['Message'] ?? $webhookData['Text'] ?? $webhookData['text'] ?? $webhookData['message'] ?? '';
            $sequence = $webhookData['Sequence'] ?? $webhookData['sequence'] ?? 1;
            $type = $webhookData['Type'] ?? $webhookData['type'] ?? 'initiation';
            
            // Enhanced logging for debugging
            error_log("=== USSD Session Debug ===");
            error_log("Full webhook data: " . json_encode($webhookData));
            error_log("Phone: $phoneNumber, Text: '$text', Sequence: $sequence, Type: $type");
            error_log("SessionId: $sessionId, ServiceCode: $serviceCode");
            
            // Simplified USSD flow - no menus, direct nominee code entry
            if ($sequence == 1 || $type == 'Initiation') {
                error_log("First interaction - showing nominee code prompt");
                return $this->showNomineeCodePrompt();
            }
            
            // Extract user input from Message field (remove USSD code prefix if present)
            $userInput = $text;
            if (strpos($text, '*') !== false) {
                // Extract the last part after the last *
                $parts = explode('*', $text);
                $userInput = end($parts);
                // Remove # if present
                $userInput = str_replace('#', '', $userInput);
            }
            
            error_log("Extracted user input: '$userInput' from text: '$text'");
            
            switch ($sequence) {
                case 2:
                    // User entered nominee shortcode
                    return $this->handleNomineeCodeEntry($userInput, $phoneNumber, $sessionId);
                    
                case 3:
                    // User entered vote count
                    return $this->handleVoteCountEntry($userInput, $phoneNumber, $sessionId);
                    
                default:
                    // End session
                    return [
                        'Type' => 'Release',
                        'Message' => "Session completed. Thank you for using BaronCast!"
                    ];
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
     * Show nominee code prompt
     */
    private function showNomineeCodePrompt() {
        return [
            'Type' => 'Response',
            'Message' => "Welcome to BaronCast!\n\nEnter nominee code to vote:\n(e.g. NOM001)"
        ];
    }
    
    /**
     * Handle nominee code entry
     */
    private function handleNomineeCodeEntry($nomineeCode, $phoneNumber, $sessionId) {
        try {
            $trimmedCode = strtoupper(trim($nomineeCode));
            error_log("Nominee code entry: '$trimmedCode'");
            
            // Validate and get nominee details
            $stmt = $this->db->prepare("
                SELECT n.id, n.name, n.short_code, e.id as event_id, e.title as event_title, 
                       COALESCE(e.vote_cost, 1.00) as vote_cost, c.name as category_name
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE n.short_code = ? AND e.status = 'active'
            ");
            $stmt->execute([$trimmedCode]);
            $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nominee) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid nominee code '$trimmedCode'. Please check and try again."
                ];
            }
            
            // Store nominee details in session
            $this->storeUSSDSession($sessionId, 'selected_nominee_id', $nominee['id']);
            $this->storeUSSDSession($sessionId, 'selected_event_id', $nominee['event_id']);
            $this->storeUSSDSession($sessionId, 'vote_cost', $nominee['vote_cost']);
            
            $voteCost = number_format($nominee['vote_cost'], 2);
            
            return [
                'Type' => 'Response',
                'Message' => "Selected: {$nominee['name']}\n" .
                           "Event: {$nominee['event_title']}\n" .
                           "Category: {$nominee['category_name']}\n\n" .
                           "Vote cost: GHS $voteCost each\n\n" .
                           "How many votes? (1-100):"
            ];
            
        } catch (Exception $e) {
            error_log("Error in handleNomineeCodeEntry: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Error processing nominee code. Please try again."
            ];
        }
    }
    
    /**
     * Handle vote count entry
     */
    private function handleVoteCountEntry($voteCount, $phoneNumber, $sessionId) {
        try {
            $votes = (int)trim($voteCount);
            error_log("Vote count entry: $votes");
            
            if ($votes < 1 || $votes > 100) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid vote count. Please enter 1-100 votes."
                ];
            }
            
            // Get session data
            $nomineeId = $this->getUSSDSession($sessionId, 'selected_nominee_id');
            $eventId = $this->getUSSDSession($sessionId, 'selected_event_id');
            $voteCost = $this->getUSSDSession($sessionId, 'vote_cost');
            
            if (!$nomineeId || !$eventId || !$voteCost) {
                return [
                    'Type' => 'Release',
                    'Message' => "Session expired. Please start again."
                ];
            }
            
            $totalAmount = $voteCost * $votes;
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
                $nomineeId, $votes, $totalAmount
            ]);
            
            // Get nominee name for confirmation
            $stmt = $this->db->prepare("SELECT name FROM nominees WHERE id = ?");
            $stmt->execute([$nomineeId]);
            $nomineeName = $stmt->fetchColumn();
            
            // Initiate actual Hubtel payment
            $description = "Vote for " . substr($nomineeName, 0, 20) . " ($votes votes)";
            $metadata = [
                'voter_phone' => $phoneNumber,
                'nominee_id' => $nomineeId,
                'event_id' => $eventId,
                'vote_count' => $votes,
                'transaction_ref' => $transactionRef
            ];
            
            error_log("Initiating Hubtel payment: Amount=$totalAmount, Phone=$phoneNumber, Ref=$transactionRef");
            
            $paymentResult = $this->generateUSSDPayment(
                $totalAmount,
                $phoneNumber,
                $description,
                $transactionRef,
                $metadata
            );
            
            if ($paymentResult && isset($paymentResult['success']) && $paymentResult['success']) {
                // Update transaction with Hubtel transaction ID
                if (isset($paymentResult['transactionId'])) {
                    $stmt = $this->db->prepare("
                        UPDATE ussd_transactions 
                        SET hubtel_transaction_id = ? 
                        WHERE transaction_ref = ?
                    ");
                    $stmt->execute([$paymentResult['transactionId'], $transactionRef]);
                }
                
                $formattedAmount = number_format($totalAmount, 2);
                
                return [
                    'Type' => 'Release',
                    'Message' => "Payment initiated!\n\n" .
                               "Nominee: " . substr($nomineeName, 0, 25) . "\n" .
                               "Votes: $votes\n" .
                               "Amount: GHS $formattedAmount\n\n" .
                               "Check your phone for payment prompt.\n" .
                               "Ref: $transactionRef"
                ];
            } else {
                // Payment initiation failed
                error_log("Payment initiation failed: " . json_encode($paymentResult));
                
                // Update transaction status
                $stmt = $this->db->prepare("
                    UPDATE ussd_transactions 
                    SET status = 'failed' 
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([$transactionRef]);
                
                return [
                    'Type' => 'Release',
                    'Message' => "Payment initiation failed. Please try again later.\n\nRef: $transactionRef"
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error in handleVoteCountEntry: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Payment initiation failed. Please try again."
            ];
        }
    }

    /**
     * Show active events for voting (DEPRECATED - keeping for compatibility)
     */
    private function showActiveEvents() {
        try {
            error_log("Fetching active events...");
            $stmt = $this->db->prepare("
                SELECT id, title, COALESCE(vote_cost, 1.00) as vote_cost 
                FROM events 
                WHERE status = 'active' 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($events) . " active events");
            
            if (empty($events)) {
                error_log("No active events found");
                return [
                    'Type' => 'Release',
                    'Message' => "No active voting events at the moment. Please check back later."
                ];
            }
            
            $message = "Active Events:\n\n";
            foreach ($events as $index => $event) {
                $cost = number_format($event['vote_cost'], 2);
                $message .= ($index + 1) . ". " . substr($event['title'], 0, 30) . "\n   (GHS $cost per vote)\n\n";
                error_log("Event " . ($index + 1) . ": " . $event['title'] . " (ID: " . $event['id'] . ")");
            }
            $message .= "Enter event number:";
            
            error_log("Returning events list message");
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
            
            // Store selected event in session
            $this->storeUSSDSession($sessionId, 'selected_event_id', $selectedEvent['id']);
            $this->storeUSSDSession($sessionId, 'current_page', '1');
            
            // Show nominees for this event (page 1)
            return $this->showEventNominees($selectedEvent['id'], $selectedEvent['title'], 1);
            
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
            $currentPage = (int)($this->getUSSDSession($sessionId, 'current_page') ?: 1);
            
            if (!$eventId) {
                return [
                    'Type' => 'Release',
                    'Message' => "Session expired. Please start again."
                ];
            }
            
            switch ($sequence) {
                case 4:
                    // Handle nominee selection or pagination
                    return $this->handleNomineePageSelection($text, $phoneNumber, $sessionId, $eventId, $currentPage);
                    
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
    private function showEventNominees($eventId, $eventTitle, $page = 1) {
        try {
            // Get total count of nominees
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                WHERE c.event_id = ?
            ");
            $stmt->execute([$eventId]);
            $totalNominees = $stmt->fetchColumn();
            
            if ($totalNominees == 0) {
                return [
                    'Type' => 'Release',
                    'Message' => "No nominees found for this event."
                ];
            }
            
            $nomineesPerPage = 5;
            $totalPages = ceil($totalNominees / $nomineesPerPage);
            $offset = ($page - 1) * $nomineesPerPage;
            
            // Get nominees for current page
            $stmt = $this->db->prepare("
                SELECT n.id, n.name
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                WHERE c.event_id = ?
                ORDER BY c.name, n.name
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$eventId, $nomineesPerPage, $offset]);
            $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build message
            $message = "Event: " . substr($eventTitle, 0, 25) . "\n";
            $message .= "Page $page of $totalPages\n\nNominees:\n\n";
            
            foreach ($nominees as $index => $nominee) {
                $nomineeNumber = $offset + $index + 1;
                $message .= $nomineeNumber . ". " . substr($nominee['name'], 0, 30) . "\n";
            }
            
            $message .= "\nSelect nominee (";
            $startNum = $offset + 1;
            $endNum = min($offset + $nomineesPerPage, $totalNominees);
            $message .= "$startNum-$endNum)";
            
            // Add navigation options
            if ($totalPages > 1) {
                $message .= "\n\nNavigation:";
                if ($page > 1) {
                    $message .= "\n8. Back";
                }
                if ($page < $totalPages) {
                    $message .= "\n9. Next";
                }
            }
            $message .= "\n0. Main Menu";
            
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
     * Handle nominee page selection (pagination + nominee selection)
     */
    private function handleNomineePageSelection($choice, $phoneNumber, $sessionId, $eventId, $currentPage) {
        try {
            $trimmedChoice = trim($choice);
            error_log("Nominee page selection: choice='$trimmedChoice', page=$currentPage");
            
            // Handle navigation options
            switch ($trimmedChoice) {
                case '0':
                    // Return to main menu
                    return $this->showMainMenu();
                    
                case '8':
                    // Go to previous page
                    if ($currentPage > 1) {
                        $newPage = $currentPage - 1;
                        $this->storeUSSDSession($sessionId, 'current_page', (string)$newPage);
                        
                        // Get event title
                        $stmt = $this->db->prepare("SELECT title FROM events WHERE id = ?");
                        $stmt->execute([$eventId]);
                        $eventTitle = $stmt->fetchColumn();
                        
                        return $this->showEventNominees($eventId, $eventTitle, $newPage);
                    }
                    break;
                    
                case '9':
                    // Go to next page
                    // Get total pages
                    $stmt = $this->db->prepare("
                        SELECT COUNT(*) as total
                        FROM nominees n
                        JOIN categories c ON n.category_id = c.id
                        WHERE c.event_id = ?
                    ");
                    $stmt->execute([$eventId]);
                    $totalNominees = $stmt->fetchColumn();
                    $totalPages = ceil($totalNominees / 5);
                    
                    if ($currentPage < $totalPages) {
                        $newPage = $currentPage + 1;
                        $this->storeUSSDSession($sessionId, 'current_page', (string)$newPage);
                        
                        // Get event title
                        $stmt = $this->db->prepare("SELECT title FROM events WHERE id = ?");
                        $stmt->execute([$eventId]);
                        $eventTitle = $stmt->fetchColumn();
                        
                        return $this->showEventNominees($eventId, $eventTitle, $newPage);
                    }
                    break;
                    
                default:
                    // Handle nominee selection
                    return $this->handleNomineeSelection($trimmedChoice, $phoneNumber, $sessionId, $eventId, $currentPage);
            }
            
            return [
                'Type' => 'Release',
                'Message' => "Invalid selection. Please try again."
            ];
            
        } catch (Exception $e) {
            error_log("Error in handleNomineePageSelection: " . $e->getMessage());
            return [
                'Type' => 'Release',
                'Message' => "Error processing selection. Please try again."
            ];
        }
    }

    /**
     * Handle nominee selection
     */
    private function handleNomineeSelection($choice, $phoneNumber, $sessionId, $eventId, $currentPage = 1) {
        try {
            $selectedNumber = (int)trim($choice);
            error_log("Nominee selection: selectedNumber=$selectedNumber, currentPage=$currentPage");
            
            // Get all nominees to find the correct one by absolute number
            $stmt = $this->db->prepare("
                SELECT n.id, n.name, COALESCE(e.vote_cost, 1.00) as vote_cost
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE c.event_id = ?
                ORDER BY c.name, n.name
            ");
            $stmt->execute([$eventId]);
            $allNominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if the selected number is valid
            if ($selectedNumber < 1 || $selectedNumber > count($allNominees)) {
                return [
                    'Type' => 'Release',
                    'Message' => "Invalid nominee selection. Please try again."
                ];
            }
            
            $selectedNominee = $allNominees[$selectedNumber - 1];
            
            // Store selected nominee
            $this->storeUSSDSession($sessionId, 'selected_nominee_id', $selectedNominee['id']);
            $this->storeUSSDSession($sessionId, 'vote_cost', $selectedNominee['vote_cost']);
            
            $voteCost = number_format($selectedNominee['vote_cost'], 2);
            
            return [
                'Type' => 'Response',
                'Message' => "Selected: " . substr($selectedNominee['name'], 0, 30) . "\n\n" .
                           "Vote cost: GHS $voteCost each\n\n" .
                           "How many votes? (1-100):"
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
            error_log("Storing USSD session: SessionId=$sessionId, Key=$key, Value=$value");
            
            // First check if table exists and what columns it has
            $stmt = $this->db->query("SHOW COLUMNS FROM ussd_sessions");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Available columns in ussd_sessions: " . implode(', ', $columns));
            
            // Use the correct column names based on what exists
            if (in_array('session_key', $columns)) {
                $stmt = $this->db->prepare("
                    INSERT INTO ussd_sessions (session_id, session_key, session_value, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE session_value = ?, created_at = NOW()
                ");
                $result = $stmt->execute([$sessionId, $key, $value, $value]);
            } else {
                // Use JSON format with the actual table structure
                $stmt = $this->db->prepare("
                    INSERT INTO ussd_sessions (session_id, session_data, created_at)
                    VALUES (?, JSON_OBJECT(?, ?), NOW())
                    ON DUPLICATE KEY UPDATE session_data = JSON_SET(COALESCE(session_data, '{}'), ?, ?), created_at = NOW()
                ");
                $result = $stmt->execute([$sessionId, $key, $value, '$.' . $key, $value]);
            }
            
            error_log("Session storage result: " . ($result ? "SUCCESS" : "FAILED"));
        } catch (Exception $e) {
            error_log("Error storing USSD session: " . $e->getMessage());
            
            // Simple fallback - store in a temporary way
            try {
                $stmt = $this->db->prepare("
                    CREATE TEMPORARY TABLE IF NOT EXISTS temp_ussd_sessions (
                        session_id VARCHAR(100),
                        session_key VARCHAR(50),
                        session_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (session_id, session_key)
                    )
                ");
                $stmt->execute();
                
                $stmt = $this->db->prepare("
                    INSERT INTO temp_ussd_sessions (session_id, session_key, session_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE session_value = ?, created_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$sessionId, $key, $value, $value]);
                error_log("Stored in temporary table as fallback");
            } catch (Exception $e2) {
                error_log("Fallback storage also failed: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * Get USSD session data
     */
    private function getUSSDSession($sessionId, $key) {
        try {
            error_log("Getting USSD session: SessionId=$sessionId, Key=$key");
            
            // Check table structure first
            $stmt = $this->db->query("SHOW COLUMNS FROM ussd_sessions");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('session_key', $columns)) {
                $stmt = $this->db->prepare("
                    SELECT session_value FROM ussd_sessions 
                    WHERE session_id = ? AND session_key = ?
                    AND created_at > NOW() - INTERVAL 30 MINUTE
                ");
                $stmt->execute([$sessionId, $key]);
                $value = $stmt->fetchColumn();
            } else {
                // Try JSON format - get the session_data and extract the key
                $stmt = $this->db->prepare("
                    SELECT session_data FROM ussd_sessions 
                    WHERE session_id = ?
                    AND created_at > NOW() - INTERVAL 30 MINUTE
                ");
                $stmt->execute([$sessionId]);
                $sessionData = $stmt->fetchColumn();
                
                error_log("Raw session data: " . ($sessionData ? $sessionData : "NULL"));
                
                if ($sessionData) {
                    $data = json_decode($sessionData, true);
                    $value = $data[$key] ?? null;
                    error_log("Decoded session data: " . json_encode($data));
                    error_log("Looking for key '$key', found: " . ($value ? $value : "NULL"));
                } else {
                    $value = null;
                }
            }
            
            // Fallback to temporary table
            if (!$value) {
                try {
                    $stmt = $this->db->prepare("
                        SELECT session_value FROM temp_ussd_sessions 
                        WHERE session_id = ? AND session_key = ?
                    ");
                    $stmt->execute([$sessionId, $key]);
                    $value = $stmt->fetchColumn();
                    error_log("Retrieved from temporary table: " . ($value ? $value : "NULL"));
                } catch (Exception $e) {
                    // Temporary table doesn't exist, that's okay
                }
            }
            
            error_log("Retrieved session value: " . ($value ? $value : "NULL"));
            return $value;
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
