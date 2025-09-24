<?php
/**
 * Create USSD Application via Arkesel API
 * Admin action to set up USSD voting application
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../services/ArkeselUSSDService.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $shortCode = $input['short_code'] ?? '*170*123#';
    
    $ussdService = new ArkeselUSSDService();
    $result = $ussdService->createUSSDApplication($shortCode);
    
    if ($result['success'] ?? false) {
        // Update database with USSD app details
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES ('ussd_app_id', ?), ('ussd_app_status', 'active')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute([$result['app_id'] ?? '']);
        
        echo json_encode([
            'success' => true,
            'message' => 'USSD application created successfully',
            'app_id' => $result['app_id'] ?? '',
            'short_code' => $shortCode
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to create USSD application'
        ]);
    }
    
} catch (Exception $e) {
    error_log("USSD App Creation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating USSD application'
    ]);
}
?>
