// E-Cast Voting System - Dashboard JavaScript
class Dashboard {
    constructor() {
        this.init();
    }

    init() {
        this.initThemeToggle();
        this.initSidebarToggle();
        this.initSubmenuToggle();
        this.initDropdown();
        this.loadTheme();
    }

    // Theme Toggle Functionality
    initThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                this.toggleTheme();
            });
        }
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        this.updateThemeIcon(newTheme);
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        this.updateThemeIcon(savedTheme);
    }

    updateThemeIcon(theme) {
        const themeIcon = document.getElementById('theme-icon');
        const themeText = document.getElementById('theme-text');
        
        if (themeIcon && themeText) {
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-sun';
                themeText.textContent = 'Light';
            } else {
                themeIcon.className = 'fas fa-moon';
                themeText.textContent = 'Dark';
            }
        }
    }

    // Unified Sidebar Toggle Functionality
    initSidebarToggle() {
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        let overlay = document.querySelector('.sidebar-overlay');
        
        // Create overlay if it doesn't exist
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        // Store initial state
        this.isMobile = window.innerWidth <= 768;
        this.sidebarOpen = false;

        if (sidebarToggle && sidebar && mainContent) {
            sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar(sidebar, mainContent, overlay);
            });

            // Load initial sidebar state for desktop
            if (!this.isMobile) {
                const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                }
            }
        }
        
        // Setup event listeners
        this.setupSidebarEvents(sidebar, overlay);
        this.setupResizeHandler(sidebar, mainContent, overlay);
        this.initSwipeGestures(sidebar, overlay);
    }
    
    // Unified toggle method for both desktop and mobile
    toggleSidebar(sidebar, mainContent, overlay) {
        this.isMobile = window.innerWidth <= 768;
        
        if (this.isMobile) {
            // Mobile behavior
            this.sidebarOpen = !this.sidebarOpen;
            
            if (this.sidebarOpen) {
                sidebar.classList.add('mobile-show');
                overlay.classList.add('show');
                document.body.classList.add('sidebar-open');
                
                // Focus trap for accessibility
                const firstFocusable = sidebar.querySelector('a, button, input, [tabindex]:not([tabindex="-1"])');
                if (firstFocusable) {
                    setTimeout(() => firstFocusable.focus(), 100);
                }
            } else {
                this.closeMobileSidebar(sidebar, overlay);
            }
        } else {
            // Desktop behavior
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Save sidebar state
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
    }
    
    // Close mobile sidebar helper
    closeMobileSidebar(sidebar, overlay) {
        sidebar.classList.remove('mobile-show');
        overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        this.sidebarOpen = false;
        
        // Return focus to toggle button
        const toggleBtn = document.getElementById('sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.focus();
        }
    }
    
    // Setup sidebar event listeners
    setupSidebarEvents(sidebar, overlay) {
        // Overlay click to close sidebar
        overlay.addEventListener('click', () => {
            if (this.isMobile && this.sidebarOpen) {
                this.closeMobileSidebar(sidebar, overlay);
            }
        });
        
        // Escape key to close sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && this.sidebarOpen) {
                this.closeMobileSidebar(sidebar, overlay);
            }
        });
    }
    
    // Handle window resize with proper state management
    setupResizeHandler(sidebar, mainContent, overlay) {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth <= 768;
                
                if (wasMobile && !this.isMobile) {
                    // Switching from mobile to desktop
                    this.closeMobileSidebar(sidebar, overlay);
                    
                    // Restore desktop sidebar state
                    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                    }
                } else if (!wasMobile && this.isMobile) {
                    // Switching from desktop to mobile
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    this.sidebarOpen = false;
                }
            }, 150);
        });
    }

    // Submenu Toggle Functionality
    initSubmenuToggle() {
        const submenuToggles = document.querySelectorAll('[data-submenu]');
        
        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                
                const submenuId = toggle.getAttribute('data-submenu');
                const submenu = document.getElementById(submenuId);
                const icon = toggle.querySelector('.submenu-icon');
                
                if (submenu) {
                    submenu.classList.toggle('show');
                    
                    if (icon) {
                        icon.classList.toggle('fa-chevron-down');
                        icon.classList.toggle('fa-chevron-up');
                    }
                }
            });
        });
    }

    // Enhanced Responsive Functionality
    initResponsive() {
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        let overlay = document.querySelector('.sidebar-overlay');
        
        // Create overlay if it doesn't exist
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        // Store initial state
        this.isMobile = window.innerWidth <= 768;
        this.sidebarOpen = false;
        
        // Mobile sidebar toggle functionality
        const toggleMobileSidebar = () => {
            this.isMobile = window.innerWidth <= 768;
            
            if (this.isMobile) {
                this.sidebarOpen = !this.sidebarOpen;
                
                if (this.sidebarOpen) {
                    // Open sidebar
                    sidebar.classList.add('mobile-show');
                    overlay.classList.add('show');
                    document.body.classList.add('sidebar-open');
                    
                    // Focus trap for accessibility
                    const firstFocusable = sidebar.querySelector('a, button, input, [tabindex]:not([tabindex="-1"])');
                    if (firstFocusable) {
                        setTimeout(() => firstFocusable.focus(), 100);
                    }
                } else {
                    // Close sidebar
                    this.closeMobileSidebar(sidebar, overlay);
                }
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Save sidebar state
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
            }
        };
        
        // Close mobile sidebar helper
        this.closeMobileSidebar = (sidebar, overlay) => {
            sidebar.classList.remove('mobile-show');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
            this.sidebarOpen = false;
            
            // Return focus to toggle button
            const toggleBtn = document.getElementById('sidebar-toggle');
            if (toggleBtn) {
                toggleBtn.focus();
            }
        };
        
        // Add click event to sidebar toggle
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleMobileSidebar);
        }
        
        // Overlay click to close sidebar
        overlay.addEventListener('click', () => {
            if (this.isMobile && this.sidebarOpen) {
                this.closeMobileSidebar(sidebar, overlay);
            }
        });
        
        // Escape key to close sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && this.sidebarOpen) {
                this.closeMobileSidebar(sidebar, overlay);
            }
        });
        
        // Close mobile sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('mobile-show') && 
                !sidebar.contains(e.target) && 
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-show');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                document.body.style.overflow = '';
            }
        });
        
        // Handle window resize with debouncing
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth <= 768;
                
                if (wasMobile && !this.isMobile) {
                    // Switching from mobile to desktop
                    this.closeMobileSidebar(sidebar, overlay);
                    
                    // Restore desktop sidebar state
                    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                    }
                } else if (!wasMobile && this.isMobile) {
                    // Switching from desktop to mobile
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    this.sidebarOpen = false;
                }
            }, 150);
        });
        
        // Initialize proper state based on screen size
        const initializeResponsiveState = () => {
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('expanded');
            } else {
                // Load desktop sidebar state
                const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    if (mainContent) mainContent.classList.add('expanded');
                }
            }
        };
        
        // Initialize on load
        initializeResponsiveState();
        
        // Add swipe gesture support for mobile
        this.initSwipeGestures(sidebar, overlay);
    }
    
    // Enhanced swipe gesture support for mobile sidebar
    initSwipeGestures(sidebar, overlay) {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let isDragging = false;
        let isValidSwipe = false;
        
        const handleTouchStart = (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;
            isValidSwipe = false;
        };
        
        const handleTouchMove = (e) => {
            if (!isDragging || !this.isMobile) return;
            
            currentX = e.touches[0].clientX;
            const currentY = e.touches[0].clientY;
            const diffX = currentX - startX;
            const diffY = Math.abs(currentY - startY);
            
            // Only handle horizontal swipes (not vertical scrolling)
            if (Math.abs(diffX) > diffY && Math.abs(diffX) > 10) {
                isValidSwipe = true;
                
                // Only handle swipe from left edge (first 50px) or when sidebar is open
                if (startX < 50 || this.sidebarOpen) {
                    e.preventDefault();
                    
                    if (diffX > 0 && !this.sidebarOpen && startX < 50) {
                        // Swiping right from edge to open
                        const progress = Math.min(diffX / 280, 1);
                        sidebar.style.transform = `translateX(${-280 + (280 * progress)}px)`;
                        overlay.style.opacity = progress * 0.6;
                        
                        if (progress > 0.1) {
                            overlay.style.visibility = 'visible';
                        }
                    } else if (diffX < -50 && this.sidebarOpen) {
                        // Swiping left to close
                        const progress = Math.max(1 + (diffX / 280), 0);
                        sidebar.style.transform = `translateX(${-280 + (280 * progress)}px)`;
                        overlay.style.opacity = progress * 0.6;
                    }
                }
            }
        };
        
        const handleTouchEnd = () => {
            if (!isDragging || !isValidSwipe || !this.isMobile) {
                isDragging = false;
                return;
            }
            
            const diffX = currentX - startX;
            
            // Reset transforms
            sidebar.style.transform = '';
            overlay.style.opacity = '';
            overlay.style.visibility = '';
            
            // Determine action based on swipe distance
            if (diffX > 100 && !this.sidebarOpen && startX < 50) {
                // Open sidebar
                sidebar.classList.add('mobile-show');
                overlay.classList.add('show');
                document.body.classList.add('sidebar-open');
                this.sidebarOpen = true;
            } else if (diffX < -100 && this.sidebarOpen) {
                // Close sidebar
                this.closeMobileSidebar(sidebar, overlay);
            }
            
            isDragging = false;
            isValidSwipe = false;
        };
        
        // Add touch event listeners with proper options
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });
    }

    // Utility Functions
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Data Table Enhancement
    initDataTables() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            this.enhanceTable(table);
        });
    }

    enhanceTable(table) {
        // Add search functionality
        const searchInput = table.parentElement.querySelector('.table-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterTable(table, e.target.value);
            });
        }

        // Add sorting functionality
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header.getAttribute('data-sort'));
            });
        });
    }

    filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    sortTable(table, column) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(table.querySelectorAll('th')).findIndex(th => 
            th.getAttribute('data-sort') === column
        );

        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            // Try to parse as numbers
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return aNum - bNum;
            }
            
            return aVal.localeCompare(bVal);
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    // Form Validation
    initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        
        field.parentElement.appendChild(errorDiv);
        field.classList.add('error');
    }

    clearFieldError(field) {
        const existingError = field.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        field.classList.remove('error');
    }

    // Dropdown Functionality
    initDropdown() {
        const dropdowns = document.querySelectorAll('.dropdown');
        
        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (toggle && menu) {
                // Toggle dropdown on click
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    this.closeAllDropdowns();
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('show');
                });
                
                // Prevent dropdown menu clicks from closing the dropdown
                menu.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                this.closeAllDropdowns();
            }
        });
        
        // Close dropdowns on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllDropdowns();
            }
        });
    }
    
    closeAllDropdowns() {
        const dropdowns = document.querySelectorAll('.dropdown.show');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
}

// Initialize Dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new Dashboard();
});

// Additional utility functions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}
