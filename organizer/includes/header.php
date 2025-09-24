<?php
// Get user information for header display
if (!isset($user) && isset($_SESSION['user_id'])) {
    try {
        // Ensure we have database connection
        if (!isset($pdo)) {
            require_once __DIR__ . '/../../config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
        }
        
        $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $user = ['full_name' => 'Organizer User', 'role' => 'organizer'];
    }
}

if (!isset($user)) {
    $user = ['full_name' => 'Organizer User', 'role' => 'organizer'];
}
?>

<header class="header">
    <div class="header-left">
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title mb-0"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Organizer Dashboard'; ?></h1>
    </div>
    
    <div class="header-right">
        <button id="theme-toggle" class="theme-toggle">
            <i id="theme-icon" class="fas fa-moon"></i>
            <span id="theme-text">Dark</span>
        </button>
        
        <div class="dropdown">
            <button class="btn btn-outline dropdown-toggle">
                <i class="fas fa-user-tie"></i>
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
