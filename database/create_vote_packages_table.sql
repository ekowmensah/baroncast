-- Create bulk_vote_packages table if it doesn't exist
CREATE TABLE IF NOT EXISTS `bulk_vote_packages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `vote_count` int(11) NOT NULL,
    `price` decimal(10,2) NOT NULL,
    `event_id` int(11) DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `organizer_id` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `fk_bulk_vote_packages_event` (`event_id`),
    KEY `fk_bulk_vote_packages_organizer` (`organizer_id`),
    CONSTRAINT `fk_bulk_vote_packages_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bulk_vote_packages_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
