<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get current user status
    $stmt = $pdo->prepare("SELECT id, full_name, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Toggle status
    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
    
    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'User status updated to ' . $newStatus,
        'new_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    error_log('Toggle user status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating user status']);
}
?>
