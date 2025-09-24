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
    $categoryId = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $displayOrder = intval($_POST['display_order'] ?? 0);
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nominee name is required']);
        exit;
    }
    
    if ($categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid category']);
        exit;
    }
    
    // Validate category exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Selected category does not exist']);
        exit;
    }
    
    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/nominees/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $filename = uniqid('nominee_') . '.' . $fileExtension;
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = $filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Please use JPG, PNG, or GIF']);
            exit;
        }
    }
    
    // Check if nominee name already exists in this category
    $stmt = $pdo->prepare("SELECT id FROM nominees WHERE category_id = ? AND name = ?");
    $stmt->execute([$categoryId, $name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A nominee with this name already exists in the selected category']);
        exit;
    }
    
    // Insert new nominee
    $stmt = $pdo->prepare("
        INSERT INTO nominees (
            category_id, name, description, image, display_order, status
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $categoryId,
        $name,
        $description,
        $imagePath,
        $displayOrder,
        $status
    ]);
    
    $nomineeId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Nominee added successfully',
        'nominee_id' => $nomineeId
    ]);
    
} catch (Exception $e) {
    error_log('Add nominee error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding the nominee']);
}
?>
