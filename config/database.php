<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Detect environment and set appropriate database credentials
        $isLocal = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                   strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
                   strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false);
        
        if ($isLocal) {
            // Local XAMPP development configuration
            $this->host = 'localhost';
            $this->db_name = 'baroncast';                // Local database name
            $this->username = 'root';                        // XAMPP default username
            $this->password = '';                            // XAMPP default password (empty)
            $this->port = '3306';
        } else {
            // Production hosting configuration
            // REPLACE THESE VALUES WITH YOUR ACTUAL HOSTING DATABASE CREDENTIALS
            $this->host = 'localhost';                       // Usually 'localhost' for shared hosting
            $this->db_name = 'menswebg_baroncast';  // Replace with your actual database name
            $this->username = 'menswebg_baroncast';      // Replace with your actual database username
            $this->password = '$Norbert3600$';        // Replace with your actual database password
            $this->port = '3306';
        }
    }

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            // Log error for debugging
            error_log("Database connection error: " . $exception->getMessage());
            
            // Don't expose sensitive database details in production
            throw new Exception("Database connection failed. Please check your database credentials and ensure the database server is running.");
        }
        
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}
?>
