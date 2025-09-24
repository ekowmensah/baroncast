<?php
/**
 * Debug Version of Vote Submission Handler
 * This will help identify the exact issue
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/debug-vote.log');

// Log the start of the request
error_log("=== DEBUG VOTE SUBMIT START ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

header('Content-Type: application/json');

try {
    // Step 1: Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = ['success' => false, 'message' => 'Invalid request method', 'step' => 'method_check'];
        error_log("Failed at method check");
        echo json_encode($response);
        exit;
    }
    
    error_log("Step 1: Method check passed");
    
    // Step 2: Check if database config exists
    $db_config_path = __DIR__ . '/../../config/database.php';
    if (!file_exists($db_config_path)) {
        $response = ['success' => false, 'message' => 'Database config not found', 'step' => 'db_config_check'];
        error_log("Database config file not found: " . $db_config_path);
        echo json_encode($response);
        exit;
    }
    
    error_log("Step 2: Database config exists");
    
    // Step 3: Try to include database config
    try {
        require_once $db_config_path;
        error_log("Step 3: Database config included successfully");
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Database config error: ' . $e->getMessage(), 'step' => 'db_config_include'];
        error_log("Database config include error: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
    
    // Step 4: Try to create database connection
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        error_log("Step 4: Database connection successful");
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage(), 'step' => 'db_connection'];
        error_log("Database connection error: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
    
    // Step 5: Check if HubtelReceiveMoneyService exists
    $hubtel_service_path = __DIR__ . '/../../services/HubtelReceiveMoneyService.php';
    if (!file_exists($hubtel_service_path)) {
        $response = ['success' => false, 'message' => 'HubtelReceiveMoneyService not found', 'step' => 'hubtel_service_check'];
        error_log("HubtelReceiveMoneyService file not found: " . $hubtel_service_path);
        echo json_encode($response);
        exit;
    }
    
    error_log("Step 5: HubtelReceiveMoneyService file exists");
    
    // Step 6: Try to include HubtelReceiveMoneyService
    try {
        require_once $hubtel_service_path;
        error_log("Step 6: HubtelReceiveMoneyService included successfully");
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'HubtelReceiveMoneyService include error: ' . $e->getMessage(), 'step' => 'hubtel_service_include'];
        error_log("HubtelReceiveMoneyService include error: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
    
    // Step 7: Validate form data
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $nominee_id = isset($_POST['nominee_id']) ? (int)$_POST['nominee_id'] : 0;
    $vote_count = isset($_POST['vote_count']) ? (int)$_POST['vote_count'] : 1;
    $voter_name = isset($_POST['voter_name']) ? trim($_POST['voter_name']) : '';
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    error_log("Step 7: Form data extracted - event_id: $event_id, nominee_id: $nominee_id, vote_count: $vote_count");
    
    if (!$event_id || !$nominee_id || !$vote_count || !$voter_name || !$phone_number) {
        $response = [
            'success' => false, 
            'message' => 'Missing required fields',
            'step' => 'form_validation',
            'data' => [
                'event_id' => $event_id,
                'nominee_id' => $nominee_id,
                'vote_count' => $vote_count,
                'voter_name' => $voter_name,
                'phone_number' => $phone_number
            ]
        ];
        error_log("Form validation failed: " . json_encode($response['data']));
        echo json_encode($response);
        exit;
    }
    
    error_log("Step 8: Form validation passed");
    
    // Step 8: Check if system_settings table exists and has required settings
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            $response = ['success' => false, 'message' => 'system_settings table not found', 'step' => 'system_settings_table'];
            error_log("system_settings table does not exist");
            echo json_encode($response);
            exit;
        }
        
        error_log("Step 9: system_settings table exists");
        
        // Check for required settings
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('enable_hubtel_payments', 'default_vote_cost')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        error_log("Step 10: Found settings: " . json_encode($settings));
        
        if (empty($settings)) {
            $response = ['success' => false, 'message' => 'No system settings found - database may need setup', 'step' => 'system_settings_data'];
            error_log("No system settings found in database");
            echo json_encode($response);
            exit;
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'System settings check failed: ' . $e->getMessage(), 'step' => 'system_settings_error'];
        error_log("System settings error: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
    
    // If we get here, everything is working
    $response = [
        'success' => true, 
        'message' => 'All checks passed - API is working correctly',
        'step' => 'all_checks_passed',
        'data' => [
            'event_id' => $event_id,
            'nominee_id' => $nominee_id,
            'vote_count' => $vote_count,
            'voter_name' => $voter_name,
            'phone_number' => $phone_number,
            'settings_found' => $settings
        ]
    ];
    
    error_log("SUCCESS: All checks passed");
    echo json_encode($response);
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage(), 'step' => 'unexpected_error'];
    error_log("Unexpected error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode($response);
} catch (Error $e) {
    $response = ['success' => false, 'message' => 'Fatal error: ' . $e->getMessage(), 'step' => 'fatal_error'];
    error_log("Fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode($response);
}

error_log("=== DEBUG VOTE SUBMIT END ===");
?>
