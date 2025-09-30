<?php
/**
 * Simple Service Test
 */

header('Content-Type: application/json');

try {
    // Test database connection
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();

    // Test basic service instantiation
    require_once __DIR__ . '/../services/HubtelTransactionStatusService.php';

    // Don't instantiate yet, just check if file loads
    echo json_encode([
        'success' => true,
        'message' => 'Service file loaded successfully',
        'database_connected' => true
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
