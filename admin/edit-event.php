<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

$eventId = $_GET['id'] ?? 0;
$event = null;
$categories = [];
$votePackages = [];

// Fetch event details
if ($eventId) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, u.full_name as organizer_name, u.email as organizer_email
            FROM events e 
            LEFT JOIN users u ON e.organizer_id = u.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            header('Location: events-approval.php?error=Event not found');
            exit;
        }
        
        // Fetch categories for this event (backward compatible)
        $catStmt = $pdo->prepare("SELECT * FROM categories WHERE event_id = ? ORDER BY name");
        $catStmt->execute([$eventId]);
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Try to fetch additional categories from many-to-many table if it exists
        try {
            $catManyStmt = $pdo->prepare("
                SELECT c.* FROM categories c
                INNER JOIN event_categories ec ON c.id = ec.category_id
                WHERE ec.event_id = ? AND c.event_id IS NULL
                ORDER BY c.name
            ");
            $catManyStmt->execute([$eventId]);
            $additionalCategories = $catManyStmt->fetchAll(PDO::FETCH_ASSOC);
            $categories = array_merge($categories, $additionalCategories);
        } catch (PDOException $e) {
            // event_categories table doesn't exist yet, ignore
        }
        
        // Fetch vote packages for this event (backward compatible)
        $pkgStmt = $pdo->prepare("SELECT * FROM bulk_vote_packages WHERE event_id = ? ORDER BY vote_count ASC");
        $pkgStmt->execute([$eventId]);
        $votePackages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Try to fetch additional packages from many-to-many table if it exists
        try {
            $pkgManyStmt = $pdo->prepare("
                SELECT p.* FROM bulk_vote_packages p
                INNER JOIN event_packages ep ON p.id = ep.package_id
                WHERE ep.event_id = ?
                ORDER BY p.vote_count ASC
            ");
            $pkgManyStmt->execute([$eventId]);
            $additionalPackages = $pkgManyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge packages, avoiding duplicates based on package ID
            $existingIds = array_column($votePackages, 'id');
            foreach ($additionalPackages as $pkg) {
                if (!in_array($pkg['id'], $existingIds)) {
                    $votePackages[] = $pkg;
                }
            }
        } catch (PDOException $e) {
            // event_packages table doesn't exist yet, ignore
        }
        
    } catch (PDOException $e) {
        header('Location: events-approval.php?error=Database error');
        exit;
    }
}

// Fetch all available categories (for dropdown selection)
$allCategoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$allCategories = $allCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available vote packages (for dropdown selection)
$allPackagesStmt = $pdo->query("SELECT * FROM bulk_vote_packages ORDER BY name, vote_count");
$allPackages = $allPackagesStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$eventId) {
    header('Location: events.php?error=Invalid event ID');
    exit;
}

// Fetch all available categories (for assignment)
$allCategories = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE event_id IS NULL OR event_id = ? ORDER BY name");
    $stmt->execute([$eventId]);
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $allCategories = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Content -->
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> Edit Event</h1>
                        <p>Modify event details, assign categories, and configure vote packages</p>
                    </div>
                    <div class="page-actions">
                        <a href="events-approval.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Events
                        </a>
                    </div>
                </div>

                <form id="editEventForm" method="POST" action="actions/update-event.php" enctype="multipart/form-data">
                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                    
                    <div class="row">
                        <!-- Event Details -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-info-circle"></i> Event Details</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="eventTitle">Event Title *</label>
                                        <input type="text" id="eventTitle" name="title" class="form-control" 
                                               value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="eventDescription">Description *</label>
                                        <textarea id="eventDescription" name="description" class="form-control" 
                                                  rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="eventType">Event Type *</label>
                                            <select id="eventType" name="event_type" class="form-control" required>
                                                <option value="">Select Event Type</option>
                                                <option value="awards" <?php echo ($event['event_type'] ?? '') === 'awards' ? 'selected' : ''; ?>>Awards Ceremony</option>
                                                <option value="competition" <?php echo ($event['event_type'] ?? '') === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                                <option value="election" <?php echo ($event['event_type'] ?? '') === 'election' ? 'selected' : ''; ?>>Election</option>
                                                <option value="survey" <?php echo ($event['event_type'] ?? '') === 'survey' ? 'selected' : ''; ?>>Survey</option>
                                                <option value="other" <?php echo ($event['event_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="eventStatus">Event Status</label>
                                            <select id="eventStatus" name="status" class="form-control">
                                                <option value="pending" <?php echo $event['status'] === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                                <option value="active" <?php echo $event['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="upcoming" <?php echo $event['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="startDate">Start Date *</label>
                                            <input type="datetime-local" id="startDate" name="start_date" class="form-control" 
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="endDate">End Date *</label>
                                            <input type="datetime-local" id="endDate" name="end_date" class="form-control" 
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="eventLocation">Location</label>
                                            <input type="text" id="eventLocation" name="location" class="form-control" 
                                                   value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>" 
                                                   placeholder="Event venue or online platform">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="maxParticipants">Max Participants</label>
                                            <input type="number" id="maxParticipants" name="max_participants" class="form-control" 
                                                   value="<?php echo $event['max_participants'] ?? ''; ?>" 
                                                   placeholder="Leave empty for unlimited">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="eventRules">Event Rules & Guidelines</label>
                                        <textarea id="eventRules" name="rules" class="form-control" rows="3" 
                                                  placeholder="Enter voting rules, eligibility criteria, etc."><?php echo htmlspecialchars($event['rules'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="eventImage">Event Image</label>
                                        <input type="file" id="eventImage" name="event_image" class="form-control" accept="image/*">
                                        <?php if (!empty($event['logo'])): ?>
                                            <div class="current-image mt-2">
                                                <img src="../uploads/events/<?php echo htmlspecialchars($event['logo']); ?>" 
                                                     alt="Current Image" style="max-width: 200px; height: auto; border-radius: 8px;">
                                                <p class="text-muted">Current image</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="organizerId">Event Organizer *</label>
                                        <select id="organizerId" name="organizer_id" class="form-control" required>
                                            <option value="">Select Organizer</option>
                                            <?php
                                            // Fetch organizers for dropdown
                                            $orgStmt = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'organizer' ORDER BY full_name");
                                            $organizers = $orgStmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($organizers as $organizer): ?>
                                                <option value="<?php echo $organizer['id']; ?>" 
                                                        <?php echo $event['organizer_id'] == $organizer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($organizer['full_name']) . ' (' . htmlspecialchars($organizer['email']) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>
                                                <input type="checkbox" name="is_public" value="1" 
                                                       <?php echo ($event['is_public'] ?? 1) ? 'checked' : ''; ?>>
                                                Public Event (visible to all users)
                                            </label>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>
                                                <input type="checkbox" name="allow_multiple_votes" value="1" 
                                                       <?php echo ($event['allow_multiple_votes'] ?? 0) ? 'checked' : ''; ?>>
                                                Allow Multiple Votes per User
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Categories Management -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h3><i class="fas fa-tags"></i> Categories</h3>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="btn btn-sm btn-success" onclick="openCategoryModal()">
                                            <i class="fas fa-plus"></i>
                                            Create New Category
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Add Existing Category Section -->
                                    <div class="form-group mb-3">
                                        <label for="existingCategorySelect">Add Existing Category</label>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <select id="existingCategorySelect" class="form-control" style="flex: 1;">
                                                <option value="">Select an existing category...</option>
                                                <?php foreach ($allCategories as $availableCategory): ?>
                                                    <?php 
                                                    // Check if this category is already assigned to this event
                                                    $isAssigned = false;
                                                    foreach ($categories as $assignedCategory) {
                                                        if ($assignedCategory['id'] == $availableCategory['id']) {
                                                            $isAssigned = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$isAssigned): ?>
                                                        <option value="<?php echo $availableCategory['id']; ?>">
                                                            <?php echo htmlspecialchars($availableCategory['name']); ?>
                                                            <?php if (!empty($availableCategory['description'])): ?>
                                                                - <?php echo htmlspecialchars(substr($availableCategory['description'], 0, 50)); ?>...
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="addExistingCategory()">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Assigned Categories List -->
                                    <div id="categoriesList">
                                        <h6>Assigned Categories:</h6>
                                        <?php if (empty($categories)): ?>
                                            <p class="text-muted">No categories assigned to this event.</p>
                                        <?php else: ?>
                                            <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                                <?php foreach ($categories as $category): ?>
                                                    <div class="category-item" data-category-id="<?php echo $category['id']; ?>" style="border: 1px solid var(--border-color); border-radius: 0.375rem; padding: 1rem; background: var(--bg-secondary);">
                                                        <div class="category-info">
                                                            <h5 style="margin: 0 0 0.5rem 0; color: var(--text-primary);"><?php echo htmlspecialchars($category['name']); ?></h5>
                                                            <p style="margin: 0 0 1rem 0; color: var(--text-secondary); font-size: 0.875rem;"><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></p>
                                                        </div>
                                                        <div class="category-actions" style="display: flex; gap: 0.5rem;">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editCategory(<?php echo $category['id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="removeCategory(<?php echo $category['id']; ?>)">
                                                                <i class="fas fa-times"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Vote Packages -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h3><i class="fas fa-box"></i> Vote Packages</h3>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="btn btn-sm btn-success" onclick="openPackageModal()">
                                            <i class="fas fa-plus"></i>
                                            Create New Package
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Add Existing Package Section -->
                                    <div class="form-group mb-3">
                                        <label for="existingPackageSelect">Add Existing Package</label>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <select id="existingPackageSelect" class="form-control" style="flex: 1;">
                                                <option value="">Select an existing package...</option>
                                                <?php foreach ($allPackages as $availablePackage): ?>
                                                    <?php 
                                                    // Check if this package is already assigned to this event
                                                    $isAssigned = false;
                                                    foreach ($votePackages as $assignedPackage) {
                                                        if ($assignedPackage['id'] == $availablePackage['id']) {
                                                            $isAssigned = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$isAssigned): ?>
                                                        <option value="<?php echo $availablePackage['id']; ?>">
                                                            <?php echo htmlspecialchars($availablePackage['name']); ?>
                                                            - <?php echo number_format($availablePackage['vote_count']); ?> votes 
                                                            - GH₵ <?php echo number_format($availablePackage['price'], 2); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="addExistingPackage()">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Assigned Packages List -->
                                    <div id="packagesList">
                                        <h6>Assigned Vote Packages:</h6>
                                        <?php if (empty($votePackages)): ?>
                                            <p class="text-muted">No vote packages configured for this event.</p>
                                        <?php else: ?>
                                            <div class="packages-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
                                                <?php foreach ($votePackages as $package): ?>
                                                    <div class="package-item" data-package-id="<?php echo $package['id']; ?>" style="border: 1px solid var(--border-color); border-radius: 0.375rem; padding: 1rem; background: var(--bg-secondary);">
                                                        <div class="package-info">
                                                            <h5 style="margin: 0 0 0.5rem 0; color: var(--text-primary);"><?php echo htmlspecialchars($package['name']); ?></h5>
                                                            <div class="package-details" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                                                <span class="vote-count" style="color: var(--text-secondary); font-size: 0.875rem;">
                                                                    <i class="fas fa-vote-yea"></i>
                                                                    <?php echo number_format($package['vote_count']); ?> votes
                                                                </span>
                                                                <span class="price" style="color: var(--success); font-weight: 600;">
                                                                    GH₵ <?php echo number_format($package['price'], 2); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="package-actions" style="display: flex; gap: 0.5rem;">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editPackage(<?php echo $package['id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="removePackage(<?php echo $package['id']; ?>)">
                                                                <i class="fas fa-times"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Event Status & Actions -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-cog"></i> Event Status</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="eventStatus">Status</label>
                                        <select id="eventStatus" name="status" class="form-control">
                                            <option value="pending" <?php echo $event['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="active" <?php echo $event['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="upcoming" <?php echo $event['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                            <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="rejected" <?php echo $event['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <label>Organizer:</label>
                                            <span><?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <label>Email:</label>
                                            <span><?php echo htmlspecialchars($event['organizer_email']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <label>Created:</label>
                                            <span><?php echo date('M j, Y', strtotime($event['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header">
                                    <h3><i class="fas fa-save"></i> Actions</h3>
                                </div>
                                <div class="card-body">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i>
                                        Save Changes
                                    </button>
                                    
                                    <?php if ($event['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-success btn-block mt-2" onclick="approveEvent()">
                                            <i class="fas fa-check"></i>
                                            Approve Event
                                        </button>
                                        <button type="button" class="btn btn-danger btn-block mt-2" onclick="rejectEvent()">
                                            <i class="fas fa-times"></i>
                                            Reject Event
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="categoryModalTitle">Add Category</h3>
                <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="category_id">
                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                    
                    <div class="form-group">
                        <label for="categoryName">Category Name *</label>
                        <input type="text" id="categoryName" name="name" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="categoryDescription">Description</label>
                        <textarea id="categoryDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Package Modal -->
    <div id="packageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="packageModalTitle">Add Vote Package</h3>
                <button class="modal-close" onclick="closePackageModal()">&times;</button>
            </div>
            <form id="packageForm">
                <div class="modal-body">
                    <input type="hidden" id="packageId" name="package_id">
                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                    
                    <div class="form-group">
                        <label for="packageName">Package Name *</label>
                        <input type="text" id="packageName" name="name" required class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="voteCount">Vote Count *</label>
                            <input type="number" id="voteCount" name="vote_count" required class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label for="packagePrice">Price (GH₵) *</label>
                            <input type="number" id="packagePrice" name="price" required class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePackageModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Package</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Category Management
        function openCategoryModal() {
            document.getElementById('categoryModalTitle').textContent = 'Add Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryModal').style.display = 'block';
        }

        function editCategory(id) {
            fetch(`actions/get-category.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
                        document.getElementById('categoryId').value = data.category.id;
                        document.getElementById('categoryName').value = data.category.name;
                        document.getElementById('categoryDescription').value = data.category.description || '';
                        document.getElementById('categoryModal').style.display = 'block';
                    }
                });
        }

        function removeCategory(id) {
            if (confirm('Remove this category from the event?')) {
                fetch('actions/remove-event-category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category_id: id, event_id: <?php echo $event['id']; ?> })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        // Package Management
        function openPackageModal() {
            document.getElementById('packageModalTitle').textContent = 'Add Vote Package';
            document.getElementById('packageForm').reset();
            document.getElementById('packageId').value = '';
            document.getElementById('packageModal').style.display = 'block';
        }

        function editPackage(id) {
            fetch(`actions/get-vote-package.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('packageModalTitle').textContent = 'Edit Vote Package';
                        document.getElementById('packageId').value = data.package.id;
                        document.getElementById('packageName').value = data.package.name;
                        document.getElementById('voteCount').value = data.package.vote_count;
                        document.getElementById('packagePrice').value = data.package.price;
                        document.getElementById('packageModal').style.display = 'block';
                    }
                });
        }

        function removePackage(id) {
            if (confirm('Remove this vote package?')) {
                fetch('actions/delete-vote-package.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ package_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function closePackageModal() {
            document.getElementById('packageModal').style.display = 'none';
        }

        // Add existing category to event
        function addExistingCategory() {
            const select = document.getElementById('existingCategorySelect');
            const categoryId = select.value;
            
            if (!categoryId) {
                alert('Please select a category to add.');
                return;
            }
            
            fetch('actions/assign-category-to-event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    category_id: categoryId, 
                    event_id: <?php echo $event['id']; ?> 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the category.');
            });
        }

        // Add existing package to event
        function addExistingPackage() {
            const select = document.getElementById('existingPackageSelect');
            const packageId = select.value;
            
            if (!packageId) {
                alert('Please select a package to add.');
                return;
            }
            
            fetch('actions/assign-package-to-event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    package_id: packageId, 
                    event_id: <?php echo $event['id']; ?> 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the package.');
            });
        }

        // Event Actions
        function approveEvent() {
            if (confirm('Approve this event?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'actions/approve-event.php';
                form.innerHTML = `<input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectEvent() {
            const reason = prompt('Reason for rejection (optional):');
            if (reason !== null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'actions/reject-event.php';
                form.innerHTML = `
                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                    <input type="hidden" name="reason" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Main form submission
        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch('actions/update-event.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Event updated successfully!');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the event');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Form submissions
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = document.getElementById('categoryId').value ? 'update-category.php' : 'create-category.php';
            
            fetch(`actions/${action}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeCategoryModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });

        document.getElementById('packageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = document.getElementById('packageId').value ? 'update-vote-package.php' : 'create-vote-package.php';
            
            fetch(`actions/${action}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePackageModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
