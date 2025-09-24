<?php
require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== Adding Test Data ===\n";
    
    // Check if we have users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount == 0) {
        echo "Adding test admin user...\n";
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, full_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@test.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'Test Admin']);
        echo "✓ Test admin user added\n";
    } else {
        echo "✓ Users exist ($userCount users)\n";
    }
    
    // Check if we have events
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $eventCount = $stmt->fetch()['count'];
    
    if ($eventCount == 0) {
        echo "Adding test event...\n";
        $stmt = $pdo->prepare("INSERT INTO events (organizer_id, title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 'Test Event', 'Test voting event', date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+1 month')), 'active']);
        $eventId = $pdo->lastInsertId();
        echo "✓ Test event added (ID: $eventId)\n";
    } else {
        echo "✓ Events exist ($eventCount events)\n";
        $stmt = $pdo->query("SELECT id FROM events LIMIT 1");
        $eventId = $stmt->fetch()['id'];
    }
    
    // Check if we have categories
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $categoryCount = $stmt->fetch()['count'];
    
    if ($categoryCount == 0) {
        echo "Adding test category...\n";
        $stmt = $pdo->prepare("INSERT INTO categories (event_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$eventId, 'Test Category', 'Test voting category']);
        $categoryId = $pdo->lastInsertId();
        echo "✓ Test category added (ID: $categoryId)\n";
    } else {
        echo "✓ Categories exist ($categoryCount categories)\n";
        $stmt = $pdo->query("SELECT id FROM categories LIMIT 1");
        $categoryId = $stmt->fetch()['id'];
    }
    
    // Check if we have nominees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM nominees");
    $nomineeCount = $stmt->fetch()['count'];
    
    if ($nomineeCount == 0) {
        echo "Adding test nominee...\n";
        $stmt = $pdo->prepare("INSERT INTO nominees (category_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$categoryId, 'Test Nominee', 'Test nominee for voting']);
        $nomineeId = $pdo->lastInsertId();
        echo "✓ Test nominee added (ID: $nomineeId)\n";
    } else {
        echo "✓ Nominees exist ($nomineeCount nominees)\n";
    }
    
    echo "\n=== Test Data Summary ===\n";
    echo "Users: $userCount\n";
    echo "Events: $eventCount\n";
    echo "Categories: $categoryCount\n";
    echo "Nominees: $nomineeCount\n";
    echo "\nTest data setup completed!\n";
    echo "You can now test with Event ID: $eventId and Nominee ID: 1\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
