<?php
/**
 * Check Current USSD/Shortcode Implementation Status
 */

require_once __DIR__ . '/config/database.php';

// Set HTTP_HOST for command line execution
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "=== USSD/SHORTCODE VOTING ANALYSIS ===\n\n";
    
    // 1. Check existing USSD settings
    echo "1. CURRENT USSD SETTINGS:\n";
    echo "-------------------------\n";
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%ussd%' OR setting_key LIKE '%arkesel%' ORDER BY setting_key");
    $ussdSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ussdSettings)) {
        echo "❌ No USSD settings found in database\n";
    } else {
        foreach ($ussdSettings as $setting) {
            $value = $setting['setting_value'] ?: 'EMPTY';
            echo sprintf("%-25s: %s\n", $setting['setting_key'], $value);
        }
    }
    
    // 2. Check USSD-related tables
    echo "\n2. USSD DATABASE TABLES:\n";
    echo "------------------------\n";
    
    $ussdTables = ['ussd_sessions', 'ussd_transactions'];
    foreach ($ussdTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt2 = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt2->fetch()['count'];
            echo "✅ $table exists ($count records)\n";
        } else {
            echo "❌ $table does not exist\n";
        }
    }
    
    // 3. Check for USSD service files
    echo "\n3. USSD SERVICE FILES:\n";
    echo "---------------------\n";
    
    $ussdFiles = [
        'services/ArkeselUSSDService.php',
        'api/ussd-callback.php',
        'api/ussd-webhook.php',
        'admin/ussd-settings.php',
        'voter/test-ussd.php'
    ];
    
    foreach ($ussdFiles as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            echo "✅ $file exists\n";
        } else {
            echo "❌ $file missing\n";
        }
    }
    
    // 4. Check payment methods support
    echo "\n4. PAYMENT METHOD SUPPORT:\n";
    echo "--------------------------\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'payment_method'");
    if ($stmt->rowCount() > 0) {
        $column = $stmt->fetch();
        echo "Payment method column: " . $column['Type'] . "\n";
        
        // Check if USSD is supported
        if (strpos($column['Type'], 'ussd') !== false) {
            echo "✅ USSD payment method supported\n";
        } else {
            echo "⚠️  USSD payment method not in enum\n";
        }
    }
    
    // 5. Check existing USSD transactions
    echo "\n5. USSD TRANSACTION HISTORY:\n";
    echo "----------------------------\n";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_method = 'ussd'");
        $ussdCount = $stmt->fetch()['count'];
        echo "USSD transactions: $ussdCount\n";
        
        if ($ussdCount > 0) {
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM transactions WHERE payment_method = 'ussd' GROUP BY status");
            while ($row = $stmt->fetch()) {
                echo "  - {$row['status']}: {$row['count']}\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error checking USSD transactions: " . $e->getMessage() . "\n";
    }
    
    // 6. Analyze current implementation
    echo "\n6. IMPLEMENTATION ANALYSIS:\n";
    echo "---------------------------\n";
    
    $hasSettings = !empty($ussdSettings);
    $hasFiles = file_exists(__DIR__ . '/api/ussd-callback.php');
    $hasTables = false;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'ussd_sessions'");
    $hasTables = $stmt->rowCount() > 0;
    
    if ($hasSettings && $hasFiles && $hasTables) {
        echo "✅ USSD infrastructure appears to be implemented\n";
        echo "   Status: Partially implemented (needs service class)\n";
    } elseif ($hasSettings || $hasFiles) {
        echo "⚠️  USSD infrastructure partially implemented\n";
        echo "   Status: Incomplete implementation\n";
    } else {
        echo "❌ USSD infrastructure not implemented\n";
        echo "   Status: Needs full implementation\n";
    }
    
    echo "\n7. RECOMMENDED IMPLEMENTATION APPROACH:\n";
    echo "--------------------------------------\n";
    
    if ($hasSettings && $hasFiles) {
        echo "🔧 COMPLETE EXISTING IMPLEMENTATION:\n";
        echo "   1. Create missing ArkeselUSSDService.php\n";
        echo "   2. Run USSD database migration\n";
        echo "   3. Configure Arkesel API credentials\n";
        echo "   4. Test USSD flow\n";
    } else {
        echo "🏗️  BUILD NEW USSD SYSTEM:\n";
        echo "   1. Choose USSD provider (Arkesel/Hubtel)\n";
        echo "   2. Create USSD service classes\n";
        echo "   3. Set up database tables\n";
        echo "   4. Implement USSD menu flow\n";
        echo "   5. Integrate with existing voting system\n";
    }
    
    echo "\n=== ANALYSIS COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
