<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking database structure...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n\n";
    
    // Check events table structure
    echo "=== EVENTS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE events");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n=== SCHEMES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'schemes'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("DESCRIBE schemes");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "Schemes table does not exist.\n";
    }
    
    echo "\n=== SAMPLE DATA CHECK ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $eventCount = $stmt->fetchColumn();
    echo "Events in database: " . $eventCount . "\n";
    
    if ($eventCount > 0) {
        $stmt = $pdo->query("SELECT id, title, organizer_id FROM events LIMIT 3");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample events:\n";
        foreach ($events as $event) {
            echo "- ID: {$event['id']}, Title: {$event['title']}, Organizer ID: {$event['organizer_id']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
