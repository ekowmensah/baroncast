<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch all schemes with available data
    $stmt = $pdo->query("
        SELECT 
            s.*
        FROM schemes s
        ORDER BY s.created_at DESC
    ");
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="schemes_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers based on actual table structure
    fputcsv($output, [
        'ID',
        'Name',
        'Vote Price ($)',
        'Bulk Discount (%)',
        'Min Bulk Quantity',
        'Status',
        'Created Date'
    ]);
    
    // Add data rows
    foreach ($schemes as $scheme) {
        fputcsv($output, [
            $scheme['id'],
            $scheme['name'] ?? 'N/A',
            number_format($scheme['vote_price'], 2),
            $scheme['bulk_discount_percentage'] ?? '0',
            $scheme['min_bulk_quantity'] ?? '0',
            ucfirst($scheme['status']),
            date('Y-m-d H:i:s', strtotime($scheme['created_at']))
        ]);
    }
    
    fclose($output);
    
} catch (PDOException $e) {
    error_log('Export schemes error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error exporting schemes data';
}
?>
