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

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get form data
    $eventId = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $voteLimit = !empty($_POST['vote_limit']) ? (int)$_POST['vote_limit'] : 1;
    $status = $_POST['status'] ?? 'active';
    $displayOrder = !empty($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    // Validate required fields (only name is required now)
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
    
    // Validate event exists if event_id is provided
    if ($eventId) {
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Selected event does not exist']);
            exit;
        }
        
        // Check if category name already exists for this event
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE event_id = ? AND name = ?");
        $stmt->execute([$eventId, $name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A category with this name already exists for the selected event']);
            exit;
        }
    } else {
        // For standalone categories, check if name already exists globally
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE event_id IS NULL AND name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A standalone category with this name already exists']);
            exit;
        }
    }
    
    // Insert new category
    $stmt = $pdo->prepare("
        INSERT INTO categories (
            event_id, name, description, vote_limit, display_order, status
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $eventId,
        $name,
        $description,
        $voteLimit,
        $displayOrder,
        $status
    ]);
    
    $categoryId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Category added successfully',
        'category_id' => $categoryId
    ]);
    
} catch (Exception $e) {
    error_log('Add category error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding the category']);
}
?>
