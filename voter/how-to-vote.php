<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How to Vote - <?php echo htmlspecialchars(SiteSettings::getSiteName()); ?></title>
    <meta name="description" content="Learn how to vote in events. <?php echo htmlspecialchars(SiteSettings::getSiteDescription()); ?>">
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
                    <a href="results.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Results</span>
                    </a>
                    <a href="how-to-vote.php" class="nav-link active">
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
                            <i class="fas fa-question-circle"></i>
                            How to Vote
                        </h1>
                        <p class="hero-subtitle">
                            Learn how to participate in voting events with our simple step-by-step guide.
                        </p>
                        <div class="hero-actions">
                            <a href="events.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-vote-yea"></i>
                                Start Voting
                            </a>
                            <a href="#guide" class="btn btn-outline btn-lg">
                                <i class="fas fa-arrow-down"></i>
                                Read Guide
                            </a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-hand-pointer"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">1</h3>
                            <p class="stat-label">Browse Events</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hand-pointer"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">2</h3>
                            <p class="stat-label">Select Nominees</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">3</h3>
                            <p class="stat-label">Make Payment</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">4</h3>
                            <p class="stat-label">Vote Confirmed</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="events-section" id="guide">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list-ol"></i>
                        Voting Guide
                    </h2>
                    <p class="section-subtitle">
                        Follow these simple steps to cast your vote
                    </p>
                </div>
                
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="step-title">Browse Events</h3>
                        <p class="step-description">
                            Visit the Active Events page to explore ongoing voting events. 
                            You can see event details, categories, and nominees.
                        </p>
                        <a href="events.php" class="btn btn-outline">
                            <i class="fas fa-calendar-alt"></i>
                            Browse Events
                        </a>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-icon">
                            <i class="fas fa-hand-pointer"></i>
                        </div>
                        <h3 class="step-title">Select Nominees</h3>
                        <p class="step-description">
                            Choose your preferred nominees in each category. 
                            You can vote for one nominee per category in each event.
                        </p>
                        <div class="step-tip">
                            <i class="fas fa-lightbulb"></i>
                            <span>Tip: Review all nominees before making your selection</span>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3 class="step-title">Make Payment</h3>
                        <p class="step-description">
                            Complete your vote by making a secure payment. 
                            We accept Mobile Money, Credit/Debit Cards, and USSD payments.
                        </p>
                        <div class="payment-methods">
                            <span class="payment-method">
                                <i class="fas fa-mobile-alt"></i>
                                Mobile Money
                            </span>
                            <span class="payment-method">
                                <i class="fas fa-credit-card"></i>
                                Cards
                            </span>
                            <span class="payment-method">
                                <i class="fas fa-phone"></i>
                                USSD
                            </span>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="step-title">Vote Confirmed</h3>
                        <p class="step-description">
                            Once payment is successful, your vote is counted immediately. 
                            You'll receive a confirmation message with your vote details.
                        </p>
                        <div class="step-tip success">
                            <i class="fas fa-shield-alt"></i>
                            <span>Your vote is secure and anonymous</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="faq-section">
                    <h3 class="faq-title">
                        <i class="fas fa-question-circle"></i>
                        Frequently Asked Questions
                    </h3>
                    
                    <div class="faq-grid">
                        <div class="faq-item">
                            <h4>Can I change my vote after payment?</h4>
                            <p>No, votes are final once payment is confirmed. Please review your selections carefully before proceeding to payment.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>How much does it cost to vote?</h4>
                            <p>Voting costs vary by event and are set by the event organizer. The cost is clearly displayed before you make payment.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>Is my vote anonymous?</h4>
                            <p>Yes, all votes are completely anonymous. We only store your phone number for payment verification, not linked to your vote choices.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>Can I vote multiple times?</h4>
                            <p>You can vote once per category in each event. Multiple votes in the same category are not allowed.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>What if payment fails?</h4>
                            <p>If payment fails, your vote is not counted. You can try again with a different payment method or contact support.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>When can I see results?</h4>
                            <p>Results are available in real-time for active events and final results for completed events on the Results page.</p>
                        </div>
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
        
        /* How to Vote Page Specific Styles */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }
        
        .step-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .step-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
            font-size: 1rem;
        }
        
        .step-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin: 1rem 0;
        }
        
        .step-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .step-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .step-tip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        
        .step-tip.success {
            background: rgba(34, 197, 94, 0.1);
            color: #059669;
        }
        
        .payment-methods {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-secondary);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .faq-section {
            background: var(--bg-secondary);
            padding: 3rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
        }
        
        .faq-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .faq-title i {
            color: var(--primary-color);
        }
        
        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .faq-item {
            background: var(--bg-primary);
            padding: 1.5rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }
        
        .faq-item h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .faq-item p {
            color: var(--text-secondary);
            line-height: 1.6;
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
            
            .steps-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .payment-methods {
                flex-direction: column;
                align-items: center;
            }
            
            .faq-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
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
