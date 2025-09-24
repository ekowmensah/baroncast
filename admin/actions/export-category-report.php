<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$categoryId = $_GET['category_id'] ?? null;

if (!$categoryId) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Category ID is required';
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch specific category details with nominees and votes
    $stmt = $pdo->prepare("
        SELECT c.id, c.name as category_name, c.description,
               e.title as event_title, e.status as event_status,
               u.full_name as organizer_name,
               n.id as nominee_id, n.name as nominee_name, n.short_code,
               COUNT(v.id) as nominee_votes,
               c.created_at
        FROM categories c
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        WHERE c.id = ?
        GROUP BY c.id, n.id
        ORDER BY nominee_votes DESC
    ");
    $stmt->execute([$categoryId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        header('HTTP/1.1 404 Not Found');
        echo 'Category not found';
        exit;
    }
    
    $categoryInfo = $results[0];
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="category_report_' . $categoryId . '_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Add category information header
    fputcsv($output, ['CATEGORY REPORT']);
    fputcsv($output, ['Category Name:', $categoryInfo['category_name']]);
    fputcsv($output, ['Event Title:', $categoryInfo['event_title']]);
    fputcsv($output, ['Organizer:', $categoryInfo['organizer_name']]);
    fputcsv($output, ['Event Status:', $categoryInfo['event_status']]);
    fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Add nominees data headers
    fputcsv($output, [
        'Nominee ID',
        'Nominee Name',
        'Short Code',
        'Total Votes',
        'Vote Percentage'
    ]);
    
    // Calculate total votes for percentage
    $totalVotes = array_sum(array_column($results, 'nominee_votes'));
    
    // Add nominees data
    foreach ($results as $result) {
        if ($result['nominee_id']) { // Only include actual nominees
            $percentage = $totalVotes > 0 ? ($result['nominee_votes'] / $totalVotes) * 100 : 0;
            fputcsv($output, [
                $result['nominee_id'],
                $result['nominee_name'],
                $result['short_code'],
                $result['nominee_votes'],
                number_format($percentage, 2) . '%'
            ]);
        }
    }
    
    // Add summary
    fputcsv($output, []); // Empty row
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Nominees:', count(array_filter($results, function($r) { return $r['nominee_id']; }))]);
    fputcsv($output, ['Total Votes:', $totalVotes]);
    fputcsv($output, ['Revenue Generated:', '$' . number_format($totalVotes * 1.00, 2)]);
    
    fclose($output);
    
} catch (Exception $e) {
    error_log('Export category report error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'An error occurred while exporting category report';
}
?>
