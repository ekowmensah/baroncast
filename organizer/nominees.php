<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch nominees for this organizer only
$stmt = $pdo->prepare("
    SELECT 
        n.id,
        n.name as nominee_name,
        n.description,
        n.image,
        n.status,
        n.display_order,
        n.created_at,
        c.name as category_name,
        e.title as event_title,
        e.id as event_id,
        COUNT(v.id) as total_votes
    FROM nominees n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN events e ON c.event_id = e.id
    LEFT JOIN votes v ON n.id = v.nominee_id
    WHERE e.organizer_id = ?
    GROUP BY n.id
    ORDER BY n.created_at DESC
");
$stmt->execute([$user['id']]);
$nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch categories for this organizer for the add nominee form
$stmt = $pdo->prepare("
    SELECT c.id, c.name, e.title as event_title
    FROM categories c
    LEFT JOIN events e ON c.event_id = e.id
    WHERE e.organizer_id = ? OR c.event_id IS NULL
    ORDER BY c.name
");
$stmt->execute([$user['id']]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch schemes assigned to this organizer
$stmt = $pdo->prepare("
    SELECT s.id, s.name, e.title as event_title
    FROM schemes s
    LEFT JOIN events e ON s.event_id = e.id
    WHERE s.organizer_id = ?
    ORDER BY s.name
");
$stmt->execute([$user['id']]);
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominees List - E-Cast Voting</title>
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
                    <a href="nominees.php" class="nav-link active">
                        <i class="fas fa-user-friends"></i>
                        <span>Nominees</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="registration.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Registration</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="page-title mb-0">Nominees List</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Nominees</span>
                        </nav>
                    </div>
                </div>
                
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
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
            <div class="content">
                <!-- Action Buttons -->
                <div class="d-flex gap-2 mb-4">
                    <button class="btn btn-primary" onclick="openAddNomineeModal()">
                        <i class="fas fa-plus"></i>
                        Add New Nominee
                    </button>
                    <button class="btn btn-success" onclick="openAddVotesModal()">
                        <i class="fas fa-plus"></i>
                        Add Manual Votes
                    </button>
                </div>

                <!-- Data Table Card -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <!-- Table Controls -->
                        <div style="padding: 1.5rem 1.5rem 0 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <label>Show</label>
                                    <select class="form-control" style="width: auto; display: inline-block;">
                                        <option>10</option>
                                        <option>25</option>
                                        <option>50</option>
                                        <option>100</option>
                                    </select>
                                    <label>entries</label>
                                </div>
                                <div>
                                    <label>Search:</label>
                                    <input type="text" class="form-control" style="width: 200px; display: inline-block; margin-left: 0.5rem;" placeholder="Search...">
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Sl</th>
                                        <th>Photo</th>
                                        <th>Fullname</th>
                                        <th>Scheme</th>
                                        <th>Category</th>
                                        <th>Short Code</th>
                                        <th>Total Votes</th>
                                        <th>Status</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($nominees)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Nominees Found</h5>
                                                    <p class="text-muted">Start by adding nominees to your event categories.</p>
                                                    <button class="btn btn-primary" onclick="openAddNomineeModal()">
                                                        <i class="fas fa-plus"></i> Add New Nominee
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($nominees as $index => $nominee): ?>
                                            <tr>
                                                <td><?= str_pad($nominee['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td>
                                                    <?php if (!empty($nominee['image'])): ?>
                                                        <img src="<?= htmlspecialchars($nominee['image']) ?>" 
                                                             alt="<?= htmlspecialchars($nominee['nominee_name']) ?>"
                                                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                                                    <?php else: ?>
                                                        <div style="width: 40px; height: 40px; background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-user" style="color: var(--text-muted);"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($nominee['nominee_name']) ?></td>
                                                <td><?= htmlspecialchars($nominee['event_title'] ?? 'No Event') ?></td>
                                                <td><?= htmlspecialchars($nominee['category_name'] ?? 'No Category') ?></td>
                                                <td><?= htmlspecialchars($nominee['id']) ?></td>
                                                <td><?= number_format($nominee['total_votes']) ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = '';
                                                    switch($nominee['status']) {
                                                        case 'active': $statusClass = 'badge-success'; break;
                                                        case 'inactive': $statusClass = 'badge-warning'; break;
                                                        case 'suspended': $statusClass = 'badge-danger'; break;
                                                        default: $statusClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($nominee['status']) ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" onclick="editNominee(<?= $nominee['id'] ?>)" title="Edit Nominee">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div style="padding: 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if (!empty($nominees)): ?>
                                        <small class="text-muted">Showing <?= count($nominees) ?> of <?= count($nominees) ?> entries</small>
                                    <?php else: ?>
                                        <small class="text-muted">No entries to display</small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($nominees) && count($nominees) > 10): ?>
                                <nav>
                                    <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                        <li><a href="#" class="btn btn-outline btn-sm" onclick="changePage('previous')">Previous</a></li>
                                        <li><a href="#" class="btn btn-primary btn-sm" onclick="changePage(1)">1</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm" onclick="changePage('next')">Next</a></li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    
    <!-- Add Nominee Modal -->
    <div id="addNomineeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Nominee</h3>
                <button class="modal-close" onclick="closeAddNomineeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="addNomineeForm" enctype="multipart/form-data">
                    <!-- Nominee Image -->
                    <div class="form-group">
                        <label for="nomineeImage">Nominee Photo</label>
                        <div class="image-upload-container">
                            <div class="image-preview" id="imagePreview">
                                <i class="fas fa-user-circle"></i>
                                <span>Click to upload photo</span>
                            </div>
                            <input type="file" id="nomineeImage" class="form-control" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <small class="form-text">Upload a clear photo of the nominee (JPG, PNG, GIF - Max 5MB)</small>
                    </div>
                    
                    <!-- Basic Information -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="nomineeName">Full Name <span class="required">*</span></label>
                            <input type="text" id="nomineeName" class="form-control" placeholder="Enter nominee's full name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="nomineeNickname">Nickname/Stage Name</label>
                            <input type="text" id="nomineeNickname" class="form-control" placeholder="e.g., DJ Talent 😎">
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="nomineeEmail">Email Address</label>
                            <input type="email" id="nomineeEmail" class="form-control" placeholder="nominee@example.com">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="nomineePhone">Phone Number</label>
                            <input type="tel" id="nomineePhone" class="form-control" placeholder="+233 XX XXX XXXX">
                        </div>
                    </div>
                    
                    <!-- Event & Category -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="eventScheme">Event Scheme <span class="required">*</span></label>
                            <select id="eventScheme" class="form-control" required>
                                <option value="">Select Event Scheme</option>
                                <?php if (empty($schemes)): ?>
                                    <option value="" disabled>No schemes available</option>
                                <?php else: ?>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?= $scheme['id'] ?>">
                                            <?= htmlspecialchars($scheme['name']) ?>
                                            <?php if ($scheme['event_title']): ?>
                                                (<?= htmlspecialchars($scheme['event_title']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="category">Category <span class="required">*</span></label>
                            <select id="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php if (empty($categories)): ?>
                                    <option value="" disabled>No categories available</option>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                            <?php if ($category['event_title']): ?>
                                                (<?= htmlspecialchars($category['event_title']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Voting Details -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="shortCode">Short Code <span class="required">*</span></label>
                            <input type="text" id="shortCode" class="form-control" placeholder="e.g., MHA012" required>
                            <small class="form-text">Unique code for USSD voting</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="nomineeStatus">Status</label>
                            <select id="nomineeStatus" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending Approval</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-group">
                        <label for="nomineeBio">Biography/Description</label>
                        <textarea id="nomineeBio" class="form-control" rows="3" placeholder="Brief description about the nominee (achievements, background, etc.)"></textarea>
                    </div>
                    
                    <!-- Social Media Links -->
                    <div class="form-group">
                        <label>Social Media Links (Optional)</label>
                        <div class="social-links">
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-facebook"></i></span>
                                </div>
                                <input type="url" id="facebookLink" class="form-control" placeholder="Facebook profile URL">
                            </div>
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                </div>
                                <input type="url" id="instagramLink" class="form-control" placeholder="Instagram profile URL">
                            </div>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                                </div>
                                <input type="url" id="twitterLink" class="form-control" placeholder="Twitter profile URL">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddNomineeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNominee()">Add Nominee</button>
            </div>
        </div>
    </div>
    
    <!-- Add Manual Votes Modal -->
    <div id="addVotesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Manual Votes</h3>
                <button class="modal-close" onclick="closeAddVotesModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="addVotesForm">
                    <div class="form-group">
                        <label for="voteNominee">Select Nominee</label>
                        <select id="voteNominee" class="form-control" required>
                            <option value="">Select Nominee</option>
                            <option value="1">DJ Talent 😎</option>
                            <option value="2">Sakara Sherif Bra-Alele</option>
                            <option value="3">Chenti Wuni</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="voteCount">Number of Votes</label>
                        <input type="number" id="voteCount" class="form-control" placeholder="Enter vote count" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="voteReason">Reason (Optional)</label>
                        <textarea id="voteReason" class="form-control" rows="3" placeholder="Reason for manual vote addition"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddVotesModal()">Cancel</button>
                <button type="button" class="btn btn-success" onclick="addManualVotes()">Add Votes</button>
            </div>
        </div>
    </div>
    
    <script>
        // Add Nominee Modal Functions
        function openAddNomineeModal() {
            document.getElementById('addNomineeModal').style.display = 'flex';
        }
        
        function closeAddNomineeModal() {
            document.getElementById('addNomineeModal').style.display = 'none';
            document.getElementById('addNomineeForm').reset();
        }
        
        function saveNominee() {
            const name = document.getElementById('nomineeName').value;
            const nickname = document.getElementById('nomineeNickname').value;
            const email = document.getElementById('nomineeEmail').value;
            const phone = document.getElementById('nomineePhone').value;
            const eventScheme = document.getElementById('eventScheme').value;
            const category = document.getElementById('category').value;
            const shortCode = document.getElementById('shortCode').value;
            const status = document.getElementById('nomineeStatus').value;
            const bio = document.getElementById('nomineeBio').value;
            const image = document.getElementById('nomineeImage').files[0];
            
            if (!name || !eventScheme || !category || !shortCode) {
                alert('Please fill in all required fields (marked with *)');
                return;
            }
            
            // Validate short code format
            if (!/^[A-Z]{2,3}\d{3,4}$/.test(shortCode)) {
                alert('Short code must be in format like MHA012 or ABC1234');
                return;
            }
            
            // Here you would typically send the data to the server
            const formData = new FormData();
            formData.append('name', name);
            formData.append('nickname', nickname);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('event_scheme', eventScheme);
            formData.append('category', category);
            formData.append('short_code', shortCode);
            formData.append('status', status);
            formData.append('bio', bio);
            if (image) formData.append('image', image);
            
            alert('Nominee "' + name + '" added successfully!');
            closeAddNomineeModal();
            // Refresh the page or update the table
            location.reload();
        }
        
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    preview.classList.add('has-image');
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '<i class="fas fa-user-circle"></i><span>Click to upload photo</span>';
                preview.classList.remove('has-image');
            }
        }
        
        // Make image preview clickable
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                if (e.target.closest('#imagePreview')) {
                    document.getElementById('nomineeImage').click();
                }
            });
        });
        
        // Add Manual Votes Modal Functions
        function openAddVotesModal() {
            document.getElementById('addVotesModal').style.display = 'flex';
        }
        
        function closeAddVotesModal() {
            document.getElementById('addVotesModal').style.display = 'none';
            document.getElementById('addVotesForm').reset();
        }
        
        function addManualVotes() {
            const nominee = document.getElementById('voteNominee').value;
            const voteCount = document.getElementById('voteCount').value;
            const reason = document.getElementById('voteReason').value;
            
            if (!nominee || !voteCount) {
                alert('Please select a nominee and enter vote count');
                return;
            }
            
            // Here you would typically send the data to the server
            alert(`${voteCount} votes added successfully!`);
            closeAddVotesModal();
            // Refresh the page or update the table
            location.reload();
        }
        
        // Save Nominee Function (Add New Nominee)
        function saveNominee() {
            const form = document.getElementById('addNomineeForm');
            const formData = new FormData();
            
            // Get form values
            const name = document.getElementById('nomineeName').value.trim();
            const category = document.getElementById('category').value;
            const eventScheme = document.getElementById('eventScheme').value;
            const image = document.getElementById('nomineeImage').files[0];
            const status = document.getElementById('nomineeStatus').value;
            const bio = document.getElementById('nomineeBio').value.trim();
            const facebook = document.getElementById('facebookLink').value.trim();
            const instagram = document.getElementById('instagramLink').value.trim();
            const twitter = document.getElementById('twitterLink').value.trim();
            
            // Validate required fields
            if (!name) {
                alert('Please enter nominee name');
                return;
            }
            
            if (!category) {
                alert('Please select a category');
                return;
            }
            
            // Event scheme is optional
            // if (!eventScheme) {
            //     alert('Please select an event scheme');
            //     return;
            // }
            
            // Prepare form data
            formData.append('name', name);
            formData.append('category_id', category);
            formData.append('scheme_id', eventScheme);
            formData.append('status', status);
            formData.append('description', bio);
            formData.append('facebook_url', facebook);
            formData.append('instagram_url', instagram);
            formData.append('twitter_url', twitter);
            
            if (image) {
                formData.append('image', image);
            }
            
            // Show loading state
            const submitBtn = document.querySelector('#addNomineeModal .btn-primary');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Adding...';
            submitBtn.disabled = true;
            
            // Send to server
            fetch('actions/add-nominee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Nominee added successfully!');
                    closeAddNomineeModal();
                    location.reload(); // Refresh to show new nominee
                } else {
                    alert('Error adding nominee: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding nominee. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // Edit Nominee Function
        function editNominee(nomineeId) {
            // Here you would typically open an edit modal or redirect to edit page
            alert(`Edit nominee with ID: ${nomineeId}`);
            // You can implement a similar modal for editing
        }
        
        // Pagination Functions
        function changePage(page) {
            if (page === 'previous') {
                alert('Going to previous page');
            } else if (page === 'next') {
                alert('Going to next page');
            } else {
                alert(`Going to page ${page}`);
            }
            // Here you would typically update the table data
        }
        
        // Enhanced modal styling with dark mode support
        const style = document.createElement('style');
        style.textContent = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            
            .modal-content {
                background: var(--bg-color, white);
                color: var(--text-color, #333);
                border-radius: 12px;
                max-width: 800px;
                width: 95%;
                max-height: 95vh;
                overflow-y: auto;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }
            
            .modal-header {
                padding: 1.5rem;
                border-bottom: 1px solid var(--border-color, #e9ecef);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: var(--header-bg, #f8f9fa);
                border-radius: 12px 12px 0 0;
            }
            
            .modal-header h3 {
                margin: 0;
                color: var(--text-color, #333);
                font-size: 1.25rem;
                font-weight: 600;
            }
            
            .modal-body {
                padding: 2rem;
            }
            
            .modal-footer {
                padding: 1.5rem 2rem;
                border-top: 1px solid var(--border-color, #e9ecef);
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                background: var(--footer-bg, #f8f9fa);
                border-radius: 0 0 12px 12px;
            }
            
            .modal-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: var(--text-muted, #6c757d);
                transition: color 0.3s ease;
            }
            
            .modal-close:hover {
                color: var(--text-color, #333);
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
                color: var(--text-color, #333);
            }
            
            .form-row {
                display: flex;
                gap: 1rem;
                margin-bottom: 0;
            }
            
            .form-row .form-group {
                flex: 1;
            }
            
            .col-md-6 {
                flex: 0 0 48%;
            }
            
            .form-control {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid var(--border-color, #ced4da);
                border-radius: 6px;
                font-size: 0.9rem;
                background-color: var(--input-bg, white);
                color: var(--text-color, #333);
                transition: border-color 0.3s ease, box-shadow 0.3s ease;
            }
            
            .form-control:focus {
                outline: none;
                border-color: var(--primary-color, #007bff);
                box-shadow: 0 0 0 3px var(--primary-color-alpha, rgba(0, 123, 255, 0.1));
            }
            
            .form-text {
                font-size: 0.8rem;
                color: var(--text-muted, #6c757d);
                margin-top: 0.25rem;
            }
            
            .required {
                color: #dc3545;
            }
            
            /* Image Upload Styling */
            .image-upload-container {
                position: relative;
                margin-bottom: 0.5rem;
            }
            
            .image-preview {
                width: 120px;
                height: 120px;
                border: 2px dashed var(--border-color, #ced4da);
                border-radius: 8px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
                background: var(--input-bg, #f8f9fa);
                margin-bottom: 0.5rem;
            }
            
            .image-preview:hover {
                border-color: var(--primary-color, #007bff);
                background: var(--primary-bg-light, #e3f2fd);
            }
            
            .image-preview i {
                font-size: 2.5rem;
                color: var(--text-muted, #6c757d);
                margin-bottom: 0.5rem;
            }
            
            .image-preview span {
                font-size: 0.8rem;
                color: var(--text-muted, #6c757d);
                text-align: center;
            }
            
            .image-preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 6px;
            }
            
            .image-preview.has-image {
                border-style: solid;
                padding: 0;
            }
            
            /* Social Media Links */
            .social-links .input-group {
                display: flex;
                margin-bottom: 0.5rem;
            }
            
            .input-group-prepend {
                display: flex;
            }
            
            .input-group-text {
                background: var(--input-group-bg, #e9ecef);
                border: 1px solid var(--border-color, #ced4da);
                border-right: none;
                padding: 0.75rem;
                color: var(--text-color, #495057);
                border-radius: 6px 0 0 6px;
            }
            
            .input-group .form-control {
                border-radius: 0 6px 6px 0;
                border-left: none;
            }
            
            .input-group .form-control:focus {
                border-left: 1px solid var(--primary-color, #007bff);
            }
            
            /* Dark mode support */
            [data-theme="dark"] .modal-content {
                --bg-color: #1a1d23;
                --text-color: #e9ecef;
                --text-muted: #adb5bd;
                --border-color: #495057;
                --input-bg: #2c3034;
                --header-bg: #212529;
                --footer-bg: #212529;
                --primary-color: #0d6efd;
                --primary-color-alpha: rgba(13, 110, 253, 0.1);
                --primary-bg-light: rgba(13, 110, 253, 0.1);
                --input-group-bg: #495057;
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .modal-content {
                    width: 98%;
                    max-height: 98vh;
                }
                
                .modal-body {
                    padding: 1.5rem;
                }
                
                .form-row {
                    flex-direction: column;
                    gap: 0;
                }
                
                .col-md-6 {
                    flex: 1;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
    <script>
        // Additional functionality for nominees page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                if (toggle && menu) {
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        menu.classList.toggle('show');
                    });
                    
                    document.addEventListener('click', (e) => {
                        if (!dropdown.contains(e.target)) {
                            menu.classList.remove('show');
                        }
                    });
                }
            });
        });
    </script>

    <style>
        .pagination {
            align-items: center;
        }
        
        .pagination .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table th {
            font-weight: 600;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        
        .table td {
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        /* Dropdown Styles */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--primary-color);
        }
        
        .dropdown-divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 0.5rem 0;
        }
    </style>
</body>
</html>
