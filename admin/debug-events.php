<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if events table exists and get all events
    $stmt = $pdo->query("SELECT id, title, status, organizer_id, created_at FROM events ORDER BY id DESC LIMIT 10");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Events in Database:</h2>";
    if (empty($events)) {
        echo "<p>No events found in the database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Organizer ID</th><th>Created At</th><th>Edit Link</th></tr>";
        foreach ($events as $event) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($event['id']) . "</td>";
            echo "<td>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td>" . htmlspecialchars($event['status']) . "</td>";
            echo "<td>" . htmlspecialchars($event['organizer_id']) . "</td>";
            echo "<td>" . htmlspecialchars($event['created_at']) . "</td>";
            echo "<td><a href='edit-event.php?id=" . $event['id'] . "'>Edit</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check categories
    $catStmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $catCount = $catStmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Categories: " . $catCount['count'] . " total</h3>";
    
    // Check packages
    $pkgStmt = $pdo->query("SELECT COUNT(*) as count FROM bulk_vote_packages");
    $pkgCount = $pkgStmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Vote Packages: " . $pkgCount['count'] . " total</h3>";
    
    // Check if many-to-many tables exist
    try {
        $pdo->query("SELECT 1 FROM event_categories LIMIT 1");
        echo "<p>✅ event_categories table exists</p>";
    } catch (PDOException $e) {
        echo "<p>❌ event_categories table does not exist</p>";
    }
    
    try {
        $pdo->query("SELECT 1 FROM event_packages LIMIT 1");
        echo "<p>✅ event_packages table exists</p>";
    } catch (PDOException $e) {
        echo "<p>❌ event_packages table does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Database Error:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
