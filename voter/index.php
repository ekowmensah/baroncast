<?php
// No authentication required for voter dashboard
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Fetch all active events
try {
    $query = "SELECT e.*, u.full_name as organizer_name, 
              COUNT(DISTINCT c.id) as category_count,
              COUNT(DISTINCT v.id) as total_votes,
              COUNT(DISTINCT bvp.id) as package_count
              FROM events e 
              LEFT JOIN users u ON e.organizer_id = u.id 
              LEFT JOIN categories c ON e.id = c.event_id 
              LEFT JOIN votes v ON e.id = v.event_id 
              LEFT JOIN bulk_vote_packages bvp ON e.id = bvp.event_id AND bvp.status = 'active'
              WHERE e.status = 'active'
              GROUP BY e.id 
              ORDER BY e.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $active_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_events = [];
}

// Fetch platform stats
try {
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM votes) as total_votes,
                    (SELECT COUNT(*) FROM events WHERE status = 'active') as active_events,
                    (SELECT COUNT(*) FROM events WHERE status IN ('ended', 'completed')) as completed_events,
                    (SELECT COUNT(DISTINCT voter_phone) FROM votes) as total_participants";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_votes' => 0, 'active_events' => 0, 'completed_events' => 0, 'total_participants' => 0];
}
$siteSettings = new SiteSettings();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteSettings->getSiteName()); ?> - Home</title>
    <meta name="description" content="<?php echo htmlspecialchars($siteSettings->getSiteDescription()); ?>">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#007bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($siteSettings->getSiteName()); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="../assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="../assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="../assets/icons/icon-512x512.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Modern Event Card Styles */
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
            background: rgba(40, 167, 69, 0.9);
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
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
                    <a href="index.php" class="nav-link active">
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
                            <i class="fas fa-vote-yea"></i>
                            Welcome to <?php echo htmlspecialchars(SiteSettings::getSiteName()); ?>
                        </h1>
                        <p class="hero-subtitle">
                            <?php echo htmlspecialchars(SiteSettings::getSiteDescription()); ?>
                        </p>
                        <div class="hero-actions">
                            <a href="events.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-vote-yea"></i>
                                Browse Active Events
                            </a>
                            <a href="results.php" class="btn btn-outline btn-lg">
                                <i class="fas fa-chart-bar"></i>
                                View Results
                            </a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <!-- Removed Total Votes Cast and Total Participants cards -->
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?php echo $stats['active_events']; ?></h3>
                            <p class="stat-label">Active Events</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?php echo $stats['completed_events']; ?></h3>
                            <p class="stat-label">Completed Events</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Active Events Section -->
        <section class="events-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-fire"></i>
                        Active Events
                    </h2>
                    <p class="section-subtitle">Vote now in these ongoing events</p>
                </div>
                
                <div class="events-grid">
                    <?php if (empty($active_events)): ?>
                        <div class="no-events">
                            <div class="no-events-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3>No Active Events</h3>
                            <p>There are currently no active voting events. Check back soon!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_events as $event): ?>
                            <?php
                            $end_date = new DateTime($event['end_date']);
                            $now = new DateTime();
                            $days_left = $end_date->diff($now)->days;
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
                                    <div class="event-status-badge active">
                                        <i class="fas fa-fire"></i>
                                        <span>Active</span>
                                    </div>
                                </div>
                                
                                <!-- Event Content -->
                                <div class="event-content">
                                    <div class="event-header">
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <div class="event-time">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo $days_left; ?> days left</span>
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
                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-vote">
                                        <i class="fas fa-vote-yea"></i>
                                        <span>Vote Now</span>
                                    </a>
                                    <a href="results.php?event=<?php echo $event['id']; ?>" class="btn btn-results">
                                        <i class="fas fa-chart-bar"></i>
                                        View Results
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($active_events)): ?>
                    <div class="section-footer">
                        <a href="events.php" class="btn btn-outline btn-lg">
                            <i class="fas fa-arrow-right"></i>
                            View All Events
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">How It Works</h2>
                    <p class="section-subtitle">Simple steps to cast your vote</p>
                </div>
                
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="step-title">Browse Events</h3>
                        <p class="step-description">Explore active voting events and find your favorites</p>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-icon">
                            <i class="fas fa-hand-pointer"></i>
                        </div>
                        <h3 class="step-title">Select Nominees</h3>
                        <p class="step-description">Choose your preferred nominees in different categories</p>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3 class="step-title">Make Payment</h3>
                        <p class="step-description">Pay securely using Mobile Money, Card, or USSD</p>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="step-title">Vote Confirmed</h3>
                        <p class="step-description">Your vote is counted and you receive a confirmation</p>
                    </div>
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

    <!-- Sign Up Modal -->
    <div id="signup-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Sign Up</h2>
                <button class="modal-close" id="signup-modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="modal-subtitle">Choose your account type to get started</p>
                
                <div class="signup-options">
                    <div class="signup-option" id="organizer-option">
                        <div class="option-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>Event Organizer</h3>
                        <p>Create and manage voting events, collect payments, and track results.</p>
                        <div class="option-features">
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>Create unlimited events</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>Payment management</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>Real-time analytics</span>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-block" onclick="window.location.href='organizer-signup.php'">
                            <i class="fas fa-arrow-right"></i>
                            Sign Up as Organizer
                        </button>
                        <p class="option-note"><i class="fas fa-gift"></i> Free Account Creation</p>
                    </div>
                    
                    <div class="signup-option" id="nominee-option">
                        <div class="option-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3>Self Nominee</h3>
                        <p>Nominate yourself for existing events without creating an account.</p>
                        <div class="option-features">
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>No account required</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>Quick nomination process</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check"></i>
                                <span>SMS verification</span>
                            </div>
                        </div>
                        <button class="btn btn-outline btn-block" onclick="window.location.href='self-nomination.php'">
                            <i class="fas fa-hand-point-right"></i>
                            Self Nominate
                        </button>
                        <p class="option-note"><i class="fas fa-info-circle"></i> Public nomination form</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            
            // Sign Up Modal functionality
            window.openSignUpModal = function() {
                const signupModal = document.getElementById('signup-modal');
                if (signupModal) {
                    signupModal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }
            };
            
            window.closeSignUpModal = function() {
                const signupModal = document.getElementById('signup-modal');
                if (signupModal) {
                    signupModal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                }
            };
            
            const signupModal = document.getElementById('signup-modal');
            const signupModalClose = document.getElementById('signup-modal-close');
            
            if (signupModalClose && signupModal) {
                signupModalClose.addEventListener('click', function() {
                    closeSignUpModal();
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
    
    // Theme management
    document.addEventListener('DOMContentLoaded', function() {
        // Get saved theme from localStorage or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        
        // Apply the saved theme
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Update toggle button text
        updateToggleText(savedTheme);
        
        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                // Apply new theme
                document.documentElement.setAttribute('data-theme', newTheme);
                
                // Save to localStorage
                localStorage.setItem('theme', newTheme);
                
                // Update toggle text
                updateToggleText(newTheme);
            });
        }
        
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../sw.js')
                .then(registration => {
                    console.log('PWA Service Worker registered successfully:', registration);
                    
                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Show update notification
                                showUpdateNotification();
                            }
                        });
                    });
                })
                .catch(error => {
                    console.log('PWA Service Worker registration failed:', error);
                });
        }
        
        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            showInstallButton();
        });
    });
    
    // PWA Helper Functions
    function showInstallButton() {
        const installBtn = document.createElement('button');
        installBtn.className = 'btn btn-primary install-btn';
        installBtn.innerHTML = '<i class="fas fa-download"></i> Install App';
        installBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1000;';
        
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to install prompt: ${outcome}`);
                deferredPrompt = null;
                installBtn.remove();
            }
        });
        
        document.body.appendChild(installBtn);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (installBtn.parentNode) {
                installBtn.remove();
            }
        }, 10000);
    }
    
    function showUpdateNotification() {
        const updateNotification = document.createElement('div');
        updateNotification.className = 'alert alert-info update-notification';
        updateNotification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1001; max-width: 300px;';
        updateNotification.innerHTML = `
            <strong>Update Available!</strong><br>
            A new version is ready.
            <button class="btn btn-sm btn-primary ms-2" onclick="window.location.reload()">Update</button>
        `;
        
        document.body.appendChild(updateNotification);
        
        // Auto-hide after 15 seconds
        setTimeout(() => {
            if (updateNotification.parentNode) {
                updateNotification.remove();
            }
        }, 15000);
    }
    </script>

    <style>
        /* Voter Dashboard Specific Styles */
        .voter-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        /* Brand Logo Styles */
        .brand-logo {
            height: 32px;
            width: auto;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .brand-link {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .brand-text {
            font-weight: 600;
        }
        
        .footer-brand .brand-logo {
            height: 28px;
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
        
        /* Hero Section */
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
            font-size: 4rem;
        }
        
        /* Stats Section */
        .stats-section {
            padding: 3rem 0;
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
            border-radius: 0.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Events Section */
        .events-section {
            padding: 4rem 0;
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
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .event-card {
            background: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .event-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .event-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .event-body {
            padding: 1.5rem;
        }
        
        .event-description {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .event-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .event-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .event-footer {
            padding: 1.5rem;
            background: var(--bg-secondary);
            display: flex;
            gap: 1rem;
        }
        
        .no-events {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .no-events-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* How It Works Section */
        .how-it-works-section {
            padding: 4rem 0;
            background: var(--bg-secondary);
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .step-card {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            position: relative;
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .step-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .step-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .step-description {
            color: var(--text-secondary);
        }
        
        /* Footer */
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: modalFadeIn 0.3s ease;
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 1rem;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            animation: modalSlideIn 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        
        .modal-header h2 i {
            color: var(--primary-color);
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.125rem;
        }
        
        .signup-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .signup-option {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .signup-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .signup-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .signup-option:hover::before {
            opacity: 1;
        }
        
        .option-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .signup-option:hover .option-icon {
            transform: scale(1.1);
            background: var(--primary-dark);
        }
        
        .signup-option h3 {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .signup-option > p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .option-features {
            margin-bottom: 2rem;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .feature i {
            color: #22c55e;
            font-size: 0.875rem;
        }
        
        .option-note {
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .option-note i {
            color: var(--primary-color);
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* Mobile Modal Styles */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                max-height: 95vh;
            }
            
            .modal-header,
            .modal-body {
                padding: 1.5rem;
            }
            
            .signup-options {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
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
                padding: 1rem;
                gap: 0;
            }
            
            .navbar-menu.show {
                display: flex;
            }
            
            .navbar-menu .nav-link {
                width: 100%;
                justify-content: flex-start;
                margin-bottom: 0.5rem;
            }
            
            .hero-section {
                padding: 6rem 0 4rem 0; /* Added extra top padding for mobile */
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
            
            .hero-actions {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
