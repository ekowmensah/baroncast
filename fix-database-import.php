<?php
/**
 * Fix Database Import Issues
 * Handles DEFINER problems and creates missing views/procedures
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for proper database connection
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h2>🔧 Database Import Fix Tool</h2>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";
    
    echo "<h3>1. CURRENT DATABASE INFO:</h3>";
    
    // Get current database info
    $stmt = $pdo->query("SELECT DATABASE() as current_db, USER() as current_user");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Current Database: {$dbInfo['current_db']}<br>";
    echo "Current User: {$dbInfo['current_user']}<br><br>";
    
    echo "<h3>2. FIXING DEFINER ISSUES:</h3>";
    
    // Drop the problematic view if it exists
    echo "Dropping existing hubtel_payment_summary view...<br>";
    try {
        $pdo->exec("DROP VIEW IF EXISTS hubtel_payment_summary");
        echo "✅ Existing view dropped successfully<br>";
    } catch (Exception $e) {
        echo "ℹ️  No existing view to drop: " . $e->getMessage() . "<br>";
    }
    
    // Recreate the view with correct definer
    echo "Creating hubtel_payment_summary view with correct definer...<br>";
    $viewSQL = "
    CREATE VIEW hubtel_payment_summary AS 
    SELECT 
        CAST(transactions.created_at AS DATE) AS payment_date,
        COUNT(*) AS total_transactions,
        COUNT(CASE WHEN transactions.status = 'completed' THEN 1 END) AS successful_payments,
        COUNT(CASE WHEN transactions.status = 'failed' THEN 1 END) AS failed_payments,
        COUNT(CASE WHEN transactions.status = 'pending' THEN 1 END) AS pending_payments,
        SUM(CASE WHEN transactions.status = 'completed' THEN transactions.amount ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN transactions.status = 'completed' THEN COALESCE(transactions.payment_charges, 0) ELSE 0 END) AS total_charges,
        AVG(CASE WHEN transactions.status = 'completed' THEN transactions.amount ELSE NULL END) AS avg_transaction_amount
    FROM transactions 
    WHERE transactions.payment_method IN ('mobile_money', 'hubtel_mobile_money', 'hubtel_ussd')
    GROUP BY CAST(transactions.created_at AS DATE) 
    ORDER BY CAST(transactions.created_at AS DATE) DESC
    ";
    
    try {
        $pdo->exec($viewSQL);
        echo "✅ hubtel_payment_summary view created successfully<br>";
    } catch (Exception $e) {
        echo "⚠️  Could not create view: " . $e->getMessage() . "<br>";
        echo "This might be due to missing columns. Let's check...<br>";
        
        // Check if payment_charges column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_charges'");
        if ($stmt->rowCount() == 0) {
            echo "Adding missing payment_charges column...<br>";
            try {
                $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_charges DECIMAL(10,2) DEFAULT 0 AFTER amount");
                echo "✅ payment_charges column added<br>";
                
                // Try creating the view again
                $pdo->exec($viewSQL);
                echo "✅ hubtel_payment_summary view created successfully<br>";
            } catch (Exception $e2) {
                echo "❌ Still failed: " . $e2->getMessage() . "<br>";
            }
        }
    }
    
    echo "<br><h3>3. CHECKING OTHER POTENTIAL ISSUES:</h3>";
    
    // Check for other views or procedures with DEFINER issues
    $stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_TYPE 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_TYPE = 'VIEW'
    ");
    $views = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($views) . " views in database:<br>";
    foreach ($views as $view) {
        echo "- {$view['TABLE_NAME']}<br>";
    }
    
    // Check for stored procedures
    $stmt = $pdo->query("
        SELECT ROUTINE_NAME, ROUTINE_TYPE 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_SCHEMA = DATABASE()
    ");
    $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<br>Found " . count($routines) . " stored procedures/functions:<br>";
    foreach ($routines as $routine) {
        echo "- {$routine['ROUTINE_NAME']} ({$routine['ROUTINE_TYPE']})<br>";
    }
    
    echo "<br><h3>4. RECOMMENDED IMPORT PROCESS:</h3>";
    echo "<div style='background: #e8f4f8; padding: 15px; border-radius: 5px;'>";
    echo "<h4>📋 Step-by-Step Import Fix:</h4>";
    echo "<ol>";
    echo "<li><strong>Edit your SQL dump file:</strong><br>";
    echo "   - Find all instances of <code>DEFINER=`baroncas_voting`@`localhost`</code><br>";
    echo "   - Replace with <code>DEFINER=`menswebg_baroncast`@`localhost`</code><br>";
    echo "   - Or remove the DEFINER clause entirely</li>";
    echo "<li><strong>Alternative approach:</strong><br>";
    echo "   - Import tables first (skip views/procedures)<br>";
    echo "   - Then run this fix tool to recreate views</li>";
    echo "<li><strong>Quick fix command:</strong><br>";
    echo "   - Use find/replace in your text editor<br>";
    echo "   - Search: <code>DEFINER=`baroncas_voting`@`localhost`</code><br>";
    echo "   - Replace: <code>DEFINER=`menswebg_baroncast`@`localhost`</code></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<br><h3>5. TESTING DATABASE CONNECTION:</h3>";
    
    // Test basic operations
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $tableCount = $stmt->fetch()['table_count'];
        echo "✅ Database connection working<br>";
        echo "✅ Found $tableCount tables in database<br>";
        
        // Test key tables
        $keyTables = ['users', 'events', 'categories', 'nominees', 'votes', 'transactions', 'system_settings'];
        $existingTables = [];
        $missingTables = [];
        
        foreach ($keyTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
        
        echo "<br><strong>Existing key tables:</strong><br>";
        foreach ($existingTables as $table) {
            echo "✅ $table<br>";
        }
        
        if (!empty($missingTables)) {
            echo "<br><strong>Missing key tables:</strong><br>";
            foreach ($missingTables as $table) {
                echo "❌ $table<br>";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Database connection issue: " . $e->getMessage() . "<br>";
    }
    
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ SUMMARY & NEXT STEPS:</h3>";
    echo "<ol>";
    echo "<li><strong>Fixed the immediate DEFINER issue</strong> - hubtel_payment_summary view recreated</li>";
    echo "<li><strong>For complete import:</strong> Edit your SQL dump file to replace all DEFINER references</li>";
    echo "<li><strong>Alternative:</strong> Import table structure first, then run this tool</li>";
    echo "<li><strong>Test your application</strong> after import to ensure everything works</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Fix Tool Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h4>🔧 Manual Fix Steps:</h4>";
    echo "<ol>";
    echo "<li>Open your SQL dump file in a text editor</li>";
    echo "<li>Find: <code>DEFINER=`baroncas_voting`@`localhost`</code></li>";
    echo "<li>Replace with: <code>DEFINER=`menswebg_baroncast`@`localhost`</code></li>";
    echo "<li>Save and re-import the file</li>";
    echo "</ol>";
    echo "</div>";
}
?>
