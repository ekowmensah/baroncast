<?php
/**
 * Free Event Configuration
 * Allows bypassing payments for specific events in production
 */

/**
 * Check if event allows free voting
 */
function isEventFree($event_id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT vote_cost FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If vote cost is 0, treat as free event
        return $event && (float)$event['vote_cost'] === 0.0;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Process free event vote (no payment required)
 */
function processFreeEventVote($transaction_ref) {
    return [
        'successful' => true,
        'reference' => 'FREE_' . $transaction_ref,
        'status' => 'completed',
        'response' => [
            'status' => 'success',
            'message' => 'Free event - no payment required',
            'transaction_id' => 'FREE_' . $transaction_ref
        ]
    ];
}
?>
