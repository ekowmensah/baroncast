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
    $name = trim($_POST['name'] ?? '');
    $organizerId = intval($_POST['organizer_id'] ?? 0);
    $eventId = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;
    $votePrice = floatval($_POST['vote_price'] ?? 0);
    $adminPercentage = floatval($_POST['admin_percentage'] ?? 10);
    $organizerPercentage = floatval($_POST['organizer_percentage'] ?? 90);
    $bulkDiscountPercentage = floatval($_POST['bulk_discount_percentage'] ?? 0);
    $minBulkQuantity = intval($_POST['min_bulk_quantity'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Scheme name is required']);
        exit;
    }
    
    if ($organizerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select an organizer for this scheme']);
        exit;
    }
    
    if ($votePrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vote price must be greater than 0']);
        exit;
    }
    
    if ($adminPercentage < 0 || $adminPercentage > 100) {
        echo json_encode(['success' => false, 'message' => 'Admin commission must be between 0% and 100%']);
        exit;
    }
    
    // Ensure percentages add up to 100%
    if (abs(($adminPercentage + $organizerPercentage) - 100) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Admin and organizer percentages must add up to 100%']);
        exit;
    }
    
    // Check if scheme name already exists
    $stmt = $pdo->prepare("SELECT id FROM schemes WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A scheme with this name already exists']);
        exit;
    }
    
    // Insert new scheme
    $stmt = $pdo->prepare("
        INSERT INTO schemes (
            name, organizer_id, event_id, vote_price, admin_percentage, organizer_percentage, 
            bulk_discount_percentage, min_bulk_quantity, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $name,
        $organizerId,
        $eventId,
        $votePrice,
        $adminPercentage,
        $organizerPercentage,
        $bulkDiscountPercentage,
        $minBulkQuantity,
        $status
    ]);
    
    $schemeId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Scheme created successfully',
        'scheme_id' => $schemeId
    ]);
    
} catch (Exception $e) {
    error_log('Create scheme error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the scheme']);
}
?>
