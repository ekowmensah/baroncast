-- Database cleanup script to remove all sample/hardcoded data
-- This preserves the database structure but removes all test data

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Clean all data (order matters due to foreign keys)
DELETE FROM votes;
DELETE FROM transactions;
DELETE FROM nominees;
DELETE FROM categories;
DELETE FROM schemes;
DELETE FROM events;
DELETE FROM users WHERE role = 'organizer';

-- Reset AUTO_INCREMENT values
ALTER TABLE votes AUTO_INCREMENT = 1;
ALTER TABLE transactions AUTO_INCREMENT = 1;
ALTER TABLE nominees AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE schemes AUTO_INCREMENT = 1;
ALTER TABLE events AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Show remaining admin users
SELECT 'Remaining Admin Users:' as info;
SELECT id, username, full_name, email FROM users WHERE role = 'admin';
