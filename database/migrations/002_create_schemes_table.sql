-- Migration to create schemes table for payment/commission management
-- This table will store the financial schemes for events

CREATE TABLE schemes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    organizer_id INT NOT NULL,
    platform_commission DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    organizer_share DECIMAL(5,2) NOT NULL DEFAULT 90.00,
    vote_price DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    processing_fee DECIMAL(10,2) NOT NULL DEFAULT 0.05,
    status ENUM('active', 'draft', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_scheme (event_id)
);
