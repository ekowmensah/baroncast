<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Calculate available balance for withdrawal
$availableBalance = 0;
$totalEarnings = 0;
$totalWithdrawn = 0;
$commissionRate = 10; // Default

try {
    // Get commission rate and minimum withdrawal
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('commission_rate', 'minimum_withdrawal')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $commissionRate = $settings['commission_rate'] ?? 10;
    $minimumWithdrawal = $settings['minimum_withdrawal'] ?? 10;
    
    // Calculate total earnings from completed transactions
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_earnings
        FROM transactions 
        WHERE organizer_id = ? AND type = 'vote_payment' AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $totalEarnings = $stmt->fetchColumn() ?: 0;
    
    // Calculate total withdrawn (completed withdrawals)
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(amount) as total_withdrawn
            FROM withdrawal_requests 
            WHERE organizer_id = ? AND status = 'completed'
        ");
        $stmt->execute([$user['id']]);
        $totalWithdrawn = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        $totalWithdrawn = 0;
        error_log("Error calculating withdrawal balance: " . $e->getMessage());
    }
    
    // Calculate net earnings after commission
    $commission = ($totalEarnings * $commissionRate) / 100;
    $netEarnings = $totalEarnings - $commission;
    $availableBalance = $netEarnings - $totalWithdrawn;
    
} catch(PDOException $e) {
    error_log("Error calculating withdrawal balance: " . $e->getMessage());
}

// Fetch withdrawal requests for the current organizer
try {
    $stmt = $pdo->prepare("
        SELECT * FROM withdrawal_requests 
        WHERE organizer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $withdrawals = [];
    error_log("Error fetching withdrawals: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw List - E-Cast Voting</title>
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
                        <i class="fas fa-chevron-up submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="transactions-menu" class="nav-submenu show">
                        <a href="votes-payments.php" class="nav-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Votes Payments</span>
                        </a>
                        <a href="withdrawal.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Withdraw List</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Withdraws</span>
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
                <!-- Balance Summary Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-value">GH₵<?= number_format($totalEarnings, 2) ?></div>
                        <div class="stat-label">Total Earnings</div>
                        <div class="stat-change">
                            <i class="fas fa-chart-line"></i>
                            From vote payments
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">GH₵<?= number_format(($totalEarnings * $commissionRate) / 100, 2) ?></div>
                        <div class="stat-label">Platform Commission (<?= $commissionRate ?>%)</div>
                        <div class="stat-change">
                            <i class="fas fa-percentage"></i>
                            System fee
                        </div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value">GH₵<?= number_format($totalWithdrawn, 2) ?></div>
                        <div class="stat-label">Total Withdrawn</div>
                        <div class="stat-change">
                            <i class="fas fa-money-bill-wave"></i>
                            Completed payouts
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">GH₵<?= number_format($availableBalance, 2) ?></div>
                        <div class="stat-label">Available Balance</div>
                        <div class="stat-change positive">
                            <i class="fas fa-wallet"></i>
                            Ready for withdrawal
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 mb-4">
                    <button class="btn btn-primary" onclick="openWithdrawalModal()" <?= $availableBalance <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i>
                        Request Withdrawal
                    </button>
                    <button class="btn btn-info" onclick="printWithdrawalStatement()">
                        <i class="fas fa-print"></i>
                        Print Withdrawal Statement
                    </button>
                    <button class="btn btn-success" onclick="exportWithdrawalStatement()">
                        <i class="fas fa-file-export"></i>
                        Export Withdrawal Statement
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
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Account Details</th>
                                        <th>Status</th>
                                        <th>Processed By</th>
                                        <th>Admin Notes</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($withdrawals)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Withdrawal Records Found</h5>
                                                    <p class="text-muted">Withdrawal requests will appear here once you request payouts from your earnings.</p>
                                                    <button class="btn btn-primary" onclick="openWithdrawalModal()" <?= $availableBalance <= 0 ? 'disabled' : '' ?>>
                                                        <i class="fas fa-plus"></i> Request New Withdrawal
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($withdrawals as $index => $withdrawal): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>GH₵<?= number_format($withdrawal['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?= ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($withdrawal['account_name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($withdrawal['account_number']) ?></small>
                                                        <?php if ($withdrawal['bank_name']): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($withdrawal['bank_name']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    switch($withdrawal['status']) {
                                                        case 'pending':
                                                            $statusClass = 'badge-warning';
                                                            $statusIcon = 'clock';
                                                            break;
                                                        case 'approved':
                                                            $statusClass = 'badge-info';
                                                            $statusIcon = 'check';
                                                            break;
                                                        case 'completed':
                                                            $statusClass = 'badge-success';
                                                            $statusIcon = 'check-circle';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'badge-danger';
                                                            $statusIcon = 'times-circle';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'badge-danger';
                                                            $statusIcon = 'exclamation-triangle';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge-secondary';
                                                            $statusIcon = 'question';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <i class="fas fa-<?= $statusIcon ?>"></i> <?= ucfirst($withdrawal['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($withdrawal['processed_by_name'] ?? 'Pending') ?></td>
                                                <td>
                                                    <?php if ($withdrawal['admin_notes']): ?>
                                                        <small><?= htmlspecialchars($withdrawal['admin_notes']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No notes</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline" onclick="viewWithdrawal(<?= $withdrawal['id'] ?>)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($withdrawal['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="cancelWithdrawal(<?= $withdrawal['id'] ?>)" title="Cancel Request">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
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
                                    <?php if (!empty($withdrawals)): ?>
                                        <small class="text-muted">Showing <?= count($withdrawals) ?> of <?= count($withdrawals) ?> entries</small>
                                    <?php else: ?>
                                        <small class="text-muted">No entries to display</small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($withdrawals) && count($withdrawals) > 10): ?>
                                <nav>
                                    <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                        <li><a href="#" class="btn btn-outline btn-sm">Previous</a></li>
                                        <li><a href="#" class="btn btn-primary btn-sm">1</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm">Next</a></li>
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

    <!-- Withdrawal Request Modal -->
    <div id="withdrawalModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Withdrawal</h3>
                <button type="button" class="close-btn" onclick="closeWithdrawalModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="withdrawalForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Available Balance</label>
                            <div class="balance-display">
                                <span class="balance-amount">GH₵<?= number_format($availableBalance, 2) ?></span>
                                <small class="text-muted">Maximum withdrawal amount</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="withdrawalAmount" class="form-label">Withdrawal Amount *</label>
                            <input type="number" id="withdrawalAmount" name="amount" class="form-control" 
                                   placeholder="Enter amount" required min="<?= $minimumWithdrawal ?>" max="<?= $availableBalance ?>" step="0.01">
                            <small class="form-text text-muted">Minimum withdrawal: GH₵<?= number_format($minimumWithdrawal, 2) ?></small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="withdrawalMethod" class="form-label">Withdrawal Method *</label>
                            <select id="withdrawalMethod" name="withdrawal_method" class="form-control" required onchange="toggleMethodFields()">
                                <option value="">Select withdrawal method</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Mobile Money Fields -->
                    <div id="mobileMoneyFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="mobileNetwork" class="form-label">Mobile Network *</label>
                                <select id="mobileNetwork" name="mobile_network" class="form-control">
                                    <option value="">Select network</option>
                                    <option value="MTN">MTN Mobile Money</option>
                                    <option value="Vodafone">Vodafone Cash</option>
                                    <option value="AirtelTigo">AirtelTigo Money</option>
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label for="mobileNumber" class="form-label">Mobile Number *</label>
                                <input type="tel" id="mobileNumber" name="account_number" class="form-control" 
                                       placeholder="0XX XXX XXXX" pattern="[0-9]{10}">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="accountName" class="form-label">Account Name *</label>
                                <input type="text" id="accountName" name="account_name" class="form-control" 
                                       placeholder="Name registered with mobile money account" maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Fields -->
                    <div id="bankFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="bankName" class="form-label">Bank Name *</label>
                                <select id="bankName" name="bank_name" class="form-control" onchange="setBankCode()">
                                    <option value="">Select bank</option>
                                    <option value="Access Bank" data-code="2001">Access Bank</option>
                                    <option value="Ecobank" data-code="2002">Ecobank Ghana</option>
                                    <option value="Fidelity Bank" data-code="2003">Fidelity Bank</option>
                                    <option value="GCB Bank" data-code="2004">GCB Bank</option>
                                    <option value="Stanbic Bank" data-code="2005">Stanbic Bank</option>
                                    <option value="Standard Chartered" data-code="2006">Standard Chartered</option>
                                    <option value="UBA Ghana" data-code="2007">UBA Ghana</option>
                                    <option value="Zenith Bank" data-code="2008">Zenith Bank</option>
                                </select>
                                <input type="hidden" id="bankCode" name="bank_code">
                            </div>
                            <div class="form-group col-6">
                                <label for="bankAccountNumber" class="form-label">Account Number *</label>
                                <input type="text" id="bankAccountNumber" name="account_number" class="form-control" 
                                       placeholder="Bank account number" maxlength="20">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bankAccountName" class="form-label">Account Name *</label>
                                <input type="text" id="bankAccountName" name="account_name" class="form-control" 
                                       placeholder="Name as registered with bank" maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeWithdrawalModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Withdrawal modal functions
        function openWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeWithdrawalModal() {
            document.getElementById('withdrawalModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('withdrawalForm').reset();
            document.getElementById('mobileMoneyFields').style.display = 'none';
            document.getElementById('bankFields').style.display = 'none';
        }

        function toggleMethodFields() {
            const method = document.getElementById('withdrawalMethod').value;
            const mobileFields = document.getElementById('mobileMoneyFields');
            const bankFields = document.getElementById('bankFields');
            
            mobileFields.style.display = 'none';
            bankFields.style.display = 'none';
            
            if (method === 'mobile_money') {
                mobileFields.style.display = 'block';
            } else if (method === 'bank') {
                bankFields.style.display = 'block';
            }
        }

        function setBankCode() {
            const bankSelect = document.getElementById('bankName');
            const selectedOption = bankSelect.options[bankSelect.selectedIndex];
            const bankCode = selectedOption.getAttribute('data-code');
            document.getElementById('bankCode').value = bankCode || '';
        }

        // Print withdrawal statement
        function printWithdrawalStatement() {
            window.print();
        }

        // Export withdrawal statement
        function exportWithdrawalStatement() {
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Amount,Method,Account Details,Status,Processed By,Admin Notes,Date\n";
            
            <?php foreach ($withdrawals as $withdrawal): ?>
            csvContent += "<?= $withdrawal['amount'] ?>,<?= addslashes(ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method']))) ?>,<?= addslashes($withdrawal['account_name'] . ' - ' . $withdrawal['account_number']) ?>,<?= addslashes(ucfirst($withdrawal['status'])) ?>,<?= addslashes($withdrawal['processed_by_name'] ?? 'Pending') ?>,<?= addslashes($withdrawal['admin_notes'] ?? 'No notes') ?>,<?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?>\n";
            <?php endforeach; ?>
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "withdrawal_statement_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // View withdrawal details
        function viewWithdrawal(id) {
            // Implementation for viewing withdrawal details
            alert('View withdrawal details for ID: ' + id);
        }

        // Cancel withdrawal request
        function cancelWithdrawal(id) {
            if (confirm('Are you sure you want to cancel this withdrawal request?')) {
                // Implementation for canceling withdrawal
                alert('Cancel withdrawal request for ID: ' + id);
            }
        }

        // Handle withdrawal form submission
        document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const method = formData.get('withdrawal_method');
            
            // Validate required fields based on method
            if (method === 'mobile_money') {
                if (!formData.get('mobile_network') || !formData.get('account_number') || !formData.get('account_name')) {
                    alert('Please fill in all mobile money fields');
                    return;
                }
            } else if (method === 'bank') {
                if (!formData.get('bank_name') || !formData.get('account_number') || !formData.get('account_name')) {
                    alert('Please fill in all bank transfer fields');
                    return;
                }
            }
            
            // Submit withdrawal request
            fetch('actions/submit-withdrawal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Withdrawal request submitted successfully!');
                    closeWithdrawalModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the request');
            });
        });

        // Additional functionality for withdrawal page
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
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            flex: 1;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-row {
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group.col-6 {
            flex: 0 0 48%;
            margin-right: 2%;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        
        .form-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .balance-display {
            padding: 1rem;
            background-color: var(--bg-tertiary);
            border-radius: 0.375rem;
            text-align: center;
        }
        
        .balance-amount {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            display: block;
        }
        
        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background-color: var(--bg-tertiary);
        }
        
        /* Dark mode specific styles */
        [data-theme="dark"] .modal-content {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .form-control {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .form-control:focus {
            border-color: var(--primary-color);
        }
    </style>
</body>
</html>
