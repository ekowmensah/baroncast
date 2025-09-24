<?php
/**
 * Update Scheme Status Action
 * Handles scheme status updates (activate, suspend, approve) from admin dashboard
 */

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['scheme_id']) || !is_numeric($input['scheme_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid scheme ID']);
        exit;
    }
    
    if (!isset($input['status']) || empty($input['status'])) {
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        exit;
    }
    
    $schemeId = (int)$input['scheme_id'];
    $status = trim($input['status']);
    
    // Validate status
    $allowedStatuses = ['active', 'suspended', 'draft', 'ended'];
    if (!in_array($status, $allowedStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if scheme exists
    $stmt = $pdo->prepare("SELECT id, name, status FROM schemes WHERE id = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scheme) {
        echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        exit;
    }
    
    // Update the scheme status
    $stmt = $pdo->prepare("UPDATE schemes SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $schemeId]);
    
    if ($result) {
        $statusText = ucfirst($status);
        echo json_encode([
            'success' => true, 
            'message' => "Scheme '{$scheme['name']}' status updated to {$statusText}"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update scheme status']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update-scheme-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in update-scheme-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the scheme status']);
}
?>
