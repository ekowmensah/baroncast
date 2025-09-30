<?php
// Force cache bypass - NEW FILE
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo "<div style='background: red; color: white; padding: 20px; font-size: 24px; text-align: center; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;'>CACHE FORCE CLEAR ACTIVATED - " . date('Y-m-d H:i:s') . "</div>";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';

$database = new Database();
$db = $database->getConnection();

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    header('Location: index.php');
    exit();
}

// Fetch event details
try {
    $query = "SELECT e.*, u.full_name as organizer_name
              FROM events e
              LEFT JOIN users u ON e.organizer_id = u.id
              WHERE e.id = ? AND e.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit();
}

// Fetch categories and nominees
try {
    $query = "SELECT c.*,
              COUNT(n.id) as nominee_count,
              COUNT(v.id) as total_votes
              FROM categories c
              LEFT JOIN nominees n ON c.id = n.category_id
              LEFT JOIN votes v ON c.id = v.category_id
              WHERE c.event_id = ?
              GROUP BY c.id
              ORDER BY c.created_at";
    $stmt = $db->prepare($query);
    $stmt->execute([$event_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch nominees for each category
    foreach ($categories as &$category) {
        $nominee_query = "SELECT n.*, n.short_code, COUNT(v.id) as vote_count
                         FROM nominees n
                         LEFT JOIN votes v ON n.id = v.nominee_id
                         WHERE n.category_id = ?
                         GROUP BY n.id
                         ORDER BY vote_count DESC, n.name";
        $nominee_stmt = $db->prepare($nominee_query);
        $nominee_stmt->execute([$category['id']]);
        $category['nominees'] = $nominee_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $categories = [];
}

// Fetch payment settings
try {
    $settingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('vote_cost', 'ussd_code', 'enable_card_payments')";
    $settingsStmt = $db->prepare($settingsQuery);
    $settingsStmt->execute();
    $paymentSettings = [];
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentSettings[$row['setting_key']] = $row['setting_value'];
    }

    // Set defaults
    if (!isset($paymentSettings['vote_cost'])) {
        $paymentSettings['vote_cost'] = '1.00';
    }
} catch (PDOException $e) {
    $paymentSettings = ['vote_cost' => '1.00', 'ussd_code' => '*123*456#', 'enable_card_payments' => '0'];
}

// Fetch site settings
try {
    $siteQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo')";
    $siteStmt = $db->prepare($siteQuery);
    $siteStmt->execute();
    $siteSettings = [];
    while ($row = $siteStmt->fetch(PDO::FETCH_ASSOC)) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }

    // Set defaults if not found
    if (empty($siteSettings['site_name'])) {
        $siteSettings['site_name'] = 'E-Cast Voting Platform';
    }
    if (empty($siteSettings['logo'])) {
        $siteSettings['logo'] = '';
    }
} catch (PDOException $e) {
    $siteSettings = ['site_name' => 'E-Cast Voting Platform', 'logo' => ''];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - <?= htmlspecialchars(SiteSettings::getSiteName()) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Modern Nominee Cards */
        .nominees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .nominee-card-modern {
            position: relative;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nominee-card-modern:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        /* Short Code Badge */
        .nominee-code-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            z-index: 2;
        }

        .code-label {
            display: block;
            font-size: 10px;
            opacity: 0.9;
            margin-bottom: 2px;
        }

        .code-value {
            display: block;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Image Section */
        .nominee-image-section {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .nominee-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .nominee-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .nominee-avatar-placeholder i {
            font-size: 48px;
            color: white;
            opacity: 0.8;
        }

        /* Content Section */
        .nominee-content {
            padding: 24px;
        }

        .nominee-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .nominee-name {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            flex: 1;
        }

        .nominee-rank {
            display: flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .nominee-rank i {
            font-size: 12px;
        }

        .nominee-description {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            margin: 12px 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Statistics */
        .nominee-stats-modern {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            padding: 16px;
            background: var(--bg-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1.2;
        }

        .stat-label {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* Action Section */
        .nominee-action-section {
            padding: 0 24px 24px 24px;
        }

        .vote-button-modern {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .vote-button-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #6d28d9);
        }

        .vote-button-modern i {
            font-size: 18px;
        }

        .vote-button-modern span {
            font-weight: 700;
        }

        .vote-button-modern small {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            margin-top: 2px;
            font-weight: 400;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .nominees-grid {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .nominees-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .nominee-card-modern {
                margin: 0 -10px;
            }

            .nominee-content,
            .nominee-action-section {
                padding-left: 20px;
                padding-right: 20px;
            }

            .nominee-stats-modern {
                flex-direction: column;
                gap: 12px;
            }

            .nominee-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .nominee-name {
                font-size: 20px;
            }

            .nominee-code-badge {
                top: 12px;
                right: 12px;
                padding: 6px 12px;
            }

            .code-value {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .nominee-image-section {
                height: 160px;
            }

            .nominee-avatar,
            .nominee-avatar-placeholder {
                width: 100px;
                height: 100px;
            }

            .nominee-avatar-placeholder i {
                font-size: 40px;
            }

            .stat-item {
                padding: 12px;
            }

            .stat-number {
                font-size: 18px;
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
                        <?= SiteSettings::getBrandHtml(true, '', '', true) ?>
                    </a>
                </div>

                <div class="navbar-menu">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Active Events</span>
                    </a>
                    <a href="results.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Results</span>
                    </a>
                </div>

                <div class="navbar-actions">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="voter-main">
        <!-- Event Details -->
        <section class="event-details-section">
            <div class="container">
                <div class="event-container">
                    <div class="event-header-content">
                        <div class="event-info">
                            <h1 class="event-title">
                                <i class="fas fa-trophy"></i>
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h1>
                            <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>

                            <div class="event-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>Organized by <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Ends: <?php echo date('M j, Y', strtotime($event['end_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-list"></i>
                                    <span><?php echo count($categories); ?> Categories</span>
                                </div>
                            </div>
                        </div>

                        <div class="event-actions">
                            <a href="results.php?event=<?php echo $event['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-chart-bar"></i>
                                View Results
                            </a>
                        </div>
                    </div>

                    <!-- Voting Categories -->
                    <div class="voting-categories">
                <?php if (empty($categories)): ?>
                    <div class="no-categories">
                        <div class="no-categories-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No Categories Available</h3>
                        <p>This event doesn't have any voting categories yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <h2 class="category-title">
                                    <i class="fas fa-award"></i>
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </h2>
                                <div class="category-stats">
                                    <span class="stat">
                                        <i class="fas fa-users"></i>
                                        <?php echo $category['nominee_count']; ?> Nominees
                                    </span>
                                    <span class="stat">
                                        <i class="fas fa-vote-yea"></i>
                                        <?php echo number_format($category['total_votes']); ?> Votes
                                    </span>
                                </div>
                            </div>

                            <div class="nominees-grid">
                                <?php if (empty($category['nominees'])): ?>
                                    <div class="no-nominees">
                                        <p>No nominees in this category yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($category['nominees'] as $nominee): ?>
                                        <div class="nominee-card-modern">
                                            <!-- Nominee Short Code Badge -->
                                            <div class="nominee-code-badge">
                                                <span class="code-label">CODE</span>
                                                <span class="code-value"><?php echo htmlspecialchars($nominee['short_code'] ?: 'N/A'); ?></span>
                                            </div>

                                            <!-- Nominee Image -->
                                            <div class="nominee-image-section">
                                                <?php if (!empty($nominee['image']) && file_exists(__DIR__ . '/../uploads/nominees/' . $nominee['image'])): ?>
                                                    <img src="../uploads/nominees/<?php echo htmlspecialchars($nominee['image']); ?>" alt="<?php echo htmlspecialchars($nominee['name']); ?>" class="nominee-avatar">
                                                <?php else: ?>
                                                    <div class="nominee-avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Nominee Details -->
                                            <div class="nominee-content">
                                                <div class="nominee-header">
                                                    <h3 class="nominee-name"><?php echo htmlspecialchars($nominee['name']); ?></h3>
                                                    <div class="nominee-rank">
                                                        <i class="fas fa-trophy"></i>
                                                        <span>#<?php echo array_search($nominee, $category['nominees']) + 1; ?></span>
                                                    </div>
                                                </div>

                                                <?php if ($nominee['description']): ?>
                                                    <p class="nominee-description"><?php echo htmlspecialchars(substr($nominee['description'], 0, 120)) . (strlen($nominee['description']) > 120 ? '...' : ''); ?></p>
                                                <?php endif; ?>

                                                <!-- Vote Statistics -->
                                                <div class="nominee-stats-modern">
                                                    <div class="stat-item">
                                                        <div class="stat-icon">
                                                            <i class="fas fa-vote-yea"></i>
                                                        </div>
                                                        <div class="stat-content">
                                                            <span class="stat-number"><?php echo number_format($nominee['vote_count']); ?></span>
                                                            <span class="stat-label">Votes</span>
                                                        </div>
                                                    </div>
                                                    <div class="stat-item">
                                                        <div class="stat-icon">
                                                            <i class="fas fa-percentage"></i>
                                                        </div>
                                                        <div class="stat-content">
                                                            <span class="stat-number">
                                                                <?php
                                                                $totalCategoryVotes = array_sum(array_column($category['nominees'], 'vote_count'));
                                                                $percentage = $totalCategoryVotes > 0 ? ($nominee['vote_count'] / $totalCategoryVotes) * 100 : 0;
                                                                echo number_format($percentage, 1);
                                                                ?>%
                                                            </span>
                                                            <span class="stat-label">Share</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Vote Action -->
                                            <div class="nominee-action-section">
                                                <button class="vote-button-modern"
                                                        data-nominee-id="<?php echo $nominee['id']; ?>"
                                                        data-nominee-name="<?php echo htmlspecialchars($nominee['name']); ?>"
                                                        data-category-name="<?php echo htmlspecialchars($category['description']); ?>">
                                                    <i class="fas fa-vote-yea"></i>
                                                    <span>Vote Now</span>
                                                    <small><?php echo SiteSettings::getCurrencySymbol(); ?> <?php echo number_format($paymentSettings['vote_cost'], 2); ?>/vote</small>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Vote Modal -->
    <div id="voteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-vote-yea"></i>
                    Cast Your Vote
                </h3>
                <button type="button" class="modal-close" onclick="closeVoteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="vote-info">
                    <h4 id="selectedNomineeName">Nominee Name</h4>
                    <p id="selectedNomineeCategory">Category</p>
                </div>

                <!-- Vote Packages -->
                <div class="form-group">
                    <label class="form-label">Select Vote Package</label>
                    <div class="vote-packages">
                        <label class="package-option">
                            <input type="radio" name="vote_package" value="1" data-price="<?= $paymentSettings['vote_cost'] ?>">
                            <div class="package-card">
                                <div class="package-title">1 Vote</div>
                                <div class="package-price">GHS <?= number_format((float)$paymentSettings['vote_cost'], 2) ?></div>
                            </div>
                        </label>
                        <label class="package-option">
                            <input type="radio" name="vote_package" value="5" data-price="<?= number_format((float)$paymentSettings['vote_cost'] * 5, 2) ?>">
                            <div class="package-card">
                                <div class="package-title">5 Votes</div>
                                <div class="package-price">GHS <?= number_format((float)$paymentSettings['vote_cost'] * 5, 2) ?></div>
                                <div class="package-discount">Popular</div>
                            </div>
                        </label>
                        <label class="package-option">
                            <input type="radio" name="vote_package" value="10" data-price="<?= number_format((float)$paymentSettings['vote_cost'] * 10, 2) ?>">
                            <div class="package-card">
                                <div class="package-title">10 Votes</div>
                                <div class="package-price">GHS <?= number_format((float)$paymentSettings['vote_cost'] * 10, 2) ?></div>
                                <div class="package-discount">Best Value</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div class="payment-options">
                        <label class="payment-option" onclick="selectPaymentMethod('online')">
                            <input type="radio" name="payment_method" value="online" id="payment_online" checked>
                            <div class="payment-card">
                                <i class="fas fa-credit-card" style="font-size: 1.5rem; color: var(--primary-color);"></i>
                                <span>Online Payment</span>
                                <small style="display: block; color: #666; font-size: 0.8rem;">Mobile Money, Visa, MasterCard</small>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Online Payment Info -->
                <div id="onlinePaymentInfo" class="contact-info">
                    <div class="payment-info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <i class="fas fa-shield-alt" style="color: #28a745; margin-right: 8px;"></i>
                            <strong>Secure Hubtel Payment</strong>
                        </div>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">
                            You'll be redirected to Hubtel's secure payment page where you can pay with:
                        </p>
                        <ul style="margin: 8px 0 0 20px; color: #666; font-size: 0.9rem;">
                            <li>Mobile Money (MTN, Vodafone, AirtelTigo)</li>
                            <li>Visa & MasterCard</li>
                            <li>Bank Transfer</li>
                        </ul>
                    </div>
                </div>

                <!-- Mobile Money Fields -->
                <div id="mobileMoneyFields" class="contact-info" style="display: none;">
                    <label for="voterPhone">Phone Number</label>
                    <input type="tel" id="voterPhone" placeholder="e.g., 0244123456" maxlength="15">
                    <small class="form-text">Enter your mobile money number</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelVote">Cancel</button>
                <button type="button" class="btn btn-primary" id="proceedPayment">
                    <i class="fas fa-credit-card"></i>
                    Proceed to Payment
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const voteModal = document.getElementById('voteModal');
            const closeModalBtn = document.querySelector('.modal-close');
            const cancelVote = document.getElementById('cancelVote');
            const proceedPayment = document.getElementById('proceedPayment');
            const voteButtons = document.querySelectorAll('.vote-button-modern');

            let currentNomineeId = null;

            // Handle vote button clicks
            voteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentNomineeId = this.dataset.nomineeId;
                    const nomineeName = this.dataset.nomineeName;
                    const categoryName = this.dataset.categoryName;

                    // Update modal content
                    document.getElementById('selectedNomineeName').textContent = nomineeName;
                    document.getElementById('selectedNomineeCategory').textContent = categoryName;

                    // Reset form
                    document.querySelector('input[name="vote_package"]:checked')?.checked = false;
                    // Auto-select online payment method since it's the only option
                    const onlinePayment = document.getElementById('payment_online');
                    if (onlinePayment) {
                        onlinePayment.checked = true;
                    }
                    document.getElementById('onlinePaymentInfo').style.display = 'block';

                    // Show modal
                    voteModal.style.display = 'flex';
                });
            });

            // Close modal function
            window.closeVoteModal = function() {
                voteModal.style.display = 'none';
                currentNomineeId = null;
            };

            // Close modal
            function closeVoteModal() {
                voteModal.style.display = 'none';
                currentNomineeId = null;
            }

            closeModalBtn.addEventListener('click', closeVoteModal);
            if (cancelVote) {
                cancelVote.addEventListener('click', closeVoteModal);
            }

            // Update total amount when package changes
            document.querySelectorAll('input[name="vote_package"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('totalAmount').textContent = this.dataset.price;
                    document.querySelector('.ussd-amount').textContent = this.dataset.price;
                });
            });

            // Handle payment method switching
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const mobileFields = document.getElementById('mobileMoneyFields');
                    const onlinePaymentInfo = document.getElementById('onlinePaymentInfo');

                    // Show phone number field for both mobile_money and ussd
                    if (this.value === 'mobile_money' || this.value === 'ussd') {
                        mobileFields.style.display = 'block';
                        onlinePaymentInfo.style.display = 'none';
                    } else if (this.value === 'online') {
                        mobileFields.style.display = 'none';
                        onlinePaymentInfo.style.display = 'block';
                    } else {
                        mobileFields.style.display = 'none';
                        onlinePaymentInfo.style.display = 'none';
                    }
                });
            });

            // Copy USSD code function
            function copyUSSDCode() {
                const ussdCode = document.getElementById('ussdCode').textContent;
                navigator.clipboard.writeText(ussdCode).then(() => {
                    const button = event.target.closest('button');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                }).catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = ussdCode;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('USSD code copied to clipboard!');
                });
            }

            // Make copyUSSDCode globally accessible
            window.copyUSSDCode = copyUSSDCode;

            // Card number formatting
            document.getElementById('cardNumber')?.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                if (formattedValue !== e.target.value) {
                    e.target.value = formattedValue;
                }
            });

            // Card expiry formatting
            document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });

            // CVV validation
            document.getElementById('cardCVV')?.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });

            // Handle payment and vote submission
            proceedPayment.addEventListener('click', function() {
                // Add debugging first
                console.log('=== DEBUGGING VOTE SUBMISSION ===');

                const selectedPackage = document.querySelector('input[name="vote_package"]:checked');
                const selectedPayment = document.querySelector('input[name="payment_method"]:checked');

                console.log('Selected package element:', selectedPackage);
                console.log('Selected payment element:', selectedPayment);
                console.log('Package value:', selectedPackage ? selectedPackage.value : 'NULL');
                console.log('Payment value:', selectedPayment ? selectedPayment.value : 'NULL');
                console.log('Current nominee ID:', currentNomineeId);

                if (!currentNomineeId) {
                    alert('No nominee selected');
                    return;
                }

                if (!selectedPackage) {
                    alert('Please select a vote package');
                    return;
                }

                if (!selectedPayment) {
                    // Auto-select online payment if not selected
                    const onlinePayment = document.getElementById('payment_online');
                    if (onlinePayment) {
                        onlinePayment.checked = true;
                        console.log('Auto-selected online payment method');
                    } else {
                        alert('Payment method selection error. Please refresh the page.');
                        return;
                    }
                }

                // Payment method specific validation
                let paymentData = {
                    nominee_id: currentNomineeId,
                    vote_count: parseInt(selectedPackage.value),
                    payment_method: selectedPayment.value,
                    total_amount: parseFloat(selectedPackage.dataset.price)
                };

                console.log('Payment data object:', paymentData);

                if (selectedPayment.value === 'mobile_money') {
                    const phoneNumber = document.getElementById('voterPhone').value.trim();
                    if (!phoneNumber) {
                        alert('Please enter your phone number');
                        return;
                    }

                    // Ghana phone number validation (+233 format)
                    const phoneRegex = /^(\+233|0)[2-9][0-9]{8}$/;
                    if (!phoneRegex.test(phoneNumber)) {
                        alert('Please enter a valid Ghana phone number (e.g., +233241234567 or 0241234567)');
                        return;
                    }
                    paymentData.phone_number = phoneNumber;

                } else if (selectedPayment.value === 'ussd') {
                    // For USSD, we still need a phone number for transaction tracking
                    const phoneNumber = document.getElementById('voterPhone').value.trim();
                    if (!phoneNumber) {
                        alert('Please enter your phone number');
                        return;
                    }

                    // Ghana phone number validation (+233 format)
                    const phoneRegex = /^(\+233|0)[2-9][0-9]{8}$/;
                    if (!phoneRegex.test(phoneNumber)) {
                        alert('Please enter a valid Ghana phone number (e.g., +233241234567 or 0241234567)');
                        return;
                    }
                    paymentData.phone_number = phoneNumber;
                } else if (selectedPayment.value === 'online') {
                    // No need for phone number validation for online payment
                }

                // Disable button and show loading
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                // Create form data to bypass ModSecurity
                const formData = new FormData();
                Object.keys(paymentData).forEach(key => {
                    formData.append(key, paymentData[key]);
                    console.log('FormData appended:', key, '=', paymentData[key]);
                });

                // Debug: Log all FormData entries
                console.log('=== FormData Contents ===');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }

                // Submit vote via AJAX using form data
                fetch('actions/hubtel-vote-submit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Server response:', data);

                    if (data.success) {
                        if (data.checkout_url) {
                            // Redirect to Hubtel checkout page
                            window.location.href = data.checkout_url;
                        } else {
                            showSuccessModal('Payment initiated successfully!');
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                    }
                })
                .catch(error => {
                    console.error('Vote submission error:', error);

                    // Better error handling for debugging
                    fetch('actions/hubtel-vote-submit.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        return response.text();
                    })
                    .then(text => {
                        console.error('Server response body:', text);
                        alert('Server error (Status): ' + text);
                    })
                    .catch(err => {
                        console.error('Network error:', err);
                        alert('Network error occurred. Please check your connection and try again.');
                    });
                })
                .finally(() => {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = originalText;
                });
            });

            // Payment method selection function
            window.selectPaymentMethod = function(method) {
                const radioButton = document.getElementById('payment_' + method);
                if (radioButton) {
                    radioButton.checked = true;
                    console.log('Payment method selected:', method);
                }
            };

            // Close modal when clicking outside
            voteModal.addEventListener('click', function(e) {
                if (e.target === voteModal) {
                    closeVoteModal();
                }
            });

            // OTP Verification Modal Functions
            function showOTPVerification(data) {
                closeVoteModal();

                // Create OTP modal
                const otpModal = document.createElement('div');
                otpModal.id = 'otpModal';
                otpModal.className = 'modal';
                otpModal.innerHTML = `
                    <div class="modal-content" style="max-width: 400px;">
                        <div class="modal-header">
                            <h3>OTP Verification</h3>
                            <button type="button" class="close-btn" onclick="closeOTPModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>An OTP has been sent to your phone number:</p>
                            <p><strong>${data.phone_number}</strong></p>
                            <div class="form-group">
                                <label for="otpCode" class="form-label">Enter OTP Code:</label>
                                <input type="text" id="otpCode" class="form-control" maxlength="6" placeholder="Enter 6-digit code">
                            </div>
                            <div id="otpTimer" class="form-text">Code expires in: <span id="countdown">300</span> seconds</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeOTPModal()">Cancel</button>
                            <button type="button" class="btn btn-primary" id="verifyOTP">Verify OTP</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(otpModal);

                // Start countdown timer
                let timeLeft = 300; // 5 minutes
                const countdownElement = document.getElementById('countdown');
                const timer = setInterval(() => {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        alert('OTP has expired. Please try again.');
                        closeOTPModal();
                    }
                }, 1000);

                // Handle OTP verification
                document.getElementById('verifyOTP').addEventListener('click', function() {
                    const otpCode = document.getElementById('otpCode').value.trim();
                    if (!otpCode || otpCode.length !== 6) {
                        alert('Please enter a valid 6-digit OTP code');
                        return;
                    }

                    // Disable button
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

                    // Verify OTP
                    const formData = new FormData();
                    formData.append('transaction_id', data.transaction_id);
                    formData.append('otp_code', otpCode);

                    fetch('actions/verify-hubtel-otp.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(otpData => {
                        if (otpData.success) {
                            clearInterval(timer);
                            closeOTPModal();
                            showVoteSuccess(otpData);
                        } else {
                            alert('OTP verification failed: ' + (otpData.message || 'Invalid code'));
                            this.disabled = false;
                            this.innerHTML = 'Verify OTP';
                        }
                    })
                    .catch(error => {
                        console.error('OTP verification error:', error);
                        alert('Network error occurred. Please try again.');
                        this.disabled = false;
                        this.innerHTML = 'Verify OTP';
                    });
                });
            }

            // USSD Instructions Modal Functions
            function showUSSDInstructions(data) {
                closeVoteModal();

                // Create USSD modal
                const ussdModal = document.createElement('div');
                ussdModal.id = 'ussdModal';
                ussdModal.className = 'modal';
                ussdModal.innerHTML = `
                    <div class="modal-content" style="max-width: 500px;">
                        <div class="modal-header">
                            <h3>USSD Payment Instructions</h3>
                            <button type="button" class="close-btn" onclick="closeUSSDModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="ussd-instructions">
                                <p><strong>Follow these steps to complete your payment:</strong></p>
                                <ol>
                                    <li>Dial the USSD code below on your mobile phone</li>
                                    <li>Follow the prompts on your phone screen</li>
                                    <li>Enter the payment amount: <strong>GHS ${data.amount}</strong></li>
                                    <li>Complete the payment process</li>
                                </ol>

                                <div class="ussd-code-section">
                                    <label class="form-label">USSD Code:</label>
                                    <div class="ussd-code-display">
                                        <span id="ussdCodeDisplay">${data.ussd_code || '*713#'}</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyUSSDCode()">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Your vote will be recorded automatically once payment is confirmed.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeUSSDModal()">Close</button>
                            <button type="button" class="btn btn-primary" id="checkPaymentStatus">Check Payment Status</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(ussdModal);

                // Handle payment status checking
                document.getElementById('checkPaymentStatus').addEventListener('click', function() {
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

                    const formData = new FormData();
                    formData.append('transaction_id', data.transaction_id);

                    fetch('actions/check-payment-status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(statusData => {
                        if (statusData.success && statusData.status === 'completed') {
                            closeUSSDModal();
                            showVoteSuccess(statusData);
                        } else if (statusData.success && statusData.status === 'pending') {
                            alert('Payment is still pending. Please complete the USSD payment process.');
                        } else {
                            alert('Payment not found or failed. Please try again.');
                        }
                        this.disabled = false;
                        this.innerHTML = 'Check Payment Status';
                    })
                    .catch(error => {
                        console.error('Payment status check error:', error);
                        alert('Network error occurred. Please try again.');
                        this.disabled = false;
                        this.innerHTML = 'Check Payment Status';
                    });
                });
            }

            // Success Modal Function
            function showVoteSuccess(data) {
                const successModal = document.createElement('div');
                successModal.id = 'successModal';
                successModal.className = 'modal';
                successModal.innerHTML = `
                    <div class="modal-content" style="max-width: 400px;">
                        <div class="modal-header">
                            <h3><i class="fas fa-check-circle text-success"></i> Vote Successful!</h3>
                        </div>
                        <div class="modal-body text-center">
                            <p><strong>Your vote has been recorded successfully!</strong></p>
                            <p>Transaction ID: <strong>${data.transaction_id || 'N/A'}</strong></p>
                            <p>Thank you for participating in the voting!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="closeSuccessModal()">OK</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(successModal);
            }

            // Modal close functions
            window.closeOTPModal = function() {
                const modal = document.getElementById('otpModal');
                if (modal) modal.remove();
            };

            window.closeUSSDModal = function() {
                const modal = document.getElementById('ussdModal');
                if (modal) modal.remove();
            };

            window.closeSuccessModal = function() {
                const modal = document.getElementById('successModal');
                if (modal) modal.remove();
                location.reload(); // Refresh to show updated vote counts
            };

            // Update copyUSSDCode function for USSD modal
            window.copyUSSDCode = function() {
                const ussdCode = document.getElementById('ussdCodeDisplay').textContent;
                navigator.clipboard.writeText(ussdCode).then(() => {
                    const button = event.target.closest('button');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                }).catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = ussdCode;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('USSD code copied to clipboard!');
                });
            };
        });
    </script>

</body>
</html>
