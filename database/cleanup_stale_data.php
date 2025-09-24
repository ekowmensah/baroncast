<?php
/**
 * Cleanup script to remove stale/orphaned data that might be causing 
 * nominee form selectors to show old entries
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Cleaning up stale data...</h2>\n";
    
    // 1. Remove categories that reference non-existent events
    echo "<h3>1. Cleaning up orphaned categories...</h3>\n";
    $stmt = $pdo->prepare("
        DELETE c FROM categories c 
        LEFT JOIN events e ON c.event_id = e.id 
        WHERE c.event_id IS NOT NULL AND e.id IS NULL
    ");
    $stmt->execute();
    $deletedCategories = $stmt->rowCount();
    echo "Deleted {$deletedCategories} orphaned categories\n";
    
    // 2. Remove schemes that reference non-existent events
    echo "<h3>2. Cleaning up orphaned schemes...</h3>\n";
    $stmt = $pdo->prepare("
        DELETE s FROM schemes s 
        LEFT JOIN events e ON s.event_id = e.id 
        WHERE s.event_id IS NOT NULL AND e.id IS NULL
    ");
    $stmt->execute();
    $deletedSchemes = $stmt->rowCount();
    echo "Deleted {$deletedSchemes} orphaned schemes\n";
    
    // 3. Remove schemes that reference non-existent organizers
    echo "<h3>3. Cleaning up schemes with invalid organizers...</h3>\n";
    $stmt = $pdo->prepare("
        DELETE s FROM schemes s 
        LEFT JOIN users u ON s.organizer_id = u.id 
        WHERE s.organizer_id IS NOT NULL AND u.id IS NULL
    ");
    $stmt->execute();
    $deletedOrganizerSchemes = $stmt->rowCount();
    echo "Deleted {$deletedOrganizerSchemes} schemes with invalid organizers\n";
    
    // 4. Remove nominees that reference non-existent categories
    echo "<h3>4. Cleaning up orphaned nominees...</h3>\n";
    $stmt = $pdo->prepare("
        DELETE n FROM nominees n 
        LEFT JOIN categories c ON n.category_id = c.id 
        WHERE c.id IS NULL
    ");
    $stmt->execute();
    $deletedNominees = $stmt->rowCount();
    echo "Deleted {$deletedNominees} orphaned nominees\n";
    
    // 5. Show current data counts
    echo "<h3>5. Current data summary:</h3>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $eventCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Events: {$eventCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $categoryCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Categories: {$categoryCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM schemes");
    $schemeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Schemes: {$schemeCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM nominees");
    $nomineeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Nominees: {$nomineeCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'organizer'");
    $organizerCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Organizers: {$organizerCount}\n";
    
    // 6. Show sample data from each table
    echo "<h3>6. Sample current data:</h3>\n";
    
    echo "<h4>Events:</h4>\n";
    $stmt = $pdo->query("SELECT id, title, status FROM events LIMIT 5");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($events)) {
        echo "No events found\n";
    } else {
        foreach ($events as $event) {
            echo "- ID: {$event['id']}, Title: {$event['title']}, Status: {$event['status']}\n";
        }
    }
    
    echo "<h4>Categories:</h4>\n";
    $stmt = $pdo->query("SELECT id, name, event_id FROM categories LIMIT 5");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($categories)) {
        echo "No categories found\n";
    } else {
        foreach ($categories as $category) {
            echo "- ID: {$category['id']}, Name: {$category['name']}, Event ID: " . ($category['event_id'] ?? 'NULL') . "\n";
        }
    }
    
    echo "<h4>Schemes:</h4>\n";
    $stmt = $pdo->query("SELECT id, name, event_id, organizer_id FROM schemes LIMIT 5");
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($schemes)) {
        echo "No schemes found\n";
    } else {
        foreach ($schemes as $scheme) {
            echo "- ID: {$scheme['id']}, Name: {$scheme['name']}, Event ID: " . ($scheme['event_id'] ?? 'NULL') . ", Organizer ID: " . ($scheme['organizer_id'] ?? 'NULL') . "\n";
        }
    }
    
    echo "<h3>Cleanup completed successfully!</h3>\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>
