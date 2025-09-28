<?php
/**
 * Shortcode Voting Service
 * Handles USSD/shortcode voting sessions and interactions
 */

class ShortcodeVotingService {
    private $db;
    private $settings;
    private $sessionTimeout = 600; // 10 minutes
    private $maxVotesPerSession = 10;
    
    public function __construct() {
        $this->loadDatabase();
        $this->loadSettings();
    }
    
    /**
     * Load database connection
     */
    private function loadDatabase() {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Load shortcode voting settings
     */
    private function loadSettings() {
        $stmt = $this->db->query("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'shortcode_%' OR setting_key = 'enable_shortcode_voting'
        ");
        
        $this->settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $this->sessionTimeout = (int)($this->settings['shortcode_session_timeout'] ?? 600);
        $this->maxVotesPerSession = (int)($this->settings['shortcode_max_votes_per_session'] ?? 10);
    }
    
    /**
     * Handle incoming shortcode/USSD request
     */
    public function handleShortcodeRequest($phoneNumber, $input = '', $sessionId = null) {
        try {
            // Clean phone number
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            // Generate session ID if not provided
            if (!$sessionId) {
                $sessionId = $this->generateSessionId($phoneNumber);
            }
            
            // Get or create session
            $session = $this->getOrCreateSession($sessionId, $phoneNumber);
            
            // Clean up expired sessions
            $this->cleanupExpiredSessions();
            
            // Process the input based on current step
            $response = $this->processInput($session, $input);
            
            // Update session
            $this->updateSession($session);
            
            return $response;
            
        } catch (Exception $e) {
            error_log("Shortcode voting error: " . $e->getMessage());
            return $this->createErrorResponse("Service temporarily unavailable. Please try again later.");
        }
    }
    
    /**
     * Process user input based on current session step
     */
    private function processInput($session, $input) {
        switch ($session['current_step']) {
            case 'welcome':
                return $this->handleWelcomeStep($session, $input);
                
            case 'select_event':
                return $this->handleEventSelection($session, $input);
                
            case 'select_category':
                return $this->handleCategorySelection($session, $input);
                
            case 'select_nominee':
                return $this->handleNomineeSelection($session, $input);
                
            case 'enter_votes':
                return $this->handleVoteCountEntry($session, $input);
                
            case 'confirm_payment':
                return $this->handlePaymentConfirmation($session, $input);
                
            default:
                return $this->handleWelcomeStep($session, '');
        }
    }
    
    /**
     * Handle welcome step
     */
    private function handleWelcomeStep($session, $input) {
        // Get active events
        $stmt = $this->db->prepare("
            SELECT id, title 
            FROM events 
            WHERE status = 'active' AND voting_end_date > NOW()
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            return $this->createResponse(
                "No active voting events available at the moment. Please try again later.",
                'Release'
            );
        }
        
        $message = $this->settings['shortcode_welcome_message'] ?? "Welcome to E-Cast Voting!";
        $message .= "\n\nSelect an event:\n";
        
        foreach ($events as $index => $event) {
            $message .= ($index + 1) . ". " . $event['title'] . "\n";
        }
        
        $message .= "\n0. Exit";
        
        // Store events in session data
        $session['session_data'] = json_encode(['events' => $events]);
        $session['current_step'] = 'select_event';
        
        return $this->createResponse($message, 'Response');
    }
    
    /**
     * Handle event selection
     */
    private function handleEventSelection($session, $input) {
        $input = trim($input);
        
        if ($input === '0') {
            return $this->createResponse("Thank you for using E-Cast Voting!", 'Release');
        }
        
        $sessionData = json_decode($session['session_data'], true);
        $events = $sessionData['events'] ?? [];
        
        $selectedIndex = (int)$input - 1;
        
        if (!isset($events[$selectedIndex])) {
            return $this->createResponse(
                "Invalid selection. Please enter a number between 1 and " . count($events) . " or 0 to exit.",
                'Response'
            );
        }
        
        $selectedEvent = $events[$selectedIndex];
        $session['event_id'] = $selectedEvent['id'];
        
        // Get categories for this event
        $stmt = $this->db->prepare("
            SELECT DISTINCT c.id, c.name 
            FROM categories c
            INNER JOIN nominees n ON c.id = n.category_id
            WHERE c.event_id = ? AND n.status = 'active'
            ORDER BY c.display_order, c.name
        ");
        $stmt->execute([$selectedEvent['id']]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categories)) {
            return $this->createResponse(
                "No categories available for this event. Please try another event.",
                'Release'
            );
        }
        
        $message = "Event: " . $selectedEvent['title'] . "\n\nSelect a category:\n";
        
        foreach ($categories as $index => $category) {
            $message .= ($index + 1) . ". " . $category['name'] . "\n";
        }
        
        $message .= "\n0. Back to events";
        
        // Update session data
        $sessionData['selected_event'] = $selectedEvent;
        $sessionData['categories'] = $categories;
        $session['session_data'] = json_encode($sessionData);
        $session['current_step'] = 'select_category';
        
        return $this->createResponse($message, 'Response');
    }
    
    /**
     * Handle category selection
     */
    private function handleCategorySelection($session, $input) {
        $input = trim($input);
        
        if ($input === '0') {
            $session['current_step'] = 'welcome';
            return $this->handleWelcomeStep($session, '');
        }
        
        $sessionData = json_decode($session['session_data'], true);
        $categories = $sessionData['categories'] ?? [];
        
        $selectedIndex = (int)$input - 1;
        
        if (!isset($categories[$selectedIndex])) {
            return $this->createResponse(
                "Invalid selection. Please enter a number between 1 and " . count($categories) . " or 0 to go back.",
                'Response'
            );
        }
        
        $selectedCategory = $categories[$selectedIndex];
        $session['category_id'] = $selectedCategory['id'];
        
        // Get nominees for this category
        $stmt = $this->db->prepare("
            SELECT id, name, short_code 
            FROM nominees 
            WHERE category_id = ? AND status = 'active'
            ORDER BY display_order, name
        ");
        $stmt->execute([$selectedCategory['id']]);
        $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($nominees)) {
            return $this->createResponse(
                "No nominees available in this category. Please try another category.",
                'Response'
            );
        }
        
        $message = "Category: " . $selectedCategory['name'] . "\n\nSelect a nominee:\n";
        
        foreach ($nominees as $index => $nominee) {
            $shortcodeDisplay = $nominee['short_code'] ? " ({$nominee['short_code']})" : "";
            $message .= ($index + 1) . ". " . $nominee['name'] . $shortcodeDisplay . "\n";
        }
        
        $message .= "\n0. Back to categories";
        
        // Update session data
        $sessionData['selected_category'] = $selectedCategory;
        $sessionData['nominees'] = $nominees;
        $session['session_data'] = json_encode($sessionData);
        $session['current_step'] = 'select_nominee';
        
        return $this->createResponse($message, 'Response');
    }
    
    /**
     * Handle nominee selection
     */
    private function handleNomineeSelection($session, $input) {
        $input = trim($input);
        
        if ($input === '0') {
            $session['current_step'] = 'select_category';
            return $this->handleCategorySelection($session, '');
        }
        
        $sessionData = json_decode($session['session_data'], true);
        $nominees = $sessionData['nominees'] ?? [];
        
        $selectedIndex = (int)$input - 1;
        
        if (!isset($nominees[$selectedIndex])) {
            return $this->createResponse(
                "Invalid selection. Please enter a number between 1 and " . count($nominees) . " or 0 to go back.",
                'Response'
            );
        }
        
        $selectedNominee = $nominees[$selectedIndex];
        $session['nominee_id'] = $selectedNominee['id'];
        
        // Get vote cost for this event
        $stmt = $this->db->prepare("SELECT vote_cost FROM events WHERE id = ?");
        $stmt->execute([$session['event_id']]);
        $voteCost = $stmt->fetchColumn();
        
        if (!$voteCost || $voteCost <= 0) {
            return $this->createResponse(
                "Voting cost not configured for this event. Please contact support.",
                'Release'
            );
        }
        
        $message = "Nominee: " . $selectedNominee['name'] . "\n";
        $message .= "Cost per vote: ₵" . number_format($voteCost, 2) . "\n\n";
        $message .= "How many votes? (1-{$this->maxVotesPerSession}):\n\n";
        $message .= "0. Back to nominees";
        
        // Update session data
        $sessionData['selected_nominee'] = $selectedNominee;
        $sessionData['vote_cost'] = $voteCost;
        $session['session_data'] = json_encode($sessionData);
        $session['current_step'] = 'enter_votes';
        
        return $this->createResponse($message, 'Response');
    }
    
    /**
     * Handle vote count entry
     */
    private function handleVoteCountEntry($session, $input) {
        $input = trim($input);
        
        if ($input === '0') {
            $session['current_step'] = 'select_nominee';
            return $this->handleNomineeSelection($session, '');
        }
        
        $voteCount = (int)$input;
        
        if ($voteCount < 1 || $voteCount > $this->maxVotesPerSession) {
            return $this->createResponse(
                "Please enter a number between 1 and {$this->maxVotesPerSession}.",
                'Response'
            );
        }
        
        $sessionData = json_decode($session['session_data'], true);
        $voteCost = $sessionData['vote_cost'];
        $totalAmount = $voteCount * $voteCost;
        
        $session['vote_count'] = $voteCount;
        $session['amount'] = $totalAmount;
        
        $message = "Vote Summary:\n";
        $message .= "Event: " . $sessionData['selected_event']['title'] . "\n";
        $message .= "Category: " . $sessionData['selected_category']['name'] . "\n";
        $message .= "Nominee: " . $sessionData['selected_nominee']['name'] . "\n";
        $message .= "Votes: {$voteCount}\n";
        $message .= "Total: ₵" . number_format($totalAmount, 2) . "\n\n";
        $message .= "1. Confirm & Pay\n";
        $message .= "2. Change vote count\n";
        $message .= "0. Cancel";
        
        $session['current_step'] = 'confirm_payment';
        
        return $this->createResponse($message, 'Response');
    }
    
    /**
     * Handle payment confirmation
     */
    private function handlePaymentConfirmation($session, $input) {
        $input = trim($input);
        
        switch ($input) {
            case '0':
                return $this->createResponse("Voting cancelled. Thank you!", 'Release');
                
            case '2':
                $session['current_step'] = 'enter_votes';
                return $this->handleVoteCountEntry($session, '');
                
            case '1':
                return $this->initiatePayment($session);
                
            default:
                return $this->createResponse(
                    "Please select:\n1. Confirm & Pay\n2. Change vote count\n0. Cancel",
                    'Response'
                );
        }
    }
    
    /**
     * Initiate payment for the votes
     */
    private function initiatePayment($session) {
        try {
            // Generate transaction reference
            $sessionData = json_decode($session['session_data'], true);
            $transactionRef = $this->generateTransactionReference(
                $sessionData['selected_event']['title'],
                $sessionData['selected_nominee']['name']
            );
            
            // Create shortcode transaction record
            $stmt = $this->db->prepare("
                INSERT INTO shortcode_transactions (
                    transaction_ref, session_id, phone_number, event_id, nominee_id,
                    vote_count, amount, status, payment_method
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $paymentMethod = $this->settings['shortcode_payment_method'] ?? 'ussd';
            
            $stmt->execute([
                $transactionRef,
                $session['session_id'],
                $session['phone_number'],
                $session['event_id'],
                $session['nominee_id'],
                $session['vote_count'],
                $session['amount'],
                $paymentMethod
            ]);
            
            // Initiate payment based on method
            if ($paymentMethod === 'ussd') {
                return $this->initiateUSSDPayment($session, $transactionRef);
            } else {
                return $this->initiateMobileMoneyPayment($session, $transactionRef);
            }
            
        } catch (Exception $e) {
            error_log("Payment initiation error: " . $e->getMessage());
            return $this->createResponse(
                "Payment initiation failed. Please try again later.",
                'Release'
            );
        }
    }
    
    /**
     * Initiate USSD payment using PayProxy API
     */
    private function initiateUSSDPayment($session, $transactionRef) {
        try {
            $sessionData = json_decode($session['session_data'], true);
            
            // Use PayProxy API for USSD payments (based on church management system implementation)
            require_once __DIR__ . '/HubtelPayProxyService.php';
            
            $payProxyService = new HubtelPayProxyService();
            
            $description = "Vote for " . $sessionData['selected_nominee']['name'] . 
                          " in " . $sessionData['selected_event']['title'];
            
            $paymentData = [
                'amount' => $session['amount'],
                'phone_number' => $session['phone_number'],
                'description' => $description,
                'client_reference' => $transactionRef,
                'voter_name' => 'Shortcode Voter',
                'email' => '',
                'event_id' => $session['event_id'],
                'nominee_id' => $session['nominee_id'],
                'vote_count' => $session['vote_count']
            ];
            
            $paymentResult = $payProxyService->createCheckout($paymentData);
            
            if ($paymentResult['success']) {
                // For shortcode, we need to trigger USSD payment, not web checkout
                $message = "Payment initiated successfully!\n";
                $message .= "You will receive a USSD prompt on your phone shortly.\n";
                $message .= "Follow the prompts to complete payment.\n";
                $message .= "Reference: {$transactionRef}\n\n";
                $message .= "Thank you for voting!";
                
                $session['current_step'] = 'payment_processing';
                
                return $this->createResponse($message, 'Release');
            } else {
                return $this->createResponse(
                    "Payment initiation failed: " . ($paymentResult['message'] ?? 'Unknown error'),
                    'Release'
                );
            }
            
        } catch (Exception $e) {
            error_log("USSD payment initiation error: " . $e->getMessage());
            return $this->createResponse(
                "Payment service temporarily unavailable. Please try again later.",
                'Release'
            );
        }
    }
    
    /**
     * Initiate mobile money payment
     */
    private function initiateMobileMoneyPayment($session, $transactionRef) {
        // Use existing mobile money service
        require_once __DIR__ . '/HubtelReceiveMoneyService.php';
        
        $mobileMoneyService = new HubtelReceiveMoneyService();
        $sessionData = json_decode($session['session_data'], true);
        
        $description = "Vote for " . $sessionData['selected_nominee']['name'] . 
                      " in " . $sessionData['selected_event']['title'];
        
        $paymentResult = $mobileMoneyService->initiatePayment(
            $session['amount'],
            $session['phone_number'],
            $description,
            $transactionRef,
            'Shortcode Voter',
            ''
        );
        
        if ($paymentResult['success']) {
            $message = "Mobile money payment initiated!\n";
            $message .= "Check your phone for payment prompt.\n";
            $message .= "Reference: {$transactionRef}\n\n";
            $message .= "Thank you for voting!";
            
            $session['current_step'] = 'payment_processing';
            
            return $this->createResponse($message, 'Release');
        } else {
            return $this->createResponse(
                "Payment failed: " . ($paymentResult['message'] ?? 'Unknown error'),
                'Release'
            );
        }
    }
    
    /**
     * Generate transaction reference
     */
    private function generateTransactionReference($eventTitle, $nomineeName) {
        // Extract first letter of each word from event name
        $eventWords = explode(' ', $eventTitle);
        $eventAbbr = '';
        foreach ($eventWords as $word) {
            if (!empty($word)) {
                $eventAbbr .= strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1));
            }
        }
        
        // Use full nominee name (cleaned)
        $nomineeClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nomineeName));
        
        return $eventAbbr . $nomineeClean . '-SC-' . date('mdHi') . '-' . rand(100, 999);
    }
    
    /**
     * Get or create voting session
     */
    private function getOrCreateSession($sessionId, $phoneNumber) {
        // Try to get existing session
        $stmt = $this->db->prepare("
            SELECT * FROM shortcode_voting_sessions 
            WHERE session_id = ? AND phone_number = ? AND expires_at > NOW()
        ");
        $stmt->execute([$sessionId, $phoneNumber]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            return $session;
        }
        
        // Create new session
        $stmt = $this->db->prepare("
            INSERT INTO shortcode_voting_sessions (
                session_id, phone_number, current_step, expires_at
            ) VALUES (?, ?, 'welcome', DATE_ADD(NOW(), INTERVAL ? SECOND))
        ");
        $stmt->execute([$sessionId, $phoneNumber, $this->sessionTimeout]);
        
        // Return the new session
        $stmt = $this->db->prepare("
            SELECT * FROM shortcode_voting_sessions WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update session data
     */
    private function updateSession($session) {
        $stmt = $this->db->prepare("
            UPDATE shortcode_voting_sessions 
            SET current_step = ?, event_id = ?, category_id = ?, nominee_id = ?, 
                vote_count = ?, amount = ?, session_data = ?,
                last_activity = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ?
        ");
        
        $stmt->execute([
            $session['current_step'],
            $session['event_id'],
            $session['category_id'],
            $session['nominee_id'],
            $session['vote_count'],
            $session['amount'],
            $session['session_data'],
            $this->sessionTimeout,
            $session['id']
        ]);
    }
    
    /**
     * Clean up expired sessions
     */
    private function cleanupExpiredSessions() {
        $stmt = $this->db->prepare("
            DELETE FROM shortcode_voting_sessions 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();
    }
    
    /**
     * Format phone number
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '233' . substr($phone, 1);
        } elseif (substr($phone, 0, 4) !== '+233' && substr($phone, 0, 3) !== '233') {
            $phone = '233' . $phone;
        }
        
        return str_replace('+', '', $phone);
    }
    
    /**
     * Generate session ID
     */
    private function generateSessionId($phoneNumber) {
        return 'SC_' . substr(md5($phoneNumber . time() . rand()), 0, 12);
    }
    
    /**
     * Create USSD response
     */
    private function createResponse($message, $type = 'Response') {
        return [
            'Type' => $type,
            'Message' => $message,
            'Mask' => 0
        ];
    }
    
    /**
     * Create error response
     */
    private function createErrorResponse($message) {
        return [
            'Type' => 'Release',
            'Message' => $message,
            'Mask' => 0
        ];
    }
    
    /**
     * Process payment callback for shortcode transactions
     */
    public function processPaymentCallback($transactionRef, $status, $hubtelTransactionId = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM shortcode_transactions WHERE transaction_ref = ?
            ");
            $stmt->execute([$transactionRef]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found: {$transactionRef}");
            }
            
            // Update transaction status
            $stmt = $this->db->prepare("
                UPDATE shortcode_transactions 
                SET status = ?, hubtel_transaction_id = ?, completed_at = NOW()
                WHERE transaction_ref = ?
            ");
            $stmt->execute([$status, $hubtelTransactionId, $transactionRef]);
            
            if ($status === 'completed') {
                // Create votes and main transaction record
                $this->createVotesFromShortcodeTransaction($transaction, $hubtelTransactionId);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Shortcode payment callback error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create votes from completed shortcode transaction
     */
    private function createVotesFromShortcodeTransaction($transaction, $hubtelTransactionId) {
        try {
            $this->db->beginTransaction();
            
            // Get organizer_id from event
            $stmt = $this->db->prepare("SELECT organizer_id FROM events WHERE id = ?");
            $stmt->execute([$transaction['event_id']]);
            $organizerId = $stmt->fetchColumn();
            
            // Create main transaction record
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    transaction_id, reference, event_id, organizer_id, nominee_id,
                    voter_phone, vote_count, amount, payment_method,
                    status, hubtel_transaction_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'shortcode_voting', 'completed', ?, NOW())
            ");
            
            $stmt->execute([
                $transaction['transaction_ref'],
                $transaction['transaction_ref'],
                $transaction['event_id'],
                $organizerId,
                $transaction['nominee_id'],
                $transaction['phone_number'],
                $transaction['vote_count'],
                $transaction['amount'],
                $hubtelTransactionId
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
                    ) VALUES (?, ?, ?, ?, ?, 'shortcode_voting', ?, 'completed', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $transaction['event_id'],
                    $categoryId,
                    $transaction['nominee_id'],
                    $transaction['phone_number'],
                    $mainTransactionId,
                    $transaction['transaction_ref'],
                    $voteAmount
                ]);
            }
            
            $this->db->commit();
            
            error_log("Shortcode votes created successfully: {$transaction['transaction_ref']} - {$voteCount} votes");
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating votes from shortcode transaction: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
