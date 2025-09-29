<?php
/**
 * Diagnostic script to test callback processing
 */

// Test data from the actual callback
$callbackData = json_decode('{
  "SessionId": "8114aa71b6e74c78b2138a66333976ad",
  "OrderId": "67a70417066f4f23aeacd3a1687e0c7a",
  "ExtraData": {},
  "OrderInfo": {
    "CustomerMobileNumber": "233545644749",
    "CustomerEmail": null,
    "CustomerName": "PAA KOW MENSAH",
    "Status": "Paid",
    "OrderDate": "2025-09-29T16:53:51.4786438+00:00",
    "Currency": "GHS",
    "BranchName": "Main",
    "IsRecurring": false,
    "RecurringInvoiceId": null,
    "Subtotal": 1.02,
    "Items": [
      {
        "ItemId": "9a1e24a908f64437968fd9ad73a42e17",
        "Name": "Vote for Ekow Mensah - 1 votes - Ref: USSD_1759164827_6305",
        "Quantity": 1,
        "UnitPrice": 1.0
      }
    ],
    "Payment": {
      "PaymentType": "mobilemoney",
      "AmountPaid": 1.02,
      "AmountAfterCharges": 1.0,
      "PaymentDate": "2025-09-29T16:53:51.4786438+00:00",
      "PaymentDescription": "The MTN Mobile Money payment has been approved and processed successfully.",
      "IsSuccessful": true
    }
  }
}', true);

echo "Testing callback processing...\n\n";

echo "1. Testing database connection...\n";
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

echo "\n2. Extracting USSD reference from callback...\n";
// Extract USSD reference from item name
$ussdReference = null;
$orderInfo = $callbackData['OrderInfo'] ?? null;

if ($orderInfo && isset($orderInfo['Items'])) {
    foreach ($orderInfo['Items'] as $item) {
        $itemName = $item['Name'] ?? '';
        if (preg_match('/Ref: (USSD_\d+_\d+)/', $itemName, $matches)) {
            $ussdReference = $matches[1];
            break;
        }
    }
}

if ($ussdReference) {
    echo "✓ Found USSD reference: $ussdReference\n";
} else {
    echo "✗ No USSD reference found in callback\n";
    exit;
}

echo "\n3. Checking if USSD transaction exists...\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM ussd_transactions WHERE transaction_ref = ?");
    $stmt->execute([$ussdReference]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transaction) {
        echo "✓ USSD transaction found:\n";
        echo "  - Status: {$transaction['status']}\n";
        echo "  - Amount: {$transaction['amount']}\n";
        echo "  - Phone: {$transaction['phone_number']}\n";
        echo "  - Votes: {$transaction['vote_count']}\n";
    } else {
        echo "✗ USSD transaction not found in database\n";
    }
} catch (Exception $e) {
    echo "✗ Database query failed: " . $e->getMessage() . "\n";
}

echo "\nDiagnostic complete.\n";
?>
