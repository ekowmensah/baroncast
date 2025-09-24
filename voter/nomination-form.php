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

// Fetch event details and categories
try {
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as organizer_name
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.id = ? AND e.status = 'active' AND e.end_date > NOW()
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header('Location: self-nomination.php');
        exit;
    }
    
    // Fetch categories for this event
    $stmt = $db->prepare("SELECT * FROM categories WHERE event_id = ? ORDER BY name");
    $stmt->execute([$event_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('Location: self-nomination.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $terms_accepted = isset($_POST['terms_accepted']);
    
    $errors = [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($contact)) {
        $errors[] = 'Contact number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
        $errors[] = 'Please enter a valid 10-digit phone number';
    }
    
    if (!$category_id || !in_array($category_id, array_column($categories, 'id'))) {
        $errors[] = 'Please select a valid category';
    }
    
    if (!$terms_accepted) {
        $errors[] = 'You must accept the Terms & Conditions';
    }
    
    // Handle image upload
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Please upload a valid image file (JPEG, PNG, or GIF)';
        } elseif ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = 'Image file size must be less than 5MB';
        } else {
            $upload_dir = '../uploads/nominees/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $profile_image = 'nominee_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $upload_path = $upload_dir . $profile_image;
            
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload image. Please try again.';
                $profile_image = null;
            }
        }
    }
    
    // If no errors, save the nomination
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO nominees (category_id, name, image, contact, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$category_id, $full_name, $profile_image, $contact]);
            
            // Redirect to success page
            header('Location: nomination-success.php?event_id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Failed to submit nomination. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nomination Form - <?= htmlspecialchars($event['title']) ?></title>
    <meta name="description" content="Complete your nomination form for <?= htmlspecialchars($event['title']) ?>.">
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
                    <a href="self-nomination.php" class="btn btn-outline btn-sm">
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
                            <i class="fas fa-edit"></i>
                            <?= htmlspecialchars($event['title']) ?>
                        </h1>
                        <p class="hero-subtitle">
                            Please fill in all the input fields to complete your nomination
                        </p>
                    </div>
                    <div class="hero-image">
                        <div class="hero-icon">
                            <i class="fas fa-user-edit"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Nomination Form Section -->
        <section class="form-section">
            <div class="container">
                <div class="form-container">
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

                    <form method="POST" enctype="multipart/form-data" class="nomination-form">
                        <div class="form-group">
                            <label for="profile_image" class="form-label">
                                <i class="fas fa-camera"></i>
                                Profile Image
                            </label>
                            <div class="image-upload">
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="form-control file-input">
                                <div class="upload-preview" id="upload-preview">
                                    <div class="upload-placeholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Click to upload image</p>
                                        <small>JPEG, PNG, or GIF (Max 5MB)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="category_id" class="form-label">
                                <i class="fas fa-tags"></i>
                                Select Category <span class="required">*</span>
                            </label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="full_name" class="form-label">
                                <i class="fas fa-user"></i>
                                Full Name Or Brand Name <span class="required">*</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact" class="form-label">
                                <i class="fas fa-phone"></i>
                                Contact (WhatsApp Or Call) <span class="required">*</span>
                            </label>
                            <input type="tel" id="contact" name="contact" class="form-control" 
                                   placeholder="0540000000" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" 
                                   pattern="[0-9]{10}" maxlength="10" required>
                            <small class="form-help">Enter 10-digit phone number without country code</small>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="terms_accepted" name="terms_accepted" 
                                       <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?> required>
                                <label for="terms_accepted" class="checkbox-label">
                                    <i class="fas fa-check-square"></i>
                                    I have accepted the <a href="#terms" target="_blank">Terms & Conditions</a>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                <i class="fas fa-paper-plane"></i>
                                Submit Nomination
                            </button>
                        </div>
                    </form>
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
            
            // Image upload preview
            const fileInput = document.getElementById('profile_image');
            const uploadPreview = document.getElementById('upload-preview');
            
            if (fileInput && uploadPreview) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            uploadPreview.innerHTML = `
                                <img src="${e.target.result}" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 0.5rem;">
                                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);">${file.name}</p>
                            `;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Phone number formatting
            const contactInput = document.getElementById('contact');
            if (contactInput) {
                contactInput.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    let value = e.target.value.replace(/\D/g, '');
                    // Limit to 10 digits
                    if (value.length > 10) {
                        value = value.slice(0, 10);
                    }
                    e.target.value = value;
                });
            }
        });
    </script>

    <style>
        /* Form Section Styles */
        .form-section {
            padding: 4rem 0;
            background: var(--bg-secondary);
        }
        
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 1rem;
            padding: 3rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }
        
        .nomination-form {
            display: flex;
            flex-direction: column;
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
        
        /* Image Upload Styles */
        .image-upload {
            position: relative;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .upload-preview {
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            background: var(--bg-secondary);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .upload-preview:hover {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }
        
        .upload-placeholder i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .upload-placeholder p {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .upload-placeholder small {
            color: var(--text-secondary);
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
        
        /* Alert Styles */
        .alert {
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
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
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .form-container {
                margin: 0 1rem;
                padding: 2rem 1.5rem;
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
        }
    </style>
</body>
</html>
