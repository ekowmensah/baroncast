<?php
/**
 * Database migration to add missing transaction_id column to votes table
 * This fixes the "Unknown column 'transaction_id'" error in vote submission
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Adding transaction_id column to votes table...</h2>\n";
    
    // Check current votes table structure
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    echo "<h3>Current columns in votes table:</h3>\n";
    echo "<ul>\n";
    foreach ($existingColumns as $column) {
        echo "<li>" . htmlspecialchars($column) . "</li>\n";
    }
    echo "</ul>\n";
    
    // Add transaction_id column if it doesn't exist
    if (!in_array('transaction_id', $existingColumns)) {
        echo "<p>Adding transaction_id column...</p>\n";
        $pdo->exec("ALTER TABLE votes ADD COLUMN transaction_id VARCHAR(100) NULL AFTER voter_phone");
        echo "<p>✅ transaction_id column added successfully</p>\n";
        
        // Add index for better performance
        try {
            $pdo->exec("ALTER TABLE votes ADD INDEX idx_transaction_id (transaction_id)");
            echo "<p>✅ Index on transaction_id added</p>\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<p>✅ Index on transaction_id already exists</p>\n";
            } else {
                echo "<p>⚠ Warning: Could not add index on transaction_id: " . $e->getMessage() . "</p>\n";
            }
        }
    } else {
        echo "<p>✅ transaction_id column already exists</p>\n";
    }
    
    // Verify the updated table structure
    echo "<h3>Updated votes table structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    foreach ($columns as $column) {
        $isNew = $column['Field'] === 'transaction_id';
        $style = $isNew ? "style='background-color: #d4edda;'" : "";
        
        echo "<tr {$style}>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>✅ Migration completed successfully!</h3>\n";
    echo "<p>The votes table now has the transaction_id column and voting should work properly.</p>\n";
    
    // Test the table by showing current record count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM votes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Current votes count:</strong> " . $result['count'] . "</p>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during migration:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
