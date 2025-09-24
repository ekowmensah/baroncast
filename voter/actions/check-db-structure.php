<?php
/**
 * Database Structure Checker
 * Shows actual table structures to fix column issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Use proper database configuration
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo '<h2>Database Structure Analysis</h2>';
    
    // Check all tables
    $tables = ['events', 'nominees', 'categories', 'votes', 'transactions'];
    
    foreach ($tables as $table) {
        echo "<h3>Table: $table</h3>";
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
            foreach ($columns as $column) {
                echo '<tr>';
                echo '<td>' . $column['Field'] . '</td>';
                echo '<td>' . $column['Type'] . '</td>';
                echo '<td>' . $column['Null'] . '</td>';
                echo '<td>' . $column['Key'] . '</td>';
                echo '<td>' . ($column['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } catch (Exception $e) {
            echo "<p>Error checking table $table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check for price-related columns in events
    echo '<h3>Price-related columns search:</h3>';
    $stmt = $pdo->query("DESCRIBE events");
    $columns = $stmt->fetchAll();
    $price_columns = [];
    foreach ($columns as $column) {
        if (stripos($column['Field'], 'price') !== false || 
            stripos($column['Field'], 'cost') !== false || 
            stripos($column['Field'], 'amount') !== false) {
            $price_columns[] = $column['Field'];
        }
    }
    
    if (empty($price_columns)) {
        echo '<p><strong>No price/cost columns found in events table!</strong></p>';
        echo '<p>Available columns: ';
        foreach ($columns as $column) {
            echo $column['Field'] . ', ';
        }
        echo '</p>';
    } else {
        echo '<p>Price-related columns: ' . implode(', ', $price_columns) . '</p>';
    }
    
} catch (Exception $e) {
    echo '<p>Database error: ' . $e->getMessage() . '</p>';
}
?>
