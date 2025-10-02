<?php
/**
 * Check payment_method column definition and fix it
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "<h2>Payment Method Column Analysis</h2>";

    // Check current column definition for transactions table
    echo "<h3>Transactions Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td style='background: " . ($column['Field'] === 'payment_method' ? '#ffeb3b' : 'white') . ";'>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check votes table too
    echo "<h3>Votes Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td style='background: " . ($column['Field'] === 'payment_method' ? '#ffeb3b' : 'white') . ";'>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test the length of our values
    echo "<h3>Value Length Analysis</h3>";
    $testValues = ['cash', 'mobile_money', 'hubtel', 'hubtel_ussd', 'payproxy'];
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Value</th><th>Length</th></tr>";
    
    foreach ($testValues as $value) {
        echo "<tr>";
        echo "<td>$value</td>";
        echo "<td>" . strlen($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Recommended Fix</h3>";
    echo "<p>Run this SQL to fix the column:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px;'>";
    echo "-- For transactions table\n";
    echo "ALTER TABLE transactions MODIFY COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cash';\n\n";
    echo "-- For votes table\n";
    echo "ALTER TABLE votes MODIFY COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cash';";
    echo "</pre>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
