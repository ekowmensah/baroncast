<?php
/**
 * Create Hubtel USSD Database Tables - Fixed Version
 * Compatible with older MySQL versions
 */

require_once '../config/database.php';

// Set HTTP_HOST for proper database connection
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>🚀 Hubtel USSD Database Setup (Fixed)</h2>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";
    
    // 1. Create USSD sessions table
    echo "Creating ussd_sessions table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL UNIQUE,
        phone_number VARCHAR(20) NOT NULL,
        current_menu VARCHAR(50) DEFAULT 'main_menu',
        session_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $pdo->exec($sql);
    echo "✅ ussd_sessions table created successfully<br>";
    
    // 2. Create USSD applications table
    echo "Creating ussd_applications table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_name VARCHAR(100) NOT NULL,
        short_code VARCHAR(20) NOT NULL UNIQUE,
        webhook_url VARCHAR(255) NOT NULL,
        application_id VARCHAR(100),
        status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_short_code (short_code),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $pdo->exec($sql);
    echo "✅ ussd_applications table created successfully<br>";
    
    // 3. Create USSD transactions table
    echo "Creating ussd_transactions table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100),
        transaction_ref VARCHAR(100) NOT NULL UNIQUE,
        phone_number VARCHAR(20) NOT NULL,
        event_id INT,
        nominee_id INT,
        vote_count INT DEFAULT 1,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        hubtel_transaction_id VARCHAR(100),
        payment_token VARCHAR(100),
        ussd_code VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        INDEX idx_transaction_ref (transaction_ref),
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_status (status),
        INDEX idx_event_id (event_id),
        INDEX idx_nominee_id (nominee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $pdo->exec($sql);
    echo "✅ ussd_transactions table created successfully<br>";
    
    // 4. Create USSD webhook logs table
    echo "Creating ussd_webhook_logs table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_webhook_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100),
        phone_number VARCHAR(20),
        request_data TEXT,
        response_data TEXT,
        processing_time_ms INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $pdo->exec($sql);
    echo "✅ ussd_webhook_logs table created successfully<br>";
    
    // 5. Update transactions table to include USSD payment method
    echo "Updating transactions table payment_method...<br>";
    try {
        // First check if the column exists and what type it is
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_method'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            $currentType = $column['Type'];
            echo "Current payment_method type: $currentType<br>";
            
            // Check if USSD is already supported
            if (strpos($currentType, 'ussd') === false) {
                // Try to update the enum - this might fail on some MySQL versions
                try {
                    $sql = "ALTER TABLE transactions MODIFY COLUMN payment_method VARCHAR(50) DEFAULT 'mobile_money'";
                    $pdo->exec($sql);
                    echo "✅ Updated transactions.payment_method to VARCHAR for flexibility<br>";
                } catch (Exception $e) {
                    echo "⚠️  Could not update payment_method column: " . $e->getMessage() . "<br>";
                    echo "ℹ️  You may need to manually update this column to support USSD payments<br>";
                }
            } else {
                echo "✅ USSD payment methods already supported in transactions table<br>";
            }
        } else {
            echo "⚠️  transactions table or payment_method column not found<br>";
        }
    } catch (Exception $e) {
        echo "⚠️  Could not check/update payment_method column: " . $e->getMessage() . "<br>";
    }
    
    // 6. Add Hubtel USSD settings
    echo "Adding Hubtel USSD system settings...<br>";
    $settings = [
        ['hubtel_ussd_enabled', '1', 'Enable Hubtel USSD voting'],
        ['hubtel_ussd_shortcode', '*928*280#', 'Hubtel USSD shortcode for voting'],
        ['hubtel_ussd_app_name', 'BaronCast Voting', 'USSD application name'],
        ['hubtel_ussd_welcome_message', 'Welcome to BaronCast Voting', 'USSD welcome message'],
        ['hubtel_ussd_session_timeout', '300', 'USSD session timeout in seconds'],
        ['hubtel_ussd_max_menu_items', '9', 'Maximum menu items per USSD screen'],
        ['hubtel_ussd_webhook_url', '', 'Webhook URL for USSD interactions']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, description) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        description = VALUES(description)
    ");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "✅ Hubtel USSD settings added successfully<br>";
    
    // 7. Verify table creation
    echo "<br>Verifying table creation...<br>";
    $tables = ['ussd_sessions', 'ussd_applications', 'ussd_transactions', 'ussd_webhook_logs'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "✅ $table: $count records<br>";
        } catch (Exception $e) {
            echo "❌ $table: Error - " . $e->getMessage() . "<br>";
        }
    }
    
    echo "</div>";
    echo "<h3 style='color: green;'>🎉 Hubtel USSD Database Setup Completed Successfully!</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>📋 Next Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Test USSD Payment:</strong> <a href='../test-hubtel-ussd.html'>Test USSD Payment Generation</a></li>";
    echo "<li><strong>Configure USSD Settings:</strong> <a href='../admin/ussd-settings.php'>Go to USSD Settings</a></li>";
    echo "<li><strong>Check Tables:</strong> Verify all tables were created in your database</li>";
    echo "<li><strong>Test Integration:</strong> Try generating a USSD payment code</li>";
    echo "</ol>";
    echo "</div>";
    
    // 8. Show current settings
    echo "<h4>📊 Current Hubtel USSD Settings:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Setting</th><th style='padding: 8px;'>Value</th></tr>";
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_ussd_%' ORDER BY setting_key");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td style='padding: 5px;'>{$row['setting_key']}</td><td style='padding: 5px;'>{$row['setting_value']}</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td colspan='2' style='padding: 5px; color: red;'>Error loading settings: " . $e->getMessage() . "</td></tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>⚠️ Important Notes:</h4>";
    echo "<ul>";
    echo "<li>If payment_method column update failed, you may need to manually allow USSD payments</li>";
    echo "<li>Test the USSD payment generation to ensure everything works</li>";
    echo "<li>Check your MySQL version - some features require MySQL 5.7+</li>";
    echo "<li>All tables use compatible data types for older MySQL versions</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>🔧 Troubleshooting:</h4>";
    echo "<ul>";
    echo "<li><strong>MySQL Version:</strong> Ensure you're using MySQL 5.5+ or MariaDB 10.0+</li>";
    echo "<li><strong>Permissions:</strong> Check that your database user has CREATE TABLE permissions</li>";
    echo "<li><strong>Charset:</strong> Some older MySQL versions may not support utf8mb4</li>";
    echo "<li><strong>JSON:</strong> JSON data type requires MySQL 5.7+ (using TEXT as fallback)</li>";
    echo "</ul>";
    echo "</div>";
    error_log("Hubtel USSD Database Setup Error: " . $e->getMessage());
}
?>
