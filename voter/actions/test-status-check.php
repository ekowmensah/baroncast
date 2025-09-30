<?php
/**
 * Simple Test Endpoint for Status Check
 */

header('Content-Type: application/json');

// Test basic functionality
try {
    // Test database connection
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();

    // Test service loading
    require_once __DIR__ . '/../services/HubtelTransactionStatusService.php';
    $service = new HubtelTransactionStatusService();

    echo json_encode([
        'success' => true,
        'message' => 'Basic setup test passed',
        'timestamp' => date('c'),
        'php_version' => phpversion()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
