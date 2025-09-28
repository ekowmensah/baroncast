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
    
    // Generate shortcode if provided, otherwise auto-generate
    $shortCode = !empty($_POST['short_code']) ? strtoupper(trim($_POST['short_code'])) : null;
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nominee name is required']);
        exit;
    }
    
    if ($categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid category']);
        exit;
    }
    
    // Validate shortcode format if provided
    if ($shortCode && !preg_match('/^[A-Z]{2,3}\d{3,4}$/', $shortCode)) {
        echo json_encode(['success' => false, 'message' => 'Short code must be in format like MHA012 or ABC1234']);
        exit;
    }
    
    // Check if shortcode already exists
    if ($shortCode) {
        $stmt = $pdo->prepare("SELECT id FROM nominees WHERE short_code = ?");
        $stmt->execute([$shortCode]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Short code already exists. Please choose a different one.']);
            exit;
        }
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
            category_id, name, short_code, description, image, display_order, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $categoryId,
        $name,
        $shortCode, // Will be updated after insert if null
        $description,
        $imagePath,
        $displayOrder,
        $status
    ]);
    
    $nomineeId = $pdo->lastInsertId();
    
    // Generate shortcode if not provided
    if (!$shortCode) {
        $generatedShortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) . 
                            str_pad($nomineeId, 3, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness
        $attempt = 0;
        $finalShortCode = $generatedShortCode;
        
        while ($attempt < 10) {
            $checkStmt = $pdo->prepare("SELECT id FROM nominees WHERE short_code = ? AND id != ?");
            $checkStmt->execute([$finalShortCode, $nomineeId]);
            
            if (!$checkStmt->fetch()) {
                break; // Unique shortcode found
            }
            
            $attempt++;
            $finalShortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 2)) . 
                            str_pad($nomineeId, 4, '0', STR_PAD_LEFT);
        }
        
        // Update nominee with generated shortcode
        $updateStmt = $pdo->prepare("UPDATE nominees SET short_code = ? WHERE id = ?");
        $updateStmt->execute([$finalShortCode, $nomineeId]);
        
        $shortCode = $finalShortCode;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Nominee added successfully',
        'nominee_id' => $nomineeId,
        'short_code' => $shortCode
    ]);
    
} catch (Exception $e) {
    error_log('Add nominee error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding the nominee']);
}
?>
