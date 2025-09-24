<?php
/**
 * Force clean all selector data to ensure nominee form shows only current valid data
 * This script will identify and remove any stale/orphaned data that might be causing issues
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Force Cleaning Selector Data</h2>\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Show current data before cleanup
    echo "<h3>1. Current Data Before Cleanup:</h3>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $categoryCount = $stmt->fetch()['count'];
    echo "Categories: {$categoryCount}<br>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM schemes");
    $schemeCount = $stmt->fetch()['count'];
    echo "Schemes: {$schemeCount}<br>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $eventCount = $stmt->fetch()['count'];
    echo "Events: {$eventCount}<br>\n";
    
    // 2. Show sample data that might be causing issues
    echo "<h3>2. Sample Categories Currently in Database:</h3>\n";
    $stmt = $pdo->query("SELECT id, name, event_id FROM categories ORDER BY id DESC LIMIT 10");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($categories)) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Event ID</th></tr>\n";
        foreach ($categories as $cat) {
            echo "<tr><td>{$cat['id']}</td><td>{$cat['name']}</td><td>" . ($cat['event_id'] ?? 'NULL') . "</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "No categories found<br>\n";
    }
    
    echo "<h3>3. Sample Schemes Currently in Database:</h3>\n";
    $stmt = $pdo->query("SELECT id, name, event_id, organizer_id FROM schemes ORDER BY id DESC LIMIT 10");
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($schemes)) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Event ID</th><th>Organizer ID</th></tr>\n";
        foreach ($schemes as $scheme) {
            echo "<tr><td>{$scheme['id']}</td><td>{$scheme['name']}</td><td>" . ($scheme['event_id'] ?? 'NULL') . "</td><td>" . ($scheme['organizer_id'] ?? 'NULL') . "</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "No schemes found<br>\n";
    }
    
    // 3. Check for problematic data patterns
    echo "<h3>4. Checking for Problematic Data:</h3>\n";
    
    // Categories with names that look like old sample data
    $stmt = $pdo->query("
        SELECT id, name FROM categories 
        WHERE name LIKE '%Best Student DJ%' 
        OR name LIKE '%Best Designer%' 
        OR name LIKE '%of the Year%'
        OR name LIKE '%Award%'
    ");
    $suspiciousCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($suspiciousCategories)) {
        echo "<h4>Suspicious Categories Found:</h4>\n";
        foreach ($suspiciousCategories as $cat) {
            echo "- ID: {$cat['id']}, Name: {$cat['name']}<br>\n";
        }
        
        // Ask if we should remove these
        echo "<p style='color: red;'>These categories look like old sample data. They will be removed.</p>\n";
        
        $categoryIds = array_column($suspiciousCategories, 'id');
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
        $stmt->execute($categoryIds);
        echo "Removed " . $stmt->rowCount() . " suspicious categories<br>\n";
    }
    
    // Schemes with names that look like old sample data
    $stmt = $pdo->query("
        SELECT id, name FROM schemes 
        WHERE name LIKE '%Mega Hall%' 
        OR name LIKE '%Smart Media%' 
        OR name LIKE '%Awards%'
    ");
    $suspiciousSchemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($suspiciousSchemes)) {
        echo "<h4>Suspicious Schemes Found:</h4>\n";
        foreach ($suspiciousSchemes as $scheme) {
            echo "- ID: {$scheme['id']}, Name: {$scheme['name']}<br>\n";
        }
        
        echo "<p style='color: red;'>These schemes look like old sample data. They will be removed.</p>\n";
        
        $schemeIds = array_column($suspiciousSchemes, 'id');
        $placeholders = str_repeat('?,', count($schemeIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM schemes WHERE id IN ($placeholders)");
        $stmt->execute($schemeIds);
        echo "Removed " . $stmt->rowCount() . " suspicious schemes<br>\n";
    }
    
    // 4. Remove any orphaned data
    echo "<h3>5. Removing Orphaned Data:</h3>\n";
    
    // Remove categories referencing non-existent events
    $stmt = $pdo->query("
        DELETE c FROM categories c 
        LEFT JOIN events e ON c.event_id = e.id 
        WHERE c.event_id IS NOT NULL AND e.id IS NULL
    ");
    $orphanedCategories = $stmt->rowCount();
    echo "Removed {$orphanedCategories} orphaned categories<br>\n";
    
    // Remove schemes referencing non-existent events
    $stmt = $pdo->query("
        DELETE s FROM schemes s 
        LEFT JOIN events e ON s.event_id = e.id 
        WHERE s.event_id IS NOT NULL AND e.id IS NULL
    ");
    $orphanedSchemes = $stmt->rowCount();
    echo "Removed {$orphanedSchemes} orphaned schemes<br>\n";
    
    // Remove schemes referencing non-existent organizers
    $stmt = $pdo->query("
        DELETE s FROM schemes s 
        LEFT JOIN users u ON s.organizer_id = u.id 
        WHERE s.organizer_id IS NOT NULL AND u.id IS NULL
    ");
    $orphanedOrganizerSchemes = $stmt->rowCount();
    echo "Removed {$orphanedOrganizerSchemes} schemes with invalid organizers<br>\n";
    
    // 5. Show final data after cleanup
    echo "<h3>6. Final Data After Cleanup:</h3>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $finalCategoryCount = $stmt->fetch()['count'];
    echo "Categories: {$finalCategoryCount}<br>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM schemes");
    $finalSchemeCount = $stmt->fetch()['count'];
    echo "Schemes: {$finalSchemeCount}<br>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $finalEventCount = $stmt->fetch()['count'];
    echo "Events: {$finalEventCount}<br>\n";
    
    // Show remaining data
    echo "<h4>Remaining Categories:</h4>\n";
    $stmt = $pdo->query("SELECT id, name, event_id FROM categories ORDER BY name");
    $remainingCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($remainingCategories)) {
        foreach ($remainingCategories as $cat) {
            echo "- {$cat['name']} (ID: {$cat['id']})<br>\n";
        }
    } else {
        echo "No categories remaining<br>\n";
    }
    
    echo "<h4>Remaining Schemes:</h4>\n";
    $stmt = $pdo->query("SELECT id, name, event_id, organizer_id FROM schemes ORDER BY name");
    $remainingSchemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($remainingSchemes)) {
        foreach ($remainingSchemes as $scheme) {
            echo "- {$scheme['name']} (ID: {$scheme['id']})<br>\n";
        }
    } else {
        echo "No schemes remaining<br>\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<h3 style='color: green;'>✅ Cleanup Completed Successfully!</h3>\n";
    echo "<p>The nominee form selectors should now only show current, valid data.</p>\n";
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<h3 style='color: red;'>❌ Error during cleanup:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
