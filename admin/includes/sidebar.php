<aside class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <?php
            // Get dynamic logo and site name from settings
            require_once __DIR__ . '/../../config/site-settings.php';
            $siteLogo = SiteSettings::getSiteLogo();
            $siteName = SiteSettings::getSiteName();
            
            if (!empty($siteLogo) && file_exists(__DIR__ . '/../../' . $siteLogo)):
            ?>
                <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" style="height: 32px; width: auto; margin-right: 8px;">
            <?php else: ?>
                <i class="fas fa-shield-alt"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars($siteName) ?> Admin</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="index.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- User Management -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="users-menu">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="users-menu" class="nav-submenu">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>All Users</span>
                </a>
                <a href="organizers.php" class="nav-link">
                    <i class="fas fa-user-tie"></i>
                    <span>Event Organizers</span>
                </a>
                <a href="admins.php" class="nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span>Administrators</span>
                </a>
            </div>
        </div>
        
        <!-- Event Management -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="events-menu">
                <i class="fas fa-calendar-alt"></i>
                <span>Event Management</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="events-menu" class="nav-submenu">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar"></i>
                    <span>All Events</span>
                </a>
                <a href="create-event.php" class="nav-link">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Create Event</span>
                </a>
                <a href="events-approval.php" class="nav-link">
                    <i class="fas fa-check-circle"></i>
                    <span>Event Approval</span>
                </a>
                <a href="categories.php" class="nav-link">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
                <a href="nominees.php" class="nav-link">
                    <i class="fas fa-user-friends"></i>
                    <span>Nominees</span>
                </a>
                <a href="scheme.php" class="nav-link">
                    <i class="fas fa-cogs"></i>
                    <span>Event Schemes</span>
                </a>
            </div>
        </div>
        
        <!-- Financial Management -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="finance-menu">
                <i class="fas fa-money-check-alt"></i>
                <span>Financial Management</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="finance-menu" class="nav-submenu">
                <a href="transactions.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i>
                    <span>All Transactions</span>
                </a>
                <a href="revenue.php" class="nav-link">
                    <i class="fas fa-chart-pie"></i>
                    <span>Revenue Reports</span>
                </a>
                <a href="withdrawals.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Withdrawal Requests</span>
                </a>
                <a href="commissions.php" class="nav-link">
                    <i class="fas fa-percentage"></i>
                    <span>Commission Settings</span>
                </a>
            </div>
        </div>
        
        <!-- Voting Analytics -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="analytics-menu">
                <i class="fas fa-chart-bar"></i>
                <span>Voting Analytics</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="analytics-menu" class="nav-submenu">
                <a href="vote-analytics.php" class="nav-link">
                    <i class="fas fa-poll"></i>
                    <span>Vote Statistics</span>
                </a>
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
        
        <!-- Vote Packages removed - now using single vote system -->
        
        <!-- Bulk Operations -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="bulk-menu">
                <i class="fas fa-layer-group"></i>
                <span>Bulk Operations</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
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
                <a href="bulk-export.php" class="nav-link">
                    <i class="fas fa-download"></i>
                    <span>Data Export</span>
                </a>
            </div>
        </div>
        
        <!-- Platform Settings -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="settings-menu">
                <i class="fas fa-cogs"></i>
                <span>Platform Settings</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="settings-menu" class="nav-submenu">
                <a href="general-settings.php" class="nav-link">
                    <i class="fas fa-sliders-h"></i>
                    <span>General Settings</span>
                </a>
                <a href="vote-settings.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Vote Settings</span>
                </a>
                <a href="hubtel-settings.php" class="nav-link">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Hubtel Settings</span>
                </a>
                
                <a href="transaction-monitor.php" class="nav-link">
                    <i class="fas fa-heartbeat"></i>
                    <span>Transaction Monitor</span>
                </a>
                
                <a href="hubtel-debug.php" class="nav-link">
                    <i class="fas fa-bug"></i>
                    <span>Debug & Test</span>
                </a>

                <a href="email-settings.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Email Settings</span>
                </a>

                <a href="security-settings.php" class="nav-link">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security Settings</span>
                </a>
            </div>
        </div>
        
        <!-- System Monitoring -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-submenu="system-menu">
                <i class="fas fa-server"></i>
                <span>System Monitoring</span>
                <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
            </a>
            <div id="system-menu" class="nav-submenu">
                <a href="system-logs.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span>System Logs</span>
                </a>
                <a href="audit-trail.php" class="nav-link">
                    <i class="fas fa-history"></i>
                    <span>Audit Trail</span>
                </a>
                <a href="system-health.php" class="nav-link">
                    <i class="fas fa-heartbeat"></i>
                    <span>System Health</span>
                </a>
            </div>
        </div>
        
        <!-- Support -->
        <div class="nav-item">
            <a href="support.php" class="nav-link">
                <i class="fas fa-life-ring"></i>
                <span>Support</span>
            </a>
        </div>
    </nav>
</aside>
