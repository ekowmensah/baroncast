-- Update categories table to support many-to-many relationship with events
-- Step 1: Create junction table for category-event relationships
CREATE TABLE IF NOT EXISTS `category_events` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_id` int(11) NOT NULL,
    `event_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category_event` (`category_id`, `event_id`),
    KEY `fk_category_events_category` (`category_id`),
    KEY `fk_category_events_event` (`event_id`),
    CONSTRAINT `fk_category_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_category_events_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Migrate existing data to junction table
INSERT IGNORE INTO `category_events` (`category_id`, `event_id`)
SELECT `id`, `event_id` FROM `categories` WHERE `event_id` IS NOT NULL;

-- Step 3: Remove event_id column from categories table (after data migration)
-- ALTER TABLE `categories` DROP FOREIGN KEY `categories_ibfk_1`;
-- ALTER TABLE `categories` DROP COLUMN `event_id`;

-- Note: The ALTER statements are commented out for safety. 
-- Run them manually after verifying data migration is successful.
