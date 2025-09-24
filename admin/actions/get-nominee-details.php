<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid nominee ID']);
    exit;
}

$nomineeId = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT n.*, c.name as category_name, e.title as event_title, 
               u.full_name as organizer_name, e.organizer_id,
               COUNT(v.id) as vote_count
        FROM nominees n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
        LEFT JOIN votes v ON n.id = v.nominee_id
        WHERE n.id = ?
        GROUP BY n.id
    ");
    $stmt->execute([$nomineeId]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        echo json_encode(['success' => false, 'message' => 'Nominee not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'nominee' => $nominee
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching nominee details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
