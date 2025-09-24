<?php
/**
 * Local Database Schema Fix Runner for XAMPP Testing
 */

try {
    // Direct local database connection for testing
    $host = 'localhost';
    $dbname = 'e_cast_voting';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to local database successfully!\n";
    echo "Starting database schema fixes...\n\n";
    
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
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Some statements might fail if columns already exist - that's OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠ Skipped (already exists): " . substr($statement, 0, 60) . "...\n";
            } else {
                echo "✗ Failed: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\nDatabase schema fixes completed!\n\n";
    
    // Verify critical tables and columns exist
    echo "Verifying schema...\n";
    
    $checks = [
        "SHOW COLUMNS FROM transactions LIKE 'otp_code'" => "OTP Code column",
        "SHOW COLUMNS FROM transactions LIKE 'transaction_id'" => "Transaction ID column", 
        "SHOW COLUMNS FROM events LIKE 'vote_cost'" => "Vote Cost column"
    ];
    
    foreach ($checks as $query => $description) {
        try {
            $result = $pdo->query($query)->fetch();
            if ($result) {
                echo "✓ $description exists\n";
            } else {
                echo "✗ $description missing\n";
            }
        } catch (PDOException $e) {
            echo "✗ Error checking $description: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nSchema verification completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
