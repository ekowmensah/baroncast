<?php
/**
 * Apply votes table fixes - Add missing columns
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Applying votes table fixes...\n";
    
    // Check if status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        echo "Adding status column to votes table...\n";
        $pdo->exec("ALTER TABLE votes ADD COLUMN status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending' AFTER payment_status");
        echo "✓ Status column added\n";
    } else {
        echo "✓ Status column already exists\n";
    }
    
    // Check if payment_response column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'payment_response'");
    if ($stmt->rowCount() == 0) {
        echo "Adding payment_response column to votes table...\n";
        $pdo->exec("ALTER TABLE votes ADD COLUMN payment_response TEXT AFTER status");
        echo "✓ Payment_response column added\n";
    } else {
        echo "✓ Payment_response column already exists\n";
    }
    
    // Check if updated_at column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'updated_at'");
    if ($stmt->rowCount() == 0) {
        echo "Adding updated_at column to votes table...\n";
        $pdo->exec("ALTER TABLE votes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER voted_at");
        echo "✓ Updated_at column added\n";
    } else {
        echo "✓ Updated_at column already exists\n";
    }
    
    echo "\nVotes table structure updated successfully!\n";
    
    // Show current table structure
    echo "\nCurrent votes table structure:\n";
    $stmt = $pdo->query("DESCRIBE votes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']}: {$row['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
