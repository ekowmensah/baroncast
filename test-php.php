<?php
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";

// Test if we can access the database config
$db_config_path = __DIR__ . '/config/database.php';
echo "Database config path: " . $db_config_path . "<br>";
echo "Database config exists: " . (file_exists($db_config_path) ? 'YES' : 'NO') . "<br>";

if (file_exists($db_config_path)) {
    try {
        require_once $db_config_path;
        echo "Database config loaded successfully<br>";
        
        $database = new Database();
        $pdo = $database->getConnection();
        echo "Database connection: SUCCESS<br>";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "Database query test: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
        
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "<br>";
    }
}

// Check if services directory exists
$services_path = __DIR__ . '/services';
echo "Services directory exists: " . (is_dir($services_path) ? 'YES' : 'NO') . "<br>";

if (is_dir($services_path)) {
    $hubtel_service = $services_path . '/HubtelReceiveMoneyService.php';
    echo "HubtelReceiveMoneyService exists: " . (file_exists($hubtel_service) ? 'YES' : 'NO') . "<br>";
}

// Check logs directory
$logs_path = __DIR__ . '/logs';
echo "Logs directory exists: " . (is_dir($logs_path) ? 'YES' : 'NO') . "<br>";
echo "Logs directory writable: " . (is_writable($logs_path) ? 'YES' : 'NO') . "<br>";
?>
