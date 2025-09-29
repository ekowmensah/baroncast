<?php
/**
 * Add Shortcode Column to Nominees Table
 * Creates unique shortcodes for each nominee for USSD voting
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Adding shortcode column to nominees table...\n";
    
    // Add shortcode column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM nominees LIKE 'shortcode'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE nominees ADD COLUMN shortcode VARCHAR(10) UNIQUE AFTER name");
        echo "✓ Added shortcode column to nominees table\n";
    } else {
        echo "✓ Shortcode column already exists\n";
    }
    
    // Generate shortcodes for existing nominees
    $stmt = $pdo->query("SELECT id, name FROM nominees WHERE shortcode IS NULL OR shortcode = ''");
    $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $counter = 1;
    foreach ($nominees as $nominee) {
        $shortcode = 'NOM' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        $updateStmt = $pdo->prepare("UPDATE nominees SET shortcode = ? WHERE id = ?");
        $updateStmt->execute([$shortcode, $nominee['id']]);
        
        echo "✓ Generated shortcode '$shortcode' for '{$nominee['name']}'\n";
        $counter++;
    }
    
    // Show all nominees with their shortcodes
    echo "\nNominee Shortcodes:\n";
    echo "Shortcode\tNominee Name\t\tEvent\n";
    echo "---------\t------------\t\t-----\n";
    
    $stmt = $pdo->query("
        SELECT n.shortcode, n.name as nominee_name, e.title as event_title
        FROM nominees n
        JOIN categories c ON n.category_id = c.id
        JOIN events e ON c.event_id = e.id
        ORDER BY n.shortcode
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nomineeName = substr($row['nominee_name'], 0, 15);
        $eventTitle = substr($row['event_title'], 0, 20);
        echo "{$row['shortcode']}\t\t{$nomineeName}\t\t{$eventTitle}\n";
    }
    
    echo "\n✅ Nominee shortcodes created successfully!\n";
    echo "\nVoters can now use these shortcodes to vote directly via USSD.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
