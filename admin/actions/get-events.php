<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch all events for the dropdown
    $stmt = $pdo->query("
        SELECT id, title, status 
        FROM events 
        WHERE status IN ('active', 'upcoming') 
        ORDER BY title ASC
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    error_log('Get events error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading events'
    ]);
}
?>
