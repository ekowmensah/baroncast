<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $nomineeId = $_POST['nominee_id'] ?? null;
    
    if (!$nomineeId) {
        throw new Exception('Nominee ID is required');
    }
    
    // First, get nominee details for logging
    $stmt = $pdo->prepare("SELECT * FROM nominees WHERE id = ?");
    $stmt->execute([$nomineeId]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        throw new Exception('Nominee not found');
    }
    
    // Check if nominee has votes - prevent deletion if they do
    $voteStmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE nominee_id = ?");
    $voteStmt->execute([$nomineeId]);
    $voteCount = $voteStmt->fetch(PDO::FETCH_ASSOC)['vote_count'];
    
    if ($voteCount > 0) {
        throw new Exception('Cannot delete nominee with existing votes. Please archive instead.');
    }
    
    // Delete nominee image if exists
    if (!empty($nominee['image']) && file_exists(__DIR__ . '/../../uploads/nominees/' . $nominee['image'])) {
        unlink(__DIR__ . '/../../uploads/nominees/' . $nominee['image']);
    }
    
    // Delete the nominee
    $deleteStmt = $pdo->prepare("DELETE FROM nominees WHERE id = ?");
    $result = $deleteStmt->execute([$nomineeId]);
    
    if ($result) {
        // Log the action
        $user = $auth->getCurrentUser();
        $logStmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, changes, created_at) 
            VALUES (?, 'delete', 'nominees', ?, ?, NOW())
        ");
        $changes = json_encode([
            'deleted_nominee' => [
                'id' => $nominee['id'],
                'name' => $nominee['name'],
                'category_id' => $nominee['category_id'],
                'event_id' => $nominee['event_id']
            ]
        ]);
        $logStmt->execute([$user['id'], $nomineeId, $changes]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Nominee deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete nominee');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
