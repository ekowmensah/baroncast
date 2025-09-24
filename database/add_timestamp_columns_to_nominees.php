<?php
/**
 * Database migration to add timestamp columns to nominees table
 * This fixes the "Unknown column 'updated_at'" error when adding nominees
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Adding timestamp columns to nominees table...</h2>\n";
    
    // Check current table structure
    $stmt = $pdo->query("DESCRIBE nominees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    echo "<h3>Current columns in nominees table:</h3>\n";
    echo "<ul>\n";
    foreach ($existingColumns as $column) {
        echo "<li>" . htmlspecialchars($column) . "</li>\n";
    }
    echo "</ul>\n";
    
    // Define timestamp columns to add
    $timestampColumns = [
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    echo "<h3>Adding missing timestamp columns:</h3>\n";
    
    foreach ($timestampColumns as $columnName => $columnDefinition) {
        if (!in_array($columnName, $existingColumns)) {
            echo "<p>Adding {$columnName} column...</p>\n";
            $pdo->exec("ALTER TABLE nominees ADD COLUMN {$columnName} {$columnDefinition}");
            echo "<p>✅ {$columnName} column added successfully</p>\n";
        } else {
            echo "<p>✅ {$columnName} column already exists</p>\n";
        }
    }
    
    // Verify the updated table structure
    echo "<h3>Updated nominees table structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE nominees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    foreach ($columns as $column) {
        $isNew = in_array($column['Field'], array_keys($timestampColumns));
        $style = $isNew ? "style='background-color: #d4edda;'" : "";
        
        echo "<tr {$style}>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra'] ?? '') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>✅ Migration completed successfully!</h3>\n";
    echo "<p>The nominees table now has all required timestamp columns and adding nominees should work properly.</p>\n";
    
    // Test the table by showing current record count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM nominees");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Current nominees count:</strong> " . $result['count'] . "</p>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during migration:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
