<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['nominee_id']) || !is_numeric($_POST['nominee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid nominee ID']);
    exit;
}

$nomineeId = (int)$_POST['nominee_id'];
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nominee name is required']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Handle image upload if provided
    $imagePath = null;
    $updateImage = false;
    
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
                $updateImage = true;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Please use JPG, PNG, or GIF']);
            exit;
        }
    }
    
    // Build update query based on whether image is being updated
    if ($updateImage) {
        $stmt = $pdo->prepare("
            UPDATE nominees 
            SET name = ?, description = ?, image = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$name, $description, $imagePath, $nomineeId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE nominees 
            SET name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$name, $description, $nomineeId]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Nominee updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update nominee']);
    }
    
} catch (Exception $e) {
    error_log("Error updating nominee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
