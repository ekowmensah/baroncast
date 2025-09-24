<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== FIXING DUPLICATE TRANSACTION_ID ISSUE ===\n";
    
    // Check current table structure
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'transaction_id'");
    $hasTransactionId = $stmt->fetch();
    
    if ($hasTransactionId) {
        echo "✓ transaction_id column exists\n";
        
        // Check for empty transaction_id entries
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_id = '' OR transaction_id IS NULL");
        $emptyCount = $stmt->fetch()['count'];
        echo "Empty transaction_id entries: $emptyCount\n";
        
        if ($emptyCount > 0) {
            // Update empty transaction_id entries with unique values
            $stmt = $pdo->query("SELECT id FROM transactions WHERE transaction_id = '' OR transaction_id IS NULL");
            $emptyRecords = $stmt->fetchAll();
            
            foreach ($emptyRecords as $record) {
                $newTransactionId = 'FIX_' . time() . '_' . $record['id'];
                $updateStmt = $pdo->prepare("UPDATE transactions SET transaction_id = ? WHERE id = ?");
                $updateStmt->execute([$newTransactionId, $record['id']]);
                echo "Updated record ID {$record['id']} with transaction_id: $newTransactionId\n";
            }
        }
        
        // Check if transaction_id has unique constraint
        $stmt = $pdo->query("SHOW INDEX FROM transactions WHERE Column_name = 'transaction_id'");
        $hasUniqueConstraint = false;
        while ($index = $stmt->fetch()) {
            if ($index['Non_unique'] == 0) {
                $hasUniqueConstraint = true;
                break;
            }
        }
        
        if (!$hasUniqueConstraint) {
            echo "Adding UNIQUE constraint to transaction_id column...\n";
            $pdo->exec("ALTER TABLE transactions ADD UNIQUE KEY unique_transaction_id (transaction_id)");
            echo "✓ UNIQUE constraint added\n";
        } else {
            echo "✓ UNIQUE constraint already exists\n";
        }
    } else {
        echo "❌ transaction_id column does not exist\n";
        echo "Adding transaction_id column...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN transaction_id VARCHAR(100) UNIQUE AFTER id");
        echo "✓ transaction_id column added\n";
    }
    
    echo "\n=== CURRENT TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE transactions");
    while ($column = $stmt->fetch()) {
        echo sprintf("%-20s %-15s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key']
        );
    }
    
    echo "\n✅ Transaction table fixed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
