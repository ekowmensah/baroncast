<?php
/**
 * Hubtel Transaction Status Check Service
 * Implementation of Hubtel's Transaction Status Check API
 * 
 * This service implements the mandatory Transaction Status Check API
 * as required by Hubtel for checking transaction status when callbacks
 * are not received within 5 minutes.
 */

class HubtelTransactionStatusService {
    private $posId;
    private $apiKey;
    private $apiSecret;
    private $statusCheckUrl;
    private $environment;
    private $db;
    
    // Status constants
    const STATUS_PAID = 'Paid';
    const STATUS_UNPAID = 'Unpaid';
    const STATUS_REFUNDED = 'Refunded';
    
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
        // Transaction Status Check API uses different endpoint
        if ($this->environment === 'sandbox') {
            $this->statusCheckUrl = 'https://api-txnstatus-sandbox.hubtel.com';
        } else {
            $this->statusCheckUrl = 'https://api-txnstatus.hubtel.com';
        }
    }
    
    /**
     * Check transaction status using clientReference (preferred method)
     * 
     * @param string $clientReference The client reference or SessionId of the transaction
     * @return array Status check result
     */
    public function checkTransactionStatus($clientReference) {
        try {
            if (empty($this->posId)) {
                return [
                    'success' => false,
                    'message' => 'Hubtel POS ID not configured'
                ];
            }
            
            if (empty($clientReference)) {
                return [
                    'success' => false,
                    'message' => 'Client reference is required'
                ];
            }
            
            // Build endpoint URL with POS Sales ID
            $endpoint = "/transactions/{$this->posId}/status";
            $queryParams = [
                'clientReference' => $clientReference
            ];
            
            $response = $this->makeStatusRequest($endpoint, $queryParams);
            
            if ($response['success']) {
                return $this->parseStatusResponse($response['data']);
            } else {
                return [
                    'success' => false,
                    'message' => 'Status check API request failed',
                    'http_code' => $response['http_code'],
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Hubtel status check error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Status check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check transaction status using Hubtel Transaction ID
     * 
     * @param string $hubtelTransactionId Transaction ID from Hubtel
     * @return array Status check result
     */
    public function checkTransactionStatusByHubtelId($hubtelTransactionId) {
        try {
            $endpoint = "/transactions/{$this->posId}/status";
            $queryParams = [
                'hubtelTransactionId' => $hubtelTransactionId
            ];
            
            $response = $this->makeStatusRequest($endpoint, $queryParams);
            
            if ($response['success']) {
                return $this->parseStatusResponse($response['data']);
            } else {
                return [
                    'success' => false,
                    'message' => 'Status check API request failed',
                    'http_code' => $response['http_code']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Hubtel status check by ID error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Status check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check transaction status using Network Transaction ID
     * 
     * @param string $networkTransactionId Transaction reference from mobile money provider
     * @return array Status check result
     */
    public function checkTransactionStatusByNetworkId($networkTransactionId) {
        try {
            $endpoint = "/transactions/{$this->posId}/status";
            $queryParams = [
                'networkTransactionId' => $networkTransactionId
            ];
            
            $response = $this->makeStatusRequest($endpoint, $queryParams);
            
            if ($response['success']) {
                return $this->parseStatusResponse($response['data']);
            } else {
                return [
                    'success' => false,
                    'message' => 'Status check API request failed',
                    'http_code' => $response['http_code']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Hubtel status check by network ID error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Status check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Make status check API request
     * 
     * @param string $endpoint API endpoint
     * @param array $queryParams Query parameters
     * @return array Response data
     */
    private function makeStatusRequest($endpoint, $queryParams = []) {
        $url = $this->statusCheckUrl . $endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        // Create Basic Auth header
        $auth = base64_encode($this->apiKey . ':' . $this->apiSecret);
        
        $headers = [
            'Authorization: Basic ' . $auth,
            'Accept: application/json',
            'Cache-Control: no-cache'
        ];
        
        error_log("Hubtel Status Check Request: GET $url");
        error_log("Hubtel Status Check Headers: " . json_encode($headers));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPGET => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Hubtel Status Check Response Code: $httpCode");
        error_log("Hubtel Status Check Response: " . $response);
        
        if ($error) {
            return [
                'success' => false,
                'http_code' => 0,
                'error' => "cURL Error: " . $error
            ];
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => json_decode($response, true),
            'raw_response' => $response
        ];
    }
    
    /**
     * Parse status check API response
     * 
     * @param array $responseData Raw API response data
     * @return array Parsed status information
     */
    private function parseStatusResponse($responseData) {
        if (!$responseData) {
            return [
                'success' => false,
                'message' => 'Empty response from status check API'
            ];
        }
        
        $responseCode = $responseData['responseCode'] ?? '';
        $message = $responseData['message'] ?? '';
        $data = $responseData['data'] ?? [];
        
        // Check if request was successful
        if ($responseCode !== '0000') {
            return [
                'success' => false,
                'message' => $message ?: 'Status check request failed',
                'response_code' => $responseCode,
                'raw_response' => $responseData
            ];
        }
        
        // Extract transaction data
        $transactionData = [
            'success' => true,
            'message' => $message,
            'response_code' => $responseCode,
            'transaction_date' => $data['date'] ?? null,
            'status' => $data['status'] ?? 'Unknown',
            'transaction_id' => $data['transactionId'] ?? '',
            'external_transaction_id' => $data['externalTransactionId'] ?? '',
            'payment_method' => $data['paymentMethod'] ?? '',
            'client_reference' => $data['clientReference'] ?? '',
            'currency_code' => $data['currencyCode'] ?? '',
            'amount' => (float)($data['amount'] ?? 0),
            'charges' => (float)($data['charges'] ?? 0),
            'amount_after_charges' => (float)($data['amountAfterCharges'] ?? 0),
            'is_fulfilled' => $data['isFulfilled'] ?? null,
            'is_paid' => strtolower($data['status'] ?? '') === 'paid',
            'is_unpaid' => strtolower($data['status'] ?? '') === 'unpaid',
            'is_refunded' => strtolower($data['status'] ?? '') === 'refunded'
        ];
        
        return $transactionData;
    }
    
    /**
     * Get all pending transactions that need status checks
     * Returns transactions that are older than 5 minutes and still pending
     * 
     * @param int $limit Maximum number of transactions to return
     * @return array List of pending transactions
     */
    public function getPendingTransactions($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    transaction_id,
                    reference,
                    event_id,
                    nominee_id,
                    voter_phone,
                    vote_count,
                    amount,
                    payment_method,
                    status,
                    created_at,
                    updated_at
                FROM transactions 
                WHERE status IN ('pending', 'processing') 
                AND payment_method IN ('mobile_money', 'hubtel_checkout', 'hubtel_ussd')
                AND created_at <= NOW() - INTERVAL 5 MINUTE
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching pending transactions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Process status check for a single transaction
     * Updates transaction status and creates votes if payment is completed
     * 
     * @param array $transaction Transaction data from database
     * @return array Processing result
     */
    public function processTransactionStatusCheck($transaction) {
        try {
            // Use client reference (transaction reference) for status check
            $clientReference = $transaction['reference'];
            $statusResult = $this->checkTransactionStatus($clientReference);
            
            if (!$statusResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Status check failed: ' . $statusResult['message'],
                    'transaction_id' => $transaction['id']
                ];
            }
            
            $hubtelStatus = $statusResult['status'];
            $internalStatus = $this->mapHubtelStatusToInternal($hubtelStatus);
            
            // Update transaction if status changed
            if ($transaction['status'] !== $internalStatus) {
                $this->updateTransactionStatus($transaction['id'], $internalStatus, $statusResult);
                
                // If payment completed, create votes
                if ($internalStatus === 'completed') {
                    $voteResult = $this->createVotesForTransaction($transaction);
                    
                    return [
                        'success' => true,
                        'status_changed' => true,
                        'old_status' => $transaction['status'],
                        'new_status' => $internalStatus,
                        'votes_created' => $voteResult['votes_created'] ?? 0,
                        'transaction_id' => $transaction['id'],
                        'hubtel_status' => $hubtelStatus
                    ];
                }
                
                return [
                    'success' => true,
                    'status_changed' => true,
                    'old_status' => $transaction['status'],
                    'new_status' => $internalStatus,
                    'transaction_id' => $transaction['id'],
                    'hubtel_status' => $hubtelStatus
                ];
            }
            
            return [
                'success' => true,
                'status_changed' => false,
                'status' => $internalStatus,
                'transaction_id' => $transaction['id'],
                'hubtel_status' => $hubtelStatus
            ];
            
        } catch (Exception $e) {
            error_log("Error processing transaction status check: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage(),
                'transaction_id' => $transaction['id']
            ];
        }
    }
    
    /**
     * Map Hubtel status to internal status
     * 
     * @param string $hubtelStatus Status from Hubtel API
     * @return string Internal status
     */
    private function mapHubtelStatusToInternal($hubtelStatus) {
        $statusMap = [
            'Paid' => 'completed',
            'Unpaid' => 'pending',
            'Refunded' => 'refunded',
            'Failed' => 'failed',
            'Cancelled' => 'cancelled'
        ];
        
        return $statusMap[$hubtelStatus] ?? 'pending';
    }
    
    /**
     * Update transaction status in database
     * 
     * @param int $transactionId Transaction ID
     * @param string $status New status
     * @param array $statusData Additional status data from Hubtel
     */
    private function updateTransactionStatus($transactionId, $status, $statusData = []) {
        try {
            // Check if we have extended columns for Hubtel data
            $stmt = $this->db->query("SHOW COLUMNS FROM transactions LIKE 'hubtel_transaction_id'");
            $hasExtendedColumns = $stmt->rowCount() > 0;
            
            if ($hasExtendedColumns) {
                // Update with full Hubtel data
                $stmt = $this->db->prepare("
                    UPDATE transactions 
                    SET status = ?, 
                        hubtel_transaction_id = ?,
                        external_transaction_id = ?,
                        payment_response = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $status,
                    $statusData['transaction_id'] ?? '',
                    $statusData['external_transaction_id'] ?? '',
                    json_encode($statusData),
                    $transactionId
                ]);
            } else {
                // Basic update for minimal schema
                $stmt = $this->db->prepare("
                    UPDATE transactions 
                    SET status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$status, $transactionId]);
            }
            
        } catch (Exception $e) {
            error_log("Error updating transaction status: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create votes for completed transaction
     * 
     * @param array $transaction Transaction data
     * @return array Creation result
     */
    private function createVotesForTransaction($transaction) {
        try {
            // Check if votes already exist
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM votes 
                WHERE payment_reference = ?
            ");
            $stmt->execute([$transaction['reference']]);
            
            if ($stmt->fetchColumn() > 0) {
                return [
                    'success' => true,
                    'votes_created' => 0,
                    'message' => 'Votes already exist for this transaction'
                ];
            }
            
            // Get category ID for the nominee
            $stmt = $this->db->prepare("
                SELECT category_id FROM nominees WHERE id = ?
            ");
            $stmt->execute([$transaction['nominee_id']]);
            $categoryId = $stmt->fetchColumn();
            
            if (!$categoryId) {
                throw new Exception('Category not found for nominee');
            }
            
            // Create individual votes
            $voteCount = (int)$transaction['vote_count'];
            $voteAmount = $transaction['amount'] / $voteCount;
            
            for ($i = 0; $i < $voteCount; $i++) {
                $stmt = $this->db->prepare("
                    INSERT INTO votes (
                        event_id, category_id, nominee_id, voter_phone, 
                        transaction_id, payment_method, payment_reference, 
                        payment_status, amount, voted_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $transaction['event_id'],
                    $categoryId,
                    $transaction['nominee_id'],
                    $transaction['voter_phone'],
                    $transaction['id'],
                    $transaction['payment_method'],
                    $transaction['reference'],
                    $voteAmount
                ]);
            }
            
            return [
                'success' => true,
                'votes_created' => $voteCount,
                'message' => "Created {$voteCount} votes successfully"
            ];
            
        } catch (Exception $e) {
            error_log("Error creating votes for transaction: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Run batch status check for all pending transactions
     * This method should be called periodically (e.g., via cron job)
     * 
     * @param int $batchSize Number of transactions to process in one batch
     * @return array Batch processing results
     */
    public function runBatchStatusCheck($batchSize = 20) {
        try {
            $pendingTransactions = $this->getPendingTransactions($batchSize);
            
            if (empty($pendingTransactions)) {
                return [
                    'success' => true,
                    'message' => 'No pending transactions found',
                    'processed_count' => 0,
                    'results' => []
                ];
            }
            
            $results = [];
            $processedCount = 0;
            $statusChangedCount = 0;
            $votesCreatedCount = 0;
            
            foreach ($pendingTransactions as $transaction) {
                $result = $this->processTransactionStatusCheck($transaction);
                $results[] = $result;
                $processedCount++;
                
                if ($result['success'] && ($result['status_changed'] ?? false)) {
                    $statusChangedCount++;
                    
                    if (isset($result['votes_created'])) {
                        $votesCreatedCount += $result['votes_created'];
                    }
                }
                
                // Add small delay between requests to avoid rate limiting
                usleep(500000); // 0.5 second delay
            }
            
            return [
                'success' => true,
                'message' => "Processed {$processedCount} transactions",
                'processed_count' => $processedCount,
                'status_changed_count' => $statusChangedCount,
                'votes_created_count' => $votesCreatedCount,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Error in batch status check: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Batch processing failed: ' . $e->getMessage(),
                'processed_count' => 0,
                'results' => []
            ];
        }
    }
}
?>
