<?php
/**
 * Delete Scheme Action
 * Handles scheme deletion from admin dashboard
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
    
    $schemeId = (int)$input['scheme_id'];
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if scheme exists and get details
    $stmt = $pdo->prepare("SELECT id, name FROM schemes WHERE id = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scheme) {
        echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        exit;
    }
    
    // Check if scheme is being used by nominees
    $stmt = $pdo->prepare("SELECT COUNT(*) as nominee_count FROM nominees WHERE scheme_id = ?");
    $stmt->execute([$schemeId]);
    $nomineeCount = $stmt->fetch(PDO::FETCH_ASSOC)['nominee_count'];
    
    if ($nomineeCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete scheme '{$scheme['name']}' because it is being used by {$nomineeCount} nominee(s). Please remove or reassign nominees first."
        ]);
        exit;
    }
    
    // Check if scheme has votes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as vote_count 
        FROM votes v 
        JOIN nominees n ON v.nominee_id = n.id 
        WHERE n.scheme_id = ?
    ");
    $stmt->execute([$schemeId]);
    $voteCount = $stmt->fetch(PDO::FETCH_ASSOC)['vote_count'];
    
    if ($voteCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete scheme '{$scheme['name']}' because it has {$voteCount} vote(s). Schemes with votes cannot be deleted for data integrity."
        ]);
        exit;
    }
    
    // Delete the scheme
    $stmt = $pdo->prepare("DELETE FROM schemes WHERE id = ?");
    $result = $stmt->execute([$schemeId]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => "Scheme '{$scheme['name']}' deleted successfully"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete scheme']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in delete-scheme.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in delete-scheme.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the scheme']);
}
?>
