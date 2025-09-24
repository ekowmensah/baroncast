<?php
/**
 * Add New Administrator Action
 * Creates a new administrator account with specified permissions
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $permissions = $_POST['permissions'] ?? [];
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Full name, email, and password are required']);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate password length
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email address already exists']);
        exit;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Convert permissions array to JSON
    $permissions_json = json_encode($permissions);
    
    // Insert new admin
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, phone, password, role, status, permissions, created_at) 
        VALUES (?, ?, ?, ?, 'admin', ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $full_name,
        $email,
        $phone,
        $hashed_password,
        $status,
        $permissions_json
    ]);
    
    if ($result) {
        $admin_id = $pdo->lastInsertId();
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (admin_id, action, details, created_at) 
            VALUES (?, 'ADMIN_CREATED', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Created new administrator: {$full_name} ({$email})"
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Administrator created successfully',
            'admin_id' => $admin_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create administrator']);
    }
    
} catch (Exception $e) {
    error_log("Add admin error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
