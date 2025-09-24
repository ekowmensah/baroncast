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
    
    $eventId = $_POST['event_id'] ?? null;
    $categoryId = $_POST['category_id'] ?? null;
    
    if (!$eventId || !$categoryId) {
        throw new Exception('Event ID and Category ID are required');
    }
    
    // Remove category from event (many-to-many relationship)
    $stmt = $pdo->prepare("DELETE FROM event_categories WHERE event_id = ? AND category_id = ?");
    $result = $stmt->execute([$eventId, $categoryId]);
    
    if ($result) {
        // Log the action
        $user = $auth->getCurrentUser();
        $logStmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, changes, created_at) 
            VALUES (?, 'remove_category', 'event_categories', ?, ?, NOW())
        ");
        $changes = json_encode([
            'event_id' => $eventId,
            'category_id' => $categoryId,
            'action' => 'removed category from event'
        ]);
        $logStmt->execute([$user['id'], $eventId, $changes]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Category removed from event successfully'
        ]);
    } else {
        throw new Exception('Failed to remove category from event');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
