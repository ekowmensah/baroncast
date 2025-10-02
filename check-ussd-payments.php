<?php
/**
 * USSD Payment Status Checker
 * Check recent USSD transactions and see if callbacks are being processed
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "<h2>USSD Payment Status Checker</h2>";

    // Check recent USSD transactions
    echo "<h3>Recent USSD Transactions:</h3>";
    $stmt = $pdo->prepare("
        SELECT ut.*, n.name as nominee_name, e.title as event_title
        FROM ussd_transactions ut
        LEFT JOIN nominees n ON ut.nominee_id = n.id
        LEFT JOIN events e ON ut.event_id = e.id
        ORDER BY ut.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($transactions)) {
        echo "<p>No USSD transactions found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Ref</th><th>Phone</th><th>Event</th><th>Nominee</th><th>Votes</th><th>Amount</th><th>Status</th><th>Hubtel TX</th><th>Created</th><th>Completed</th></tr>";
        foreach ($transactions as $tx) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>{$tx['transaction_ref']}</td>";
            echo "<td>{$tx['phone_number']}</td>";
            echo "<td>" . substr($tx['event_title'] ?? '', 0, 20) . "</td>";
            echo "<td>" . substr($tx['nominee_name'] ?? '', 0, 20) . "</td>";
            echo "<td>{$tx['vote_count']}</td>";
            echo "<td>{$tx['amount']}</td>";
            echo "<td><strong>{$tx['status']}</strong></td>";
            echo "<td>" . substr($tx['hubtel_transaction_id'] ?? '', 0, 10) . "</td>";
            echo "<td>{$tx['created_at']}</td>";
            echo "<td>{$tx['completed_at'] ?? '-'}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check if any main transactions were created from successful USSD payments
    echo "<h3>Main Transactions from USSD Payments:</h3>";
    $stmt = $pdo->prepare("
        SELECT t.*, n.name as nominee_name, e.title as event_title
        FROM transactions t
        LEFT JOIN nominees n ON t.nominee_id = n.id
        LEFT JOIN events e ON t.event_id = e.id
        WHERE t.payment_method = 'hubtel_ussd'
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $mainTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($mainTransactions)) {
        echo "<p style='color: red;'>No main transactions created from USSD payments!</p>";
        echo "<p>This suggests USSD payment callbacks are not being processed.</p>";
    } else {
        echo "<p style='color: green;'>Found " . count($mainTransactions) . " main transactions from USSD payments</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Reference</th><th>Event</th><th>Nominee</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
        foreach ($mainTransactions as $tx) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>{$tx['reference']}</td>";
            echo "<td>" . substr($tx['event_title'] ?? '', 0, 20) . "</td>";
            echo "<td>" . substr($tx['nominee_name'] ?? '', 0, 20) . "</td>";
            echo "<td>{$tx['voter_phone']}</td>";
            echo "<td>{$tx['amount']}</td>";
            echo "<td><strong>{$tx['status']}</strong></td>";
            echo "<td>{$tx['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check webhook logs for payment callbacks
    echo "<h3>Recent Webhook Logs (Payment Callbacks):</h3>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ussd_webhook_logs'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            $stmt = $pdo->prepare("
                SELECT * FROM ussd_webhook_logs
                WHERE JSON_EXTRACT(request_data, '$.Type') IN ('payment', 'payment_callback', 'paymentcallback')
                OR JSON_EXTRACT(request_data, '$.type') IN ('payment', 'payment_callback', 'paymentcallback')
                OR JSON_EXTRACT(request_data, '$.TransactionId') IS NOT NULL
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($logs)) {
                echo "<p style='color: red;'>No payment callback logs found!</p>";
                echo "<p>This suggests payment callbacks are not reaching the webhook.</p>";
            } else {
                echo "<p style='color: green;'>Found " . count($logs) . " payment callback logs</p>";
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Session ID</th><th>Phone</th><th>Request Data</th><th>Response Data</th><th>Created</th></tr>";
                foreach ($logs as $log) {
                    $request = json_decode($log['request_data'], true);
                    $response = json_decode($log['response_data'], true);

                    echo "<tr>";
                    echo "<td>" . substr($log['session_id'], 0, 10) . "</td>";
                    echo "<td>{$log['phone_number']}</td>";
                    echo "<td>" . substr(json_encode($request), 0, 100) . "...</td>";
                    echo "<td>" . substr(json_encode($response), 0, 100) . "...</td>";
                    echo "<td>{$log['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color: red;'>ussd_webhook_logs table doesn't exist</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking webhook logs: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Connection Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
