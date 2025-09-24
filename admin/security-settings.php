<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'enable_rate_limiting' => isset($_POST['enable_rate_limiting']) ? 1 : 0,
            'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
            'lockout_duration' => (int)($_POST['lockout_duration'] ?? 15),
            'session_timeout' => (int)($_POST['session_timeout'] ?? 30),
            'enable_2fa' => isset($_POST['enable_2fa']) ? 1 : 0,
            'password_min_length' => (int)($_POST['password_min_length'] ?? 8),
            'require_special_chars' => isset($_POST['require_special_chars']) ? 1 : 0,
            'enable_audit_log' => isset($_POST['enable_audit_log']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success = "Security settings updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%rate_limiting%' OR setting_key LIKE '%login%' OR setting_key LIKE '%session%' OR setting_key LIKE '%2fa%' OR setting_key LIKE '%password%' OR setting_key LIKE '%audit%'");
$currentSettings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
</head>
<body>
    <?php 
    $pageTitle = "Security Settings";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Security Settings</h2>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Platform Security Configuration</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Login Security</h5>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enable_rate_limiting" id="enable_rate_limiting" <?= ($currentSettings['enable_rate_limiting'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_rate_limiting">
                                            Enable Rate Limiting
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                    <input type="number" class="form-control" name="max_login_attempts" id="max_login_attempts" value="<?= $currentSettings['max_login_attempts'] ?? 5 ?>" min="1" max="20">
                                </div>
                                <div class="mb-3">
                                    <label for="lockout_duration" class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" class="form-control" name="lockout_duration" id="lockout_duration" value="<?= $currentSettings['lockout_duration'] ?? 15 ?>" min="1" max="1440">
                                </div>
                                <div class="mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" name="session_timeout" id="session_timeout" value="<?= $currentSettings['session_timeout'] ?? 30 ?>" min="5" max="480">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Password Policy</h5>
                                <div class="mb-3">
                                    <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                    <input type="number" class="form-control" name="password_min_length" id="password_min_length" value="<?= $currentSettings['password_min_length'] ?? 8 ?>" min="6" max="32">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="require_special_chars" id="require_special_chars" <?= ($currentSettings['require_special_chars'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="require_special_chars">
                                            Require Special Characters
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enable_2fa" id="enable_2fa" <?= ($currentSettings['enable_2fa'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_2fa">
                                            Enable Two-Factor Authentication
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enable_audit_log" id="enable_audit_log" <?= ($currentSettings['enable_audit_log'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_audit_log">
                                            Enable Audit Logging
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
