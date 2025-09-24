<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['transaction_id']) || !is_numeric($input['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

$transactionId = (int)$input['transaction_id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // First check if transaction exists and can be refunded
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    if ($transaction['status'] === 'refunded') {
        echo json_encode(['success' => false, 'message' => 'Transaction already refunded']);
        exit;
    }
    
    if ($transaction['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Only completed transactions can be refunded']);
        exit;
    }
    
    // Update transaction status to refunded
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = 'refunded', updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$transactionId]);
    
    if ($result) {
        // Here you would typically integrate with the payment gateway to process the actual refund
        // For now, we'll just update the database status
        
        echo json_encode(['success' => true, 'message' => 'Transaction refunded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to refund transaction']);
    }
    
} catch (Exception $e) {
    error_log("Error refunding transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
