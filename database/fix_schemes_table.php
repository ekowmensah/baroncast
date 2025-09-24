<?php
/**
 * Database migration script to fix schemes table event_id foreign key constraint
 * This allows creating general schemes not tied to specific events
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Starting database migration to fix schemes table...\n";
    
    // Step 1: Drop the existing foreign key constraint
    echo "1. Dropping existing foreign key constraint...\n";
    $pdo->exec("ALTER TABLE schemes DROP FOREIGN KEY schemes_ibfk_1");
    echo "   ✓ Foreign key constraint dropped\n";
    
    // Step 2: Modify the event_id column to allow NULL values
    echo "2. Modifying event_id column to allow NULL values...\n";
    $pdo->exec("ALTER TABLE schemes MODIFY COLUMN event_id INT(11) NULL");
    echo "   ✓ Column modified to allow NULL\n";
    
    // Step 3: Re-add the foreign key constraint with NULL support
    echo "3. Re-adding foreign key constraint with NULL support...\n";
    $pdo->exec("ALTER TABLE schemes ADD CONSTRAINT schemes_ibfk_1 FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    echo "   ✓ Foreign key constraint re-added\n";
    
    // Step 4: Verify the changes
    echo "4. Verifying table structure...\n";
    $stmt = $pdo->query("DESCRIBE schemes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'event_id') {
            echo "   event_id column: " . $column['Type'] . " " . $column['Null'] . " " . $column['Key'] . "\n";
            if ($column['Null'] === 'YES') {
                echo "   ✓ event_id now allows NULL values\n";
            } else {
                echo "   ✗ event_id still does not allow NULL values\n";
            }
            break;
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "You can now create schemes with or without associating them to specific events.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>
