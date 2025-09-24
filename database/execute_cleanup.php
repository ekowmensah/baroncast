<?php
require_once __DIR__ . '/../config/database.php';

echo "Executing database cleanup...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clean all data
    $tables = [
        'votes' => 'DELETE FROM votes',
        'transactions' => 'DELETE FROM transactions', 
        'nominees' => 'DELETE FROM nominees',
        'categories' => 'DELETE FROM categories',
        'schemes' => 'DELETE FROM schemes',
        'events' => 'DELETE FROM events',
        'organizer_users' => "DELETE FROM users WHERE role = 'organizer'"
    ];
    
    $deletedCounts = [];
    foreach ($tables as $name => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $deletedCounts[$name] = $stmt->rowCount();
        echo "Deleted {$deletedCounts[$name]} records from {$name}\n";
    }
    
    // Reset AUTO_INCREMENT
    $resetTables = ['votes', 'transactions', 'nominees', 'categories', 'schemes', 'events'];
    foreach ($resetTables as $table) {
        $pdo->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }
    echo "Reset AUTO_INCREMENT values\n";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n✅ Cleanup completed successfully!\n";
    echo "Database is now clean and ready for fresh data.\n";
    
    // Show remaining admin users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetchColumn();
    echo "\nRemaining admin users: {$adminCount}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
