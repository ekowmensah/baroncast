<?php
require_once '../config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$event_id) {
    header('Location: self-nomination.php');
    exit;
}

// Fetch event details
try {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as organizer_name
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header('Location: self-nomination.php');
        exit;
    }
    
} catch (PDOException $e) {
    header('Location: self-nomination.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomination Submitted - E-Cast Voting Platform</title>
    <meta name="description" content="Your nomination has been submitted successfully for processing.">
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
                    <a href="index.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
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
        <!-- Success Section -->
        <section class="success-section">
            <div class="container">
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <h1 class="success-title">
                        Submitted Successfully For Processing!
                    </h1>
                    
                    <div class="success-message">
                        <p class="success-text">
                            You will receive an SMS after you have been verified successfully by the event organizers.
                        </p>
                        
                        <p class="success-thanks">
                            Thank you for being part of us.
                        </p>
                    </div>
                    
                    <div class="event-info">
                        <div class="event-card success-event-card">
                            <div class="event-header">
                                <h3 class="event-title">
                                    <i class="fas fa-calendar-check"></i>
                                    <?= htmlspecialchars($event['title']) ?>
                                </h3>
                                <span class="event-status status-pending">
                                    <i class="fas fa-clock"></i>
                                    Pending Verification
                                </span>
                            </div>
                            
                            <div class="event-body">
                                <div class="event-stats">
                                    <div class="event-stat">
                                        <i class="fas fa-user"></i>
                                        <span>Organized by <?= htmlspecialchars($event['organizer_name']) ?></span>
                                    </div>
                                    <div class="event-stat">
                                        <i class="fas fa-calendar"></i>
                                        <span>Event ends <?= date('M j, Y', strtotime($event['end_date'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="success-actions">
                        <a href="self-nomination.php" class="btn btn-outline btn-lg">
                            <i class="fas fa-plus"></i>
                            Nominate for Another Event
                        </a>
                        
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i>
                            Go to Home
                        </a>
                    </div>
                    
                    <div class="next-steps">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            What happens next?
                        </h3>
                        <div class="steps-list">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>Verification Process</h4>
                                    <p>Event organizers will review your nomination and verify your details</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>SMS Notification</h4>
                                    <p>You'll receive an SMS confirmation once your nomination is approved</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>Voting Begins</h4>
                                    <p>People can vote for you once the voting period starts</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
                    <p>Transparent, secure, and accessible voting for everyone.</p>
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
            
            // Add celebration animation
            setTimeout(() => {
                const successIcon = document.querySelector('.success-icon');
                if (successIcon) {
                    successIcon.classList.add('celebrate');
                }
            }, 500);
        });
    </script>

    <style>
        /* Success Page Styles */
        .success-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            min-height: 80vh;
            display: flex;
            align-items: center;
        }
        
        .success-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            box-shadow: 0 20px 60px rgba(34, 197, 94, 0.3);
            animation: successPulse 2s ease-in-out infinite;
        }
        
        .success-icon.celebrate {
            animation: successCelebrate 0.6s ease-in-out;
        }
        
        .success-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #166534;
            margin-bottom: 2rem;
        }
        
        .success-message {
            margin-bottom: 3rem;
        }
        
        .success-text {
            font-size: 1.25rem;
            color: #374151;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .success-thanks {
            font-size: 1.125rem;
            color: #16a34a;
            font-weight: 600;
        }
        
        .event-info {
            margin-bottom: 3rem;
        }
        
        .success-event-card {
            background: white;
            border: 2px solid #22c55e;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .status-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #d97706;
        }
        
        .success-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }
        
        .next-steps {
            background: white;
            border-radius: 1rem;
            padding: 3rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        .next-steps h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
            justify-content: center;
        }
        
        .next-steps h3 i {
            color: var(--primary-color);
        }
        
        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .step {
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .step-content p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 20px 60px rgba(34, 197, 94, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 25px 80px rgba(34, 197, 94, 0.4);
            }
        }
        
        @keyframes successCelebrate {
            0% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.1) rotate(-5deg); }
            50% { transform: scale(1.2) rotate(5deg); }
            75% { transform: scale(1.1) rotate(-2deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .success-title {
                font-size: 2rem;
            }
            
            .success-icon {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .success-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .success-actions .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .next-steps {
                padding: 2rem 1.5rem;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .step-number {
                margin: 0 auto;
            }
        }
    </style>
</body>
</html>
