<?php
/**
 * Switch Hubtel to Sandbox Mode for Testing
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== SWITCHING TO HUBTEL SANDBOX MODE ===\n\n";
    
    // Backup current production settings
    echo "1. Backing up current production settings...\n";
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
    $currentSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $backupFile = __DIR__ . '/hubtel_production_backup_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($backupFile, json_encode($currentSettings, JSON_PRETTY_PRINT));
    echo "✅ Production settings backed up to: " . basename($backupFile) . "\n\n";
    
    // Switch to sandbox settings
    echo "2. Updating to sandbox configuration...\n";
    
    $sandboxSettings = [
        'hubtel_environment' => 'sandbox',
        'hubtel_test_mode' => '1',
        'hubtel_pos_id' => 'sandbox_pos_id',  // Hubtel will provide sandbox credentials
        'hubtel_api_key' => 'sandbox_api_key',
        'hubtel_api_secret' => 'sandbox_api_secret',
        'hubtel_callback_url' => 'http://localhost/baroncast/webhooks/hubtel-receive-money-callback.php'
    ];
    
    foreach ($sandboxSettings as $key => $value) {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        echo "✅ Updated $key\n";
    }
    
    echo "\n3. SANDBOX MODE ACTIVATED\n";
    echo "------------------------\n";
    echo "✅ Environment: sandbox\n";
    echo "✅ Test Mode: enabled\n";
    echo "✅ Callback URL: localhost\n";
    echo "⚠️  Note: You'll need actual Hubtel sandbox credentials\n";
    
    echo "\n4. NEXT STEPS:\n";
    echo "-------------\n";
    echo "1. Contact Hubtel to get sandbox credentials\n";
    echo "2. Update the sandbox credentials in admin panel\n";
    echo "3. Test payments in sandbox mode\n";
    echo "4. Switch back to production when ready\n";
    
    echo "\n5. TO RESTORE PRODUCTION SETTINGS:\n";
    echo "---------------------------------\n";
    echo "Run: php restore-hubtel-production.php\n";
    echo "Or manually restore from: " . basename($backupFile) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
