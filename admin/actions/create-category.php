<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Only allow admin access
$auth = new Auth();
$auth->requireAuth(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$event_id = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;

// Validate required fields
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if category name already exists for the same event (or globally if no event)
    if ($event_id) {
        $checkQuery = "SELECT id FROM categories WHERE name = ? AND event_id = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$name, $event_id]);
    } else {
        $checkQuery = "SELECT id FROM categories WHERE name = ? AND event_id IS NULL";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$name]);
    }
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'A category with this name already exists']);
        exit();
    }
    
    // Insert new category
    $insertQuery = "INSERT INTO categories (name, description, event_id, created_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([$name, $description, $event_id]);
    
    $categoryId = $pdo->lastInsertId();
    
    // Get the created category data
    $selectQuery = "SELECT c.*, e.title as event_title FROM categories c 
                    LEFT JOIN events e ON c.event_id = e.id 
                    WHERE c.id = ?";
    $selectStmt = $pdo->prepare($selectQuery);
    $selectStmt->execute([$categoryId]);
    $category = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the activity
    $user = $auth->getCurrentUser();
    if ($user) {
        $logQuery = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $eventContext = $event_id ? " for event '{$category['event_title']}'" : " as standalone category";
        $logStmt->execute([
            $user['id'],
            'create_category',
            "Created category '{$name}'{$eventContext}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Category created successfully',
        'category' => $category
    ]);
    
} catch (PDOException $e) {
    error_log("Category creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred while creating category']);
} catch (Exception $e) {
    error_log("Category creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the category']);
}
?>
