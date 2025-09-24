<?php
/**
 * Hubtel Schema Setup Script
 * Safely adds Hubtel-specific database columns and tables
 */

require_once __DIR__ . '/config/database.php';

echo "<h1>🔧 Hubtel Schema Setup</h1>";
echo "<p>Setting up database schema for Hubtel integration...</p>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h3>1. Checking existing columns...</h3>";
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hubtel_columns = [
        'hubtel_transaction_id' => 'VARCHAR(100) NULL COMMENT \'Hubtel transaction ID\'',
        'external_transaction_id' => 'VARCHAR(100) NULL COMMENT \'Telco transaction ID\'',
        'payment_charges' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Hubtel payment charges\'',
        'amount_charged' => 'DECIMAL(10,2) NULL COMMENT \'Total amount charged to customer\''
    ];
    
    foreach ($hubtel_columns as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $sql = "ALTER TABLE transactions ADD COLUMN $column_name $column_definition";
                $pdo->exec($sql);
                echo "✅ Added column: $column_name<br>";
            } catch (PDOException $e) {
                echo "❌ Failed to add column $column_name: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "ℹ️ Column already exists: $column_name<br>";
        }
    }
    
    echo "<h3>2. Creating indexes...</h3>";
    
    // Add indexes (skip if they already exist)
    $indexes = [
        'idx_transactions_hubtel_id' => 'CREATE INDEX idx_transactions_hubtel_id ON transactions(hubtel_transaction_id)',
        'idx_transactions_external_id' => 'CREATE INDEX idx_transactions_external_id ON transactions(external_transaction_id)'
    ];
    
    foreach ($indexes as $index_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Created index: $index_name<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "ℹ️ Index already exists: $index_name<br>";
            } else {
                echo "❌ Failed to create index $index_name: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<h3>3. Creating Hubtel transaction logs table...</h3>";
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hubtel_transaction_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_reference VARCHAR(100) NOT NULL,
                log_type ENUM('request', 'response', 'callback', 'status_check', 'error') NOT NULL,
                log_data JSON NOT NULL,
                http_code INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reference (transaction_reference),
                INDEX idx_type_date (log_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created hubtel_transaction_logs table<br>";
    } catch (PDOException $e) {
        echo "❌ Failed to create logs table: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>4. Checking system_settings table structure...</h3>";
    
    // Check if system_settings table has category column
    $stmt = $pdo->query("SHOW COLUMNS FROM system_settings");
    $settings_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $has_category = in_array('category', $settings_columns);
    $has_description = in_array('description', $settings_columns);
    
    echo "ℹ️ Table structure detected:<br>";
    echo "- Has 'category' column: " . ($has_category ? "Yes" : "No") . "<br>";
    echo "- Has 'description' column: " . ($has_description ? "Yes" : "No") . "<br><br>";
    
    echo "<h3>5. Adding Hubtel system settings...</h3>";
    
    $hubtel_settings = [
        'hubtel_pos_id' => ['', 'Hubtel POS Sales ID for Direct Receive Money API'],
        'hubtel_api_key' => ['', 'Hubtel API Key for authentication'],
        'hubtel_api_secret' => ['', 'Hubtel API Secret for authentication'],
        'hubtel_environment' => ['sandbox', 'Hubtel environment (sandbox/production)'],
        'hubtel_callback_url' => ['', 'Webhook callback URL for payment confirmations'],
        'hubtel_ip_whitelist' => ['', 'Comma-separated list of whitelisted IPs'],
        'enable_hubtel_payments' => ['1', 'Enable Hubtel mobile money payments'],
        'hubtel_timeout' => ['30', 'API request timeout in seconds'],
        'hubtel_max_retries' => ['3', 'Maximum retry attempts for failed requests'],
        'hubtel_test_mode' => ['1', 'Enable test mode for development']
    ];
    
    // Prepare SQL based on available columns
    if ($has_category && $has_description) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES (?, ?, ?, 'payment') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($hubtel_settings as $key => $data) {
            try {
                $stmt->execute([$key, $data[0], $data[1]]);
                echo "✅ Added setting: $key<br>";
            } catch (PDOException $e) {
                echo "❌ Failed to add setting $key: " . $e->getMessage() . "<br>";
            }
        }
    } elseif ($has_description) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($hubtel_settings as $key => $data) {
            try {
                $stmt->execute([$key, $data[0], $data[1]]);
                echo "✅ Added setting: $key<br>";
            } catch (PDOException $e) {
                echo "❌ Failed to add setting $key: " . $e->getMessage() . "<br>";
            }
        }
    } else {
        // Basic table structure with just setting_key and setting_value
        $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($hubtel_settings as $key => $data) {
            try {
                $stmt->execute([$key, $data[0]]);
                echo "✅ Added setting: $key<br>";
            } catch (PDOException $e) {
                echo "❌ Failed to add setting $key: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<h3>6. Creating analytics view...</h3>";
    
    try {
        $pdo->exec("
            CREATE OR REPLACE VIEW hubtel_payment_summary AS
            SELECT 
                DATE(created_at) as payment_date,
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN payment_charges ELSE 0 END) as total_charges,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction_amount
            FROM transactions 
            WHERE payment_method = 'mobile_money'
            GROUP BY DATE(created_at)
            ORDER BY payment_date DESC
        ");
        echo "✅ Created hubtel_payment_summary view<br>";
    } catch (PDOException $e) {
        echo "❌ Failed to create analytics view: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>✅ Schema Setup Complete!</h3>";
    echo "<p>You can now configure Hubtel settings in the admin panel.</p>";
    echo "<p><a href='/admin/hubtel-settings.php'>→ Go to Hubtel Settings</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>