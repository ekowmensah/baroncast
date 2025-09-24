<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get current commission settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('commission_rate', 'minimum_withdrawal', 'withdrawal_fee')");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set defaults if not found
$commissionRate = $settings['commission_rate'] ?? '10';
$minimumWithdrawal = $settings['minimum_withdrawal'] ?? '10';
$withdrawalFee = $settings['withdrawal_fee'] ?? '0';

// Get commission statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as total_earnings,
        SUM(amount * (? / 100)) as total_commission,
        COUNT(*) as total_transactions
    FROM transactions 
    WHERE type = 'vote_payment' AND status = 'completed'
");
$stmt->execute([$commissionRate]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly commission data for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount * (? / 100)) as commission
    FROM transactions 
    WHERE type = 'vote_payment' AND status = 'completed'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$commissionRate]);
$monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Settings - E-Cast Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php 
            $pageTitle = "Commission Settings";
            include 'includes/header.php'; 
            ?>

            <!-- Content -->
            <div class="content">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Earnings</h6>
                                        <h3 class="card-title mb-0">GH₵<?= number_format($stats['total_earnings'] ?? 0, 2) ?></h3>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Commission</h6>
                                        <h3 class="card-title mb-0">GH₵<?= number_format($stats['total_commission'] ?? 0, 2) ?></h3>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-percentage fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Commission Rate</h6>
                                        <h3 class="card-title mb-0"><?= number_format($commissionRate, 1) ?>%</h3>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-cog fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Transactions</h6>
                                        <h3 class="card-title mb-0"><?= number_format($stats['total_transactions'] ?? 0) ?></h3>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-exchange-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Commission Settings Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cog me-2"></i>
                                    Commission Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="commissionForm">
                                    <div class="form-group mb-3">
                                        <label for="commissionRate" class="form-label">Commission Rate (%)</label>
                                        <input type="number" id="commissionRate" name="commission_rate" class="form-control" 
                                               value="<?= htmlspecialchars($commissionRate) ?>" 
                                               min="0" max="50" step="0.1" required>
                                        <small class="form-text text-muted">Percentage of each vote payment taken as platform commission</small>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="minimumWithdrawal" class="form-label">Minimum Withdrawal (GH₵)</label>
                                        <input type="number" id="minimumWithdrawal" name="minimum_withdrawal" class="form-control" 
                                               value="<?= htmlspecialchars($minimumWithdrawal) ?>" 
                                               min="1" step="0.01" required>
                                        <small class="form-text text-muted">Minimum amount organizers can withdraw</small>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="withdrawalFee" class="form-label">Withdrawal Fee (GH₵)</label>
                                        <input type="number" id="withdrawalFee" name="withdrawal_fee" class="form-control" 
                                               value="<?= htmlspecialchars($withdrawalFee) ?>" 
                                               min="0" step="0.01" required>
                                        <small class="form-text text-muted">Fixed fee charged for each withdrawal request</small>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>
                                            Save Settings
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="resetForm()">
                                            <i class="fas fa-undo me-2"></i>
                                            Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Commission Analytics Chart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Monthly Commission Earnings
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="commissionChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Commission Impact Calculator -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calculator me-2"></i>
                                    Commission Impact Calculator
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="calcAmount" class="form-label">Vote Amount (GH₵)</label>
                                            <input type="number" id="calcAmount" class="form-control" 
                                                   placeholder="Enter vote amount" min="0.01" step="0.01" 
                                                   onchange="calculateCommission()">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="calcRate" class="form-label">Commission Rate (%)</label>
                                            <input type="number" id="calcRate" class="form-control" 
                                                   value="<?= htmlspecialchars($commissionRate) ?>" 
                                                   min="0" max="50" step="0.1" onchange="calculateCommission()">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="calculation-results">
                                            <div class="result-item">
                                                <label class="form-label">Platform Commission</label>
                                                <div id="commissionAmount" class="result-value">GH₵0.00</div>
                                            </div>
                                            <div class="result-item">
                                                <label class="form-label">Organizer Receives</label>
                                                <div id="organizerAmount" class="result-value">GH₵0.00</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Commission form submission
        document.getElementById('commissionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('actions/update-commission-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Commission settings updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating settings');
            });
        });

        // Reset form function
        function resetForm() {
            document.getElementById('commissionForm').reset();
        }

        // Commission calculator
        function calculateCommission() {
            const amount = parseFloat(document.getElementById('calcAmount').value) || 0;
            const rate = parseFloat(document.getElementById('calcRate').value) || 0;
            
            const commission = (amount * rate) / 100;
            const organizerAmount = amount - commission;
            
            document.getElementById('commissionAmount').textContent = 'GH₵' + commission.toFixed(2);
            document.getElementById('organizerAmount').textContent = 'GH₵' + organizerAmount.toFixed(2);
        }

        // Initialize commission chart
        const ctx = document.getElementById('commissionChart').getContext('2d');
        const monthlyData = <?= json_encode($monthlyData) ?>;
        
        const labels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        
        const data = monthlyData.map(item => parseFloat(item.commission));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Commission Earnings (GH₵)',
                    data: data,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Monthly Commission Earnings Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return 'GH₵' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    </script>

    <style>
        .calculation-results {
            margin-top: 1.5rem;
        }
        
        .result-item {
            margin-bottom: 1rem;
        }
        
        .result-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            padding: 0.5rem;
            background-color: var(--bg-tertiary);
            border-radius: 0.375rem;
            text-align: center;
        }
        
        .form-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .card-header {
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
        }
        
        .card-title {
            color: var(--text-primary);
            font-weight: 600;
        }
    </style>
</body>
</html>
