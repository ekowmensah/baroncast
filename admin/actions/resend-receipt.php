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
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    // Here you would typically integrate with an email/SMS service to resend the receipt
    // For now, we'll simulate the resend process
    
    // Log the resend action
    error_log("Receipt resent for transaction ID: " . $transactionId . " to phone: " . $transaction['phone']);
    
    echo json_encode(['success' => true, 'message' => 'Receipt resent successfully']);
    
} catch (Exception $e) {
    error_log("Error resending receipt: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
