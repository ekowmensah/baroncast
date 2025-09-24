<?php
/**
 * Direct Migration Script for Missing Event Columns
 * Run this directly to add missing columns to events table
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Event Table Migration</h2>";
    
    $migrationsRun = [];
    
    // Check and add missing columns
    $columnsToAdd = [
        'event_type' => "VARCHAR(50) DEFAULT 'other'",
        'location' => "VARCHAR(255) NULL",
        'max_participants' => "INT NULL",
        'rules' => "TEXT NULL",
        'is_public' => "TINYINT(1) DEFAULT 1",
        'allow_multiple_votes' => "TINYINT(1) DEFAULT 0"
    ];
    
    foreach ($columnsToAdd as $columnName => $columnDefinition) {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE '$columnName'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            // Add the column
            $pdo->exec("ALTER TABLE events ADD COLUMN $columnName $columnDefinition");
            $migrationsRun[] = "$columnName column added";
            echo "<p>✅ Added column: $columnName</p>";
        } else {
            echo "<p>ℹ️ Column already exists: $columnName</p>";
        }
    }
    
    if (empty($migrationsRun)) {
        echo "<h3>✅ All columns already exist - no migration needed</h3>";
    } else {
        echo "<h3>✅ Migration completed successfully!</h3>";
        echo "<p>Added " . count($migrationsRun) . " columns:</p>";
        echo "<ul>";
        foreach ($migrationsRun as $migration) {
            echo "<li>$migration</li>";
        }
        echo "</ul>";
    }
    
    // Test the edit-event.php page
    echo "<hr>";
    echo "<h3>Test Edit Event Form</h3>";
    echo "<p><a href='edit-event.php?id=1' target='_blank'>Test Edit Event Form (ID: 1)</a></p>";
    echo "<p><a href='events.php' target='_blank'>Back to Events Management</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
