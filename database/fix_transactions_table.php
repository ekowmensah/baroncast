<?php
/**
 * Database migration to fix transactions table structure
 * This fixes the "Unknown column 'transaction_id'" error in vote submission
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Fixing transactions table structure...</h2>\n";
    
    // Check if transactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'transactions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p>Creating transactions table...</p>\n";
        $pdo->exec("
            CREATE TABLE transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id VARCHAR(100) UNIQUE NOT NULL,
                nominee_id INT NOT NULL,
                voter_phone VARCHAR(20) NOT NULL,
                vote_count INT NOT NULL DEFAULT 1,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_nominee_id (nominee_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p>✅ Transactions table created successfully</p>\n";
    } else {
        echo "<p>Transactions table exists. Checking structure...</p>\n";
        
        // Check current structure
        $stmt = $pdo->query("DESCRIBE transactions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'Field');
        
        echo "<h3>Current columns in transactions table:</h3>\n";
        echo "<ul>\n";
        foreach ($existingColumns as $column) {
            echo "<li>" . htmlspecialchars($column) . "</li>\n";
        }
        echo "</ul>\n";
        
        // Add missing columns
        $requiredColumns = [
            'transaction_id' => "VARCHAR(100) UNIQUE NOT NULL",
            'nominee_id' => "INT NOT NULL",
            'voter_phone' => "VARCHAR(20) NOT NULL",
            'vote_count' => "INT NOT NULL DEFAULT 1",
            'amount' => "DECIMAL(10,2) NOT NULL",
            'payment_method' => "VARCHAR(50) NOT NULL",
            'status' => "ENUM('pending', 'completed', 'failed') DEFAULT 'pending'",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($requiredColumns as $columnName => $columnDefinition) {
            if (!in_array($columnName, $existingColumns)) {
                echo "<p>Adding {$columnName} column...</p>\n";
                try {
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN {$columnName} {$columnDefinition}");
                    echo "<p>✅ {$columnName} column added successfully</p>\n";
                } catch (Exception $e) {
                    echo "<p>❌ Error adding {$columnName}: " . $e->getMessage() . "</p>\n";
                }
            }
        }
        
        // Add foreign key constraint if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_nominee FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE");
            echo "<p>✅ Foreign key constraint added</p>\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate foreign key constraint name') !== false) {
                echo "<p>✅ Foreign key constraint already exists</p>\n";
            } else {
                echo "<p>⚠ Warning: Could not add foreign key: " . $e->getMessage() . "</p>\n";
            }
        }
        
        // Add indexes
        $indexes = [
            'idx_transaction_id' => 'transaction_id',
            'idx_nominee_id' => 'nominee_id',
            'idx_status' => 'status',
            'idx_created_at' => 'created_at'
        ];
        
        foreach ($indexes as $indexName => $columnName) {
            try {
                $pdo->exec("ALTER TABLE transactions ADD INDEX {$indexName} ({$columnName})");
                echo "<p>✅ Index {$indexName} added</p>\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "<p>✅ Index {$indexName} already exists</p>\n";
                } else {
                    echo "<p>⚠ Warning: Could not add index {$indexName}: " . $e->getMessage() . "</p>\n";
                }
            }
        }
    }
    
    // Verify the final table structure
    echo "<h3>Final transactions table structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE transactions");
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
    
    echo "<h3>✅ Transactions table migration completed successfully!</h3>\n";
    echo "<p>The voting functionality should now work properly.</p>\n";
    
    // Test the table by showing current record count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Current transactions count:</strong> " . $result['count'] . "</p>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during migration:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
