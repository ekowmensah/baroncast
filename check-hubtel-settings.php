<?php
require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== Current Hubtel Settings ===\n";
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%' ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        // Mask sensitive values
        if (strpos($setting['setting_key'], 'secret') !== false || strpos($setting['setting_key'], 'key') !== false) {
            $value = str_repeat('*', strlen($value));
        }
        echo "{$setting['setting_key']}: {$value}\n";
    }
    
    echo "\n=== Issue Analysis ===\n";
    echo "The 403 Forbidden error indicates:\n";
    echo "1. Invalid API credentials (POS ID, API Key, or Secret)\n";
    echo "2. Account doesn't have permission for Direct Receive Money\n";
    echo "3. Using production credentials in sandbox mode (or vice versa)\n";
    echo "4. IP address not whitelisted (if required)\n";
    
    echo "\n=== Recommendations ===\n";
    echo "1. Verify Hubtel credentials are correct\n";
    echo "2. Check if account has Direct Receive Money enabled\n";
    echo "3. Consider switching to sandbox mode for testing\n";
    echo "4. Contact Hubtel support if credentials are correct\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
