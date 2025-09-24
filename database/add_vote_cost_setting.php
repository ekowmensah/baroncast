<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if default_vote_cost setting exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'default_vote_cost'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if (!$exists) {
        // Add default vote cost setting
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->execute([
            'default_vote_cost',
            '1.00',
            'Default cost per vote in GHS'
        ]);
        echo "✅ Added default_vote_cost setting (1.00 GHS)\n";
    } else {
        echo "ℹ️ default_vote_cost setting already exists\n";
    }
    
    // Display current vote cost
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_vote_cost'");
    $stmt->execute();
    $currentCost = $stmt->fetchColumn();
    echo "Current vote cost: GHS " . $currentCost . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
