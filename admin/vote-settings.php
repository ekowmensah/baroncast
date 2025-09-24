<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $default_vote_cost = (float)($_POST['default_vote_cost'] ?? 1.00);
        $enable_event_custom_fee = isset($_POST['enable_event_custom_fee']) ? 1 : 0;
        $min_vote_cost = (float)($_POST['min_vote_cost'] ?? 0.50);
        $max_vote_cost = (float)($_POST['max_vote_cost'] ?? 100.00);
        
        // Validation
        if ($default_vote_cost < 0.01) {
            throw new Exception('Default vote cost must be at least GH₵ 0.01');
        }
        
        if ($min_vote_cost >= $max_vote_cost) {
            throw new Exception('Minimum vote cost must be less than maximum vote cost');
        }
        
        // Update or insert settings
        $settings = [
            'default_vote_cost' => $default_vote_cost,
            'enable_event_custom_fee' => $enable_event_custom_fee,
            'min_vote_cost' => $min_vote_cost,
            'max_vote_cost' => $max_vote_cost
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        
        $success = 'Vote settings updated successfully!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load current settings
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('default_vote_cost', 'enable_event_custom_fee', 'min_vote_cost', 'max_vote_cost')");
    $current_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch (Exception $e) {
    $current_settings = [];
}

// Set defaults if not found
$default_vote_cost = $current_settings['default_vote_cost'] ?? '1.00';
$enable_event_custom_fee = $current_settings['enable_event_custom_fee'] ?? '0';
$min_vote_cost = $current_settings['min_vote_cost'] ?? '0.50';
$max_vote_cost = $current_settings['max_vote_cost'] ?? '100.00';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Settings - E-Cast Admin</title>
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
            <?php 
            $pageTitle = 'Vote Settings';
            include 'includes/header.php'; 
            ?>

            <!-- Content -->
            <div class="content-area">
                <div class="page-header">
                    <h1><i class="fas fa-money-bill-wave"></i> Vote Settings</h1>
                    <p>Configure voting fees and pricing options</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="settings-card">
                    <div class="card-header">
                        <h3><i class="fas fa-money-bill-wave"></i> Voting Fee Configuration</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-coins"></i>
                                    Default Voting Fee
                                </h4>
                                <div class="form-group">
                                    <label for="default_vote_cost" class="form-label">
                                        Default Vote Cost (GH₵) <span class="required">*</span>
                                    </label>
                                    <input type="number" 
                                           id="default_vote_cost" 
                                           name="default_vote_cost" 
                                           class="form-control" 
                                           step="0.01" 
                                           min="0.01" 
                                           max="1000" 
                                           value="<?= htmlspecialchars($default_vote_cost) ?>" 
                                           required>
                                    <small class="form-text">This will be used when events don't have custom voting fees set</small>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4 class="form-section-title">
                                    <i class="fas fa-sliders-h"></i>
                                    Custom Event Fees
                                </h4>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               id="enable_event_custom_fee" 
                                               name="enable_event_custom_fee" 
                                               class="form-check-input"
                                               <?= $enable_event_custom_fee ? 'checked' : '' ?>>
                                        <label for="enable_event_custom_fee" class="form-check-label">
                                            Allow events to set custom voting fees
                                        </label>
                                    </div>
                                    <small class="form-text">When enabled, organizers can set custom vote costs for their events</small>
                                </div>

                                <div class="form-row" id="custom-fee-limits" style="<?= $enable_event_custom_fee ? '' : 'display: none;' ?>">
                                    <div class="form-group">
                                        <label for="min_vote_cost" class="form-label">
                                            Minimum Vote Cost (GH₵)
                                        </label>
                                        <input type="number" 
                                               id="min_vote_cost" 
                                               name="min_vote_cost" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0.01" 
                                               value="<?= htmlspecialchars($min_vote_cost) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="max_vote_cost" class="form-label">
                                            Maximum Vote Cost (GH₵)
                                        </label>
                                        <input type="number" 
                                               id="max_vote_cost" 
                                               name="max_vote_cost" 
                                               class="form-control" 
                                               step="0.01" 
                                               max="10000" 
                                               value="<?= htmlspecialchars($max_vote_cost) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Settings
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Current Settings Preview -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3><i class="fas fa-eye"></i> Current Settings Preview</h3>
                    </div>
                    <div class="card-body">
                        <div class="settings-preview">
                            <div class="preview-item">
                                <label>Default Vote Cost:</label>
                                <span class="preview-value">GH₵ <?= number_format((float)$default_vote_cost, 2) ?></span>
                            </div>
                            <div class="preview-item">
                                <label>Custom Event Fees:</label>
                                <span class="preview-value <?= $enable_event_custom_fee ? 'enabled' : 'disabled' ?>">
                                    <?= $enable_event_custom_fee ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </div>
                            <?php if ($enable_event_custom_fee): ?>
                            <div class="preview-item">
                                <label>Fee Range:</label>
                                <span class="preview-value">GH₵ <?= number_format((float)$min_vote_cost, 2) ?> - GH₵ <?= number_format((float)$max_vote_cost, 2) ?></span>
                            </div>
                            <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle custom fee limits visibility
        document.getElementById('enable_event_custom_fee').addEventListener('change', function() {
            const limitsDiv = document.getElementById('custom-fee-limits');
            limitsDiv.style.display = this.checked ? 'block' : 'none';
        });
    </script>

    <script src="../assets/js/dashboard.js"></script>

    <style>
        .content-area {
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .page-header h1 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .settings-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--bg-secondary);
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h3 {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }

    <style>
        .content-area {
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .page-header h1 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            color: var(--text-secondary);
            margin: 0;
        }
        
        .settings-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--bg-secondary);
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h3 {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section-title {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 6px;
            padding: 0.75rem;
            width: 100%;
            font-size: 0.875rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-text {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .form-check-input {
            width: 1.2rem;
            height: 1.2rem;
            accent-color: var(--primary-color);
        }
        
        .form-check-label {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-color-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--bg-tertiary);
            transform: translateY(-1px);
        }
        
        .settings-preview {
            display: grid;
            gap: 1rem;
        }
        
        .preview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        
        .preview-item label {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .preview-value {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .preview-value.enabled {
            color: var(--success-color);
        }
        
        .preview-value.disabled {
            color: var(--danger-color);
        }
        
        .required {
            color: var(--danger-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }
        
        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid var(--danger-border);
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>