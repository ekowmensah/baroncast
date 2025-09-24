<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting database migration for standalone categories...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n";
    
    // Step 1: Drop the existing foreign key constraint
    echo "Dropping existing foreign key constraint...\n";
    $pdo->exec("ALTER TABLE categories DROP FOREIGN KEY categories_ibfk_1");
    
    // Step 2: Modify event_id column to allow NULL values
    echo "Modifying event_id column to allow NULL values...\n";
    $pdo->exec("ALTER TABLE categories MODIFY COLUMN event_id INT NULL");
    
    // Step 3: Add the foreign key constraint back with NULL support
    echo "Adding foreign key constraint back with NULL support...\n";
    $pdo->exec("ALTER TABLE categories ADD CONSTRAINT categories_ibfk_1 
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    
    // Step 4: Add indexes for better performance
    echo "Adding performance indexes...\n";
    try {
        $pdo->exec("CREATE INDEX idx_categories_event_id ON categories(event_id)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "Index idx_categories_event_id already exists, skipping...\n";
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Categories table now supports standalone categories (event_id can be NULL).\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
