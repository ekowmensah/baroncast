<?php
/**
 * Database migration script to update schemes table for admin-centric workflow
 * Adds organizer_id, admin_percentage, and organizer_percentage columns
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Starting database migration for admin-centric scheme workflow...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE schemes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    // Step 1: Add organizer_id column if it doesn't exist
    if (!in_array('organizer_id', $existingColumns)) {
        echo "1. Adding organizer_id column...\n";
        $pdo->exec("ALTER TABLE schemes ADD COLUMN organizer_id INT(11) NULL AFTER id");
        echo "   ✓ organizer_id column added\n";
    } else {
        echo "1. organizer_id column already exists\n";
    }
    
    // Step 2: Add admin_percentage column if it doesn't exist
    if (!in_array('admin_percentage', $existingColumns)) {
        echo "2. Adding admin_percentage column...\n";
        $pdo->exec("ALTER TABLE schemes ADD COLUMN admin_percentage DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER vote_price");
        echo "   ✓ admin_percentage column added\n";
    } else {
        echo "2. admin_percentage column already exists\n";
    }
    
    // Step 3: Add organizer_percentage column if it doesn't exist
    if (!in_array('organizer_percentage', $existingColumns)) {
        echo "3. Adding organizer_percentage column...\n";
        $pdo->exec("ALTER TABLE schemes ADD COLUMN organizer_percentage DECIMAL(5,2) NOT NULL DEFAULT 90.00 AFTER admin_percentage");
        echo "   ✓ organizer_percentage column added\n";
    } else {
        echo "3. organizer_percentage column already exists\n";
    }
    
    // Step 4: Add foreign key constraint for organizer_id if it doesn't exist
    echo "4. Adding foreign key constraint for organizer_id...\n";
    try {
        $pdo->exec("ALTER TABLE schemes ADD CONSTRAINT schemes_organizer_fk FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "   ✓ Foreign key constraint added\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ✓ Foreign key constraint already exists\n";
        } else {
            echo "   ⚠ Warning: Could not add foreign key constraint: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 5: Verify the changes
    echo "5. Verifying table structure...\n";
    $stmt = $pdo->query("DESCRIBE schemes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['organizer_id', 'admin_percentage', 'organizer_percentage'];
    foreach ($requiredColumns as $column) {
        $found = false;
        foreach ($columns as $col) {
            if ($col['Field'] === $column) {
                echo "   ✓ $column: " . $col['Type'] . " " . $col['Null'] . "\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "   ✗ $column: NOT FOUND\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Schemes table is now ready for admin-centric workflow:\n";
    echo "- Admin can assign schemes to specific organizers\n";
    echo "- Admin sets commission percentage (V. Charges)\n";
    echo "- Organizer percentage is automatically calculated\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>
