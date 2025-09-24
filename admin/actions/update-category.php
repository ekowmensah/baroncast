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

if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit;
}

$categoryId = (int)$_POST['category_id'];
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = trim($_POST['status'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit;
}

if (!in_array($status, ['active', 'inactive', 'ended'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        UPDATE categories 
        SET name = ?, description = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$name, $description, $status, $categoryId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
    
} catch (Exception $e) {
    error_log("Error updating category: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
