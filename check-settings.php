<?php
require_once __DIR__ . '/config/database.php';

try {
    // Set HTTP_HOST for command line execution
    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== Checking System Settings ===\n";
    
    // Check Hubtel settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%' OR setting_key = 'enable_hubtel_payments' OR setting_key = 'default_vote_cost'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "No Hubtel settings found. Creating default settings...\n";
        
        // Insert default settings
        $defaultSettings = [
            'enable_hubtel_payments' => '1',
            'default_vote_cost' => '1.00',
            'hubtel_environment' => 'sandbox',
            'hubtel_pos_id' => '',
            'hubtel_api_key' => '',
            'hubtel_api_secret' => ''
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
            echo "Created setting: $key = $value\n";
        }
    } else {
        echo "Found settings:\n";
        foreach ($settings as $setting) {
            echo "- {$setting['setting_key']}: {$setting['setting_value']}\n";
        }
    }
    
    echo "\n=== Checking Transactions Table Structure ===\n";
    
    // Check transactions table structure
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['created_at', 'updated_at', 'hubtel_transaction_id', 'payment_response'];
    $missingColumns = [];
    
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumns)) {
            $missingColumns[] = $col;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "Missing columns in transactions table: " . implode(', ', $missingColumns) . "\n";
        echo "Adding missing columns...\n";
        
        foreach ($missingColumns as $col) {
            switch ($col) {
                case 'created_at':
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    echo "Added created_at column\n";
                    break;
                case 'updated_at':
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    echo "Added updated_at column\n";
                    break;
                case 'hubtel_transaction_id':
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN hubtel_transaction_id VARCHAR(100)");
                    echo "Added hubtel_transaction_id column\n";
                    break;
                case 'payment_response':
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_response TEXT");
                    echo "Added payment_response column\n";
                    break;
            }
        }
    } else {
        echo "All required columns exist in transactions table\n";
    }
    
    echo "\n=== Checking Votes Table Structure ===\n";
    
    // Check votes table structure
    $stmt = $pdo->query("DESCRIBE votes");
    $voteColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $voteExistingColumns = array_column($voteColumns, 'Field');
    
    if (!in_array('created_at', $voteExistingColumns)) {
        echo "Adding created_at column to votes table...\n";
        $pdo->exec("ALTER TABLE votes ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added created_at column to votes table\n";
    } else {
        echo "Votes table has required columns\n";
    }
    
    echo "\nSetup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
