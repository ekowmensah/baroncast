<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Fetch dynamic vote analytics
try {
    // Total votes by payment method
    $paymentMethodQuery = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount 
                          FROM votes 
                          WHERE payment_status = 'completed' 
                          GROUP BY payment_method 
                          ORDER BY count DESC";
    $paymentMethodStmt = $pdo->prepare($paymentMethodQuery);
    $paymentMethodStmt->execute();
    $paymentMethods = $paymentMethodStmt->fetchAll(PDO::FETCH_ASSOC);

    // Votes by event
    $eventVotesQuery = "SELECT e.title, e.id, COUNT(v.id) as vote_count, SUM(v.amount) as total_revenue
                       FROM events e 
                       LEFT JOIN votes v ON e.id = v.event_id AND v.payment_status = 'completed'
                       GROUP BY e.id, e.title 
                       ORDER BY vote_count DESC";
    $eventVotesStmt = $pdo->prepare($eventVotesQuery);
    $eventVotesStmt->execute();
    $eventVotes = $eventVotesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Daily vote trends (last 30 days)
    $dailyTrendsQuery = "SELECT DATE(voted_at) as vote_date, COUNT(*) as daily_votes, SUM(amount) as daily_revenue
                        FROM votes 
                        WHERE payment_status = 'completed' 
                        AND voted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY DATE(voted_at) 
                        ORDER BY vote_date DESC";
    $dailyTrendsStmt = $pdo->prepare($dailyTrendsQuery);
    $dailyTrendsStmt->execute();
    $dailyTrends = $dailyTrendsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top nominees by votes
    $topNomineesQuery = "SELECT n.name, c.name as category_name, e.title as event_title, 
                        COUNT(v.id) as vote_count, SUM(v.amount) as total_amount
                        FROM nominees n
                        LEFT JOIN votes v ON n.id = v.nominee_id AND v.payment_status = 'completed'
                        LEFT JOIN categories c ON n.category_id = c.id
                        LEFT JOIN events e ON c.event_id = e.id
                        GROUP BY n.id, n.name, c.name, e.title
                        ORDER BY vote_count DESC
                        LIMIT 10";
    $topNomineesStmt = $pdo->prepare($topNomineesQuery);
    $topNomineesStmt->execute();
    $topNominees = $topNomineesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Overall statistics
    $statsQuery = "SELECT 
                   (SELECT COUNT(*) FROM votes WHERE payment_status = 'completed') as total_votes,
                   (SELECT SUM(amount) FROM votes WHERE payment_status = 'completed') as total_revenue,
                   (SELECT COUNT(DISTINCT voter_phone) FROM votes WHERE payment_status = 'completed') as unique_voters,
                   (SELECT COUNT(*) FROM events WHERE status = 'active') as active_events";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $paymentMethods = [];
    $eventVotes = [];
    $dailyTrends = [];
    $topNominees = [];
    $stats = ['total_votes' => 0, 'total_revenue' => 0, 'unique_voters' => 0, 'active_events' => 0];
}

$pageTitle = 'Vote Analytics';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content-area">
                <div class="page-header">
                    <h1><i class="fas fa-chart-line"></i> Vote Analytics</h1>
                    <p>Comprehensive voting statistics and insights</p>
                </div>

                <!-- Overview Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_votes']); ?></h3>
                            <p>Total Votes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>GH₵<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['unique_voters']); ?></h3>
                            <p>Unique Voters</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_events']); ?></h3>
                            <p>Active Events</p>
                        </div>
                    </div>
                </div>

                <div class="analytics-grid">
                    <!-- Payment Methods Chart -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($paymentMethods)): ?>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <div class="payment-method-item">
                                        <div class="method-info">
                                            <span class="method-name"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></span>
                                            <span class="method-stats"><?php echo number_format($method['count']); ?> votes - GH₵<?php echo number_format($method['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="method-bar">
                                            <div class="bar-fill" style="width: <?php echo ($method['count'] / max(array_column($paymentMethods, 'count'))) * 100; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data">No payment data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Event Performance -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-trophy"></i> Event Performance</h3>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($eventVotes)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Votes</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eventVotes as $event): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                    <td><?php echo number_format($event['vote_count']); ?></td>
                                                    <td>GH₵<?php echo number_format($event['total_revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No event data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Nominees -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-star"></i> Top Nominees</h3>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($topNominees)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Nominee</th>
                                                <th>Category</th>
                                                <th>Event</th>
                                                <th>Votes</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topNominees as $nominee): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($nominee['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($nominee['category_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($nominee['event_title']); ?></td>
                                                    <td><?php echo number_format($nominee['vote_count']); ?></td>
                                                    <td>GH₵<?php echo number_format($nominee['total_amount'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No nominee data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Daily Trends -->
                    <div class="analytics-card full-width">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Daily Vote Trends (Last 30 Days)</h3>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($dailyTrends)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Votes</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dailyTrends as $trend): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($trend['vote_date'])); ?></td>
                                                    <td><?php echo number_format($trend['daily_votes']); ?></td>
                                                    <td>GH₵<?php echo number_format($trend['daily_revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No daily trend data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
