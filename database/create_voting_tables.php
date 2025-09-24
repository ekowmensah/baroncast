<?php
/**
 * Database migration to create/update voting and transaction tables
 * Ensures all necessary tables exist for the voting functionality
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>Creating/Updating Voting System Tables...</h2>\n";
    
    // Create transactions table
    echo "<h3>Creating transactions table...</h3>\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(100) UNIQUE NOT NULL,
            nominee_id INT(11) NOT NULL,
            voter_phone VARCHAR(20) NOT NULL,
            vote_count INT(11) NOT NULL DEFAULT 1,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('mobile_money', 'card', 'ussd') NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            payment_reference VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nominee_id (nominee_id),
            INDEX idx_voter_phone (voter_phone),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Transactions table created/updated</p>\n";
    
    // Create/update votes table
    echo "<h3>Creating/updating votes table...</h3>\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS votes (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            nominee_id INT(11) NOT NULL,
            category_id INT(11) NOT NULL,
            event_id INT(11) NULL,
            voter_phone VARCHAR(20) NOT NULL,
            transaction_id VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_nominee_id (nominee_id),
            INDEX idx_category_id (category_id),
            INDEX idx_event_id (event_id),
            INDEX idx_voter_phone (voter_phone),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB
    ");
    echo "<p>✅ Votes table created/updated</p>\n";
    
    // Add foreign key constraints if they don't exist
    echo "<h3>Adding foreign key constraints...</h3>\n";
    
    try {
        $pdo->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_nominee FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE");
        echo "<p>✅ Transactions-Nominees foreign key added</p>\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✅ Transactions-Nominees foreign key already exists</p>\n";
        } else {
            echo "<p>⚠ Warning: Could not add transactions-nominees foreign key: " . $e->getMessage() . "</p>\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE votes ADD CONSTRAINT fk_votes_nominee FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE");
        echo "<p>✅ Votes-Nominees foreign key added</p>\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✅ Votes-Nominees foreign key already exists</p>\n";
        } else {
            echo "<p>⚠ Warning: Could not add votes-nominees foreign key: " . $e->getMessage() . "</p>\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE votes ADD CONSTRAINT fk_votes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE");
        echo "<p>✅ Votes-Categories foreign key added</p>\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✅ Votes-Categories foreign key already exists</p>\n";
        } else {
            echo "<p>⚠ Warning: Could not add votes-categories foreign key: " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Check table structures
    echo "<h3>Verifying table structures...</h3>\n";
    
    echo "<h4>Transactions table structure:</h4>\n";
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
    
    echo "<h4>Votes table structure:</h4>\n";
    $stmt = $pdo->query("DESCRIBE votes");
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
    
    echo "<h3>✅ Voting system database setup completed successfully!</h3>\n";
    echo "<p>The voting functionality is now ready to use with proper transaction tracking and vote recording.</p>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during database setup:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}
?>
