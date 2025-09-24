<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    echo 'Invalid transaction ID';
    exit;
}

$transactionId = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT t.*, e.title as event_title, u.full_name as organizer_name
        FROM transactions t 
        LEFT JOIN events e ON t.event_id = e.id 
        LEFT JOIN users u ON t.organizer_id = u.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        header('HTTP/1.0 404 Not Found');
        echo 'Transaction not found';
        exit;
    }
    
    // Generate receipt content
    $receiptContent = "
    ========================================
                   RECEIPT
    ========================================
    
    Transaction ID: {$transaction['id']}
    Reference: {$transaction['reference']}
    Date: " . date('Y-m-d H:i:s', strtotime($transaction['created_at'])) . "
    
    Event: {$transaction['event_title']}
    Organizer: {$transaction['organizer_name']}
    
    Amount: $" . number_format($transaction['amount'], 2) . "
    Type: " . ucfirst(str_replace('_', ' ', $transaction['type'])) . "
    Status: " . ucfirst($transaction['status']) . "
    
    Phone: {$transaction['phone']}
    
    ========================================
    Thank you for using E-Cast Voting System
    ========================================
    ";
    
    // Set headers for file download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="receipt_' . $transaction['id'] . '.txt"');
    header('Content-Length: ' . strlen($receiptContent));
    
    echo $receiptContent;
    
} catch (Exception $e) {
    error_log("Error generating receipt: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error generating receipt';
}
?>
