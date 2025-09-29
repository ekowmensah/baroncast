<?php
/**
 * Update Vote Costs for Existing Events
 * Sets default vote cost for events that have NULL or 0.00 vote_cost
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Updating vote costs for existing events...\n";
    
    // Update events with NULL or 0.00 vote_cost to 1.00
    $stmt = $pdo->prepare("
        UPDATE events 
        SET vote_cost = 1.00 
        WHERE vote_cost IS NULL OR vote_cost = 0.00
    ");
    $stmt->execute();
    $updatedRows = $stmt->rowCount();
    
    echo "✓ Updated $updatedRows events with default vote cost of GHS 1.00\n";
    
    // Show current events and their vote costs
    $stmt = $pdo->query("
        SELECT id, title, status, vote_cost 
        FROM events 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent events:\n";
    echo "ID\tTitle\t\t\tStatus\tVote Cost\n";
    echo "---\t-----\t\t\t------\t---------\n";
    
    foreach ($events as $event) {
        $title = substr($event['title'], 0, 20);
        $cost = number_format($event['vote_cost'], 2);
        echo "{$event['id']}\t{$title}\t\t{$event['status']}\tGHS {$cost}\n";
    }
    
    echo "\n✅ Vote costs updated successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
