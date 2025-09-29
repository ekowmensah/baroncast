<?php
/**
 * Test callback logging
 */

// Test direct logging
$log_file = __DIR__ . '/../logs/test-logging.log';
$message = date('c') . " TEST: Callback handler logging test\n";

if (file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX)) {
    echo "SUCCESS: Logging works\n";
} else {
    echo "ERROR: Cannot write to log file\n";
}

// Test if callback file is accessible
echo "Callback file exists: " . (file_exists(__DIR__ . '/hubtel-receive-money-callback.php') ? 'YES' : 'NO') . "\n";

// Test database connection
try {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    echo "Database connection: SUCCESS\n";
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}

echo "Test completed at " . date('c') . "\n";
?>
