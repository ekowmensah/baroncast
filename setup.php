<?php
// Database Setup Script for E-Cast Voting System

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'e_cast_voting';

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>E-Cast Voting System - Database Setup</h2>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database");
    echo "<p style='color: green;'>✓ Database '$database' created successfully</p>";
    
    // Use the database
    $pdo->exec("USE $database");
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    
    // Remove the CREATE DATABASE and USE statements from schema since we already did that
    $schema = preg_replace('/CREATE DATABASE.*?;/', '', $schema);
    $schema = preg_replace('/USE.*?;/', '', $schema);
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore table already exists errors and duplicate entry errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }
    
    echo "<p style='color: green;'>✓ Database tables created successfully</p>";
    echo "<p style='color: green;'>✓ Default users created:</p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin, password: password</li>";
    echo "<li><strong>Organizer:</strong> username: organizer1, password: password</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='login.php'>Go to Login Page</a></li>";
    echo "<li>Use the credentials above to log in</li>";
    echo "<li>Start creating your events and managing your voting platform</li>";
    echo "</ol>";
    
    echo "<p style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007bff;'>";
    echo "<strong>Note:</strong> This setup script can be run multiple times safely. ";
    echo "It will not overwrite existing data.";
    echo "</p>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";
    echo "<h2>Database Setup Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>XAMPP is running</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>Database credentials are correct</li>";
    echo "</ul>";
    echo "</div>";
}
?>
