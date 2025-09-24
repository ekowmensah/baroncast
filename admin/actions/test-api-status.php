<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../services/HubtelReceiveMoneyService.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo "ERROR: Unauthorized access";
    exit;
}

header('Content-Type: text/plain');

try {
    $reference = $_POST['reference'] ?? '';
    
    if (empty($reference)) {
        echo "ERROR: Transaction reference is required";
        exit;
    }
    
    $hubtel = new HubtelReceiveMoneyService();
    
    echo "=== HUBTEL STATUS CHECK TEST ===\n";
    echo "Transaction Reference: $reference\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "Checking status with Hubtel...\n\n";
    
    $result = $hubtel->checkTransactionStatus($reference);
    
    echo "=== API RESPONSE ===\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    
    if ($result['success']) {
        echo "\n\n=== STATUS EXPLANATION ===\n";
        if ($result['is_paid']) {
            echo "✓ Transaction is PAID/COMPLETED\n";
            echo "Amount: GHS " . number_format($result['amount'], 2) . "\n";
            echo "Charges: GHS " . number_format($result['charges'], 2) . "\n";
            echo "Payment Date: " . ($result['payment_date'] ?? 'N/A') . "\n";
        } else {
            $status = $result['status'] ?? 'unknown';
            if ($status === 'pending') {
                echo "⏳ Transaction is still PENDING\n";
            } elseif ($status === 'failed') {
                echo "❌ Transaction has FAILED\n";
            } else {
                echo "❓ Transaction status: " . strtoupper($status) . "\n";
            }
        }
        
        if (!empty($result['external_transaction_id'])) {
            echo "External Transaction ID: " . $result['external_transaction_id'] . "\n";
        }
    } else {
        echo "\n\n=== ERROR ANALYSIS ===\n";
        echo "❌ Status check failed\n";
        echo "Message: " . ($result['message'] ?? 'No message provided') . "\n";
        
        // Check if transaction exists in local database
        echo "\n=== LOCAL DATABASE CHECK ===\n";
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT status, created_at, amount, hubtel_transaction_id
            FROM transactions 
            WHERE reference = ? OR transaction_id = ?
        ");
        $stmt->execute([$reference, $reference]);
        $local_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($local_transaction) {
            echo "✓ Transaction found in local database\n";
            echo "Local Status: " . $local_transaction['status'] . "\n";
            echo "Created: " . $local_transaction['created_at'] . "\n";
            echo "Amount: GHS " . number_format($local_transaction['amount'], 2) . "\n";
            echo "Hubtel Transaction ID: " . ($local_transaction['hubtel_transaction_id'] ?: 'Not set') . "\n";
        } else {
            echo "❌ Transaction not found in local database\n";
        }
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>