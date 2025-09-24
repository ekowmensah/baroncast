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
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user exists and get their role
    $stmt = $pdo->prepare("SELECT id, role, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Prevent deletion of the last admin
    if ($user['role'] === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetch(PDO::FETCH_ASSOC)['admin_count'];
        
        if ($adminCount <= 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last administrator']);
            exit;
        }
    }
    
    // If user is an organizer, handle their events
    if ($user['role'] === 'organizer') {
        // Delete related data in correct order to avoid foreign key constraints
        
        // Delete votes for events organized by this user
        $stmt = $pdo->prepare("
            DELETE v FROM votes v 
            JOIN events e ON v.event_id = e.id 
            WHERE e.organizer_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Delete nominees for categories in events organized by this user
        $stmt = $pdo->prepare("
            DELETE n FROM nominees n 
            JOIN categories c ON n.category_id = c.id 
            JOIN events e ON c.event_id = e.id 
            WHERE e.organizer_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Delete categories for events organized by this user
        $stmt = $pdo->prepare("
            DELETE c FROM categories c 
            JOIN events e ON c.event_id = e.id 
            WHERE e.organizer_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Delete transactions for events organized by this user
        $stmt = $pdo->prepare("
            DELETE t FROM transactions t 
            JOIN events e ON t.event_id = e.id 
            WHERE e.organizer_id = ?
        ");
        $stmt->execute([$userId]);
        
        // Delete events organized by this user
        $stmt = $pdo->prepare("DELETE FROM events WHERE organizer_id = ?");
        $stmt->execute([$userId]);
    }
    
    // Delete any votes cast by this user
    $stmt = $pdo->prepare("DELETE FROM votes WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Delete any transactions by this user
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'User "' . $user['full_name'] . '" has been successfully deleted'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Delete user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the user']);
}
?>
