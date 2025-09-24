<?php
/**
 * USSD Database Migration Script
 * Creates necessary tables for USSD voting functionality
 */

require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>USSD Database Migration</h2>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";
    
    // Create USSD sessions table
    echo "Creating ussd_sessions table...<br>";
    $sql = "
    CREATE TABLE IF NOT EXISTS ussd_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL UNIQUE,
        session_data TEXT,
        phone_number VARCHAR(20),
        current_level INT DEFAULT 0,
        current_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 MINUTE),
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_expires_at (expires_at)
    )";
    
    $pdo->exec($sql);
    echo "✅ ussd_sessions table created successfully<br>";
    
    // Create USSD transactions table
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
        paystack_reference VARCHAR(100),
        status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        payment_response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_transaction_ref (transaction_ref),
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_status (status)
    )";
    
    $pdo->exec($sql);
    echo "✅ ussd_transactions table created successfully<br>";
    
    // Add USSD settings
    echo "Adding USSD system settings...<br>";
    $settings = [
        ['ussd_short_code', '*170*123#'],
        ['ussd_app_name', 'E-Cast Voting'],
        ['ussd_welcome_message', 'Welcome to E-Cast Voting'],
        ['enable_ussd_voting', '1'],
        ['enable_ussd_sms', '1'],
        ['ussd_session_timeout', '300'],
        ['ussd_max_menu_items', '9']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
        echo "✅ Added setting: {$setting[0]} = {$setting[1]}<br>";
    }
    
    echo "</div>";
    echo "<h3 style='color: green;'>✅ USSD Migration Completed Successfully!</h3>";
    echo "<p><a href='../admin/ussd-settings.php'>Go to USSD Settings</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Migration Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    error_log("USSD Migration Error: " . $e->getMessage());
}
?>
