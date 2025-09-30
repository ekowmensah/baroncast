# Hubtel Callback Payloads Reference

## Overview
This document contains all the callback payload structures that Hubtel sends to your webhook endpoints for different payment methods.

## 1. Direct Receive Money Callback

**Webhook URL:** `/webhooks/hubtel-receive-money-callback.php`

### Success Payload
```json
{
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "TransactionId": "7fd01221faeb41469daec7b3561bddc5",
    "ClientReference": "PAY-68c17f01b6a7f",
    "Amount": 1.01,
    "Charges": 0.02,
    "AmountCharged": 1.03,
    "CustomerMsisdn": "233545644749",
    "Channel": "mtn-gh",
    "Description": "Vote for John Doe in Awards 2024",
    "ExternalTransactionId": "0000006824852622",
    "Status": "Success"
  }
}
```

### Failed Payload
```json
{
  "ResponseCode": "2001",
  "Status": "Failed", 
  "Data": {
    "TransactionId": "7fd01221faeb41469daec7b3561bddc5",
    "ClientReference": "PAY-68c17f01b6a7f",
    "Amount": 1.01,
    "CustomerMsisdn": "233545644749",
    "Channel": "mtn-gh",
    "Description": "Payment failed - insufficient balance",
    "Status": "Failed"
  }
}
```

## 2. USSD Service Fulfillment Callback

**Webhook URL:** `/webhooks/hubtel-ussd-callback.php`

### Success Payload
```json
{
  "SessionId": "session_abc123def456",
  "OrderId": "order_789xyz012",
  "OrderInfo": {
    "Status": "Paid",
    "Items": [
      {
        "Name": "Vote for John Doe - Ref: USSD_123_456",
        "Quantity": 1,
        "UnitPrice": 1.00,
        "TotalPrice": 1.00,
        "ItemId": "vote_item_001"
      }
    ],
    "Payment": {
      "IsSuccessful": true,
      "TransactionId": "hubtel_txn_789abc",
      "Amount": 1.00,
      "Charges": 0.02,
      "CustomerMsisdn": "233545644749",
      "Channel": "mtn-gh",
      "PaymentMethod": "mobilemoney"
    },
    "Customer": {
      "Name": "John Voter",
      "PhoneNumber": "233545644749"
    }
  }
}
```

### Failed Payload
```json
{
  "SessionId": "session_abc123def456",
  "OrderId": "order_789xyz012", 
  "OrderInfo": {
    "Status": "Failed",
    "Items": [
      {
        "Name": "Vote for John Doe - Ref: USSD_123_456",
        "Quantity": 1,
        "UnitPrice": 1.00,
        "TotalPrice": 1.00
      }
    ],
    "Payment": {
      "IsSuccessful": false,
      "TransactionId": null,
      "Amount": 1.00,
      "CustomerMsisdn": "233545644749",
      "ErrorMessage": "Payment cancelled by user"
    }
  }
}
```

## 3. Checkout/PayProxy Callback

**Webhook URL:** `/webhooks/hubtel-checkout-callback.php`

### Success Payload
```json
{
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "CheckoutId": "ebe6ffeafde14d92a216fe666ef9d7f0",
    "SalesInvoiceId": "2fe96501201f43e29d024cd11614d151",
    "ClientReference": "PAY-68c17f01b6a7f",
    "Status": "Success",
    "Amount": 1.01,
    "CustomerPhoneNumber": "233545644749",
    "PaymentDetails": {
      "MobileMoneyNumber": "233545644749",
      "PaymentType": "mobilemoney",
      "Channel": "mtn-gh",
      "TransactionId": "hubtel_txn_checkout_123",
      "ExternalTransactionId": "0000006824852622"
    },
    "Description": "The MTN Mobile Money payment has been approved and processed successfully.",
    "Timestamp": "2024-09-10T14:55:27+00:00"
  }
}
```

### Failed Payload
```json
{
  "ResponseCode": "2001",
  "Status": "Failed",
  "Data": {
    "CheckoutId": "ebe6ffeafde14d92a216fe666ef9d7f0",
    "SalesInvoiceId": "2fe96501201f43e29d024cd11614d151", 
    "ClientReference": "PAY-68c17f01b6a7f",
    "Status": "Failed",
    "Amount": 1.01,
    "CustomerPhoneNumber": "233545644749",
    "PaymentDetails": {
      "MobileMoneyNumber": "233545644749",
      "PaymentType": "mobilemoney",
      "Channel": "mtn-gh"
    },
    "Description": "Payment failed - transaction declined by provider",
    "Timestamp": "2024-09-10T14:55:27+00:00"
  }
}
```

## 4. Special GS-Callback Response (USSD)

Based on church management system pattern, USSD may require special callback acknowledgment:

**Send to:** `https://gs-callback.hubtel.com:9055/callback`

### Acknowledgment Payload
```json
{
  "SessionId": "session_abc123def456",
  "OrderId": "order_789xyz012",
  "ServiceStatus": "success",
  "MetaData": null
}
```

## Common Response Codes

| Code | Status | Description |
|------|--------|-------------|
| 0000 | Success | Transaction completed successfully |
| 0001 | Pending | Transaction is being processed |
| 2001 | Failed | Transaction failed |
| 4000 | Validation Error | Invalid request parameters |
| 4070 | Fees Error | Fee calculation error |
| 4101 | Setup Error | Account setup issue |
| 4103 | Permission Error | Insufficient permissions |

## Channel Codes

| Channel | Network |
|---------|---------|
| mtn-gh | MTN Ghana |
| vodafone-gh | Telecel Ghana (formerly Vodafone) |
| tigo-gh | AirtelTigo Ghana |

## Webhook Response Requirements

Your webhook should respond with:

### Success Response
```json
{
  "status": "success",
  "message": "Callback processed successfully",
  "transaction_id": "local_txn_123"
}
```

### Error Response
```json
{
  "status": "error", 
  "message": "Error processing callback",
  "error_code": "PROCESSING_ERROR"
}
```

## Testing Callback Payloads

You can use these sample payloads to test your webhook handlers:

### Test Success Callback
```bash
curl -X POST https://yourdomain.com/webhooks/hubtel-receive-money-callback.php \
  -H "Content-Type: application/json" \
  -d '{
    "ResponseCode": "0000",
    "Status": "Success",
    "Data": {
      "TransactionId": "test_txn_123",
      "ClientReference": "TEST_REF_001",
      "Amount": 1.00,
      "Charges": 0.02,
      "CustomerMsisdn": "233545644749",
      "Channel": "mtn-gh",
      "Status": "Success"
    }
  }'
```

### Test USSD Callback
```bash
curl -X POST https://yourdomain.com/webhooks/hubtel-ussd-callback.php \
  -H "Content-Type: application/json" \
  -d '{
    "SessionId": "test_session_123",
    "OrderId": "test_order_456",
    "OrderInfo": {
      "Status": "Paid",
      "Items": [
        {
          "Name": "Test Vote - Ref: USSD_TEST_001",
          "Quantity": 1,
          "UnitPrice": 1.00,
          "TotalPrice": 1.00
        }
      ],
      "Payment": {
        "IsSuccessful": true,
        "TransactionId": "test_hubtel_txn",
        "Amount": 1.00,
        "CustomerMsisdn": "233545644749"
      }
    }
  }'
```

## Callback Security

### Verify Callback Source
- Check IP address against Hubtel's known IPs
- Validate callback signature if provided
- Use HTTPS for all webhook URLs

### Example IP Validation
```php
$hubtel_ips = [
    '196.216.238.0/24',
    '41.215.160.0/24'
    // Add other Hubtel IP ranges
];

function isValidHubtelIP($ip) {
    global $hubtel_ips;
    foreach ($hubtel_ips as $range) {
        if (ipInRange($ip, $range)) {
            return true;
        }
    }
    return false;
}
```

## Debugging Callbacks

### Enable Detailed Logging
```php
// Log all callback data
$log_data = [
    'timestamp' => date('c'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'ip' => $_SERVER['REMOTE_ADDR']
];

file_put_contents('/logs/hubtel-callbacks.log', 
    json_encode($log_data, JSON_PRETTY_PRINT) . "\n", 
    FILE_APPEND
);
```

### Common Issues
1. **Callbacks not received**: Check webhook URL configuration in Hubtel portal
2. **Invalid JSON**: Ensure proper JSON parsing and error handling
3. **Timeout errors**: Keep webhook processing under 30 seconds
4. **Duplicate callbacks**: Implement idempotency checks using transaction IDs

## Integration Notes

- **Always respond with HTTP 200** for successful callback processing
- **Process callbacks idempotently** - handle duplicate callbacks gracefully  
- **Log all callback data** for debugging and audit purposes
- **Validate callback data** before processing
- **Use transaction status check API** as backup for missed callbacks
