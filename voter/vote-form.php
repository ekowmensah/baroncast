<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site-settings.php';

$database = new Database();
$db = $database->getConnection();

// Get parameters from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$nominee_id = isset($_GET['nominee_id']) ? (int)$_GET['nominee_id'] : 0;

if (!$event_id || !$nominee_id) {
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

// Fetch nominee details
try {
    $query = "SELECT n.*, c.name as category_name 
              FROM nominees n 
              JOIN categories c ON n.category_id = c.id 
              WHERE n.id = ? AND c.event_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$nominee_id, $event_id]);
    $nominee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nominee) {
        header('Location: event.php?id=' . $event_id);
        exit();
    }
} catch (PDOException $e) {
    header('Location: event.php?id=' . $event_id);
    exit();
}

// Set default payment settings (vote_cost column doesn't exist in events table)
$paymentSettings = [
    'vote_cost' => '1.00',
    'ussd_code' => '*123*456#',
    'enable_card_payments' => '0' // Disabled since we're Hubtel-only now
];

$siteSettings = new SiteSettings();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Vote - <?php echo htmlspecialchars($siteSettings->getSiteName()); ?></title>
    <meta name="description" content="Cast your vote for <?php echo htmlspecialchars($nominee['name']); ?>">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="assets/css/hubtel-modals.css" rel="stylesheet">
    
    <style>
        /* Vote Form Styles with Dark/Light Mode Support */
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --primary-light: rgba(79, 70, 229, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-muted: #9ca3af;
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            --border-color: #374151;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        
        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Header Styles */
        .voter-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar {
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
        
        .theme-toggle {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }
        
        /* Main Content */
        .main-content {
            padding: 2rem;
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .vote-form-container {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            padding: 3rem;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .form-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .vote-form {
            display: grid;
            gap: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .form-input,
        .form-select {
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
            min-height: 48px;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .form-input[readonly] {
            background: var(--bg-secondary);
            color: var(--text-secondary);
        }
        
        .cost-info {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        
        .otp-notice {
            background: var(--primary-light);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-top: 1rem;
        }
        
        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }
        
        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .submit-btn:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .vote-form-container {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-title {
                font-size: 1.25rem;
            }
        }
        
        /* Loading and Success States */
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .loading.show {
            display: block;
        }
        
        .spinner {
            border: 3px solid var(--border-color);
            border-top: 3px solid var(--primary-color);
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
        
        .success-message {
            display: none;
            text-align: center;
            padding: 2rem;
            color: var(--success-color);
        }
        
        .success-message.show {
            display: block;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 1rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }

        .payment-option {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
        }

        .payment-option:hover {
            border-color: #007bff;
            background-color: #f8f9ff;
        }

        .payment-option input[type="radio"]:checked + i {
            color: #007bff;
        }

        .payment-option:has(input[type="radio"]:checked) {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        
        .pending-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-primary);
        }
        
        .pending-message h3 {
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        
        .pending-message p {
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }
        
        .pending-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .pending-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .pending-actions .submit-btn {
                margin-left: 0 !important;
                margin-top: 0.5rem;
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
                        <?php echo SiteSettings::getLogoOnlyHtml('brand-logo', true); ?>
                    </a>
                </div>
                
                <button id="theme-toggle" class="theme-toggle">
                    <i id="theme-icon" class="fas fa-moon"></i>
                    <span id="theme-text">Dark</span>
                </button>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="vote-form-container">
                <div class="form-header">
                    <h1 class="form-title">Cast Your Vote</h1>
                    <p class="form-subtitle">
                        Please fill in all the input fields<br>
                        Note: Nominee Name and Event Name are filled automatically and<br>
                        depending on your previous selections
                    </p>
                </div>

                <form id="voteForm" method="POST">
                    <input type="hidden" name="nominee_id" value="<?php echo $nominee_id; ?>">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nominee</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($nominee['name']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($nominee['category_name']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Number of Votes</label>
                            <input type="number" name="vote_count" class="form-input" placeholder="Enter Number Of Votes" min="1" value="1" required>
                            <div class="cost-info">Cost per vote is <?php echo SiteSettings::getCurrencySymbol(); ?> <?php echo number_format($paymentSettings['vote_cost'], 2); ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="voter_name" class="form-input" placeholder="Enter your full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-input" placeholder="0245152060" required>
                            <div class="cost-info">For payment and notifications</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email (Optional)</label>
                            <input type="email" name="email" class="form-input" placeholder="your@email.com">
                        </div>
                    </div>

                    <div class="otp-notice">
                        <i class="fas fa-info-circle"></i>
                        You will be redirected to Hubtel's secure payment page to complete your payment using Mobile Money, Bank Card, or other supported methods.
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Payment Method</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 10px 0;">
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="payproxy" checked style="margin-right: 10px;">
                                <i class="fas fa-credit-card"></i> Secure Checkout (Recommended)
                                <small style="display: block; color: #666; margin-top: 5px;">Mobile Money, Cards, Bank Transfers</small>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="paymentMethod" value="direct" style="margin-right: 10px;">
                                <i class="fas fa-mobile-alt"></i> Direct Mobile Money
                                <small style="display: block; color: #666; margin-top: 5px;">Direct mobile money payment</small>
                            </label>
                        </div>
                    </div>
<!--
                    <div class="form-group full-width">
                        <label class="form-label">
                            <input type="checkbox" id="testMode" style="margin-right: 10px;">
                            Test Mode (No real payment - for testing only)
                        </label>
                    </div>  -->

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-vote-yea"></i>
                        CAST VOTE(S)
                    </button>

                    <div class="error-message" id="errorMessage"></div>
                </form>

                <!-- Loading State -->
                <div class="loading" id="loadingState">
                    <div class="spinner"></div>
                    <p>Processing your vote...</p>
                </div>


                <!-- Success State -->
                <div class="success-message" id="successMessage">
                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>Vote Cast Successfully!</h3>
                    <p>Your vote has been recorded and payment processed.</p>
                    <button onclick="window.location.href='event.php?id=<?php echo $event_id; ?>'" class="submit-btn">
                        Back to Event
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const voteForm = document.getElementById('voteForm');
            const loadingState = document.getElementById('loadingState');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            const voteCountInput = document.querySelector('input[name="vote_count"]');
            const voteCost = <?php echo $paymentSettings['vote_cost']; ?>;

            // Update cost display when vote count changes
            voteCountInput.addEventListener('input', function() {
                const count = parseInt(this.value) || 0;
                const total = count * voteCost;
                const costInfo = this.nextElementSibling;
                costInfo.textContent = `Cost per vote is <?php echo SiteSettings::getCurrencySymbol(); ?> ${voteCost.toFixed(2)} | Total: <?php echo SiteSettings::getCurrencySymbol(); ?> ${total.toFixed(2)}`;
            });

            // Global variables for payment tracking
            let currentPaymentId = null;
            let paymentCheckInterval = null;
            
            // Handle form submission
            voteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(voteForm);
                const voteCount = parseInt(formData.get('vote_count'));
                const totalAmount = voteCount * voteCost;
                
                // Validate required fields
                const voterName = formData.get('voter_name').trim();
                const phoneNumber = formData.get('phone_number').trim();
                
                if (!voterName || !phoneNumber || !voteCount || voteCount < 1) {
                    showError('Please fill in all required fields.');
                    return;
                }
                
                // Show loading state
                voteForm.style.display = 'none';
                loadingState.classList.add('show');
                errorMessage.classList.remove('show');
                
                // Submit to Hubtel Direct Receive Money
                submitHubtelVote(formData);
            });
            
            // Submit vote using selected payment method
            function submitHubtelVote(formData) {
                // Check if test mode is enabled
                const testMode = document.getElementById('testMode').checked;
                
                // Get selected payment method
                const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                
                let endpoint;
                if (testMode) {
                    endpoint = 'actions/test-vote-submit.php';
                } else if (paymentMethod === 'payproxy') {
                    endpoint = 'actions/hubtel-payproxy-vote-submit.php';
                } else {
                    endpoint = 'actions/hubtel-vote-submit.php';
                }
                
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingState.classList.remove('show');
                    
                    if (data.success) {
                        if (data.status === 'completed') {
                            // Payment completed immediately (test mode)
                            showSuccess(data);
                        } else if (data.status === 'checkout_created' && data.checkout_url) {
                            // PayProxy checkout created - redirect to payment page
                            showPayProxyCheckout(data);
                        } else if (data.status === 'pending') {
                            // Payment pending - show pending status
                            showPendingPayment(data);
                        } else {
                            showError(data.message || 'Vote submission failed. Please try again.');
                        }
                    } else {
                        showError(data.message || 'Vote submission failed. Please try again.');
                    }
                })
                .catch(error => {
                    loadingState.classList.remove('show');
                    showError('Network error. Please check your connection and try again.');
                });
            }
            
            // Show PayProxy checkout
            function showPayProxyCheckout(data) {
                voteForm.style.display = 'none';
                
                // Create PayProxy checkout message
                const checkoutMessage = document.createElement('div');
                checkoutMessage.className = 'pending-message';
                checkoutMessage.innerHTML = `
                    <i class="fas fa-credit-card" style="font-size: 3rem; margin-bottom: 1rem; color: #007bff;"></i>
                    <h3>Secure Payment Checkout Created!</h3>
                    <p>Your vote for <strong>${data.nominee_name}</strong> is ready for payment.</p>
                    <p><strong>Amount:</strong> ₵${data.amount} for ${data.vote_count} vote(s)</p>
                    <div style="margin: 20px 0;">
                        <a href="${data.checkout_url}" target="_blank" style="
                            display: inline-block;
                            background: #007bff;
                            color: white;
                            padding: 15px 30px;
                            text-decoration: none;
                            border-radius: 8px;
                            font-weight: bold;
                            font-size: 18px;
                        ">
                            <i class="fas fa-external-link-alt"></i> Complete Payment
                        </a>
                    </div>
                    <p style="color: #666; font-size: 14px;">
                        You'll be redirected to Hubtel's secure payment page.<br>
                        Payment options: Mobile Money, Bank Cards, Bank Transfers
                    </p>
                    <p style="color: #666; font-size: 12px;">
                        Transaction Reference: ${data.transaction_ref}
                    </p>
                `;
                
                // Replace the form with checkout message
                document.querySelector('.vote-form-container').appendChild(checkoutMessage);
            }

            // Show success message
            function showSuccess(data) {
                voteForm.style.display = 'none';
                const testModeNote = data.test_mode ? ' (TEST MODE - No real payment processed)' : '';
                successMessage.querySelector('h3').textContent = 'Vote Cast Successfully!' + testModeNote;
                successMessage.querySelector('p').textContent = `Your ${data.vote_count} vote(s) for ${data.nominee_name} have been recorded and payment of ₵${data.amount} has been processed.`;
                successMessage.classList.add('show');
            }
            
            // Show pending payment status
            function showPendingPayment(data) {
                voteForm.style.display = 'none';
                
                // Create pending payment message
                const pendingMessage = document.createElement('div');
                pendingMessage.className = 'pending-message';
                pendingMessage.innerHTML = `
                    <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem; color: #f59e0b;"></i>
                    <h3>Payment Pending</h3>
                    <p>Your payment of ₵${data.amount} is being processed.</p>
                    <p>You should receive a mobile money prompt on ${data.transaction_ref.replace('ECAST_', '').split('_')[0]}. Please complete the payment to confirm your vote.</p>
                    <div class="pending-actions">
                        <button type="button" class="submit-btn" onclick="checkPaymentStatus('${data.transaction_ref}')">
                            <i class="fas fa-sync"></i> Check Payment Status
                        </button>
                        <button type="button" class="submit-btn" style="background: #6b7280; margin-left: 1rem;" onclick="window.location.href='event.php?id=<?php echo $event_id; ?>'">
                            <i class="fas fa-arrow-left"></i> Back to Event
                        </button>
                    </div>
                `;
                
                document.querySelector('.vote-form-container').appendChild(pendingMessage);
                
                // Auto-check payment status every 10 seconds
                const statusCheckInterval = setInterval(() => {
                    checkPaymentStatus(data.transaction_ref, statusCheckInterval);
                }, 10000);
                
                // Stop checking after 5 minutes
                setTimeout(() => {
                    clearInterval(statusCheckInterval);
                }, 300000);
            }
            
            // Handle URL parameters for payment return
            const urlParams = new URLSearchParams(window.location.search);
            const paymentStatus = urlParams.get('payment_status');
            const transactionId = urlParams.get('transaction_id');
            
            if (paymentStatus === 'success' && transactionId) {
                // Payment was successful, show success message
                voteForm.style.display = 'none';
                successMessage.classList.add('show');
            } else if (paymentStatus === 'cancelled') {
                // Payment was cancelled, show error
                showError('Payment was cancelled. Please try again.');
            } else if (paymentStatus === 'failed') {
                // Payment failed, show error
                showError('Payment failed. Please try again.');
            }
            
            // Show Error Message
            function showError(message) {
                voteForm.style.display = 'block';
                errorMessage.textContent = message;
                errorMessage.classList.add('show');
            }
        });
        
        // Global function for checking payment status
        function checkPaymentStatus(transactionRef, intervalId = null) {
            fetch('actions/check-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'transaction_ref=' + encodeURIComponent(transactionRef)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'completed') {
                        // Payment completed, clear interval and show success
                        if (intervalId) clearInterval(intervalId);
                        
                        // Remove pending message and show success
                        const pendingMessage = document.querySelector('.pending-message');
                        if (pendingMessage) pendingMessage.remove();
                        
                        document.querySelector('.vote-form-container').innerHTML = `
                            <div class="success-message show">
                                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #10b981;"></i>
                                <h3>Payment Confirmed!</h3>
                                <p>Your vote has been successfully recorded and payment confirmed.</p>
                                <button onclick="window.location.href='event.php?id=<?php echo $event_id; ?>'" class="submit-btn">
                                    Back to Event
                                </button>
                            </div>
                        `;
                    } else if (data.status === 'failed') {
                        // Payment failed, clear interval and show error
                        if (intervalId) clearInterval(intervalId);
                        
                        const pendingMessage = document.querySelector('.pending-message');
                        if (pendingMessage) {
                            pendingMessage.innerHTML = `
                                <i class="fas fa-times-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #ef4444;"></i>
                                <h3>Payment Failed</h3>
                                <p>Your payment could not be processed. Please try again.</p>
                                <button type="button" class="submit-btn" onclick="window.location.reload()">
                                    <i class="fas fa-retry"></i> Try Again
                                </button>
                            `;
                        }
                    }
                    // If still pending, continue checking (interval will handle it)
                } else {
                    console.error('Status check failed:', data.message);
                }
            })
            .catch(error => {
                console.error('Status check error:', error);
            });
        }
    </script>
</body>
</html>
