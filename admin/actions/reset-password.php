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
    
    // Get user details
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Generate temporary password
    $tempPassword = bin2hex(random_bytes(8));
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    
    // Here you would typically send an email with the temporary password
    // For now, we'll just return success
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password reset successfully. Temporary password: ' . $tempPassword,
        'temp_password' => $tempPassword
    ]);
    
} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while resetting the password']);
}
?>
