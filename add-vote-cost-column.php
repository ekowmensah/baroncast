<?php
/**
 * Add vote_cost column to events table for localhost testing
 * This makes localhost schema compatible with the configurable voting fee system
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Adding vote_cost Column to Events Table</h2>";
    echo "<style>body{font-family: Arial; margin: 20px;} .success{color: green;} .error{color: red;} .info{color: blue;}</style>";

    // Check if vote_cost column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'vote_cost'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='info'>✓ vote_cost column already exists in events table</div>";
    } else {
        echo "<div class='info'>Adding vote_cost column to events table...</div>";
        
        // Add the vote_cost column
        $sql = "ALTER TABLE events ADD COLUMN vote_cost DECIMAL(10,2) DEFAULT NULL AFTER organizer_id";
        $pdo->exec($sql);
        
        echo "<div class='success'>✓ vote_cost column added successfully</div>";
    }

    // Show current events with vote costs
    echo "<h3>Current Events with Vote Cost Information:</h3>";
    $stmt = $pdo->query("SELECT id, title, vote_cost, voting_fee, status FROM events LIMIT 10");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>vote_cost</th><th>voting_fee</th><th>Status</th><th>Calculated Cost</th></tr>";
        
        // Include the getVoteCost function for testing
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
        
        foreach ($events as $event) {
            $calculated_cost = getVoteCost($event, $pdo);
            echo "<tr>";
            echo "<td>" . htmlspecialchars($event['id']) . "</td>";
            echo "<td>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td>" . ($event['vote_cost'] ?? 'NULL') . "</td>";
            echo "<td>" . ($event['voting_fee'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($event['status']) . "</td>";
            echo "<td><strong>GH₵ " . number_format($calculated_cost, 2) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<div class='success'>✓ Database schema updated successfully!</div>";
        echo "<div>Now you can test the vote cost function with real events.</div>";
        echo "<div>The 'Calculated Cost' column shows what the system would use for voting fees.</div>";
        echo "</div>";
        
    } else {
        echo "<div class='info'>No events found in the database.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>