<?php
/**
 * Assign Existing Category to Event
 * Allows admin to assign an existing category to an event
 */

require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Check if user is logged in and is admin
$auth = new Auth();
$auth->requireAuth(['admin']);
$user = $auth->getCurrentUser();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['category_id']) || !isset($input['event_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $categoryId = (int)$input['category_id'];
    $eventId = (int)$input['event_id'];
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if category exists
    $categoryStmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
    $categoryStmt->execute([$categoryId]);
    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }
    
    // Check if event exists
    $eventStmt = $pdo->prepare("SELECT id, title FROM events WHERE id = ?");
    $eventStmt->execute([$eventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    // Create event_categories table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                category_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_event_category (event_id, category_id)
            )
        ");
    } catch (PDOException $e) {
        // Table creation failed, but continue - might already exist
    }
    
    // Check if category is already assigned to this event (with fallback)
    $exists = ['count' => 0];
    try {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_categories 
            WHERE event_id = ? AND category_id = ?
        ");
        $checkStmt->execute([$eventId, $categoryId]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist, so no existing assignment
    }
    
    if ($exists['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Category is already assigned to this event']);
        exit;
    }
    
    // Assign category to event
    $assignStmt = $pdo->prepare("
        INSERT INTO event_categories (event_id, category_id) 
        VALUES (?, ?)
    ");
    $assignStmt->execute([$eventId, $categoryId]);
    
    // Also update the category's event_id for backward compatibility
    $updateStmt = $pdo->prepare("
        UPDATE categories 
        SET event_id = ? 
        WHERE id = ? AND event_id IS NULL
    ");
    $updateStmt->execute([$eventId, $categoryId]);
    
    // Log the action (optional - skip if table doesn't exist)
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO system_logs (admin_id, action, details, created_at) 
            VALUES (?, 'ASSIGN_CATEGORY', ?, NOW())
        ");
        $logStmt->execute([
            $user['id'],
            "Assigned category '{$category['name']}' to event '{$event['title']}'"
        ]);
    } catch (PDOException $e) {
        // Logging failed, but continue - not critical for functionality
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Category assigned to event successfully',
        'category' => $category,
        'event' => $event
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in assign-category-to-event.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in assign-category-to-event.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while assigning category'
    ]);
}
?>
