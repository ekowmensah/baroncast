<?php
/**
 * Add Currency Setting to System Settings
 * This script adds a configurable currency setting to replace hardcoded GHS references
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Adding Currency Setting</h2>";
    
    // Add currency setting if it doesn't exist
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
        VALUES ('payment_currency', 'GHS', 'Payment currency code (GHS, USD, NGN, etc.)')
    ");
    $stmt->execute();
    
    echo "<p>✅ Currency setting added/verified</p>";
    
    // Add currency symbol setting
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
        VALUES ('currency_symbol', 'GHS', 'Currency symbol for display')
    ");
    $stmt->execute();
    
    echo "<p>✅ Currency symbol setting added/verified</p>";
    
    // Add currency name setting
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO system_settings (setting_key, setting_value, description) 
        VALUES ('currency_name', 'Ghana Cedis', 'Full currency name')
    ");
    $stmt->execute();
    
    echo "<p>✅ Currency name setting added/verified</p>";
    
    echo "<h3>Current Currency Settings:</h3>";
    $stmt = $pdo->prepare("
        SELECT setting_key, setting_value, description 
        FROM system_settings 
        WHERE setting_key LIKE '%currency%' OR setting_key = 'payment_currency'
        ORDER BY setting_key
    ");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Setting Key</th><th>Value</th><th>Description</th></tr>";
    foreach ($settings as $setting) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['description']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>✅ Currency settings configured successfully!</strong></p>";
    echo "<p>You can now update currency settings in the admin panel.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
