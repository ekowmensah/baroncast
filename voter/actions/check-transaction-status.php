<?php
/**
 * Check Transaction Status Endpoint
 * Allows manual checking of transaction status using Hubtel's Transaction Status Check API
 */

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/status-check.log');

function logDebug($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logEntry .= " - " . json_encode($data, JSON_PRETTY_PRINT);
    }
    file_put_contents(__DIR__ . '/../../logs/status-check.log', $logEntry . "\n", FILE_APPEND);
}

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $transactionRef = $input['transaction_ref'] ?? '';
    $checkType = $input['check_type'] ?? 'client_reference'; // client_reference, hubtel_id, network_id
    
    if (!$transactionRef) {
        throw new Exception('Missing transaction reference');
    }
    
    // Load services
    require_once __DIR__ . '/../../services/HubtelTransactionStatusService.php';
    require_once __DIR__ . '/../../config/database.php';
    
    $statusService = new HubtelTransactionStatusService();
    $database = new Database();
    $pdo = $database->getConnection();
    
    logDebug("Status check request", [
        'transaction_ref' => $transactionRef,
        'check_type' => $checkType
    ]);
    
    // Get transaction from database first
    $stmt = $pdo->prepare("
        SELECT t.*, n.name as nominee_name, e.title as event_title
        FROM transactions t
        LEFT JOIN nominees n ON t.nominee_id = n.id
        LEFT JOIN events e ON t.event_id = e.id
        WHERE t.reference = ? OR t.transaction_id = ?
    ");
    $stmt->execute([$transactionRef, $transactionRef]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check status with Hubtel based on check type
    switch ($checkType) {
        case 'hubtel_id':
            $statusResult = $statusService->checkTransactionStatusByHubtelId($transactionRef);
            break;
        case 'network_id':
            $statusResult = $statusService->checkTransactionStatusByNetworkId($transactionRef);
            break;
        case 'client_reference':
        default:
            $statusResult = $statusService->checkTransactionStatus($transactionRef);
            break;
    }
    
    logDebug("Hubtel status check result", $statusResult);
    
    if ($statusResult['success']) {
        $response = [
            'success' => true,
            'hubtel_status' => $statusResult['status'],
            'is_paid' => $statusResult['is_paid'],
            'is_unpaid' => $statusResult['is_unpaid'],
            'is_refunded' => $statusResult['is_refunded'],
            'transaction_data' => [
                'transaction_id' => $statusResult['transaction_id'],
                'external_transaction_id' => $statusResult['external_transaction_id'],
                'client_reference' => $statusResult['client_reference'],
                'payment_method' => $statusResult['payment_method'],
                'amount' => $statusResult['amount'],
                'charges' => $statusResult['charges'],
                'amount_after_charges' => $statusResult['amount_after_charges'],
                'transaction_date' => $statusResult['transaction_date'],
                'currency_code' => $statusResult['currency_code'],
                'is_fulfilled' => $statusResult['is_fulfilled']
            ],
            'message' => $statusResult['message']
        ];
        
        // If we have the transaction in our database, include local data
        if ($transaction) {
            $response['local_transaction'] = [
                'id' => $transaction['id'],
                'reference' => $transaction['reference'],
                'status' => $transaction['status'],
                'event_title' => $transaction['event_title'],
                'nominee_name' => $transaction['nominee_name'],
                'vote_count' => $transaction['vote_count'],
                'amount' => $transaction['amount'],
                'created_at' => $transaction['created_at'],
                'updated_at' => $transaction['updated_at']
            ];
            
            // Check if status needs updating
            $hubtelStatus = $statusResult['status'];
            $internalStatus = mapHubtelStatusToInternal($hubtelStatus);
            
            if ($transaction['status'] !== $internalStatus) {
                $response['status_update_needed'] = true;
                $response['current_status'] = $transaction['status'];
                $response['new_status'] = $internalStatus;
                
                // Optionally auto-update if requested
                if ($input['auto_update'] ?? false) {
                    $updateResult = $statusService->processTransactionStatusCheck($transaction);
                    $response['update_result'] = $updateResult;
                }
            } else {
                $response['status_update_needed'] = false;
            }
        } else {
            $response['local_transaction'] = null;
            $response['note'] = 'Transaction not found in local database';
        }
        
        echo json_encode($response);
        
    } else {
        // Status check failed
        $response = [
            'success' => false,
            'message' => $statusResult['message'],
            'error_details' => [
                'http_code' => $statusResult['http_code'] ?? null,
                'response_code' => $statusResult['response_code'] ?? null
            ]
        ];
        
        // Include local transaction data if available
        if ($transaction) {
            $response['local_transaction'] = [
                'id' => $transaction['id'],
                'reference' => $transaction['reference'],
                'status' => $transaction['status'],
                'event_title' => $transaction['event_title'],
                'nominee_name' => $transaction['nominee_name'],
                'created_at' => $transaction['created_at']
            ];
        }
        
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    logDebug("Error in status check", ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Map Hubtel status to internal status
 */
function mapHubtelStatusToInternal($hubtelStatus) {
    $statusMap = [
        'Paid' => 'completed',
        'Unpaid' => 'pending',
        'Refunded' => 'refunded',
        'Failed' => 'failed',
        'Cancelled' => 'cancelled'
    ];
    
    return $statusMap[$hubtelStatus] ?? 'pending';
}
?>
