<?php
/**
 * Setup System Settings Database - Local Version
 * Run this to create the system_settings table and default data on localhost
 */

// Force local environment for command line execution
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Setting up System Settings Database\n";
    echo "===================================\n";

    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        echo "✓ system_settings table already exists\n";
    } else {
        echo "Creating system_settings table...\n";
        
        // Create table
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        )";
        
        $pdo->exec($createTableSQL);
        echo "✓ system_settings table created\n";
    }

    // Insert default settings
    echo "Inserting/updating default vote settings...\n";
    
    $defaultSettings = [
        [
            'key' => 'default_vote_cost',
            'value' => '1.00',
            'description' => 'Default cost per vote when event has no custom fee'
        ],
        [
            'key' => 'enable_event_custom_fee',
            'value' => '1',
            'description' => 'Allow events to set custom voting fees (1=enabled, 0=disabled)'
        ],
        [
            'key' => 'min_vote_cost',
            'value' => '0.50',
            'description' => 'Minimum allowed vote cost for custom event fees'
        ],
        [
            'key' => 'max_vote_cost',
            'value' => '100.00',
            'description' => 'Maximum allowed vote cost for custom event fees'
        ]
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE description = VALUES(description)
        ");
        $stmt->execute([$setting['key'], $setting['value'], $setting['description']]);
        echo "✓ Setting: {$setting['key']} = {$setting['value']}\n";
    }
    
    echo "\nCurrent Vote Settings:\n";
    echo "=====================\n";
    $stmt = $pdo->query("SELECT setting_key, setting_value, description FROM system_settings WHERE setting_key LIKE '%vote%' OR setting_key LIKE '%fee%' ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        echo sprintf("%-25s: %-10s (%s)\n", 
            $setting['setting_key'], 
            $setting['setting_value'], 
            $setting['description']
        );
    }

    echo "\n✓ System settings database setup complete!\n";
    echo "You can now access the Vote Settings admin panel to configure voting fees.\n";

} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}
?>