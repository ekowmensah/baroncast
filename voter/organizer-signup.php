<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/site-settings.php';

// Initialize Auth
$auth = new Auth();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $organization = trim($_POST['organization'] ?? '');
    $terms_accepted = isset($_POST['terms_accepted']);
    
    $errors = [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit phone number';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!$terms_accepted) {
        $errors[] = 'You must accept the Terms & Conditions';
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        $userData = [
            'full_name' => $full_name,
            'email' => $email,
            'username' => $username,
            'phone' => $phone,
            'password' => $password,
            'role' => 'organizer'
        ];
        
        $result = $auth->register($userData);
        
        if ($result['success']) {
            $success_message = "Account created successfully! You can now login to start creating events.";
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Signup - E-Cast Voting Platform</title>
    <meta name="description" content="Create your free Event Organizer account to start managing voting events.">
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
                    <a href="../login.php" class="btn btn-outline btn-sm">
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
                            <i class="fas fa-calendar-plus"></i>
                            Event Organizer Signup
                        </h1>
                        <p class="hero-subtitle">
                            Create your free account to start organizing professional voting events with real-time results and payment management.
                        </p>
                        <div class="hero-features">
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Free Account Creation</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Unlimited Events</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Payment Management</span>
                            </div>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Signup Form Section -->
        <section class="form-section">
            <div class="container">
                <div class="form-container">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4>Success!</h4>
                                <p><?= htmlspecialchars($success_message) ?></p>
                                <a href="login.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Login Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <h4>Please fix the following errors:</h4>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!isset($success_message)): ?>
                        <div class="form-header">
                            <h2>
                                <i class="fas fa-user-plus"></i>
                                Create Your Organizer Account
                            </h2>
                            <p>Fill in your details to get started with event management</p>
                        </div>

                        <form method="POST" class="signup-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name" class="form-label">
                                        <i class="fas fa-user"></i>
                                        Full Name <span class="required">*</span>
                                    </label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="organization" class="form-label">
                                        <i class="fas fa-building"></i>
                                        Organization (Optional)
                                    </label>
                                    <input type="text" id="organization" name="organization" class="form-control" 
                                           placeholder="Company or organization name" value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address <span class="required">*</span>
                                    </label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone"></i>
                                        Phone Number <span class="required">*</span>
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           placeholder="0540000000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                           pattern="[0-9]{10}" maxlength="10" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="username" class="form-label">
                                    <i class="fas fa-at"></i>
                                    Username <span class="required">*</span>
                                </label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       placeholder="Choose a unique username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                <small class="form-help">This will be used to login to your account</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i>
                                        Password <span class="required">*</span>
                                    </label>
                                    <input type="password" id="password" name="password" class="form-control" 
                                           placeholder="Enter a secure password" required>
                                    <small class="form-help">Minimum 6 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock"></i>
                                        Confirm Password <span class="required">*</span>
                                    </label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm your password" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="terms_accepted" name="terms_accepted" 
                                           <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?> required>
                                    <label for="terms_accepted" class="checkbox-label">
                                        <i class="fas fa-check-square"></i>
                                        I agree to the <a href="#terms" target="_blank">Terms & Conditions</a> and <a href="#privacy" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg btn-block">
                                    <i class="fas fa-user-plus"></i>
                                    Create Free Account
                                </button>
                            </div>

                            <div class="form-footer">
                                <p>Already have an account? <a href="../login.php">Login here</a></p>
                            </div>
                        </form>
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
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) {
                        value = value.slice(0, 10);
                    }
                    e.target.value = value;
                });
            }
            
            // Password confirmation validation
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (passwordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
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
        
        .hero-features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }
        
        .feature-item i {
            color: #22c55e;
            font-size: 1.125rem;
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
        
        /* Form Section Styles */
        .form-section {
            padding: 4rem 0;
            background: var(--bg-secondary);
        }
        
        .form-container {
            max-width: 700px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 1rem;
            padding: 3rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .form-header h2 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .form-header h2 i {
            color: var(--primary-color);
        }
        
        .form-header p {
            color: var(--text-secondary);
            font-size: 1.125rem;
        }
        
        .signup-form {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .form-label i {
            color: var(--primary-color);
        }
        
        .required {
            color: #ef4444;
        }
        
        .form-control {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-help {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        /* Checkbox Styles */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            margin: 0;
            cursor: pointer;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
            cursor: pointer;
            line-height: 1.5;
        }
        
        .checkbox-label a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .checkbox-label a:hover {
            text-decoration: underline;
        }
        
        /* Button Styles */
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
        
        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }
        
        .btn-block {
            width: 100%;
            text-align: center;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-footer p {
            color: var(--text-secondary);
        }
        
        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #059669;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .alert i {
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }
        
        .alert h4 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1.25rem;
        }
        
        .alert li {
            margin-bottom: 0.25rem;
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
            
            .form-container {
                margin: 0 1rem;
                padding: 2rem 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
    </style>
</body>
</html>
