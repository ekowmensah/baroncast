<?php
/**
 * Export Administrators Data
 * Exports administrator data to CSV format
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    // Build query with filters
    $query = "SELECT id, full_name, email, phone, status, permissions, created_at, last_login 
              FROM users WHERE role = 'admin'";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if (!empty($status)) {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    $filename = 'administrators_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Full Name',
        'Email',
        'Phone',
        'Status',
        'Permissions',
        'Created Date',
        'Last Login'
    ]);
    
    // Add data rows
    foreach ($admins as $admin) {
        // Format permissions
        $permissions = json_decode($admin['permissions'] ?? '[]', true);
        $permissions_str = is_array($permissions) ? implode(', ', $permissions) : '';
        
        // Format dates
        $created_date = $admin['created_at'] ? date('Y-m-d H:i:s', strtotime($admin['created_at'])) : '';
        $last_login = $admin['last_login'] ? date('Y-m-d H:i:s', strtotime($admin['last_login'])) : 'Never';
        
        fputcsv($output, [
            $admin['id'],
            $admin['full_name'],
            $admin['email'],
            $admin['phone'] ?? '',
            ucfirst($admin['status']),
            $permissions_str,
            $created_date,
            $last_login
        ]);
    }
    
    // Log the export action
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (admin_id, action, details, created_at) 
        VALUES (?, 'ADMIN_EXPORT', ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['admin_id'],
        "Exported " . count($admins) . " administrator records"
    ]);
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Export admins error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting data";
}
?>
