<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Get all nominees with category and event information
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.name as nominee_name,
            n.description,
            n.image,
            n.status,
            n.display_order,
            n.created_at,
            c.name as category_name,
            e.title as event_title,
            e.id as event_id,
            COUNT(v.id) as total_votes
        FROM nominees n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN votes v ON n.id = v.nominee_id
        GROUP BY n.id
        ORDER BY e.title, c.name, n.display_order, n.name
    ");
    $stmt->execute();
    $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    $filename = 'nominees_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV headers
    fputcsv($output, [
        'ID',
        'Nominee Name',
        'Description',
        'Category',
        'Event',
        'Status',
        'Display Order',
        'Total Votes',
        'Image URL',
        'Created Date'
    ]);

    // Write data rows
    foreach ($nominees as $nominee) {
        fputcsv($output, [
            $nominee['id'],
            $nominee['nominee_name'],
            $nominee['description'],
            $nominee['category_name'] ?? 'No Category',
            $nominee['event_title'] ?? 'Standalone Category',
            ucfirst($nominee['status']),
            $nominee['display_order'],
            $nominee['total_votes'],
            $nominee['image'],
            date('Y-m-d H:i:s', strtotime($nominee['created_at']))
        ]);
    }

    // Close output stream
    fclose($output);
    exit;

} catch (PDOException $e) {
    error_log("Export nominees error: " . $e->getMessage());
    header('Location: ../nominees.php?error=export_failed');
    exit;
} catch (Exception $e) {
    error_log("Export nominees error: " . $e->getMessage());
    header('Location: ../nominees.php?error=export_failed');
    exit;
}
?>
