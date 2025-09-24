<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

$eventId = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get total votes for this event
    $stmt = $pdo->prepare("
        SELECT COUNT(v.id) as total_votes
        FROM votes v
        JOIN nominees n ON v.nominee_id = n.id
        JOIN categories c ON n.category_id = c.id
        WHERE c.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $totalVotes = $stmt->fetchColumn() ?: 0;
    
    // Get total nominees for this event
    $stmt = $pdo->prepare("
        SELECT COUNT(n.id) as total_nominees
        FROM nominees n
        JOIN categories c ON n.category_id = c.id
        WHERE c.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $totalNominees = $stmt->fetchColumn() ?: 0;
    
    // Get total categories for this event
    $stmt = $pdo->prepare("
        SELECT COUNT(c.id) as total_categories
        FROM categories c
        WHERE c.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $totalCategories = $stmt->fetchColumn() ?: 0;
    
    // Get total revenue for this event
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) as total_revenue
        FROM transactions t
        WHERE t.event_id = ? AND t.status = 'completed'
    ");
    $stmt->execute([$eventId]);
    $totalRevenue = $stmt->fetchColumn() ?: 0;
    
    echo json_encode([
        'success' => true,
        'analytics' => [
            'total_votes' => (int)$totalVotes,
            'total_nominees' => (int)$totalNominees,
            'total_categories' => (int)$totalCategories,
            'total_revenue' => number_format($totalRevenue, 2)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching event analytics: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
