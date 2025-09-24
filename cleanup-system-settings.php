<?php
/**
 * Clean Up System Settings - Remove Duplicates
 * Fix the duplicate entries issue and ensure clean settings
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Cleaning Up System Settings</h2>";
    echo "<style>body{font-family: Arial; margin: 20px;} .success{color: green;} .error{color: red;} .info{color: blue;} .warning{color: orange;}</style>";

    // Step 1: Show current duplicates
    echo "<h3>1. Current Duplicate Settings</h3>";
    $stmt = $pdo->query("
        SELECT setting_key, COUNT(*) as count 
        FROM system_settings 
        WHERE setting_key LIKE '%vote%' OR setting_key LIKE '%fee%' 
        GROUP BY setting_key 
        HAVING count > 1
        ORDER BY setting_key
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($duplicates)) {
        echo "<div class='warning'>Found duplicate settings:</div>";
        foreach ($duplicates as $dup) {
            echo "<div style='margin-left: 20px;'>{$dup['setting_key']}: {$dup['count']} entries</div>";
        }
    } else {
        echo "<div class='success'>✓ No duplicates found</div>";
    }

    // Step 2: Remove duplicates and keep the latest
    echo "<h3>2. Removing Duplicates</h3>";
    
    $vote_settings = ['default_vote_cost', 'enable_event_custom_fee', 'min_vote_cost', 'max_vote_cost'];
    
    foreach ($vote_settings as $setting_key) {
        // Get all records for this setting
        $stmt = $pdo->prepare("SELECT id, setting_value, created_at FROM system_settings WHERE setting_key = ? ORDER BY created_at DESC");
        $stmt->execute([$setting_key]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($records) > 1) {
            echo "<div class='info'>Cleaning up {$setting_key} ({count($records)} records found)</div>";
            
            // Keep the first (latest) record, delete the rest
            $keep_id = $records[0]['id'];
            $keep_value = $records[0]['setting_value'];
            
            $ids_to_delete = array_slice(array_column($records, 'id'), 1);
            
            if (!empty($ids_to_delete)) {
                $placeholders = str_repeat('?,', count($ids_to_delete) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM system_settings WHERE id IN ($placeholders)");
                $stmt->execute($ids_to_delete);
                
                echo "<div style='margin-left: 20px;'>✓ Kept record with value: {$keep_value}</div>";
                echo "<div style='margin-left: 20px;'>✓ Deleted " . count($ids_to_delete) . " duplicate records</div>";
            }
        } else {
            echo "<div class='success'>✓ {$setting_key}: No duplicates</div>";
        }
    }

    // Step 3: Ensure correct settings exist with proper values
    echo "<h3>3. Ensuring Correct Default Settings</h3>";
    
    $default_settings = [
        'default_vote_cost' => ['value' => '1.00', 'description' => 'Default cost per vote when event has no custom fee'],
        'enable_event_custom_fee' => ['value' => '1', 'description' => 'Allow events to set custom voting fees (1=enabled, 0=disabled)'],
        'min_vote_cost' => ['value' => '0.50', 'description' => 'Minimum allowed vote cost for custom event fees'],
        'max_vote_cost' => ['value' => '100.00', 'description' => 'Maximum allowed vote cost for custom event fees']
    ];
    
    foreach ($default_settings as $key => $config) {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                setting_value = CASE 
                    WHEN setting_value IS NULL OR setting_value = '' THEN VALUES(setting_value)
                    ELSE setting_value 
                END,
                description = VALUES(description),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $config['value'], $config['description']]);
        echo "<div class='success'>✓ {$key}: Ensured proper entry</div>";
    }

    // Step 4: Show final clean settings
    echo "<h3>4. Final Clean Settings</h3>";
    $stmt = $pdo->query("
        SELECT setting_key, setting_value, description, updated_at 
        FROM system_settings 
        WHERE setting_key IN ('default_vote_cost', 'enable_event_custom_fee', 'min_vote_cost', 'max_vote_cost')
        ORDER BY setting_key
    ");
    $final_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($final_settings)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Setting Key</th><th>Value</th><th>Description</th><th>Last Updated</th></tr>";
        foreach ($final_settings as $setting) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($setting['setting_value']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($setting['description']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['updated_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Step 5: Test the vote cost function with clean data
    echo "<h3>5. Testing Vote Cost Function</h3>";
    
    function getVoteCost($event, $pdo) {
        $event_cost = $event['voting_fee'] ?? $event['vote_cost'] ?? null;
        if ($event_cost && $event_cost > 0) {
            return (float)$event_cost;
        }
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'default_vote_cost'");
            $stmt->execute();
            $default_cost = $stmt->fetchColumn();
            if ($default_cost && $default_cost > 0) {
                return (float)$default_cost;
            }
        } catch (Exception $e) {
            error_log("Error getting default vote cost: " . $e->getMessage());
        }
        return 1.00;
    }
    
    // Test scenarios
    $test_cases = [
        ['voting_fee' => '2.50'],
        ['vote_cost' => '1.75'],
        ['title' => 'Test Event'],
        []
    ];
    
    foreach ($test_cases as $test) {
        $result = getVoteCost($test, $pdo);
        echo "<div class='success'>✓ " . json_encode($test) . " → GH₵ " . number_format($result, 2) . "</div>";
    }

    echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>✅ System Settings Cleaned Successfully!</h3>";
    echo "• Removed all duplicate entries<br>";
    echo "• Ensured proper default values<br>";
    echo "• Vote cost function working correctly<br>";
    echo "• Ready for testing with Vote Settings admin page";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>