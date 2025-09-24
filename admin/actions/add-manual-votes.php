<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Get form data
    $nominee_id = $_POST['nominee_id'] ?? '';
    $vote_count = (int)($_POST['vote_count'] ?? 1);
    $voter_phone = $_POST['voter_phone'] ?? null;
    $reason = $_POST['reason'] ?? '';

    // Validate required fields
    if (empty($nominee_id) || $vote_count < 1) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }

    // Verify nominee exists and get event info
    $stmt = $pdo->prepare("
        SELECT n.id, n.name, n.category_id, c.event_id, e.title as event_title 
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        LEFT JOIN events e ON c.event_id = e.id 
        WHERE n.id = ?
    ");
    $stmt->execute([$nominee_id]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nominee) {
        echo json_encode(['success' => false, 'message' => 'Nominee not found']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    // Add votes to the votes table
    for ($i = 0; $i < $vote_count; $i++) {
        $stmt = $pdo->prepare("
            INSERT INTO votes (nominee_id, event_id, voter_phone, vote_method, created_at, notes) 
            VALUES (?, ?, ?, 'manual', NOW(), ?)
        ");
        
        $notes = "Manual vote entry by admin. Reason: " . $reason;
        $stmt->execute([
            $nominee_id, 
            $nominee['event_id'], 
            $voter_phone, 
            $notes
        ]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => "{$vote_count} manual votes added successfully for {$nominee['name']}"
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Add manual votes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Add manual votes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding votes']);
}
?>
