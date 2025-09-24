<?php
/**
 * Comprehensive Update Event Action
 * Handles all event fields, image upload, organizer assignment, and status changes
 */

require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Check if user is logged in and is admin
$auth = new Auth();
$auth->requireAuth(['admin']);
$user = $auth->getCurrentUser();

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
    
    // Get and validate form data
    $event_id = (int)($_POST['event_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_type = trim($_POST['event_type'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $rules = trim($_POST['rules'] ?? '');
    $organizer_id = (int)($_POST['organizer_id'] ?? 0);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $allow_multiple_votes = isset($_POST['allow_multiple_votes']) ? 1 : 0;
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($event_type) || empty($start_date) || empty($end_date) || empty($organizer_id)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    // Validate event ID exists
    if ($event_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'active', 'upcoming', 'completed', 'cancelled', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Validate dates
    if (strtotime($start_date) >= strtotime($end_date)) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        exit;
    }
    
    // Validate organizer exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'organizer'");
    $stmt->execute([$organizer_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid organizer selected']);
        exit;
    }
    
    // Handle image upload if provided
    $image_filename = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WebP']);
            exit;
        }
        
        if ($_FILES['event_image']['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
            exit;
        }
        
        $image_filename = 'event_' . $event_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $image_filename;
        
        if (!move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get existing columns in events table
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM events");
    $existingColumns = [];
    while ($row = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    // Build update query with only existing columns
    $update_fields = [];
    $params = [];
    
    // Core required fields that should always exist
    $coreFields = [
        'title' => $title,
        'description' => $description,
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    // Optional fields that might exist
    $optionalFields = [
        'event_type' => $event_type,
        'status' => $status,
        'location' => $location,
        'max_participants' => $max_participants,
        'rules' => $rules,
        'organizer_id' => $organizer_id,
        'is_public' => $is_public,
        'allow_multiple_votes' => $allow_multiple_votes
    ];
    
    // Add core fields
    foreach ($coreFields as $field => $value) {
        if (in_array($field, $existingColumns)) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    // Add optional fields if they exist
    foreach ($optionalFields as $field => $value) {
        if (in_array($field, $existingColumns)) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    // Add updated_at if column exists
    if (in_array('updated_at', $existingColumns)) {
        $update_fields[] = 'updated_at = NOW()';
    }
    
    // Add image update if new image was uploaded and column exists
    if ($image_filename && in_array('logo', $existingColumns)) {
        $update_fields[] = 'logo = ?';
        $params[] = $image_filename;
    }
    
    $params[] = $event_id; // For WHERE clause
    
    if (empty($update_fields)) {
        throw new Exception('No valid fields to update');
    }
    
    $sql = "UPDATE events SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        throw new Exception('Failed to update event');
    }
    
    // Log the action (optional - skip if table doesn't exist)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (admin_id, action, details, created_at) 
            VALUES (?, 'EVENT_UPDATED', ?, NOW())
        ");
        $stmt->execute([
            $user['id'],
            "Updated event: {$title} (ID: {$event_id})"
        ]);
    } catch (PDOException $e) {
        // Logging failed, but continue - not critical for functionality
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully',
        'event_id' => $event_id,
        'redirect' => $status === 'active' ? 'events.php' : 'events-approval.php'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Update event error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
