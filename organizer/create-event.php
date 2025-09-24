<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();
$success = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $vote_cost = floatval($_POST['vote_cost'] ?? 0);
    $max_votes_per_user = intval($_POST['max_votes_per_user'] ?? 1);
    $event_type = $_POST['event_type'] ?? 'public';
    $voting_method = $_POST['voting_method'] ?? 'single';
    $selected_categories = $_POST['categories'] ?? [];
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = uniqid('event_') . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/events/' . $filename;
            } else {
                $errors[] = 'Failed to upload image';
            }
        } else {
            $errors[] = 'Invalid image format. Please use JPG, PNG, or GIF';
        }
    }
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Event title is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Event description is required';
    }
    
    if (empty($start_date)) {
        $errors[] = 'Start date is required';
    }
    
    if (empty($end_date)) {
        $errors[] = 'End date is required';
    }
    
    if (!empty($start_date) && !empty($end_date)) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $now = new DateTime();
        
        if ($start <= $now) {
            $errors[] = 'Start date must be in the future';
        }
        
        if ($end <= $start) {
            $errors[] = 'End date must be after start date';
        }
    }
    
    if ($vote_cost < 0) {
        $errors[] = 'Vote cost cannot be negative';
    }
    
    if ($max_votes_per_user < 1) {
        $errors[] = 'Maximum votes per user must be at least 1';
    }
    
    // If no errors, create the event
    if (empty($errors)) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    title, description, organizer_id, start_date, end_date, logo, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'draft')
            ");
            
            $stmt->execute([
                $title, $description, $user['id'], $start_date, $end_date, $image_path
            ]);
            
            $event_id = $pdo->lastInsertId();
            
            // Assign selected categories to the event
            if (!empty($selected_categories)) {
                $updateStmt = $pdo->prepare("UPDATE categories SET event_id = ? WHERE id = ? AND event_id IS NULL");
                foreach ($selected_categories as $category_id) {
                    $updateStmt->execute([$event_id, intval($category_id)]);
                }
                $success = 'Event created successfully with ' . count($selected_categories) . ' categories assigned! It is now pending admin approval.';
            } else {
                $success = 'Event created successfully! It is now pending admin approval and will be active once approved.';
            }
            
            // Clear form data on success
            $_POST = [];
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch standalone categories for assignment
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Fetch standalone categories (categories without event_id)
    $stmt = $pdo->query("SELECT id, name, description FROM categories WHERE event_id IS NULL AND status = 'active' ORDER BY name");
    $standaloneCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $standaloneCategories = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - E-Cast Organizer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-vote-yea"></i>
                    <span>E-Cast Organizer</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="create-event.php" class="nav-link active">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Create Event</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="transactions-menu">
                        <i class="fas fa-credit-card"></i>
                        <span>Transactions</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="transactions-menu" class="nav-submenu">
                        <a href="votes-payments.php" class="nav-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Votes Payments</span>
                        </a>
                        <a href="withdrawal.php" class="nav-link">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Withdrawal</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="tally-menu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Tally</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="tally-menu" class="nav-submenu">
                        <a href="category-tally.php" class="nav-link">
                            <i class="fas fa-list-alt"></i>
                            <span>Category Tally</span>
                        </a>
                        <a href="nominees-tally.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Nominees Tally</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="scheme.php" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <span>Scheme</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="bulk-votes.php" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span>Bulk Votes</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="categories.php" class="nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="nominees.php" class="nav-link">
                        <i class="fas fa-user-friends"></i>
                        <span>Nominees</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="registration.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Registering</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title mb-0">Create New Event</h1>
                </div>
                
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">
                            <i class="fas fa-user-tie"></i>
                            <span><?= htmlspecialchars($user['full_name']) ?></span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user-cog"></i>
                                Profile
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content" style="padding: 0 2rem;">
                <div class="page-header">
                    <h2 class="page-title">Create New Voting Event</h2>
                    <p class="page-subtitle">Set up a new voting event. Events require admin approval before going live.</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <h4>Success!</h4>
                            <p><?= htmlspecialchars($success) ?></p>
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

                <!-- Event Creation Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-plus"></i>
                            Event Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="event-form" enctype="multipart/form-data">
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-info-circle"></i>
                                    Basic Information
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="title" class="form-label">
                                            <i class="fas fa-heading"></i>
                                            Event Title <span class="required">*</span>
                                        </label>
                                        <input type="text" id="title" name="title" class="form-control" 
                                               placeholder="Enter event title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left"></i>
                                        Event Description <span class="required">*</span>
                                    </label>
                                    <textarea id="description" name="description" class="form-control" rows="4" 
                                              placeholder="Describe your event, its purpose, and any important details" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="event_image" class="form-label">
                                        <i class="fas fa-image"></i>
                                        Event Image (Optional)
                                    </label>
                                    <input type="file" id="event_image" name="event_image" class="form-control" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    <small class="form-text">Upload an image for your event (JPG, PNG, GIF - Max 5MB)</small>
                                </div>
                            </div>

                            <!-- Event Schedule -->
                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    Event Schedule
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="start_date" class="form-label">
                                            <i class="fas fa-play"></i>
                                            Start Date & Time <span class="required">*</span>
                                        </label>
                                        <input type="datetime-local" id="start_date" name="start_date" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="end_date" class="form-label">
                                            <i class="fas fa-stop"></i>
                                            End Date & Time <span class="required">*</span>
                                        </label>
                                        <input type="datetime-local" id="end_date" name="end_date" class="form-control" 
                                               value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Voting Configuration -->
                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-vote-yea"></i>
                                    Voting Configuration
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="vote_cost" class="form-label">
                                            <i class="fas fa-dollar-sign"></i>
                                            Vote Cost (<?php echo SiteSettings::getCurrencySymbol(); ?>)
                                        </label>
                                        <input type="number" id="vote_cost" name="vote_cost" class="form-control" 
                                               min="0" step="0.01" placeholder="0.00" value="<?= htmlspecialchars($_POST['vote_cost'] ?? '0') ?>">
                                        <small class="form-text">Set to 0 for free voting</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="max_votes_per_user" class="form-label">
                                            <i class="fas fa-hashtag"></i>
                                            Max Votes Per User
                                        </label>
                                        <input type="number" id="max_votes_per_user" name="max_votes_per_user" class="form-control" 
                                               min="1" placeholder="1" value="<?= htmlspecialchars($_POST['max_votes_per_user'] ?? '1') ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="event_type" class="form-label">
                                            <i class="fas fa-eye"></i>
                                            Event Visibility
                                        </label>
                                        <select id="event_type" name="event_type" class="form-control">
                                            <option value="public" <?= ($_POST['event_type'] ?? '') === 'public' ? 'selected' : '' ?>>Public - Anyone can view and vote</option>
                                            <option value="private" <?= ($_POST['event_type'] ?? '') === 'private' ? 'selected' : '' ?>>Private - Invitation only</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="voting_method" class="form-label">
                                            <i class="fas fa-check-square"></i>
                                            Voting Method
                                        </label>
                                        <select id="voting_method" name="voting_method" class="form-control">
                                            <option value="single" <?= ($_POST['voting_method'] ?? '') === 'single' ? 'selected' : '' ?>>Single Choice - One vote per category</option>
                                            <option value="multiple" <?= ($_POST['voting_method'] ?? '') === 'multiple' ? 'selected' : '' ?>>Multiple Choice - Multiple votes allowed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Category Management -->
                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-tags"></i>
                                    Category Assignment (Optional)
                                </h4>
                                
                                <?php if (!empty($standaloneCategories)): ?>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-list"></i>
                                        Assign Existing Categories
                                    </label>
                                    <p class="form-text mb-3">Select standalone categories to assign to this event. You can also create new categories after event approval.</p>
                                    
                                    <div class="category-selection" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 0.375rem; padding: 1rem;">
                                        <?php foreach ($standaloneCategories as $category): ?>
                                        <div class="form-check" style="margin-bottom: 0.75rem;">
                                            <input type="checkbox" id="category_<?= $category['id'] ?>" name="categories[]" value="<?= $category['id'] ?>" class="form-check-input">
                                            <label for="category_<?= $category['id'] ?>" class="form-check-label" style="margin-left: 0.5rem;">
                                                <strong><?= htmlspecialchars($category['name']) ?></strong>
                                                <?php if ($category['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($category['description']) ?></small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No standalone categories available. You can create categories after your event is approved, or ask an admin to create standalone categories.
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calendar-plus"></i>
                                    Create Event
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Important Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="fas fa-clock text-warning"></i>
                                <div>
                                    <h4>Approval Process</h4>
                                    <p>All events require admin approval before going live. You'll be notified once your event is approved.</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-edit text-info"></i>
                                <div>
                                    <h4>Event Management</h4>
                                    <p>After approval, you can add categories, nominees, and manage all aspects of your event.</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-chart-line text-success"></i>
                                <div>
                                    <h4>Real-time Analytics</h4>
                                    <p>Track votes, revenue, and engagement with comprehensive analytics and reporting tools.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const minDate = tomorrow.toISOString().slice(0, 16);
        
        document.getElementById('start_date').min = minDate;
        document.getElementById('end_date').min = minDate;
        
        // Update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            startDate.setHours(startDate.getHours() + 1); // Minimum 1 hour duration
            document.getElementById('end_date').min = startDate.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>
