<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Get analytics data
try {
    // Vote trends (last 30 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as vote_date, COUNT(*) as vote_count 
        FROM votes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY vote_date ASC
    ");
    $voteTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue trends (last 30 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as transaction_date, SUM(amount) as revenue 
        FROM transactions 
        WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY transaction_date ASC
    ");
    $revenueTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top events by votes
    $stmt = $pdo->query("
        SELECT e.title, COUNT(v.id) as vote_count, SUM(t.amount) as revenue
        FROM events e
        LEFT JOIN categories c ON e.id = c.event_id
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        LEFT JOIN transactions t ON v.payment_reference = t.reference AND t.status = 'completed'
        WHERE e.status != 'deleted'
        GROUP BY e.id, e.title
        ORDER BY vote_count DESC
        LIMIT 10
    ");
    $topEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Payment method distribution
    $stmt = $pdo->query("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount
        FROM transactions 
        WHERE status = 'completed'
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading analytics: " . $e->getMessage();
    $voteTrends = [];
    $revenueTrends = [];
    $topEvents = [];
    $paymentMethods = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php 
    $pageTitle = "Analytics";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Analytics Dashboard</h2>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Vote Trends (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="voteTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Revenue Trends (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables Row -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Top Performing Events</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topEvents)): ?>
                                <p class="text-muted text-center py-4">No event data available yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Votes</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topEvents as $event): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                                    <td><?= number_format($event['vote_count']) ?></td>
                                                    <td>GHS <?= number_format($event['revenue'] ?? 0, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($paymentMethods)): ?>
                                <p class="text-muted text-center py-4">No payment data available yet.</p>
                            <?php else: ?>
                                <canvas id="paymentMethodsChart" height="300"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Vote Trends Chart
        const voteTrendsData = <?= json_encode($voteTrends) ?>;
        const voteTrendsCtx = document.getElementById('voteTrendsChart').getContext('2d');
        new Chart(voteTrendsCtx, {
            type: 'line',
            data: {
                labels: voteTrendsData.map(item => item.vote_date),
                datasets: [{
                    label: 'Votes',
                    data: voteTrendsData.map(item => item.vote_count),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Revenue Trends Chart
        const revenueTrendsData = <?= json_encode($revenueTrends) ?>;
        const revenueTrendsCtx = document.getElementById('revenueTrendsChart').getContext('2d');
        new Chart(revenueTrendsCtx, {
            type: 'bar',
            data: {
                labels: revenueTrendsData.map(item => item.transaction_date),
                datasets: [{
                    label: 'Revenue (GHS)',
                    data: revenueTrendsData.map(item => item.revenue),
                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Payment Methods Chart
        <?php if (!empty($paymentMethods)): ?>
        const paymentMethodsData = <?= json_encode($paymentMethods) ?>;
        const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(paymentMethodsCtx, {
            type: 'doughnut',
            data: {
                labels: paymentMethodsData.map(item => item.payment_method),
                datasets: [{
                    data: paymentMethodsData.map(item => item.count),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
