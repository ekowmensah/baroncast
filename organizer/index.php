<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();
$organizerId = $user['id'];

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch dynamic analytics data for this organizer
try {
    // Organizer's Events
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ?");
    $stmt->execute([$organizerId]);
    $myEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Events
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
    $stmt->execute([$organizerId]);
    $activeEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Votes for Organizer's Events
    $stmt = $pdo->prepare("
        SELECT COUNT(v.id) as total 
        FROM votes v 
        JOIN nominees n ON v.nominee_id = n.id 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE e.organizer_id = ?
    ");
    $stmt->execute([$organizerId]);
    $totalVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Revenue for Organizer's Events
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) as total 
        FROM transactions t 
        JOIN events e ON t.event_id = e.id 
        WHERE e.organizer_id = ? AND t.status = 'completed'
    ");
    $stmt->execute([$organizerId]);
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Events
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ? AND status = 'pending'");
    $stmt->execute([$organizerId]);
    $pendingEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's Votes
    $stmt = $pdo->prepare("
        SELECT COUNT(v.id) as total 
        FROM votes v 
        JOIN nominees n ON v.nominee_id = n.id 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE e.organizer_id = ? AND DATE(v.created_at) = CURDATE()
    ");
    $stmt->execute([$organizerId]);
    $todayVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Categories
    $stmt = $pdo->prepare("
        SELECT COUNT(c.id) as total 
        FROM categories c 
        JOIN events e ON c.event_id = e.id 
        WHERE e.organizer_id = ?
    ");
    $stmt->execute([$organizerId]);
    $totalCategories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Nominees
    $stmt = $pdo->prepare("
        SELECT COUNT(n.id) as total 
        FROM nominees n 
        JOIN categories c ON n.category_id = c.id 
        JOIN events e ON c.event_id = e.id 
        WHERE e.organizer_id = ?
    ");
    $stmt->execute([$organizerId]);
    $totalNominees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent Events
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COUNT(v.id) as vote_count,
               COALESCE(SUM(t.amount), 0) as revenue
        FROM events e 
        LEFT JOIN categories c ON e.id = c.event_id 
        LEFT JOIN nominees n ON c.id = n.category_id 
        LEFT JOIN votes v ON n.id = v.nominee_id 
        LEFT JOIN transactions t ON e.id = t.event_id AND t.status = 'completed'
        WHERE e.organizer_id = ? 
        GROUP BY e.id 
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$organizerId]);
    $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Fallback values if queries fail
    $myEvents = 0;
    $activeEvents = 0;
    $totalVotes = 0;
    $totalRevenue = 0;
    $pendingEvents = 0;
    $todayVotes = 0;
    $totalCategories = 0;
    $totalNominees = 0;
    $recentEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive-sidebar.css">
</head>
<body>
    <?php 
    $pageTitle = "Organizer Dashboard";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Dashboard Overview</h2>
                <div class="d-flex gap-2">
                    <a href="create-event.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Create Event
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">My Events</h6>
                                    <h3 class="card-title mb-0"><?= number_format($myEvents) ?></h3>
                                </div>
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Active Events</h6>
                                    <h3 class="card-title mb-0"><?= number_format($activeEvents) ?></h3>
                                </div>
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Total Votes</h6>
                                    <h3 class="card-title mb-0"><?= number_format($totalVotes) ?></h3>
                                </div>
                                <div class="stats-icon bg-info">
                                    <i class="fas fa-vote-yea"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Revenue</h6>
                                    <h3 class="card-title mb-0"><?= SiteSettings::getCurrencySymbol() ?> <?= number_format($totalRevenue, 2) ?></h3>
                                </div>
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Events -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Recent Events</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Total Votes</th>
                                    <th>Revenue</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentEvents)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-calendar-times"></i>
                                            No events found. <a href="create-event.php">Create your first event</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentEvents as $event): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($event['title']) ?></td>
                                            <td>
                                                <?php 
                                                $badgeClass = '';
                                                switch($event['status']) {
                                                    case 'active': $badgeClass = 'badge-success'; break;
                                                    case 'pending': $badgeClass = 'badge-warning'; break;
                                                    case 'draft': $badgeClass = 'badge-secondary'; break;
                                                    case 'ended': $badgeClass = 'badge-info'; break;
                                                    default: $badgeClass = 'badge-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($event['status']) ?></span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($event['start_date'] ?? $event['created_at'])) ?></td>
                                            <td><?= isset($event['end_date']) ? date('M j, Y', strtotime($event['end_date'])) : 'Not set' ?></td>
                                            <td><?= number_format($event['vote_count']) ?></td>
                                            <td><?= SiteSettings::getCurrencySymbol() ?> <?= number_format($event['revenue'], 2) ?></td>
                                            <td>
                                                <a href="view-event.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                                <?php if ($event['status'] !== 'ended'): ?>
                                                    <a href="edit-event.php?id=<?= $event['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                                <?php else: ?>
                                                    <a href="event-report.php?id=<?= $event['id'] ?>" class="btn btn-outline btn-sm">Report</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="create-event.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus mb-2"></i><br>
                                Create New Event
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="categories.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-tags mb-2"></i><br>
                                Manage Categories
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="nominees.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-friends mb-2"></i><br>
                                Manage Nominees
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="withdrawal.php" class="btn btn-success w-100">
                                <i class="fas fa-hand-holding-usd mb-2"></i><br>
                                Request Withdrawal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JavaScript -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
