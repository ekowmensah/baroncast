<?php
// No authentication required for voter dashboard
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';

$database = new Database();
$db = $database->getConnection();

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$where_conditions = [];
$params = [];

// Only show approved/active events to public (not pending)
$where_conditions[] = "e.status IN ('active', 'upcoming', 'completed')";

if ($status_filter !== 'all') {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Fetch events with counts for tabs
try {
    $counts_query = "SELECT 
                     COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_count,
                     COUNT(CASE WHEN e.status = 'upcoming' THEN 1 END) as upcoming_count,
                     COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_count,
                     COUNT(*) as total_count
                     FROM events e
                     WHERE e.status IN ('active', 'upcoming', 'completed')";
    $counts_stmt = $db->prepare($counts_query);
    $counts_stmt->execute();
    $counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $counts = ['active_count' => 0, 'upcoming_count' => 0, 'completed_count' => 0, 'total_count' => 0];
}

// Fetch events
try {
    $query = "SELECT e.*, u.full_name as organizer_name,
              COUNT(DISTINCT c.id) as category_count,
              COUNT(DISTINCT n.id) as nominee_count,
              COUNT(DISTINCT v.id) as total_votes
              FROM events e 
              LEFT JOIN users u ON e.organizer_id = u.id 
              LEFT JOIN categories c ON e.id = c.event_id 
              LEFT JOIN nominees n ON c.id = n.category_id
              LEFT JOIN votes v ON e.id = v.event_id 
              $where_clause
              GROUP BY e.id 
              ORDER BY 
                CASE 
                    WHEN e.status = 'active' THEN 1
                    WHEN e.status = 'upcoming' THEN 2
                    WHEN e.status = 'completed' THEN 3
                    ELSE 4
                END,
                e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - <?php echo htmlspecialchars(SiteSettings::getSiteName()); ?></title>
    <meta name="description" content="Browse and participate in voting events. <?php echo htmlspecialchars(SiteSettings::getSiteDescription()); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Modern Event Card Styles - Same as Home page */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .modern-card {
            background: var(--card-bg, white);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color, #e9ecef);
        }
        
        .modern-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }
        
        .event-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .modern-card:hover .card-image {
            transform: scale(1.05);
        }
        
        .card-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .card-image-placeholder i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        
        .event-status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            backdrop-filter: blur(10px);
        }
        
        .event-status-badge.active {
            background: rgba(40, 167, 69, 0.9);
        }
        
        .event-status-badge.upcoming {
            background: rgba(255, 193, 7, 0.9);
        }
        
        .event-status-badge.ended,
        .event-status-badge.completed {
            background: rgba(108, 117, 125, 0.9);
        }
        
        .event-content {
            padding: 1.5rem;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color, #333);
            margin: 0;
            line-height: 1.3;
            flex: 1;
            margin-right: 1rem;
        }
        
        .event-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--text-muted, #6c757d);
            font-size: 0.875rem;
            white-space: nowrap;
        }
        
        .event-description {
            color: var(--text-muted, #6c757d);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        
        .event-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color, #007bff);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted, #6c757d);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.25rem;
        }
        
        .stat-organizer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--text-muted, #6c757d);
            font-size: 0.875rem;
        }
        
        .event-actions {
            padding: 0 1.5rem 1.5rem;
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-vote {
            flex: 1;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-vote:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-results {
            flex: 1;
            background: var(--card-bg, white);
            color: var(--primary-color, #007bff);
            border: 2px solid var(--primary-color, #007bff);
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-results:hover {
            background: var(--primary-color, #007bff);
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .event-status-text {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--text-muted, #6c757d);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.875rem 1.5rem;
            flex: 1;
        }
        
        /* Dark mode support */
        [data-theme="dark"] .modern-card {
            --card-bg: #1a1d23;
            --text-color: #e9ecef;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #0d6efd;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .event-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .event-title {
                margin-right: 0;
            }
            
            .event-actions {
                flex-direction: column;
            }
        }
        
        /* Navigation Menu Spacing Fix */
        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 1rem; /* Reduced from excessive spacing */
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: var(--text-primary);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .nav-link i {
            font-size: 0.875rem;
        }
        
        .nav-link span {
            font-size: 0.875rem;
        }
        
        /* Uniform Button Sizing Fix */
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .navbar-actions .btn,
        .navbar-actions .theme-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: auto;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }
        
        .theme-toggle {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .theme-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .mobile-menu-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem;
                background: none;
                border: none;
                color: var(--text-primary);
                font-size: 1.25rem;
                cursor: pointer;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="voter-header">
        <nav class="navbar">
            <div class="container">
                <div class="navbar-brand">
                    <a href="index.php" class="brand-link">
                        <?php echo SiteSettings::getLogoOnlyHtml('brand-logo', true); ?>
                    </a>
                </div>
                
                <div class="navbar-menu">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    <a href="events.php" class="nav-link active">
                        <i class="fas fa-vote-yea"></i>
                        <span>Vote Now</span>
                    </a>
                    <a href="results.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Results</span>
                    </a>
                    <a href="how-to-vote.php" class="nav-link">
                        <i class="fas fa-question-circle"></i>
                        <span>How to Vote</span>
                    </a>
                </div>
                
                <div class="navbar-actions">
                    <!-- Login and Sign Up buttons hidden from voters -->
                    <!-- Admin/Organizer Login: ../login.php -->
                    <!-- Organizer Sign Up: organizer-signup.php -->
                    
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="voter-main">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1 class="hero-title">
                            <i class="fas fa-calendar-star"></i>
                            Discover Events
                        </h1>
                        <p class="hero-subtitle">
                            Explore and participate in exciting voting events. Your voice matters in shaping the future.
                        </p>
                        
                        <!-- Search Bar -->
                        <div class="hero-search">
                            <form method="GET" class="search-form">
                                <div class="search-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" name="search" placeholder="Search events, organizers, categories..." 
                                           value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                                    <button type="submit" class="search-btn">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section removed as requested -->

        <!-- Filter Tabs Section removed as requested -->

        <!-- Events Section -->
        <section class="events-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        <?php 
                        switch($status_filter) {
                            case 'active': echo 'Active Events'; break;
                            case 'upcoming': echo 'Upcoming Events'; break;
                            case 'completed': echo 'Completed Events'; break;
                            default: echo 'All Events'; break;
                        }
                        ?>
                    </h2>
                    <p class="section-subtitle">
                        <?php if ($search): ?>
                            Search results for "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Browse and participate in voting events
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="events-grid">
                    <?php if (empty($events)): ?>
                        <div class="no-events">
                            <div class="no-events-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3>No Events Found</h3>
                            <p>
                                <?php if ($search): ?>
                                    No events match your search criteria. Try different keywords.
                                <?php else: ?>
                                    There are currently no <?php echo $status_filter === 'all' ? '' : $status_filter; ?> events.
                                <?php endif; ?>
                            </p>
                            <?php if ($search || $status_filter !== 'all'): ?>
                                <a href="events.php" class="btn btn-primary">
                                    <i class="fas fa-refresh"></i>
                                    View All Events
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $start_date = new DateTime($event['start_date']);
                            $end_date = new DateTime($event['end_date']);
                            $now = new DateTime();
                            
                            if ($event['status'] === 'active') {
                                $days_left = $end_date->diff($now)->days;
                                $time_info = $days_left . ' days left';
                                $time_icon = 'fa-clock';
                            } elseif ($event['status'] === 'upcoming') {
                                $days_until = $start_date->diff($now)->days;
                                $time_info = 'Starts in ' . $days_until . ' days';
                                $time_icon = 'fa-calendar-plus';
                            } else {
                                $time_info = 'Ended ' . $end_date->format('M j, Y');
                                $time_icon = 'fa-calendar-check';
                            }
                            ?>
                            <div class="event-card modern-card">
                                <!-- Event Image -->
                                <div class="event-image">
                                    <?php if (!empty($event['logo']) && file_exists(__DIR__ . '/../uploads/events/' . $event['logo'])): ?>
                                        <img src="../uploads/events/<?php echo htmlspecialchars($event['logo']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="card-image">
                                    <?php else: ?>
                                        <div class="card-image-placeholder">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Event Image</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Event Status Badge -->
                                    <div class="event-status-badge <?php echo $event['status']; ?>">
                                        <i class="fas <?php echo $time_icon; ?>"></i>
                                        <span><?php echo ucfirst($event['status']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Event Content -->
                                <div class="event-content">
                                    <div class="event-header">
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <div class="event-time">
                                            <i class="fas <?php echo $time_icon; ?>"></i>
                                            <span><?php echo $time_info; ?></span>
                                        </div>
                                    </div>
                                    
                                    <p class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 120)) . '...'; ?></p>
                                    
                                    <!-- Removed category analytics, vote analytics, and organizer name -->
                                    <div class="event-stats">
                                        <!-- Event stats removed as requested -->
                                    </div>
                                </div>
                                
                                <!-- Event Actions -->
                                <div class="event-actions">
                                    <?php if ($event['status'] === 'active'): ?>
                                        <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-vote">
                                            <i class="fas fa-vote-yea"></i>
                                            <span>Vote Now</span>
                                        </a>
                                        <!-- Live Results button removed as requested -->
                                    <?php elseif ($event['status'] === 'upcoming'): ?>
                                        <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-results">
                                            <i class="fas fa-eye"></i>
                                            <span>Preview Event</span>
                                        </a>
                                        <div class="event-status-text">
                                            <i class="fas fa-clock"></i>
                                            <span>Coming Soon</span>
                                        </div>
                                    <?php else: ?>
                                        <a href="results.php?event=<?php echo $event['id']; ?>" class="btn btn-vote">
                                            <i class="fas fa-trophy"></i>
                                            <span>View Results</span>
                                        </a>
                                        <div class="event-status-text">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Completed</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="voter-footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SiteSettings::getSiteName()); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Events page specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const navbarMenu = document.querySelector('.navbar-menu');
            
            if (mobileMenuToggle && navbarMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navbarMenu.classList.toggle('show');
                });
            }
            
            // Search form enhancement
            const searchForm = document.querySelector('.search-form');
            const searchInput = document.querySelector('.search-input');
            
            if (searchForm && searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchForm.submit();
                    }
                });
            }
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>

    <style>
        /* Voter Dashboard Specific Styles - Matching Home Page Exactly */
        .voter-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar {
            padding: 1rem 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .navbar-brand .brand-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .navbar-menu .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .navbar-menu .nav-link:hover,
        .navbar-menu .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.25rem;
            cursor: pointer;
        }
        
        /* Hero Section - Exact Home Page Style */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 4rem 0;
        }
        
        .hero-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .hero-image {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-icon {
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            backdrop-filter: blur(10px);
        }
        
        /* Stats Section - Exact Home Page Style */
        .stats-section {
            padding: 4rem 0;
            background: var(--bg-secondary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .stat-card {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Events Section - Exact Home Page Style */
        .events-section {
            padding: 4rem 0;
            background: var(--bg-primary);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        
        .section-title i {
            color: var(--primary-color);
        }
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .event-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .event-header {
            margin-bottom: 1rem;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .event-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .event-status.status-active {
            background: rgba(34, 197, 94, 0.1);
            color: #059669;
        }
        
        .event-status.status-upcoming {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .event-status.status-completed {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .event-body {
            margin-bottom: 1.5rem;
        }
        
        .event-description {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .event-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .event-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .event-stat i {
            color: var(--primary-color);
        }
        
        .event-footer {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }
        
        .no-events {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
        }
        
        .no-events-icon {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .no-events h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .no-events p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .section-footer {
            text-align: center;
            margin-top: 3rem;
        }
        
        /* Footer - Exact Home Page Style */
        .voter-footer {
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            padding: 3rem 0 1rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }
        
        .footer-brand .brand-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .footer-brand p {
            color: var(--text-secondary);
        }
        
        .footer-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
        }
        
        .footer-section h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .footer-section ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-section ul li {
            margin-bottom: 0.5rem;
        }
        
        .footer-section ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .footer-section ul li a:hover {
            color: var(--primary-color);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .hero-section {
                padding: 6rem 0 4rem 0; /* Added extra top padding for mobile */
            }
            
            .navbar-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg-primary);
                border-top: 1px solid var(--border-color);
                flex-direction: column;
                gap: 0;
                padding: 1rem;
            }
            
            .navbar-menu.show {
                display: flex;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }
            
            .hero-text {
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
                justify-content: center;
            }
            
            .hero-subtitle {
                text-align: center;
            }
            
            .hero-icon {
                width: 150px;
                height: 150px;
                font-size: 3rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .event-footer {
                flex-direction: column;
            }
        }
    </style>



    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Voter dashboard specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const navbarMenu = document.querySelector('.navbar-menu');
            
            if (mobileMenuToggle && navbarMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navbarMenu.classList.toggle('show');
                });
            }
            

        });
    </script>
</body>
</html>
