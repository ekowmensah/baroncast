<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$selectedCategory = $_GET['category'] ?? '';
$selectedEvent = $_GET['event'] ?? '';

// Fetch all categories for dropdown
$allCategories = [];
try {
    $catQuery = "SELECT DISTINCT c.*, c.event_id, e.title as event_title 
                 FROM categories c 
                 LEFT JOIN events e ON c.event_id = e.id 
                 WHERE e.status IN ('active', 'upcoming', 'completed')
                 ORDER BY e.title, c.name";
    $catStmt = $db->prepare($catQuery);
    $catStmt->execute();
    $allCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $allCategories = [];
}

// Fetch events with results (filtered if needed)
try {
    $whereConditions = ["e.status IN ('active', 'upcoming', 'completed')"];
    $params = [];
    
    if ($selectedEvent) {
        $whereConditions[] = "e.id = ?";
        $params[] = $selectedEvent;
    } elseif ($selectedCategory) {
        $whereConditions[] = "EXISTS (SELECT 1 FROM categories c WHERE c.event_id = e.id AND c.id = ?)";
        $params[] = $selectedCategory;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT e.*, u.full_name as organizer_name,
              COUNT(DISTINCT c.id) as category_count,
              COUNT(DISTINCT v.id) as total_votes
              FROM events e 
              LEFT JOIN users u ON e.organizer_id = u.id 
              LEFT JOIN categories c ON e.id = c.event_id 
              LEFT JOIN votes v ON e.id = v.event_id 
              WHERE {$whereClause}
              GROUP BY e.id 
              ORDER BY e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events = [];
}

// Fetch detailed nominee results for each event
$eventResults = [];
foreach ($events as $event) {
    try {
        // Get categories for this event
        $categoryQuery = "SELECT * FROM categories WHERE event_id = ? ORDER BY name";
        $categoryStmt = $db->prepare($categoryQuery);
        $categoryStmt->execute([$event['id']]);
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $eventResults[$event['id']] = [
            'event' => $event,
            'categories' => []
        ];
        
        foreach ($categories as $category) {
            // Get nominees with vote counts for this category
            $nomineeQuery = "SELECT n.*, 
                            COUNT(v.id) as vote_count,
                            ROUND((COUNT(v.id) * 100.0 / NULLIF(total_votes.total, 0)), 2) as vote_percentage
                            FROM nominees n 
                            LEFT JOIN votes v ON n.id = v.nominee_id 
                            LEFT JOIN (
                                SELECT c.id as category_id, COUNT(v2.id) as total
                                FROM categories c
                                LEFT JOIN nominees n2 ON c.id = n2.category_id
                                LEFT JOIN votes v2 ON n2.id = v2.nominee_id
                                WHERE c.id = ?
                                GROUP BY c.id
                            ) total_votes ON n.category_id = total_votes.category_id
                            WHERE n.category_id = ? 
                            GROUP BY n.id 
                            ORDER BY vote_count DESC, n.name ASC";
            $nomineeStmt = $db->prepare($nomineeQuery);
            $nomineeStmt->execute([$category['id'], $category['id']]);
            $nominees = $nomineeStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $eventResults[$event['id']]['categories'][$category['id']] = [
                'category' => $category,
                'nominees' => $nominees
            ];
        }
    } catch (PDOException $e) {
        $eventResults[$event['id']] = [
            'event' => $event,
            'categories' => []
        ];
    }
}

// Fetch stats
try {
    $stats_query = "SELECT 
                    COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_count,
                    COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_count,
                    COUNT(*) as total_events,
                    COALESCE(SUM(vote_counts.total_votes), 0) as total_votes
                    FROM events e
                    LEFT JOIN (
                        SELECT event_id, COUNT(*) as total_votes 
                        FROM votes 
                        GROUP BY event_id
                    ) vote_counts ON e.id = vote_counts.event_id
                    WHERE e.status IN ('active', 'upcoming', 'completed')";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['active_count' => 0, 'completed_count' => 0, 'total_events' => 0, 'total_votes' => 0];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?php echo htmlspecialchars(SiteSettings::getSiteName()); ?></title>
    <meta name="description" content="View voting results for ongoing and completed events. <?php echo htmlspecialchars(SiteSettings::getSiteDescription()); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-vote-yea"></i>
                        <span>Vote Now</span>
                    </a>
                    <a href="results.php" class="nav-link active">
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
                            <i class="fas fa-chart-bar"></i>
                            Voting Results
                        </h1>
                        <p class="hero-subtitle">
                            View transparent and real-time voting results for ongoing and completed events.
                        </p>
                        <div class="hero-actions">
                            <a href="events.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-vote-yea"></i>
                                Vote in Active Events
                            </a>
                            <a href="#results" class="btn btn-outline btn-lg">
                                <i class="fas fa-chart-line"></i>
                                View All Results
                            </a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        <!-- Results Section -->
        <section class="events-section" id="results">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Welcome To View Votes Section
                    </h2>
                    <p class="section-subtitle">
                        Select Scheme To View list Of All Nominees Votes OR Select Scheme AND Category To View Particular Category Votes.
                    </p>
                </div>
                
                <!-- New Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="results.php" class="filter-form" id="resultsForm">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="schemeFilter" class="filter-label">Scheme:</label>
                                <select id="schemeFilter" name="event" class="filter-select">
                                    <option value="">Select Event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>" 
                                                <?php echo $selectedEvent == $event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="categoryFilter" class="filter-label">Categories:</label>
                                <select id="categoryFilter" name="category" class="filter-select">
                                    <option value="">Select Event First</option>
                                    <?php if ($selectedEvent): ?>
                                        <option value="">All Categories</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <?php if ($category['event_id'] == $selectedEvent): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo $selectedCategory == $category['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </form>

                </div>
                
                <!-- Results Table -->
                <div class="results-table-container">
                    <?php if (!$selectedEvent): ?>
                        <div class="no-selection">
                            <p>No data available, please select event</p>
                        </div>
                    <?php elseif ($selectedEvent && !$selectedCategory): ?>
                        <!-- Show Event Schemes and Total Votes -->
                        <?php
                        // Get all categories for the selected event with their total votes
                        $categoryVotesQuery = "SELECT c.*, c.name as category_name, COUNT(v.id) as total_votes
                                              FROM categories c
                                              LEFT JOIN nominees n ON c.id = n.category_id
                                              LEFT JOIN votes v ON n.id = v.nominee_id
                                              WHERE c.event_id = ?
                                              GROUP BY c.id
                                              ORDER BY total_votes DESC, c.name ASC";
                        $categoryVotesStmt = $db->prepare($categoryVotesQuery);
                        $categoryVotesStmt->execute([$selectedEvent]);
                        $categoryVotes = $categoryVotesStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Total Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categoryVotes)): ?>
                                        <tr>
                                            <td colspan="2" style="text-align: center;">No categories found for this event</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categoryVotes as $category): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                                <td><?php echo number_format($category['total_votes']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($selectedEvent && $selectedCategory): ?>
                        <!-- Show Nominees and Their Votes for Selected Category -->
                        <?php
                        // Get all nominees for the selected category with their vote counts
                        $nomineeVotesQuery = "SELECT n.*, COUNT(v.id) as vote_count
                                             FROM nominees n
                                             LEFT JOIN votes v ON n.id = v.nominee_id
                                             WHERE n.category_id = ?
                                             GROUP BY n.id
                                             ORDER BY vote_count DESC, n.name ASC";
                        $nomineeVotesStmt = $db->prepare($nomineeVotesQuery);
                        $nomineeVotesStmt->execute([$selectedCategory]);
                        $nomineeVotes = $nomineeVotesStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div class="results-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Total Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($nomineeVotes)): ?>
                                        <tr>
                                            <td colspan="2" style="text-align: center;">No nominees found for this category</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($nomineeVotes as $nominee): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($nominee['name']); ?>
                                                    <?php if (!empty($nominee['code'])): ?>
                                                        - <?php echo htmlspecialchars($nominee['code']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($nominee['vote_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const navbarMenu = document.querySelector('.navbar-menu');
            
            if (mobileMenuToggle && navbarMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navbarMenu.classList.toggle('show');
                });
            }
            
            // Handle scheme/category selection
            const schemeSelect = document.getElementById('schemeFilter');
            const categorySelect = document.getElementById('categoryFilter');
            
            if (schemeSelect && categorySelect) {
                schemeSelect.addEventListener('change', function() {
                    const selectedEvent = this.value;
                    
                    if (selectedEvent) {
                        // Auto-submit form when scheme is selected
                        document.getElementById('resultsForm').submit();
                    } else {
                        // Reset category dropdown
                        categorySelect.innerHTML = '<option value="">Select Event First</option>';
                    }
                });
                
                categorySelect.addEventListener('change', function() {
                    // Auto-submit form when category is selected
                    if (schemeSelect.value) {
                        document.getElementById('resultsForm').submit();
                    }
                });
            }
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
            align-items: center;
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
        
        /* Hero Section Button Overrides for Better Visibility */
        .hero-section .btn-outline {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            font-weight: 600;
        }
        
        .hero-section .btn-outline:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
            border-color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
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
        
        /* Nominee Results Styles */
        .results-container {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }
        
        .event-results-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .event-results-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .event-info .event-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .event-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .event-meta > div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .event-meta i {
            color: var(--primary-color);
        }
        
        .categories-results {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }
        
        .category-results {
            background: var(--bg-primary);
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }
        
        .category-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .category-title i {
            color: var(--primary-color);
        }
        
        .nominees-results {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .nominee-result {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }
        
        .nominee-result:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .nominee-result.winner {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
            border-color: #ffc107;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
        }
        
        .nominee-position {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .nominee-result.winner .nominee-position {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: white;
        }
        
        .nominee-result.winner .nominee-position i {
            font-size: 1.2rem;
        }
        
        .nominee-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }
        
        .nominee-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .nominee-photo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 1.5rem;
            border: 2px solid var(--border-color);
        }
        
        .nominee-details {
            flex: 1;
        }
        
        .nominee-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .nominee-bio {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 0;
        }
        
        .nominee-stats {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-shrink: 0;
        }
        
        .vote-count {
            text-align: center;
        }
        
        .vote-count .count {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .vote-count .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .vote-percentage {
            text-align: center;
            min-width: 80px;
        }
        
        .vote-percentage .percentage {
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .progress-bar {
            width: 80px;
            height: 6px;
            background: var(--bg-tertiary);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color) 0%, #3b82f6 100%);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .nominee-result.winner .progress-fill {
            background: linear-gradient(90deg, #ffc107 0%, #ffb300 100%);
        }
        
        .no-categories,
        .no-nominees {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-style: italic;
        }

        /* Results Table Styles */
        .results-table-container {
            margin-top: 2rem;
        }
        
        .filter-section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .filter-label {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-size: 1rem;
            letter-spacing: 0.025em;
        }
        
        .filter-select {
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23666" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 3rem;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-1px);
        }
        
        .filter-select:hover {
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .filter-select option {
            padding: 0.75rem;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .results-table {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }
        
        .results-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-size: 1rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            border: none;
        }
        
        .results-table th:first-child {
            border-top-left-radius: 12px;
        }
        
        .results-table th:last-child {
            border-top-right-radius: 12px;
        }
        
        .results-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .results-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .results-table tbody tr:hover {
            background: var(--bg-secondary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .results-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tbody tr:nth-child(even) {
            background: rgba(0, 0, 0, 0.02);
        }
        
        .results-table tbody tr:nth-child(even):hover {
            background: var(--bg-secondary);
        }
        
        .results-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: var(--primary-color);
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
            
            .event-results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .event-meta {
                gap: 1rem;
            }
            
            .nominee-result {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .nominee-info {
                flex-direction: column;
                text-align: center;
            }
            
            .nominee-stats {
                justify-content: center;
                gap: 2rem;
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
                text-align: center;
            }
            
            .hero-actions {
                flex-direction: column;
                gap: 1rem;
            }
            
            .hero-actions .btn {
                flex: 1;
                min-width: 140px;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .filter-section {
                padding: 1.5rem;
            }
            
            .filter-select {
                padding: 0.875rem 1rem;
                font-size: 0.95rem;
            }
            
            .results-table {
                overflow-x: auto;
            }
            
            .results-table table {
                min-width: 400px;
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
