<?php
/**
 * Database Migration Runner
 * Run this file to create the system_settings table
 */

require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Starting database migration...\n";
    
    // Read the migration file
    $migrationFile = __DIR__ . '/migrations/create_system_settings_table.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (trim($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }
    
    echo "Migration completed successfully!\n";
    echo "System settings table created with default values.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
