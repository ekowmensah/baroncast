<aside class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <?php
            // Get dynamic logo and site name from settings
            try {
                require_once __DIR__ . '/../../config/site-settings.php';
                $siteLogo = SiteSettings::getSiteLogo();
                $siteName = SiteSettings::getSiteName();
                
                if (!empty($siteLogo) && file_exists(__DIR__ . '/../../' . $siteLogo)):
                ?>
                    <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" style="height: 32px; width: auto; margin-right: 8px;">
                <?php else: ?>
                    <i class="fas fa-calendar-alt"></i>
                <?php endif; ?>
                <span><?= htmlspecialchars($siteName) ?> Organizer</span>
            <?php
            } catch (Exception $e) {
                // Fallback if site settings not available
                ?>
                <i class="fas fa-calendar-alt"></i>
                <span>E-Cast Organizer</span>
                <?php
            }
            ?>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="index.php" class="nav-link" data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Event Management -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="events-menu" data-tooltip="Event Management">
                <i class="fas fa-calendar-alt"></i>
                <span>Event Management</span>
                <i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="events-menu" class="nav-submenu">
                <a href="create-event.php" class="nav-link">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Create Event</span>
                </a>
                <a href="categories.php" class="nav-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
                <a href="nominees.php" class="nav-link">
                    <i class="fas fa-user-friends"></i>
                    <span>Nominees</span>
                </a>
            </div>
        </div>
        
        <!-- Voting Analytics -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="analytics-menu" data-tooltip="Analytics">
                <i class="fas fa-chart-bar"></i>
                <span>Voting Analytics</span>
                <i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="analytics-menu" class="nav-submenu">
                <a href="category-tally.php" class="nav-link">
                    <i class="fas fa-list-alt"></i>
                    <span>Category Tally</span>
                </a>
                <a href="nominees-tally.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Nominees Tally</span>
                </a>
                <a href="votes-payments.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Vote Payments</span>
                </a>
            </div>
        </div>
        
        <!-- Financial Management -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="finance-menu" data-tooltip="Financial">
                <i class="fas fa-money-check-alt"></i>
                <span>Financial</span>
                <i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="finance-menu" class="nav-submenu">
                <a href="withdrawal.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Withdrawal Request</span>
                </a>
                <a href="scheme.php" class="nav-link">
                    <i class="fas fa-cogs"></i>
                    <span>Pricing Schemes</span>
                </a>
            </div>
        </div>
        
        <!-- Bulk Operations -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="bulk-menu" data-tooltip="Bulk Operations">
                <i class="fas fa-layer-group"></i>
                <span>Bulk Operations</span>
                <i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <div id="bulk-menu" class="nav-submenu">
                <a href="bulk-votes.php" class="nav-link">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Vote Import</span>
                </a>
                <a href="registration.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>User Registration</span>
                </a>
            </div>
        </div>
    </nav>
</aside>
