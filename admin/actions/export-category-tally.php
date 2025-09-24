<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch category tally data with revenue calculations
    $stmt = $pdo->query("
        SELECT c.id, c.name as category_name, c.description,
               e.title as event_title, e.status as event_status,
               u.full_name as organizer_name,
               COUNT(DISTINCT n.id) as nominee_count,
               COUNT(DISTINCT v.id) as total_votes,
               (COUNT(DISTINCT v.id) * 1.00) as total_revenue,
               (COUNT(DISTINCT v.id) * 0.10) as commission,
               (COUNT(DISTINCT v.id) * 0.90) as organizer_share,
               c.created_at
        FROM categories c
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        GROUP BY c.id
        ORDER BY total_votes DESC, c.created_at DESC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="category_tally_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Category ID',
        'Event Title',
        'Category Name',
        'Description',
        'Organizer Name',
        'Total Votes',
        'Total Revenue ($)',
        'Commission ($)',
        'Organizer Share ($)',
        'Nominee Count',
        'Event Status',
        'Created Date'
    ]);
    
    // Add data rows
    foreach ($categories as $category) {
        fputcsv($output, [
            $category['id'],
            $category['event_title'],
            $category['category_name'],
            $category['description'],
            $category['organizer_name'],
            $category['total_votes'],
            number_format($category['total_revenue'], 2),
            number_format($category['commission'], 2),
            number_format($category['organizer_share'], 2),
            $category['nominee_count'],
            $category['event_status'],
            $category['created_at']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    error_log('Export category tally error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'An error occurred while exporting category tally data';
}
?>
