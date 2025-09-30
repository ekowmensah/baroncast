# Hubtel Transaction Status Check Implementation

## Overview

This implementation provides a comprehensive solution for Hubtel's **mandatory** Transaction Status Check API. The system automatically checks transaction statuses when callbacks are not received within 5 minutes, as required by Hubtel's integration guidelines.

## Key Features

### 1. **Mandatory Transaction Status Check API**
- Implements Hubtel's official Transaction Status Check API
- Uses the correct endpoint: `https://api-txnstatus.hubtel.com/transactions/{POS_Sales_ID}/status`
- Supports all three transaction identifier types:
  - `clientReference` (recommended)
  - `hubtelTransactionId`
  - `networkTransactionId`

### 2. **Automatic Status Monitoring**
- Batch processing of pending transactions
- Automatic vote creation when payments are completed
- Configurable batch sizes and processing intervals
- Comprehensive error handling and logging

### 3. **Multiple Access Methods**
- Manual single transaction checks
- Batch processing via admin interface
- Automated cron job execution
- RESTful API endpoints

## File Structure

```
baroncast/
├── services/
│   └── HubtelTransactionStatusService.php    # Core status check service
├── voter/actions/
│   └── check-transaction-status.php          # Manual status check endpoint
├── admin/
│   ├── batch-status-check.php               # Batch processing endpoint
│   ├── transaction-status-checker.html      # Admin interface
│   └── api/
│       └── pending-transactions.php         # Pending transactions API
├── cron/
│   └── status-check-cron.php               # Automated cron job
└── docs/
    └── HUBTEL_TRANSACTION_STATUS_CHECK.md   # This documentation
```

## Core Service: HubtelTransactionStatusService

### Key Methods

#### `checkTransactionStatus($clientReference)`
Checks transaction status using client reference (recommended method).

```php
$statusService = new HubtelTransactionStatusService();
$result = $statusService->checkTransactionStatus('TRANSACTION_REF_123');

if ($result['success']) {
    echo "Status: " . $result['status']; // Paid, Unpaid, Refunded
    echo "Amount: " . $result['amount'];
    echo "Is Paid: " . ($result['is_paid'] ? 'Yes' : 'No');
}
```

#### `runBatchStatusCheck($batchSize)`
Processes multiple pending transactions in batch.

```php
$result = $statusService->runBatchStatusCheck(20);
echo "Processed: " . $result['processed_count'];
echo "Status Changed: " . $result['status_changed_count'];
echo "Votes Created: " . $result['votes_created_count'];
```

#### `getPendingTransactions($limit)`
Gets transactions that need status checking (older than 5 minutes).

```php
$pending = $statusService->getPendingTransactions(50);
foreach ($pending as $transaction) {
    echo "Transaction: " . $transaction['reference'];
    echo "Age: " . $transaction['created_at'];
}
```

## API Endpoints

### 1. Manual Status Check
**Endpoint:** `POST /voter/actions/check-transaction-status.php`

**Request:**
```json
{
    "transaction_ref": "TRANSACTION_REF_123",
    "check_type": "client_reference",
    "auto_update": true
}
```

**Response:**
```json
{
    "success": true,
    "hubtel_status": "Paid",
    "is_paid": true,
    "transaction_data": {
        "transaction_id": "7fd01221faeb41469daec7b3561bddc5",
        "amount": 5.00,
        "charges": 0.10,
        "amount_after_charges": 4.90
    },
    "local_transaction": {
        "reference": "TRANSACTION_REF_123",
        "status": "completed",
        "event_title": "Awards 2024",
        "nominee_name": "John Doe"
    }
}
```

### 2. Batch Status Check
**Endpoint:** `POST /admin/batch-status-check.php`

**Request:**
```json
{
    "batch_size": 20
}
```

**Response:**
```json
{
    "success": true,
    "processed_count": 15,
    "status_changed_count": 8,
    "votes_created_count": 24,
    "execution_time": "2024-01-15 10:30:45"
}
```

### 3. Pending Transactions Info
**Endpoint:** `GET /admin/api/pending-transactions.php?action=summary`

**Response:**
```json
{
    "success": true,
    "summary": {
        "total_pending": 25,
        "ready_for_check": 15,
        "overdue": 5,
        "oldest_pending": "2024-01-15 09:45:00"
    },
    "last_status_check": "2024-01-15 10:25:00"
}
```

## Automated Processing

### Cron Job Setup

The system includes an automated cron job for regular status checking:

**File:** `/cron/status-check-cron.php`

#### Linux/Unix Cron Setup
```bash
# Run every 5 minutes
*/5 * * * * /usr/bin/php /path/to/baroncast/cron/status-check-cron.php

# Run every 10 minutes (recommended)
*/10 * * * * /usr/bin/php /path/to/baroncast/cron/status-check-cron.php
```

#### Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Every 10 minutes
4. Action: Start a program
5. Program: `php.exe`
6. Arguments: `"C:\xampp\htdocs\baroncast\cron\status-check-cron.php"`

#### Web-based Cron (Alternative)
```bash
# Using wget or curl
*/10 * * * * wget -q -O - "https://yourdomain.com/baroncast/cron/status-check-cron.php?api_key=YOUR_API_KEY"
```

## Configuration

### Required Hubtel Settings

Ensure these settings are configured in your `system_settings` table:

```sql
INSERT INTO system_settings (setting_key, setting_value) VALUES
('hubtel_pos_id', 'YOUR_POS_SALES_ID'),
('hubtel_api_key', 'YOUR_API_KEY'),
('hubtel_api_secret', 'YOUR_API_SECRET'),
('hubtel_environment', 'live'), -- or 'sandbox'
('batch_status_api_key', 'SECURE_API_KEY_FOR_BATCH_OPERATIONS');
```

### Environment URLs

The service automatically uses the correct endpoints:

- **Live:** `https://api-txnstatus.hubtel.com`
- **Sandbox:** `https://api-txnstatus-sandbox.hubtel.com`

## Status Mapping

The system maps Hubtel statuses to internal statuses:

| Hubtel Status | Internal Status | Description |
|---------------|----------------|-------------|
| `Paid` | `completed` | Payment successful, votes created |
| `Unpaid` | `pending` | Payment still processing |
| `Refunded` | `refunded` | Payment was refunded |
| `Failed` | `failed` | Payment failed |
| `Cancelled` | `cancelled` | Payment was cancelled |

## Error Handling

### Common Error Scenarios

1. **401 Unauthorized**
   - Check API credentials
   - Verify POS Sales ID
   - Ensure account has Transaction Status Check access

2. **404 Not Found**
   - Verify POS Sales ID is correct
   - Check if transaction exists in Hubtel system

3. **Rate Limiting**
   - System includes automatic delays between requests
   - Batch processing uses smaller batch sizes

4. **Network Issues**
   - Comprehensive retry logic
   - Detailed logging for debugging

### Logging

All operations are logged to:
- `/logs/cron-status-check.log` - Cron job logs
- `/logs/status-check.log` - Manual status checks
- `/logs/batch-status-check.log` - Batch operations

## Admin Interface

### Transaction Status Checker
**URL:** `/admin/transaction-status-checker.html`

Features:
- Single transaction status lookup
- Batch processing controls
- Real-time results display
- JSON response viewer
- Pending transaction statistics

### Usage Examples

1. **Check Single Transaction:**
   - Enter transaction reference
   - Select check type (client reference recommended)
   - Enable auto-update if needed
   - Click "Check Status"

2. **Run Batch Check:**
   - Set batch size (1-100)
   - Click "Run Batch Status Check"
   - Monitor results in real-time

## Integration with Existing System

### Vote Creation Process

When a transaction status changes to "Paid":

1. **Update Transaction Status**
   ```sql
   UPDATE transactions SET status = 'completed' WHERE reference = ?
   ```

2. **Create Individual Votes**
   ```sql
   INSERT INTO votes (event_id, category_id, nominee_id, voter_phone, 
                     transaction_id, payment_method, payment_reference, 
                     payment_status, amount, voted_at, created_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())
   ```

3. **Prevent Duplicate Votes**
   - System checks for existing votes before creation
   - Uses `payment_reference` as unique identifier

### Database Schema Compatibility

The service works with both minimal and extended schemas:

**Minimal Schema:**
- Basic `transactions` table with `status` field

**Extended Schema:**
- Additional fields: `hubtel_transaction_id`, `external_transaction_id`, `payment_response`

## Security Considerations

### API Key Protection
- Store API keys securely in database
- Use environment variables for sensitive data
- Implement proper authentication for admin endpoints

### IP Whitelisting
Hubtel requires IP whitelisting for Transaction Status Check API:
- Submit your server's public IP to Hubtel support
- Use static IP addresses for production servers

### Rate Limiting
- Built-in delays between API requests
- Configurable batch sizes to prevent overwhelming API
- Exponential backoff for failed requests

## Monitoring and Maintenance

### Health Checks

Monitor these metrics:
- Pending transaction count
- Status check success rate
- Average processing time
- Error frequency

### Performance Optimization

1. **Batch Size Tuning**
   - Start with batch size of 10-20
   - Increase based on API performance
   - Monitor for rate limiting

2. **Frequency Adjustment**
   - Run every 5-10 minutes for active systems
   - Reduce frequency during low-traffic periods
   - Increase frequency during high-volume events

3. **Database Optimization**
   - Index on `status` and `created_at` fields
   - Regular cleanup of old completed transactions
   - Archive old transaction data

## Troubleshooting

### Common Issues

1. **No Pending Transactions Found**
   - Check if Hubtel payments are enabled
   - Verify transaction creation process
   - Review callback handling

2. **Status Check Always Fails**
   - Verify Hubtel credentials
   - Check IP whitelisting
   - Test with sandbox environment first

3. **Votes Not Created**
   - Check nominee and category relationships
   - Verify vote creation logic
   - Review database constraints

### Debug Mode

Enable detailed logging by setting:
```php
error_reporting(E_ALL);
ini_set('log_errors', 1);
```

## Best Practices

1. **Regular Monitoring**
   - Set up alerts for failed status checks
   - Monitor pending transaction accumulation
   - Track vote creation success rates

2. **Backup Strategy**
   - Regular database backups
   - Transaction log retention
   - Configuration backup

3. **Testing**
   - Test with sandbox environment first
   - Validate status mapping logic
   - Test batch processing limits

4. **Documentation**
   - Keep API credentials documented securely
   - Document any customizations
   - Maintain deployment procedures

## Support and Maintenance

For issues related to:
- **Hubtel API:** Contact Hubtel support with transaction details
- **System Integration:** Review logs and check configuration
- **Performance:** Monitor batch sizes and processing frequency

## Conclusion

This implementation provides a robust, scalable solution for Hubtel's mandatory Transaction Status Check requirement. It ensures that no transactions are left in pending status due to missed callbacks, automatically creates votes when payments are completed, and provides comprehensive monitoring and management tools.

The system is designed to handle high-volume voting events while maintaining data integrity and providing real-time status updates for administrators and users.
