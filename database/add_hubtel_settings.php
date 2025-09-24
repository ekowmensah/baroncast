<?php
/**
 * Add Hubtel SMS API settings to system_settings table
 * Run this script once to add Hubtel SMS integration settings
 */

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Adding Hubtel SMS settings to system_settings table...\n";
    
    // Hubtel SMS settings to add
    $hubtelSettings = [
        ['hubtel_client_id', '', 'Hubtel SMS Client ID'],
        ['hubtel_client_secret', '', 'Hubtel SMS Client Secret'],
        ['hubtel_api_key', '', 'Hubtel SMS API Key'],
        ['hubtel_sender_id', 'E-Cast', 'Hubtel SMS Sender ID'],
        ['enable_hubtel_sms', '0', 'Enable Hubtel SMS Integration'],
        ['hubtel_environment', 'sandbox', 'Hubtel Environment (sandbox/production)'],
        ['hubtel_sms_timeout', '30', 'Hubtel SMS Timeout in seconds'],
        ['hubtel_max_retries', '3', 'Maximum SMS retry attempts']
    ];
    
    // Prepare the insert/update statement
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, description) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = IF(setting_value = '', VALUES(setting_value), setting_value),
        description = VALUES(description)
    ");
    
    $added = 0;
    $updated = 0;
    
    foreach ($hubtelSettings as $setting) {
        // Check if setting already exists
        $checkStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $checkStmt->execute([$setting[0]]);
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            echo "Setting '{$setting[0]}' already exists with value: '{$exists['setting_value']}'\n";
            $updated++;
        } else {
            $stmt->execute($setting);
            echo "Added setting: {$setting[0]} = {$setting[1]}\n";
            $added++;
        }
    }
    
    echo "\nHubtel SMS settings migration completed!\n";
    echo "Added: $added new settings\n";
    echo "Updated: $updated existing settings\n";
    echo "\nYou can now configure Hubtel SMS settings in the admin panel.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>
