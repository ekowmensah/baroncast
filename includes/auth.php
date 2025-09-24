<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, email, password, role, full_name, status FROM users WHERE (username = :username OR email = :email) AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    // Update last login if column exists
                    $this->updateLastLogin($user['id']);
                    
                    // Set secure session
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    return [
                        'success' => true,
                        'user' => $user,
                        'redirect' => $this->getRedirectUrl($user['role'])
                    ];
                }
            }
            
            return ['success' => false, 'message' => 'Invalid credentials'];
            
        } catch (PDOException $e) {
            error_log("Login database error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
    }
    
    private function updateLastLogin($userId) {
        try {
            // Check if last_login column exists before updating
            $checkQuery = "SHOW COLUMNS FROM users LIKE 'last_login'";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
            }
        } catch (PDOException $e) {
            // Silently fail if column doesn't exist - not critical for login
            error_log("Last login update failed: " . $e->getMessage());
        }
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true, 'redirect' => '/e-cast-voting-system/login.php'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireAuth($allowedRoles = []) {
        if (!$this->isLoggedIn()) {
            // Get the current script directory to build relative paths
            $currentDir = dirname($_SERVER['SCRIPT_NAME']);
            $baseDir = str_replace('/admin', '', $currentDir);
            $baseDir = str_replace('/organizer', '', $baseDir);
            $baseDir = rtrim($baseDir, '/');
            
            header('Location: ' . $baseDir . '/login.php');
            exit();
        }
        
        if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
            // Get the current script directory to build relative paths
            $currentDir = dirname($_SERVER['SCRIPT_NAME']);
            $baseDir = str_replace('/admin', '', $currentDir);
            $baseDir = str_replace('/organizer', '', $baseDir);
            $baseDir = rtrim($baseDir, '/');
            
            header('Location: ' . $baseDir . '/unauthorized.php');
            exit();
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name'] ?? ''
            ];
        }
        return null;
    }
    
    private function getRedirectUrl($role) {
        // Get the current script directory to build relative paths
        $currentDir = dirname($_SERVER['SCRIPT_NAME']);
        $baseDir = str_replace('/admin', '', $currentDir);
        $baseDir = str_replace('/organizer', '', $baseDir);
        $baseDir = rtrim($baseDir, '/');
        
        switch ($role) {
            case 'admin':
                return $baseDir . '/admin/';
            case 'organizer':
                return $baseDir . '/organizer/';
            default:
                return $baseDir . '/voter/';
        }
    }
    
    public function register($data) {
        try {
            // Check if username or email already exists
            $query = "SELECT id FROM users WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, email, password, role, full_name, phone) VALUES (:username, :email, :password, :role, :full_name, :phone)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':role', $data['role']);
            $stmt->bindParam(':full_name', $data['full_name']);
            $stmt->bindParam(':phone', $data['phone']);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'User registered successfully'];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>
