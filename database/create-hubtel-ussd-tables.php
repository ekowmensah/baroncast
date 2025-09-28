<?php
/**
 * Create Hubtel USSD Database Tables
 * Sets up necessary tables for USSD voting functionality
 */

require_once '../config/database.php';

// Set HTTP_HOST for proper database connection
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>🚀 Hubtel USSD Database Setup</h2>";
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
        expires_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_short_code (short_code),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
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
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_transaction_ref (transaction_ref),
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ ussd_transactions table created successfully<br>";
    
    // 4. Update transactions table to include USSD payment method
    echo "Updating transactions table payment_method enum...<br>";
    try {
        // Check current enum values
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_method'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column && strpos($column['Type'], 'ussd') === false) {
            // Add USSD to payment method enum
            $sql = "ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('mobile_money', 'card', 'ussd', 'hubtel_ussd', 'test_payment') DEFAULT 'mobile_money'";
            $pdo->exec($sql);
            echo "✅ Added USSD payment methods to transactions table<br>";
        } else {
            echo "✅ USSD payment methods already supported in transactions table<br>";
        }
    } catch (Exception $e) {
        echo "⚠️  Could not update payment_method enum: " . $e->getMessage() . "<br>";
    }
    
    // 5. Add Hubtel USSD settings
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
    
    // 6. Create USSD webhook log table
    echo "Creating ussd_webhook_logs table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_webhook_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100),
        phone_number VARCHAR(20),
        request_data JSON,
        response_data JSON,
        processing_time_ms INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ ussd_webhook_logs table created successfully<br>";
    
    // 7. Verify table creation
    echo "<br>Verifying table creation...<br>";
    $tables = ['ussd_sessions', 'ussd_applications', 'ussd_transactions', 'ussd_webhook_logs'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "✅ $table: $count records<br>";
    }
    
    echo "</div>";
    echo "<h3 style='color: green;'>🎉 Hubtel USSD Database Setup Completed Successfully!</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>📋 Next Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Configure USSD Settings:</strong> <a href='../admin/ussd-settings.php'>Go to USSD Settings</a></li>";
    echo "<li><strong>Test USSD Payment:</strong> <a href='../voter/test-ussd-payment.php'>Test USSD Payment</a></li>";
    echo "<li><strong>Create USSD Application:</strong> Contact Hubtel for shortcode approval</li>";
    echo "<li><strong>Set Webhook URL:</strong> Configure webhook in Hubtel dashboard</li>";
    echo "</ol>";
    echo "</div>";
    
    // 8. Show current settings
    echo "<h4>📊 Current Hubtel USSD Settings:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_ussd_%' ORDER BY setting_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['setting_key']}</td><td>{$row['setting_value']}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Database Setup Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    error_log("Hubtel USSD Database Setup Error: " . $e->getMessage());
}
?>
