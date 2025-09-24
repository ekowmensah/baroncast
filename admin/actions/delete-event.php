<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only allow admin access
$auth = new Auth();
$auth->requireAuth(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if event exists
    $stmt = $pdo->prepare("SELECT id, title, organizer_id FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit();
    }
    
    // Check if event has votes (prevent deletion if votes exist)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $voteCount = $stmt->fetchColumn();
    
    if ($voteCount > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete event '{$event['title']}' because it has {$voteCount} votes. Events with votes cannot be deleted for data integrity."
        ]);
        exit();
    }
    
    // Check if event has transactions (prevent deletion if transactions exist)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $transactionCount = $stmt->fetchColumn();
    
    if ($transactionCount > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete event '{$event['title']}' because it has {$transactionCount} transactions. Events with financial records cannot be deleted."
        ]);
        exit();
    }
    
    // Delete related data in correct order (respecting foreign key constraints)
    
    // 1. Delete nominees (which will cascade to votes if any)
    $stmt = $pdo->prepare("
        DELETE n FROM nominees n 
        INNER JOIN categories c ON n.category_id = c.id 
        WHERE c.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $deletedNominees = $stmt->rowCount();
    
    // 2. Delete categories
    $stmt = $pdo->prepare("DELETE FROM categories WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $deletedCategories = $stmt->rowCount();
    
    // 3. Delete bulk vote packages
    // Vote package cleanup removed - table no longer exists
    $deletedPackages = 0;
    
    // 4. Finally delete the event
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    
    // Log the deletion activity
    $user = $auth->getCurrentUser();
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        'delete_event',
        "Deleted event '{$event['title']}' (ID: {$eventId}) with {$deletedCategories} categories, {$deletedNominees} nominees, and {$deletedPackages} vote packages",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Event '{$event['title']}' and all related data have been successfully deleted.",
        'details' => [
            'categories_deleted' => $deletedCategories,
            'nominees_deleted' => $deletedNominees,
            'packages_deleted' => $deletedPackages
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Event deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred while deleting event']);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Event deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the event']);
}
?>
