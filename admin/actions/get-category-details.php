<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit;
}

$categoryId = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.status,
            c.created_at,
            e.title as event_title,
            e.id as event_id,
            u.full_name as organizer_name,
            COUNT(DISTINCT n.id) as nominee_count,
            COUNT(DISTINCT v.id) as total_votes
        FROM categories c
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'category' => $category
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching category details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
