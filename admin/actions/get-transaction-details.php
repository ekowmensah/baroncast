<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
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
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'transaction' => $transaction
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching transaction details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
