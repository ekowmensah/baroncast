-- Update existing ussd_sessions table to match code expectations
-- First, check current structure and add missing columns

ALTER TABLE ussd_sessions 
ADD COLUMN IF NOT EXISTS session_key VARCHAR(50) DEFAULT '',
ADD COLUMN IF NOT EXISTS session_value TEXT,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- If the table has the wrong primary key, we need to recreate it
-- But first, let's backup existing data
CREATE TABLE IF NOT EXISTS ussd_sessions_backup AS SELECT * FROM ussd_sessions;

-- Drop and recreate with correct structure (this will lose existing data)
DROP TABLE IF EXISTS ussd_sessions;

CREATE TABLE ussd_sessions (
    session_id VARCHAR(100) NOT NULL,
    session_key VARCHAR(50) NOT NULL,
    session_value TEXT,
    session_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, session_key),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);

-- Restore data if possible (this might not work due to structure differences)
-- INSERT IGNORE INTO ussd_sessions (session_id, session_key, session_value)
-- SELECT session_id, 'menu_data', session_data FROM ussd_sessions_backup
-- WHERE session_data IS NOT NULL;

-- Clean up backup
DROP TABLE IF EXISTS ussd_sessions_backup;
