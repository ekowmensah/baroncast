<?php
/**
 * Database migration to add scheme_id column to nominees table
 * This fixes the "Unknown column 'scheme_id'" error when adding nominees
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Adding scheme_id column to nominees table...</h2>\n";
    
    // Check if scheme_id column already exists
    $stmt = $pdo->query("DESCRIBE nominees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    if (in_array('scheme_id', $existingColumns)) {
        echo "<p>✅ scheme_id column already exists in nominees table</p>\n";
    } else {
        echo "<p>Adding scheme_id column...</p>\n";
        
        // Add scheme_id column
        $pdo->exec("ALTER TABLE nominees ADD COLUMN scheme_id INT(11) NULL AFTER category_id");
        echo "<p>✅ scheme_id column added successfully</p>\n";
        
        // Add foreign key constraint
        try {
            $pdo->exec("ALTER TABLE nominees ADD CONSTRAINT nominees_scheme_fk FOREIGN KEY (scheme_id) REFERENCES schemes(id) ON DELETE SET NULL");
            echo "<p>✅ Foreign key constraint added</p>\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p>✅ Foreign key constraint already exists</p>\n";
            } else {
                echo "<p>⚠ Warning: Could not add foreign key constraint: " . $e->getMessage() . "</p>\n";
            }
        }
    }
    
    // Verify the table structure
    echo "<h3>Current nominees table structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE nominees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>✅ Migration completed successfully!</h3>\n";
    echo "<p>The nominees table now has the scheme_id column and adding nominees should work properly.</p>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during migration:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
