<?php
/**
 * Authentication Helper Functions
 * This file provides simple helper functions for authentication
 * Used by admin and organizer dashboard pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? ''
        ];
    }
    return null;
}

/**
 * Require authentication with optional role check
 */
function requireAuth($allowedRoles = []) {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        header('Location: ../unauthorized.php');
        exit();
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireAuth(['admin']);
}

/**
 * Require organizer role
 */
function requireOrganizer() {
    requireAuth(['organizer']);
}

/**
 * Check if current user can access admin features
 */
function canAccessAdmin() {
    return hasRole('admin');
}

/**
 * Check if current user can access organizer features
 */
function canAccessOrganizer() {
    return hasRole('organizer') || hasRole('admin');
}

/**
 * Get user's full name or username as fallback
 */
function getUserDisplayName() {
    $user = getCurrentUser();
    if ($user) {
        return !empty($user['full_name']) ? $user['full_name'] : $user['username'];
    }
    return 'Guest';
}

/**
 * Logout user
 */
function logout() {
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

/**
 * Get redirect URL based on user role
 */
function getRedirectUrl($role = null) {
    if (!$role && isLoggedIn()) {
        $role = $_SESSION['role'];
    }
    
    switch ($role) {
        case 'admin':
            return '../admin/';
        case 'organizer':
            return '../organizer/';
        default:
            return '../voter/';
    }
}

/**
 * Check if user is accessing their own data
 */
function canAccessUserData($userId) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    // Admin can access all user data
    if ($currentUser['role'] === 'admin') {
        return true;
    }
    
    // Users can only access their own data
    return $currentUser['id'] == $userId;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user has permission for specific action
 */
function hasPermission($action, $resource = null) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    $role = $user['role'];
    
    // Admin has all permissions
    if ($role === 'admin') {
        return true;
    }
    
    // Define role-based permissions
    $permissions = [
        'organizer' => [
            'create_event',
            'edit_own_event',
            'create_category',
            'edit_own_category',
            'create_nominee',
            'edit_own_nominee',
            'view_own_analytics',
            'manage_own_schemes'
        ]
    ];
    
    return isset($permissions[$role]) && in_array($action, $permissions[$role]);
}

/**
 * Log authentication events
 */
function logAuthEvent($event, $details = []) {
    $user = getCurrentUser();
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $user['id'] ?? null,
        'username' => $user['username'] ?? 'anonymous',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    // Log to file (you can extend this to log to database)
    $logFile = __DIR__ . '/../logs/auth.log';
    $logEntry = json_encode($logData) . "\n";
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
