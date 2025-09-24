<?php
/**
 * Initialize Development Mode Settings in Database
 * Run this once to set up development mode configuration
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Initializing Development Mode Settings...</h2>";
    
    // Development mode settings to insert/update
    $devSettings = [
        'development_mode' => '1',
        'dev_bypass_sms' => '1', 
        'dev_default_otp' => '123456',
        'dev_simulate_payments' => '1'
    ];
    
    foreach ($devSettings as $key => $value) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle existing settings
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type, description) 
            VALUES (?, ?, 'string', ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        $description = '';
        switch ($key) {
            case 'development_mode':
                $description = 'Enable development mode for localhost testing';
                break;
            case 'dev_bypass_sms':
                $description = 'Bypass real SMS sending in development mode';
                break;
            case 'dev_default_otp':
                $description = 'Fixed OTP code for development testing';
                break;
            case 'dev_simulate_payments':
                $description = 'Simulate successful payments in development mode';
                break;
        }
        
        $stmt->execute([$key, $value, $description]);
        echo "✓ Set {$key} = {$value}<br>";
    }
    
    echo "<br><h3>Development Mode Settings Initialized Successfully!</h3>";
    echo "<p><strong>Current Mode:</strong> Development Mode (localhost testing)</p>";
    echo "<p><strong>Fixed OTP:</strong> 123456</p>";
    echo "<p><strong>SMS:</strong> Bypassed (no real SMS sending)</p>";
    echo "<p><strong>Payments:</strong> Simulated (no real money transactions)</p>";
    
    echo "<hr>";
    echo "<h4>How to Switch Modes:</h4>";
    echo "<ol>";
    echo "<li><strong>Admin Dashboard:</strong> Go to <a href='general-settings.php'>General Settings</a></li>";
    echo "<li><strong>Development Mode Section:</strong> Toggle the 'Enable Development Mode' switch</li>";
    echo "<li><strong>Save Settings:</strong> Click 'Save Settings' to apply changes</li>";
    echo "</ol>";
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h5>Mode Comparison:</h5>";
    echo "<strong>Development Mode (ON):</strong><br>";
    echo "• Perfect for localhost testing<br>";
    echo "• No real API credentials needed<br>";
    echo "• Fixed OTP: 123456<br>";
    echo "• Simulated payments<br><br>";
    
    echo "<strong>Production Mode (OFF):</strong><br>";
    echo "• For live server deployment<br>";
    echo "• Requires real API credentials<br>";
    echo "• Real SMS sending<br>";
    echo "• Real money transactions<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
