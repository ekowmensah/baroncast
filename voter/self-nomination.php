<?php
require_once '../config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Fetch active events for self-nomination
try {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as organizer_name,
               COUNT(DISTINCT c.id) as category_count
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        LEFT JOIN categories c ON e.id = c.event_id 
        WHERE e.status = 'active' AND e.end_date > NOW()
        GROUP BY e.id 
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
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
    <title>Self Nomination - E-Cast Voting Platform</title>
    <meta name="description" content="Select an event to nominate yourself for participation.">
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
                        <i class="fas fa-vote-yea"></i>
                        <span>E-Cast</span>
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
                    <a href="login.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                    
                    <a href="index.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                    
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
                            <i class="fas fa-user-check"></i>
                            Self Nomination
                        </h1>
                        <p class="hero-subtitle">
                            Select an event below to nominate yourself. No account creation required - just fill out the form and get verified!
                        </p>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-hand-point-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Events Selection Section -->
        <section class="events-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        Available Events for Nomination
                    </h2>
                    <p class="section-subtitle">
                        Choose the event you want to participate in and complete your nomination
                    </p>
                </div>

                <div class="events-grid">
                    <?php if (empty($events)): ?>
                        <div class="no-events">
                            <div class="no-events-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3>No Active Events Available</h3>
                            <p>There are currently no active events accepting nominations. Please check back later.</p>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home"></i>
                                Go Home
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-card nomination-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                                    <span class="event-status status-active">
                                        <i class="fas fa-circle"></i>
                                        Accepting Nominations
                                    </span>
                                </div>
                                
                                <div class="event-body">
                                    <p class="event-description">
                                        <?= htmlspecialchars(substr($event['description'], 0, 120)) ?>...
                                    </p>
                                    
                                    <div class="event-stats">
                                        <div class="event-stat">
                                            <i class="fas fa-user"></i>
                                            <span>By <?= htmlspecialchars($event['organizer_name']) ?></span>
                                        </div>
                                        <div class="event-stat">
                                            <i class="fas fa-tags"></i>
                                            <span><?= $event['category_count'] ?> Categories</span>
                                        </div>
                                        <div class="event-stat">
                                            <i class="fas fa-calendar"></i>
                                            <span>Ends <?= date('M j, Y', strtotime($event['end_date'])) ?></span>
                                        </div>
                                        <div class="event-stat">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>GH₵<?= number_format($event['vote_price'], 2) ?> per vote</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="event-footer">
                                    <a href="nomination-form.php?event_id=<?= $event['id'] ?>" class="btn btn-primary btn-block">
                                        <i class="fas fa-hand-point-right"></i>
                                        Nominate Yourself
                                    </a>
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
            <div class="footer-content">
                <div class="footer-brand">
                    <a href="index.php" class="brand-link">
                        <i class="fas fa-vote-yea"></i>
                        <span>E-Cast</span>
                    </a>
                    <p>Transparent, secure, and accessible voting for everyone. Making democracy digital.</p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-section">
                        <h4>Platform</h4>
                        <ul>
                            <li><a href="events.php">Vote Now</a></li>
                            <li><a href="results.php">Results</a></li>
                            <li><a href="how-to-vote.php">How to Vote</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h4>Account</h4>
                        <ul>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="organizer-signup.php">Organizer Signup</a></li>
                            <li><a href="self-nomination.php">Self Nominate</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h4>Support</h4>
                        <ul>
                            <li><a href="#help">Help Center</a></li>
                            <li><a href="#contact">Contact Us</a></li>
                            <li><a href="#terms">Terms & Conditions</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 E-Cast Voting Platform. All rights reserved.</p>
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-block {
            width: 100%;
            text-align: center;
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
        
        /* Self Nomination Page Specific Styles */
        .nomination-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .nomination-card:hover {
            border-left-color: var(--primary-dark);
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
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .event-footer {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
