<?php
/**
 * Setup System Settings Database
 * Run this to create the system_settings table and default data
 * Access via: http://localhost/e-cast-voting-system/setup-system-settings.php
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Setting up System Settings Database</h2>";
    echo "<style>body{font-family: Arial; margin: 20px;} .success{color: green;} .error{color: red;} .info{color: blue;}</style>";

    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='info'>ℹ️ system_settings table already exists</div>";
    } else {
        echo "<div class='info'>Creating system_settings table...</div>";
        
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
        echo "<div class='success'>✅ system_settings table created</div>";
    }

    // Insert default settings
    echo "<div class='info'>Inserting/updating default vote settings...</div>";
    
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
    }
    
    echo "<div class='success'>✅ Default settings inserted/updated</div>";

    // Show current settings
    echo "<h3>Current Vote Settings:</h3>";
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE '%vote%' OR setting_key LIKE '%fee%' ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($settings)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Setting Key</th><th>Value</th><th>Description</th><th>Last Updated</th></tr>";
        foreach ($settings as $setting) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['description']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['updated_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<div class='success'>✅ System settings database setup complete!</div>";
    echo "<div>You can now access the Vote Settings admin panel to configure voting fees.</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>