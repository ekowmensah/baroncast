<?php
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

// Vote packages system removed - using single vote form instead

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
        $nominee_query = "SELECT n.*, COUNT(v.id) as vote_count 
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
        /* Payment Method Styles */
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card-bg);
            color: var(--text-color);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* USSD Styles */
        .ussd-info {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-top: 10px;
        }
        
        .ussd-info h4 {
            margin: 0 0 15px 0;
            color: var(--primary-color);
            font-size: 16px;
        }
        
        .ussd-code {
            margin-bottom: 20px;
        }
        
        .ussd-code p {
            margin: 0 0 10px 0;
            color: var(--text-color);
        }
        
        .code-display {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-color);
            border: 2px dashed var(--primary-color);
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .code-display span {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
            flex: 1;
        }
        
        .ussd-steps {
            background: var(--bg-color);
            border-radius: 8px;
            padding: 15px;
        }
        
        .ussd-steps ol {
            margin: 0;
            padding-left: 20px;
        }
        
        .ussd-steps li {
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .ussd-amount {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .code-display {
                flex-direction: column;
                text-align: center;
            }
            
            .code-display span {
                font-size: 16px;
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
                                        <div class="nominee-card">
                                            <div class="nominee-image">
                                                <?php if (!empty($nominee['image']) && file_exists(__DIR__ . '/../uploads/nominees/' . $nominee['image'])): ?>
                                                    <img src="../uploads/nominees/<?php echo htmlspecialchars($nominee['image']); ?>" alt="<?php echo htmlspecialchars($nominee['name']); ?>">
                                                <?php else: ?>
                                                    <div class="nominee-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="nominee-info">
                                                <h3 class="nominee-name"><?php echo htmlspecialchars($nominee['name']); ?></h3>
                                                <?php if ($nominee['description']): ?>
                                                    <p class="nominee-description"><?php echo htmlspecialchars(substr($nominee['description'], 0, 100)) . '...'; ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="nominee-stats">
                                                    <div class="vote-count">
                                                        <i class="fas fa-vote-yea"></i>
                                                        <span><?php echo number_format($nominee['vote_count']); ?> votes</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="nominee-actions">
                                                <a href="vote-form.php?event_id=<?php echo $event_id; ?>&nominee_id=<?php echo $nominee['id']; ?>" 
                                                   class="btn btn-primary vote-btn">
                                                    <i class="fas fa-vote-yea"></i>
                                                    Vote - <?php echo SiteSettings::getCurrencySymbol(); ?> <?php echo number_format($paymentSettings['vote_cost'], 2); ?> per vote
                                                </a>
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
                <button type="button" class="btn btn-secondary" onclick="closeVoteModal()">Cancel</button>
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
            const closeModal = document.getElementById('closeModal');
            const cancelVote = document.getElementById('cancelVote');
            const proceedPayment = document.getElementById('proceedPayment');
            const voteButtons = document.querySelectorAll('.vote-btn');
            
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
            
            closeModal.addEventListener('click', closeVoteModal);
            cancelVote.addEventListener('click', closeVoteModal);
            
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
                seconds--;
            }, 1000);
        }
    </script>

    <style>
        /* Event page specific styles */
        .event-details-section {
            padding: 3rem 0;
            background: var(--bg-secondary);
        }
        
        .event-container {
            background: var(--bg-primary);
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin: 0 auto;
            max-width: 1200px;
        }
        
        .event-header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2rem;
        }
        
        .event-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .event-description {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .voting-section {
            padding: 3rem 0;
        }
        
        .category-section {
            margin-bottom: 4rem;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .category-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .category-stats {
            display: flex;
            gap: 1.5rem;
        }
        
        .category-stats .stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }
        
        .nominees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .nominee-card {
            background: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .nominee-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .nominee-image {
            height: 200px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nominee-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .nominee-placeholder {
            font-size: 3rem;
            color: var(--text-secondary);
        }
        
        .nominee-info {
            padding: 1.5rem;
        }
        
        .nominee-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .nominee-description {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .nominee-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .vote-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .nominee-actions {
            padding: 1.5rem;
            background: var(--bg-secondary);
        }
        
        .vote-btn {
            width: 100%;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        
        /* Vote Success Popup Styles */
        .vote-success-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }
        
        .success-content {
            background: var(--card-bg, white);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .success-content h3 {
            color: var(--text-primary, #333);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .success-content p {
            color: var(--text-secondary, #666);
            margin-bottom: 0.5rem;
        }
        
        .success-details {
            background: var(--bg-secondary, #f8f9fa);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1.5rem 0;
            border-left: 4px solid #28a745;
        }
        
        .success-details p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* OTP Verification Popup Styles */
        .otp-verification-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2500;
            animation: fadeIn 0.3s ease;
        }
        
        .otp-content {
            background: var(--card-bg, white);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        .otp-header h3 {
            color: var(--text-primary, #333);
            margin-bottom: 0.5rem;
            font-size: 1.4rem;
        }
        
        .otp-header p {
            color: var(--text-secondary, #666);
            margin-bottom: 1.5rem;
        }
        
        .otp-input-group {
            margin-bottom: 1.5rem;
        }
        
        .otp-input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary, #333);
            font-weight: 500;
        }
        
        .otp-input {
            width: 100%;
            padding: 1rem;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            border: 2px solid var(--border-color, #ddd);
            border-radius: 0.5rem;
            background: var(--input-bg, white);
            color: var(--text-primary, #333);
        }
        
        .otp-input:focus {
            outline: none;
            border-color: var(--primary-color, #007bff);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        
        .payment-info {
            background: var(--bg-secondary, #f8f9fa);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid var(--primary-color, #007bff);
        }
        
        .provider-info, .amount-info {
            margin-bottom: 0.5rem;
            color: var(--text-primary, #333);
        }
        
        .otp-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1.5rem 0;
        }
        
        .otp-timer {
            color: var(--text-secondary, #666);
            font-size: 0.9rem;
        }
        
        /* USSD Instructions Popup Styles */
        .ussd-instructions-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2500;
            animation: fadeIn 0.3s ease;
        }
        
        .ussd-content {
            background: var(--card-bg, white);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        .ussd-header h3 {
            color: var(--text-primary, #333);
            margin-bottom: 0.5rem;
            font-size: 1.4rem;
            text-align: center;
        }
        
        .ussd-header p {
            color: var(--text-secondary, #666);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .ussd-steps {
            margin: 1.5rem 0;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--bg-secondary, #f8f9fa);
            border-radius: 0.5rem;
        }
        
        .step-number {
            background: var(--primary-color, #007bff);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
            color: var(--text-primary, #333);
        }
        
        .ussd-code {
            background: var(--dark-bg, #333);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin: 0.5rem 0;
            letter-spacing: 2px;
        }
        
        .transaction-info {
            background: var(--bg-secondary, #f8f9fa);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1.5rem 0;
            border-left: 4px solid var(--success-color, #28a745);
        }
        
        .transaction-info p {
            margin-bottom: 0.5rem;
            color: var(--text-primary, #333);
            font-size: 0.9rem;
        }
        
        .ussd-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        /* Payment Processing Popup Styles */
        .payment-processing-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2500;
            animation: fadeIn 0.3s ease;
        }
        
        .processing-content {
            background: var(--card-bg, white);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        .processing-icon {
            color: var(--primary-color, #007bff);
            margin-bottom: 1rem;
        }
        
        .processing-content h3 {
            color: var(--text-primary, #333);
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }
        
        .payment-details {
            background: var(--bg-secondary, #f8f9fa);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1.5rem 0;
            border-left: 4px solid var(--primary-color, #007bff);
        }
        
        .payment-details p {
            margin-bottom: 0.5rem;
            color: var(--text-primary, #333);
        }
        
        .processing-status {
            margin: 1.5rem 0;
        }
        
        .spinner {
            border: 3px solid var(--border-color, #f3f3f3);
            border-top: 3px solid var(--primary-color, #007bff);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .processing-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .otp-content, .ussd-content, .processing-content {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .otp-actions, .ussd-actions, .processing-actions {
                flex-direction: column;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                margin: 0 auto 1rem;
            }
            
            .ussd-code {
                font-size: 1rem;
                letter-spacing: 1px;
            }
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 0.5rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .vote-info {
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 0.375rem;
        }
        
        .vote-packages {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .package-option {
            cursor: pointer;
        }
        
        .package-option input {
            display: none;
        }
        
        .package-card {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.375rem;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .package-option input:checked + .package-card {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        
        .package-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .package-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .package-discount {
            font-size: 0.875rem;
            color: var(--success-color);
            font-weight: 500;
        }
        
        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .payment-option {
            cursor: pointer;
        }
        
        .payment-option input {
            display: none;
        }
        
        .payment-card {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.375rem;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .payment-option input:checked + .payment-card {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        
        .contact-info {
            margin-bottom: 1rem;
        }
        
        .contact-info label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .contact-info input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .modal-footer {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .modal-footer .btn {
            flex: 1;
        }
        
        /* Modal overlay styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .event-header-content {
                flex-direction: column;
            }
            
            .event-title {
                font-size: 2rem;
            }
            
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .nominees-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
