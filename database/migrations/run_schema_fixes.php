<?php
/**
 * Database Schema Fix Runner
 * Ensures all required columns and constraints exist for live server deployment
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Starting database schema fixes...\n";
    
    // Read and execute the SQL fix file
    $sqlFile = __DIR__ . '/fix_database_schema.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL fix file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL statements and execute them one by one
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            // Some statements might fail if columns already exist - that's OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "✗ Failed: " . $e->getMessage() . "\n";
                echo "Statement: " . $statement . "\n";
            }
        }
    }
    
    echo "\nDatabase schema fixes completed!\n";
    
    // Verify critical tables and columns exist
    echo "\nVerifying schema...\n";
    
    $checks = [
        "SHOW COLUMNS FROM transactions LIKE 'otp_code'",
        "SHOW COLUMNS FROM transactions LIKE 'transaction_id'", 
        "SHOW COLUMNS FROM events LIKE 'vote_cost'",
        "SELECT COUNT(*) as count FROM information_schema.table_constraints WHERE constraint_name = 'fk_transactions_nominee'"
    ];
    
    foreach ($checks as $check) {
        $result = $pdo->query($check)->fetch();
        if ($result) {
            echo "✓ " . $check . "\n";
        } else {
            echo "✗ " . $check . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
