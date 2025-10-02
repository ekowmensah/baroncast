<?php
/**
 * USSD Session Diagnostic Script
 * Check USSD sessions and transactions
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    echo "<h2>USSD Session Diagnostic</h2>";

    // Check if ussd_sessions table exists
    echo "<h3>USSD Sessions Table:</h3>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ussd_sessions'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            echo "<p style='color: green;'>✓ ussd_sessions table exists</p>";

            // Show table structure
            echo "<h4>Table Structure:</h4>";
            $stmt = $pdo->query("DESCRIBE ussd_sessions");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";

            // Show recent session data
            echo "<h4>Recent Session Data (last hour):</h4>";
            $stmt = $pdo->prepare("
                SELECT session_id, session_key, session_value, session_data, created_at
                FROM ussd_sessions
                WHERE created_at > NOW() - INTERVAL 1 HOUR
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($sessions)) {
                echo "<p style='color: orange;'>No recent session data found</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Session ID</th><th>Key</th><th>Value</th><th>Data</th><th>Created</th></tr>";
                foreach ($sessions as $session) {
                    echo "<tr>";
                    echo "<td>{$session['session_id']}</td>";
                    echo "<td>{$session['session_key']}</td>";
                    echo "<td>{$session['session_value']}</td>";
                    echo "<td>" . substr($session['session_data'] ?? '', 0, 100) . "</td>";
                    echo "<td>{$session['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

        } else {
            echo "<p style='color: red;'>✗ ussd_sessions table does not exist</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking ussd_sessions table: " . $e->getMessage() . "</p>";
    }

    // Check ussd_transactions table
    echo "<h3>USSD Transactions:</h3>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ussd_transactions'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            echo "<p style='color: green;'>✓ ussd_transactions table exists</p>";

            // Show recent transactions
            echo "<h4>Recent Transactions:</h4>";
            $stmt = $pdo->prepare("
                SELECT transaction_ref, session_id, phone_number, event_id, nominee_id,
                       vote_count, amount, status, created_at
                FROM ussd_transactions
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($transactions)) {
                echo "<p style='color: orange;'>No transactions found</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Ref</th><th>Session ID</th><th>Phone</th><th>Event</th><th>Nominee</th><th>Votes</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
                foreach ($transactions as $tx) {
                    echo "<tr>";
                    echo "<td>{$tx['transaction_ref']}</td>";
                    echo "<td>" . substr($tx['session_id'], 0, 10) . "...</td>";
                    echo "<td>{$tx['phone_number']}</td>";
                    echo "<td>{$tx['event_id']}</td>";
                    echo "<td>{$tx['nominee_id']}</td>";
                    echo "<td>{$tx['vote_count']}</td>";
                    echo "<td>{$tx['amount']}</td>";
                    echo "<td>{$tx['status']}</td>";
                    echo "<td>{$tx['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

        } else {
            echo "<p style='color: red;'>✗ ussd_transactions table does not exist</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking ussd_transactions table: " . $e->getMessage() . "</p>";
    }

    // Check webhook logs
    echo "<h3>Recent Webhook Logs:</h3>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ussd_webhook_logs'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            $stmt = $pdo->prepare("
                SELECT session_id, phone_number, request_data, response_data, processing_time_ms, created_at
                FROM ussd_webhook_logs
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($logs)) {
                echo "<p style='color: orange;'>No webhook logs found</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Session ID</th><th>Phone</th><th>Request</th><th>Response</th><th>Processing (ms)</th><th>Created</th></tr>";
                foreach ($logs as $log) {
                    $request = json_decode($log['request_data'], true);
                    $response = json_decode($log['response_data'], true);

                    echo "<tr>";
                    echo "<td>" . substr($log['session_id'], 0, 10) . "...</td>";
                    echo "<td>{$log['phone_number']}</td>";
                    echo "<td>" . substr(json_encode($request), 0, 50) . "...</td>";
                    echo "<td>" . substr(json_encode($response), 0, 50) . "...</td>";
                    echo "<td>{$log['processing_time_ms']}</td>";
                    echo "<td>{$log['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

        } else {
            echo "<p style='color: red;'>✗ ussd_webhook_logs table does not exist</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking webhook logs: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Connection Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
