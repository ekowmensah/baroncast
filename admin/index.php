<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch comprehensive dynamic analytics data
try {
    // Total Events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $totalEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Event Organizers (users with organizer role)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'organizer'");
    $totalOrganizers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Users (all roles)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Votes Cast
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Events (currently running)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'active'");
    $activeEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Events (awaiting approval)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'pending'");
    $pendingEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's Votes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes WHERE DATE(created_at) = CURDATE()");
    $todayVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // This Month's Revenue from completed transactions
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'completed'");
    $monthlyRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Revenue (all time)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed'");
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Completed Events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'completed'");
    $completedEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Categories across all events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $totalCategories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Nominees across all events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nominees");
    $totalNominees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // This Week's Votes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes WHERE WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $weeklyVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Transactions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status = 'pending'");
    $pendingTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Top performing event with vote count
    $stmt = $pdo->query("
        SELECT e.title, COUNT(v.id) as vote_count 
        FROM events e 
        LEFT JOIN categories c ON e.id = c.event_id 
        LEFT JOIN nominees n ON c.id = n.category_id 
        LEFT JOIN votes v ON n.id = v.nominee_id 
        WHERE e.status != 'deleted'
        GROUP BY e.id, e.title
        ORDER BY vote_count DESC 
        LIMIT 1
    ");
    $topEvent = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['title' => 'No Events Yet', 'vote_count' => 0];
    
    // Most active organizer
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(e.id) as event_count 
        FROM users u 
        LEFT JOIN events e ON u.id = e.organizer_id 
        WHERE u.role = 'organizer' 
        GROUP BY u.id, u.full_name
        ORDER BY event_count DESC 
        LIMIT 1
    ");
    $topOrganizer = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => 'No Organizers', 'event_count' => 0];
    
    // Average votes per event
    $stmt = $pdo->query("
        SELECT COALESCE(AVG(vote_counts.vote_count), 0) as avg_votes
        FROM (
            SELECT COUNT(v.id) as vote_count 
            FROM events e 
            LEFT JOIN categories c ON e.id = c.event_id 
            LEFT JOIN nominees n ON c.id = n.category_id 
            LEFT JOIN votes v ON n.id = v.nominee_id 
            GROUP BY e.id
        ) as vote_counts
    ");
    $avgVotesPerEvent = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_votes'], 1);
    
} catch (Exception $e) {
    // Comprehensive fallback values if queries fail
    $totalEvents = 0;
    $totalOrganizers = 0;
    $totalUsers = 0;
    $totalVotes = 0;
    $activeEvents = 0;
    $pendingEvents = 0;
    $completedEvents = 0;
    $todayVotes = 0;
    $weeklyVotes = 0;
    $monthlyRevenue = 0;
    $totalRevenue = 0;
    $totalCategories = 0;
    $totalNominees = 0;
    $pendingTransactions = 0;
    $topEvent = ['title' => 'No Events Yet', 'vote_count' => 0];
    $topOrganizer = ['full_name' => 'No Organizers', 'event_count' => 0];
    $avgVotesPerEvent = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
</head>
<body>
    <?php 
    $pageTitle = "Admin Dashboard";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Admin Dashboard</h2>
                <div class="d-flex gap-2">
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Manage Events
                    </a>
                    <a href="users.php" class="btn btn-outline-primary">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards - Square Layout -->
            <div class="row mb-4">
                <!-- Total Events -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-primary mb-3 mx-auto">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Total Events</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($totalEvents) ?></h3>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i><?= number_format($completedEvents) ?> completed
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Platform Users -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-success mb-3 mx-auto">
                                <i class="fas fa-users"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Platform Users</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($totalUsers) ?></h3>
                            <small class="text-info">
                                <i class="fas fa-user-tie me-1"></i><?= number_format($totalOrganizers) ?> organizers
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Total Votes Cast -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-info mb-3 mx-auto">
                                <i class="fas fa-vote-yea"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Total Votes Cast</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($totalVotes) ?></h3>
                            <small class="text-warning">
                                <i class="fas fa-calendar-day me-1"></i><?= number_format($todayVotes) ?> today
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-warning mb-3 mx-auto">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Monthly Revenue</h6>
                            <h3 class="card-title mb-2 text-light">GHS <?= number_format($monthlyRevenue, 2) ?></h3>
                            <small class="text-success">
                                <i class="fas fa-chart-line me-1"></i>GHS <?= number_format($totalRevenue, 2) ?> total
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-secondary mb-3 mx-auto">
                                <i class="fas fa-tags"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Categories</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($totalCategories) ?></h3>
                            <small class="text-info">Across all events</small>
                        </div>
                    </div>
                </div>

                <!-- Nominees -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-dark mb-3 mx-auto">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Nominees</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($totalNominees) ?></h3>
                            <small class="text-info">Total registered</small>
                        </div>
                    </div>
                </div>

                <!-- Weekly Votes -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-primary mb-3 mx-auto">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Weekly Votes</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($weeklyVotes) ?></h3>
                            <small class="text-primary">This week</small>
                        </div>
                    </div>
                </div>

                <!-- Average Votes per Event -->
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-body text-center">
                            <div class="stats-icon bg-success mb-3 mx-auto">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h6 class="card-subtitle mb-2 text-light-emphasis">Avg. Votes/Event</h6>
                            <h3 class="card-title mb-2 text-light"><?= number_format($avgVotesPerEvent, 1) ?></h3>
                            <small class="text-success">Performance metric</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Overview -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">System Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-play-circle text-success fa-2x"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1 text-light"><?= number_format($activeEvents) ?></h5>
                                            <small class="text-light-emphasis">Active Events</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-clock text-warning fa-2x"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1 text-light"><?= number_format($pendingEvents) ?></h5>
                                            <small class="text-light-emphasis">Pending Approval</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-user-tie text-info fa-2x"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1 text-light"><?= number_format($totalOrganizers) ?></h5>
                                            <small class="text-light-emphasis">Event Organizers</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-calendar-day text-primary fa-2x"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1 text-light"><?= number_format($todayVotes) ?></h5>
                                            <small class="text-light-emphasis">Today's Votes</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Performance Leaders</h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-trophy text-warning fa-3x mb-3"></i>
                                <h6 class="text-light-emphasis">Top Event</h6>
                                <h5 class="text-light"><?= htmlspecialchars($topEvent['title']) ?></h5>
                                <p class="text-warning"><?= number_format($topEvent['vote_count']) ?> votes</p>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="text-center">
                                <i class="fas fa-user-crown text-info fa-2x mb-3"></i>
                                <h6 class="text-light-emphasis">Top Organizer</h6>
                                <h6 class="text-light"><?= htmlspecialchars($topOrganizer['full_name']) ?></h6>
                                <p class="text-info"><?= number_format($topOrganizer['event_count']) ?> events</p>
                            </div>
                        </div>
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
                        <div class="col-md-2 mb-3">
                            <a href="events.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-alt mb-2"></i><br>
                                All Events
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="users.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-users mb-2"></i><br>
                                User Management
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="transactions.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-money-bill-wave mb-2"></i><br>
                                Transactions
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="general-settings.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-cogs mb-2"></i><br>
                                Settings
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="system-logs.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-file-alt mb-2"></i><br>
                                System Logs
                            </a>
                        </div>
                        <div class="col-md-2 mb-3">
                            <a href="support.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-life-ring mb-2"></i><br>
                                Support
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
