<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check transactions table structure
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== TRANSACTIONS TABLE STRUCTURE ===\n";
    foreach ($columns as $column) {
        echo sprintf("%-20s %-15s %-10s %-10s %-15s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'], 
            $column['Extra']
        );
    }
    
    // Check for empty transaction_id entries
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_id = '' OR transaction_id IS NULL");
    $emptyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "\nEmpty transaction_id entries: " . $emptyCount . "\n";
    
    // Show recent transactions
    $stmt = $pdo->query("SELECT id, transaction_id, reference, status, created_at FROM transactions ORDER BY created_at DESC LIMIT 5");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== RECENT TRANSACTIONS ===\n";
    foreach ($recent as $tx) {
        echo sprintf("ID: %d | TxID: %s | Ref: %s | Status: %s | Created: %s\n",
            $tx['id'],
            $tx['transaction_id'] ?? 'NULL',
            $tx['reference'] ?? 'NULL',
            $tx['status'],
            $tx['created_at']
        );
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
