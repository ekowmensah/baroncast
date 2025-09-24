<?php
/**
 * Database cleanup script to remove vote package system
 * This script removes the bulk_vote_packages table and cleans up related references
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Starting vote package system removal...\n";
    
    // 1. Drop the bulk_vote_packages table
    echo "Dropping bulk_vote_packages table...\n";
    $pdo->exec("DROP TABLE IF EXISTS bulk_vote_packages");
    echo "✓ bulk_vote_packages table removed\n";
    
    // 2. Remove any event_packages references if they exist
    echo "Checking for event_packages table...\n";
    $result = $pdo->query("SHOW TABLES LIKE 'event_packages'");
    if ($result->rowCount() > 0) {
        echo "Dropping event_packages table...\n";
        $pdo->exec("DROP TABLE IF EXISTS event_packages");
        echo "✓ event_packages table removed\n";
    } else {
        echo "✓ event_packages table does not exist\n";
    }
    
    // 3. Remove any package_id columns from events table if they exist
    echo "Checking for package_id column in events table...\n";
    $result = $pdo->query("SHOW COLUMNS FROM events LIKE 'package_id'");
    if ($result->rowCount() > 0) {
        echo "Removing package_id column from events table...\n";
        $pdo->exec("ALTER TABLE events DROP COLUMN package_id");
        echo "✓ package_id column removed from events table\n";
    } else {
        echo "✓ package_id column does not exist in events table\n";
    }
    
    // 4. Clean up any vote package references in votes table
    echo "Checking votes table structure...\n";
    $result = $pdo->query("SHOW COLUMNS FROM votes LIKE 'package_id'");
    if ($result->rowCount() > 0) {
        echo "Removing package_id column from votes table...\n";
        $pdo->exec("ALTER TABLE votes DROP COLUMN package_id");
        echo "✓ package_id column removed from votes table\n";
    } else {
        echo "✓ package_id column does not exist in votes table\n";
    }
    
    // 5. Update any remaining vote records to use single vote system
    echo "Updating vote records to single vote system...\n";
    $pdo->exec("UPDATE votes SET vote_count = 1 WHERE vote_count IS NULL OR vote_count = 0");
    echo "✓ Vote records updated to single vote system\n";
    
    echo "\n=== Vote Package System Removal Complete ===\n";
    echo "✓ All vote package tables and references removed\n";
    echo "✓ System now uses single vote per transaction\n";
    echo "✓ Database cleanup successful\n";
    
} catch (Exception $e) {
    echo "Error during vote package removal: " . $e->getMessage() . "\n";
    exit(1);
}
?>
