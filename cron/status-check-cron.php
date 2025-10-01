<?php
/**
 * Cron Job for Automatic Transaction Status Checking
 * 
 * This script should be run every 5-10 minutes via cron job to automatically
 * check the status of pending transactions and update them accordingly.
 * 
 * Example cron job entry (runs every 5 minutes):
 * * /5 * * * * /usr/bin/php /path/to/baroncast/cron/status-check-cron.php
 * 
 * Or for Windows Task Scheduler:
 * php.exe "C:\xampp\htdocs\baroncast\cron\status-check-cron.php"
 */

// Ensure this script is run from command line or has proper authentication
if (isset($_SERVER['HTTP_HOST'])) {
    // If accessed via web, require API key
    $apiKey = $_GET['api_key'] ?? '';
    if (empty($apiKey)) {
        die('Unauthorized: API key required for web access');
    }
    
    // Validate API key
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'batch_status_api_key'");
    $stmt->execute();
    $validApiKey = $stmt->fetchColumn();
    
    if (!$validApiKey || $apiKey !== $validApiKey) {
        die('Unauthorized: Invalid API key');
    }
}

// Set execution time limit
set_time_limit(600); // 10 minutes

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron-status-check.log');

function logMessage($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " [CRON] " . $message;
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    
    // Log to file
    file_put_contents(__DIR__ . '/../logs/cron-status-check.log', $logEntry . "\n", FILE_APPEND);
    
    // Also output to console if run from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry . "\n";
    }
}

try {
    logMessage("Starting automated status check cron job");
    
    // Load required services
    require_once __DIR__ . '/../services/HubtelTransactionStatusService.php';
    require_once __DIR__ . '/../config/database.php';
    
    $statusService = new HubtelTransactionStatusService();
    
    // Check if Hubtel is properly configured
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'hubtel_pos_id' AND setting_value != ''");
    $stmt->execute();
    $hubtelConfigured = $stmt->fetchColumn() > 0;
    
    if (!$hubtelConfigured) {
        logMessage("Hubtel not configured, skipping status check");
        exit(0);
    }
    
    // Get pending transactions count first
    $pendingTransactions = $statusService->getPendingTransactions(1);
    if (empty($pendingTransactions)) {
        logMessage("No pending transactions found");
        exit(0);
    }
    
    // Get total count for logging
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM transactions 
        WHERE status IN ('pending', 'processing') 
        AND payment_method IN ('mobile_money', 'hubtel_checkout', 'hubtel_ussd')
        AND created_at <= NOW() - INTERVAL 5 MINUTE
    ");
    $stmt->execute();
    $totalPending = $stmt->fetchColumn();
    
    logMessage("Found {$totalPending} pending transactions to check");
    
    // Process in batches to avoid memory issues and API rate limits
    $batchSize = 10; // Smaller batch size for cron job
    $totalProcessed = 0;
    $totalStatusChanged = 0;
    $totalVotesCreated = 0;
    $errors = [];
    
    while (true) {
        // Get next batch
        $batch = $statusService->getPendingTransactions($batchSize);
        
        if (empty($batch)) {
            break; // No more transactions to process
        }
        
        logMessage("Processing batch of " . count($batch) . " transactions");
        
        // Process this batch
        $batchResult = $statusService->runBatchStatusCheck($batchSize);
        
        if ($batchResult['success']) {
            $totalProcessed += $batchResult['processed_count'];
            $totalStatusChanged += $batchResult['status_changed_count'];
            $totalVotesCreated += $batchResult['votes_created_count'];
            
            logMessage("Batch completed", [
                'processed' => $batchResult['processed_count'],
                'status_changed' => $batchResult['status_changed_count'],
                'votes_created' => $batchResult['votes_created_count']
            ]);
        } else {
            $errors[] = $batchResult['message'];
            logMessage("Batch failed: " . $batchResult['message']);
        }
        
        // Add delay between batches to avoid overwhelming the API
        sleep(2);
        
        // Safety check to avoid infinite loops
        if ($totalProcessed >= 200) {
            logMessage("Reached processing limit of 200 transactions, stopping");
            break;
        }
    }
    
    // Final summary
    $summary = [
        'total_processed' => $totalProcessed,
        'total_status_changed' => $totalStatusChanged,
        'total_votes_created' => $totalVotesCreated,
        'errors_count' => count($errors),
        'execution_time' => date('Y-m-d H:i:s')
    ];
    
    logMessage("Cron job completed", $summary);
    
    // Update last run timestamp in database
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('last_status_check_run', ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $timestamp = date('Y-m-d H:i:s');
    $stmt->execute([$timestamp, $timestamp]);
    
    // If run via web, return JSON response
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Cron job completed successfully',
            'summary' => $summary
        ]);
    }
    
} catch (Exception $e) {
    $errorMessage = "Cron job failed: " . $e->getMessage();
    logMessage($errorMessage);
    
    // If run via web, return error response
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $errorMessage,
            'execution_time' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}
?>
