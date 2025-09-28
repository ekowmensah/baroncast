<?php
/**
 * Add Nominee Action for Organizer Dashboard
 * Handles nominee creation from organizer dashboard
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Validate required fields
    if (empty($_POST['name'])) {
        echo json_encode(['success' => false, 'message' => 'Nominee name is required']);
        exit;
    }
    
    if (empty($_POST['category_id'])) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        exit;
    }
    
    // Generate shortcode if provided, otherwise auto-generate
    $shortCode = !empty($_POST['short_code']) ? strtoupper(trim($_POST['short_code'])) : null;
    
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
    
    // Scheme ID is optional - can be null
    $schemeId = !empty($_POST['scheme_id']) ? $_POST['scheme_id'] : null;
    
    // Verify that the category belongs to this organizer's events
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, e.organizer_id 
        FROM categories c 
        LEFT JOIN events e ON c.event_id = e.id 
        WHERE c.id = ? AND (e.organizer_id = ? OR c.event_id IS NULL)
    ");
    $stmt->execute([$_POST['category_id'], $user['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Invalid category selected']);
        exit;
    }
    
    // Verify that the scheme belongs to this organizer (if scheme is provided)
    $scheme = null;
    if ($schemeId) {
        $stmt = $pdo->prepare("
            SELECT id, name FROM schemes 
            WHERE id = ? AND organizer_id = ?
        ");
        $stmt->execute([$schemeId, $user['id']]);
        $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$scheme) {
            echo json_encode(['success' => false, 'message' => 'Invalid event scheme selected']);
            exit;
        }
    }
    
    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/nominees/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, and GIF are allowed']);
            exit;
        }
        
        // Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image file too large. Maximum size is 5MB']);
            exit;
        }
        
        $fileName = uniqid('nominee_') . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/nominees/' . $fileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    }
    
    // Insert nominee into database
    $stmt = $pdo->prepare("
        INSERT INTO nominees (
            name, 
            short_code,
            description, 
            category_id, 
            scheme_id, 
            image, 
            status, 
            facebook_url, 
            instagram_url, 
            twitter_url, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $stmt->execute([
        trim($_POST['name']),
        $shortCode, // Will be updated after insert if null
        trim($_POST['description'] ?? ''),
        $_POST['category_id'],
        $schemeId,
        $imagePath,
        $_POST['status'] ?? 'active',
        trim($_POST['facebook_url'] ?? ''),
        trim($_POST['instagram_url'] ?? ''),
        trim($_POST['twitter_url'] ?? '')
    ]);
    
    if ($result) {
        $nomineeId = $pdo->lastInsertId();
        
        // Generate shortcode if not provided
        if (!$shortCode) {
            $generatedShortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $_POST['name']), 0, 3)) . 
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
                $finalShortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $_POST['name']), 0, 2)) . 
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
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add nominee']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in organizer add-nominee.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in organizer add-nominee.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding the nominee']);
}
?>
