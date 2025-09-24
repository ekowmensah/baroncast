<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting automatic database cleanup - removing all sample/hardcoded data...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n\n";
    
    // Start transaction for safety
    $pdo->beginTransaction();
    
    echo "=== CLEANING SAMPLE DATA ===\n";
    
    // 1. Clean votes (must be first due to foreign keys)
    echo "Cleaning votes data...\n";
    $stmt = $pdo->prepare("DELETE FROM votes");
    $stmt->execute();
    $deletedVotes = $stmt->rowCount();
    echo "Deleted {$deletedVotes} vote records.\n";
    
    // 2. Clean transactions
    echo "Cleaning transactions data...\n";
    $stmt = $pdo->prepare("DELETE FROM transactions");
    $stmt->execute();
    $deletedTransactions = $stmt->rowCount();
    echo "Deleted {$deletedTransactions} transaction records.\n";
    
    // 3. Clean nominees
    echo "Cleaning nominees data...\n";
    $stmt = $pdo->prepare("DELETE FROM nominees");
    $stmt->execute();
    $deletedNominees = $stmt->rowCount();
    echo "Deleted {$deletedNominees} nominee records.\n";
    
    // 4. Clean categories
    echo "Cleaning categories data...\n";
    $stmt = $pdo->prepare("DELETE FROM categories");
    $stmt->execute();
    $deletedCategories = $stmt->rowCount();
    echo "Deleted {$deletedCategories} category records.\n";
    
    // 5. Clean schemes
    echo "Cleaning schemes data...\n";
    $stmt = $pdo->prepare("DELETE FROM schemes");
    $stmt->execute();
    $deletedSchemes = $stmt->rowCount();
    echo "Deleted {$deletedSchemes} scheme records.\n";
    
    // 6. Clean events
    echo "Cleaning events data...\n";
    $stmt = $pdo->prepare("DELETE FROM events");
    $stmt->execute();
    $deletedEvents = $stmt->rowCount();
    echo "Deleted {$deletedEvents} event records.\n";
    
    // 7. Clean organizer users (keep admin users)
    echo "Cleaning organizer users (keeping admin users)...\n";
    $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'organizer'");
    $stmt->execute();
    $deletedOrganizers = $stmt->rowCount();
    echo "Deleted {$deletedOrganizers} organizer user records.\n";
    
    // 8. Reset AUTO_INCREMENT values to start fresh
    echo "Resetting AUTO_INCREMENT values...\n";
    $tables = ['votes', 'transactions', 'nominees', 'categories', 'schemes', 'events'];
    foreach ($tables as $table) {
        $pdo->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }
    echo "AUTO_INCREMENT values reset.\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n=== CLEANUP SUMMARY ===\n";
    echo "✅ Votes: {$deletedVotes} records deleted\n";
    echo "✅ Transactions: {$deletedTransactions} records deleted\n";
    echo "✅ Nominees: {$deletedNominees} records deleted\n";
    echo "✅ Categories: {$deletedCategories} records deleted\n";
    echo "✅ Schemes: {$deletedSchemes} records deleted\n";
    echo "✅ Events: {$deletedEvents} records deleted\n";
    echo "✅ Organizer Users: {$deletedOrganizers} records deleted\n";
    echo "✅ AUTO_INCREMENT values reset\n";
    
    echo "\n🎉 Database cleanup completed successfully!\n";
    echo "The database structure is preserved and ready for fresh data.\n";
    echo "Admin users are preserved for system access.\n";
    
    // Show remaining admin users
    echo "\n=== REMAINING ADMIN USERS ===\n";
    $stmt = $pdo->query("SELECT id, username, full_name, email FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "⚠️  WARNING: No admin users found! You may need to create an admin user.\n";
    } else {
        foreach ($admins as $admin) {
            echo "- ID: {$admin['id']}, Username: {$admin['username']}, Name: {$admin['full_name']}, Email: {$admin['email']}\n";
        }
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
