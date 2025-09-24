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
    
    // First check if transaction exists and is pending
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    if ($transaction['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Transaction is not pending approval']);
        exit;
    }
    
    // Update transaction status to completed
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = 'completed', updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$transactionId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Transaction approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve transaction']);
    }
    
} catch (Exception $e) {
    error_log("Error approving transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
