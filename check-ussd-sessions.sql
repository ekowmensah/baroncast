-- Check if ussd_sessions table exists and show its structure
SHOW TABLES LIKE 'ussd_sessions';

-- If it exists, show the structure
DESCRIBE ussd_sessions;

-- Check recent session data (if table exists)
SELECT * FROM ussd_sessions
WHERE created_at > NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC
LIMIT 10;

-- Check ussd_transactions table for recent activity
SELECT * FROM ussd_transactions
ORDER BY created_at DESC
LIMIT 10;
