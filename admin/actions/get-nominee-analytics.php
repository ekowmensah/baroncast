<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid nominee ID']);
    exit;
}

$nomineeId = (int)$_GET['id'];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get total votes for this nominee
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE nominee_id = ?");
    $stmt->execute([$nomineeId]);
    $totalVotes = $stmt->fetchColumn() ?: 0;
    
    // Get votes today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as votes_today 
        FROM votes 
        WHERE nominee_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$nomineeId]);
    $votesToday = $stmt->fetchColumn() ?: 0;
    
    // Get nominee's category to calculate position and percentage
    $stmt = $pdo->prepare("
        SELECT c.id as category_id 
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        WHERE n.id = ?
    ");
    $stmt->execute([$nomineeId]);
    $categoryId = $stmt->fetchColumn();
    
    $position = 1;
    $votePercentage = 0;
    
    if ($categoryId) {
        // Get all nominees in the same category with their vote counts
        $stmt = $pdo->prepare("
            SELECT n.id, COUNT(v.id) as vote_count
            FROM nominees n
            LEFT JOIN votes v ON n.id = v.nominee_id
            WHERE n.category_id = ?
            GROUP BY n.id
            ORDER BY vote_count DESC
        ");
        $stmt->execute([$categoryId]);
        $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalCategoryVotes = array_sum(array_column($nominees, 'vote_count'));
        
        foreach ($nominees as $index => $nominee) {
            if ($nominee['id'] == $nomineeId) {
                $position = $index + 1;
                if ($totalCategoryVotes > 0) {
                    $votePercentage = round(($nominee['vote_count'] / $totalCategoryVotes) * 100, 1);
                }
                break;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_votes' => (int)$totalVotes,
            'votes_today' => (int)$votesToday,
            'position' => $position,
            'vote_percentage' => $votePercentage
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching nominee analytics: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
