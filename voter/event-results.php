<?php
require_once '../config/database.php';
require_once '../config/site-settings.php';

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$event_id) {
    header('Location: results.php');
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get event details
    $stmt = $pdo->prepare("
        SELECT e.*, u.username as organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.id = ? AND e.status IN ('active', 'completed')
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header('Location: results.php');
        exit;
    }
    
    // Get categories and nominees with vote counts for this event
    $stmt = $pdo->prepare("
        SELECT 
            c.id as category_id,
            c.description as category_name,
            n.id as nominee_id,
            n.name as nominee_name,
            n.description as nominee_description,
            n.image as nominee_image,
            COALESCE(SUM(v.vote_count), 0) as total_votes
        FROM categories c
        LEFT JOIN nominees n ON c.id = n.category_id
        LEFT JOIN votes v ON n.id = v.nominee_id
        WHERE c.event_id = ?
        GROUP BY c.id, n.id
        ORDER BY c.id, total_votes DESC
    ");
    $stmt->execute([$event_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize results by category
    $categories = [];
    foreach ($results as $row) {
        if (!isset($categories[$row['category_id']])) {
            $categories[$row['category_id']] = [
                'name' => $row['category_name'],
                'nominees' => []
            ];
        }
        
        if ($row['nominee_id']) {
            $categories[$row['category_id']]['nominees'][] = [
                'id' => $row['nominee_id'],
                'name' => $row['nominee_name'],
                'description' => $row['nominee_description'],
                'image' => $row['nominee_image'],
                'votes' => $row['total_votes']
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Error in event-results.php: " . $e->getMessage());
    header('Location: results.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Results | <?= htmlspecialchars(SiteSettings::getSiteName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/voter-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <?= SiteSettings::getBrandHtml(true, '', '', true) ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-calendar-alt"></i> Vote Now
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="results.php">
                            <i class="fas fa-chart-bar"></i> Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="how-to-vote.php">
                            <i class="fas fa-question-circle"></i> How to Vote
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <button class="btn btn-outline-light me-2" id="themeToggle">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <!-- Event Header -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="hero-text">
                            <h1 class="hero-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                            <p class="hero-subtitle">Event Results</p>
                            <div class="event-meta">
                                <span class="badge bg-<?php echo $event['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                    <?php echo ucfirst($event['status']); ?>
                                </span>
                                <span class="text-muted ms-2">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($event['organizer_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-center">
                        <?php if (!empty($event['logo']) && file_exists(__DIR__ . '/../uploads/events/' . $event['logo'])): ?>
                            <img src="../uploads/events/<?php echo htmlspecialchars($event['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($event['title']); ?>" 
                                 class="img-fluid rounded shadow">
                        <?php else: ?>
                            <div class="event-placeholder">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Event Image</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Results Section -->
        <section class="results-section py-5">
            <div class="container">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h3>No Results Available</h3>
                        <p class="text-muted">Results will be displayed once voting begins.</p>
                        <a href="events.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Events
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-results mb-5">
                            <h3 class="category-title">
                                <i class="fas fa-trophy"></i>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h3>
                            
                            <div class="row">
                                <?php foreach ($category['nominees'] as $index => $nominee): ?>
                                    <div class="col-lg-6 col-xl-4 mb-4">
                                        <div class="nominee-result-card <?php echo $index === 0 ? 'winner' : ''; ?>">
                                            <?php if ($index === 0): ?>
                                                <div class="winner-badge">
                                                    <i class="fas fa-crown"></i> Leading
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="nominee-image">
                                                <?php if (!empty($nominee['image']) && file_exists(__DIR__ . '/../uploads/nominees/' . $nominee['image'])): ?>
                                                    <img src="../uploads/nominees/<?php echo htmlspecialchars($nominee['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($nominee['name']); ?>" class="nominee-image">
                                                <?php else: ?>
                                                    <div class="nominee-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="nominee-info">
                                                <h5 class="nominee-name"><?php echo htmlspecialchars($nominee['name']); ?></h5>
                                                <?php if ($nominee['description']): ?>
                                                    <p class="nominee-description">
                                                        <?php echo htmlspecialchars(substr($nominee['description'], 0, 100)) . '...'; ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <div class="vote-stats">
                                                    <div class="vote-count">
                                                        <i class="fas fa-vote-yea"></i>
                                                        <strong><?php echo number_format($nominee['votes']); ?></strong> votes
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-5">
                        <a href="results.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to All Results
                        </a>
                        <?php if ($event['status'] === 'active'): ?>
                            <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-primary ms-2">
                                <i class="fas fa-vote-yea"></i> Vote Now
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(SiteSettings::getSiteName()) ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>Secure • Transparent • Reliable</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
