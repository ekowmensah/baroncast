<?php
/**
 * Apply Paystack Migration
 * Removes Arkesel/Hubtel settings and adds Paystack settings
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Paystack Migration Script</h2>\n";
    echo "<p>Removing Arkesel/Hubtel settings and adding Paystack settings...</p>\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Remove Arkesel settings
    $arkesel_keys = [
        'arkesel_api_key',
        'arkesel_sender_id', 
        'arkesel_ussd_shortcode',
        'arkesel_webhook_url'
    ];
    
    $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
    foreach ($arkesel_keys as $key) {
        $stmt->execute([$key]);
        echo "✓ Removed setting: {$key}<br>\n";
    }
    
    // Remove Hubtel settings
    $hubtel_keys = [
        'hubtel_client_id',
        'hubtel_client_secret',
        'hubtel_sender_id',
        'hubtel_api_key'
    ];
    
    foreach ($hubtel_keys as $key) {
        $stmt->execute([$key]);
        echo "✓ Removed setting: {$key}<br>\n";
    }
    
    // Add Paystack settings
    $paystack_settings = [
        ['paystack_public_key', '', 'Paystack Public Key for frontend integration', 'payment'],
        ['paystack_secret_key', '', 'Paystack Secret Key for API calls', 'payment'],
        ['paystack_webhook_secret', '', 'Paystack Webhook Secret for signature verification', 'payment'],
        ['payment_currency', 'GHS', 'Payment currency (Ghana Cedis)', 'payment'],
        ['payment_gateway', 'paystack', 'Primary payment gateway', 'payment']
    ];
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, description, category) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            description = VALUES(description),
            category = VALUES(category)
    ");
    
    foreach ($paystack_settings as $setting) {
        $insert_stmt->execute($setting);
        echo "✓ Added/Updated setting: {$setting[0]} = {$setting[1]}<br>\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<br><strong>✅ Migration completed successfully!</strong><br>\n";
    echo "<p>Paystack settings have been added. Please update the Paystack API keys in the admin dashboard.</p>\n";
    
    // Display current payment settings
    echo "<h3>Current Payment Settings:</h3>\n";
    $stmt = $pdo->prepare("SELECT * FROM system_settings WHERE category = 'payment' ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Setting Key</th><th>Value</th><th>Description</th></tr>\n";
    foreach ($settings as $setting) {
        $value = empty($setting['setting_value']) ? '<em>Not set</em>' : htmlspecialchars($setting['setting_value']);
        echo "<tr><td>{$setting['setting_key']}</td><td>{$value}</td><td>{$setting['description']}</td></tr>\n";
    }
    echo "</table>\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo "<strong>❌ Migration failed:</strong> " . htmlspecialchars($e->getMessage()) . "<br>\n";
    error_log("Paystack migration error: " . $e->getMessage());
}
?>
