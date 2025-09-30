<?php
/**
 * Batch Transaction Status Check
 * Automatically checks status of all pending transactions older than 5 minutes
 * This should be run periodically via cron job or manual trigger
 */

// Set execution time limit for batch processing
set_time_limit(300); // 5 minutes

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/batch-status-check.log');

function logDebug($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $logEntry .= " - " . json_encode($data, JSON_PRETTY_PRINT);
    }
    file_put_contents(__DIR__ . '/../logs/batch-status-check.log', $logEntry . "\n", FILE_APPEND);
}

try {
    // Check if user is admin (if session-based auth is used)
    session_start();
    
    // For API access, you might want to add API key authentication here
    $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    
    // Simple API key check (you should implement proper authentication)
    if (empty($apiKey)) {
        // Check if user is logged in as admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            throw new Exception('Unauthorized access');
        }
    } else {
        // Validate API key against database or config
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'batch_status_api_key'");
        $stmt->execute();
        $validApiKey = $stmt->fetchColumn();
        
        if (!$validApiKey || $apiKey !== $validApiKey) {
            throw new Exception('Invalid API key');
        }
    }
    
    // Get batch size from request (default 20)
    $batchSize = (int)($_GET['batch_size'] ?? $_POST['batch_size'] ?? 20);
    $batchSize = max(1, min(100, $batchSize)); // Limit between 1 and 100
    
    // Load the status service
    require_once __DIR__ . '/../services/HubtelTransactionStatusService.php';
    
    $statusService = new HubtelTransactionStatusService();
    
    logDebug("Starting batch status check", ['batch_size' => $batchSize]);
    
    // Run batch status check
    $result = $statusService->runBatchStatusCheck($batchSize);
    
    logDebug("Batch status check completed", $result);
    
    // Add timestamp and execution info
    $result['execution_time'] = date('Y-m-d H:i:s');
    $result['batch_size'] = $batchSize;
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logDebug("Error in batch status check", ['error' => $e->getMessage()]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'execution_time' => date('Y-m-d H:i:s')
    ]);
}
?>
