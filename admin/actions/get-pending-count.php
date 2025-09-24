<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get pending transactions count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count
        FROM transactions 
        WHERE status = 'pending' 
        AND payment_method = 'mobile_money'
        AND created_at <= NOW() - INTERVAL 5 MINUTE
    ");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pending count'
    ]);
}
?>