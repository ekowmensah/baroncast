<?php
/**
 * Apply Hubtel Schema Fixes
 * Adds missing columns and indexes for proper Hubtel integration
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Applying Hubtel schema fixes...\n";
    
    // Check if checkout_url column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'checkout_url'");
    if ($stmt->rowCount() == 0) {
        echo "Adding checkout_url column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN checkout_url VARCHAR(500) NULL AFTER payment_token");
    } else {
        echo "checkout_url column already exists.\n";
    }
    
    // Check if hubtel_transaction_id column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'hubtel_transaction_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding hubtel_transaction_id column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN hubtel_transaction_id VARCHAR(100) NULL AFTER checkout_url");
    } else {
        echo "hubtel_transaction_id column already exists.\n";
    }
    
    // Check if payment_details column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_details'");
    if ($stmt->rowCount() == 0) {
        echo "Adding payment_details column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_details TEXT NULL AFTER hubtel_transaction_id");
    } else {
        echo "payment_details column already exists.\n";
    }
    
    // Add indexes if they don't exist
    try {
        $pdo->exec("CREATE INDEX idx_transactions_hubtel_id ON transactions(hubtel_transaction_id)");
        echo "Added index for hubtel_transaction_id.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Index for hubtel_transaction_id already exists.\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_transactions_checkout_url ON transactions(checkout_url(255))");
        echo "Added index for checkout_url.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Index for checkout_url already exists.\n";
        } else {
            throw $e;
        }
    }
    
    // Update existing transactions to have proper status values
    $stmt = $pdo->prepare("UPDATE transactions SET status = 'pending' WHERE status IS NULL OR status = ''");
    $stmt->execute();
    $updatedRows = $stmt->rowCount();
    echo "Updated $updatedRows transactions with proper status values.\n";
    
    echo "\nHubtel schema fixes applied successfully!\n";
    echo "Database is now ready for full Hubtel integration.\n";
    
} catch (Exception $e) {
    echo "Error applying schema fixes: " . $e->getMessage() . "\n";
    exit(1);
}
?>
