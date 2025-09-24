<?php
/**
 * Add Arkesel USSD Payment Settings to System Settings
 * Run this script to add Arkesel configuration options
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Adding Arkesel USSD Payment Settings...\n";
    
    // Arkesel settings to add
    $arkeselSettings = [
        [
            'setting_key' => 'arkesel_api_key',
            'setting_value' => '',
            'description' => 'Arkesel API Key for USSD payments'
        ],
        [
            'setting_key' => 'arkesel_api_secret',
            'setting_value' => '',
            'description' => 'Arkesel API Secret for USSD payments'
        ],
        [
            'setting_key' => 'enable_ussd_payments',
            'setting_value' => '1',
            'description' => 'Enable USSD payment method for voting'
        ],
        [
            'setting_key' => 'ussd_payment_provider',
            'setting_value' => 'arkesel',
            'description' => 'USSD payment provider (arkesel, hubtel, etc.)'
        ],
        [
            'setting_key' => 'ussd_fallback_code',
            'setting_value' => '*170*456#',
            'description' => 'Fallback USSD code for development/testing'
        ]
    ];
    
    // Insert settings
    $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, description, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    description = VALUES(description)";
    
    $stmt = $pdo->prepare($insertQuery);
    
    foreach ($arkeselSettings as $setting) {
        $stmt->execute([
            $setting['setting_key'],
            $setting['setting_value'],
            $setting['description']
        ]);
        
        echo "✓ Added/Updated: {$setting['setting_key']}\n";
    }
    
    echo "\n✅ Arkesel USSD Payment Settings added successfully!\n";
    echo "\nNext Steps:\n";
    echo "1. Go to Admin Panel > System Settings\n";
    echo "2. Configure your Arkesel API credentials\n";
    echo "3. Test USSD payments with real phone numbers\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
