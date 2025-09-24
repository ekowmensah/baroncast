<?php
require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== Switching to Hubtel Sandbox Mode ===\n";
    
    // Update to sandbox settings
    $sandboxSettings = [
        'hubtel_environment' => 'sandbox',
        'hubtel_test_mode' => '1',
        'hubtel_pos_id' => 'sandbox_pos_id',
        'hubtel_api_key' => 'sandbox_api_key',
        'hubtel_api_secret' => 'sandbox_api_secret'
    ];
    
    foreach ($sandboxSettings as $key => $value) {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        echo "✓ Updated $key to: $value\n";
    }
    
    echo "\n✅ Switched to sandbox mode for testing!\n";
    echo "Note: Sandbox mode will simulate payments without real money transfer.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
