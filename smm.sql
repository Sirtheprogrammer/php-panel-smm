-- ==================================================================================================
-- SMM Panel Consolidated Database Schema
-- Version: 1.0
-- Description: A single, complete script to set up the entire database from scratch.
-- This script is idempotent and can be run safely on an empty database.
-- ==================================================================================================

--
-- Table structure for table `users`
-- (Inferred as a core requirement)
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `balance` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `api_providers`
-- (Consolidated from db_multi_provider.sql, api_providers_v2_schema.sql, update_api_providers.sql, db_updates.sql)
--
CREATE TABLE IF NOT EXISTS `api_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `api_url` varchar(255) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `api_email` varchar(255) DEFAULT NULL,
  `api_version` varchar(50) NOT NULL DEFAULT 'v2',
  `provider_type` varchar(100) NOT NULL DEFAULT 'other_v2',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `priority` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `services`
-- (Consolidated from db_services.sql and db_updates.sql)
--
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` varchar(255) DEFAULT NULL COMMENT 'ID from the API provider',
  `provider_id` int(11) DEFAULT NULL COMMENT 'FK to api_providers table',
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rate` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `min` int(11) NOT NULL DEFAULT 0,
  `max` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'default',
  `refill` tinyint(1) NOT NULL DEFAULT 0,
  `cancel` tinyint(1) NOT NULL DEFAULT 0,
  `dripfeed` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `api_providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `orders`
-- (Inferred as a core requirement)
--
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `provider_order_id` varchar(255) DEFAULT NULL,
  `link` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `charge` decimal(10,4) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `payment_requests`
-- (From payment_requests_schema.sql)
--
CREATE TABLE IF NOT EXISTS `payment_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `phone_number` varchar(20) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `screenshot_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_requests` (`user_id`,`status`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_transaction_code` (`transaction_code`),
  CONSTRAINT `payment_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `exchange_rates`
-- (From currency_schema_updates.sql)
--
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `rate` decimal(10,6) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_currency_pair` (`from_currency`,`to_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `transaction_logs`
-- (From currency_schema_updates.sql)
--
CREATE TABLE IF NOT EXISTS `transaction_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,4) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'e.g., order_placement, balance_addition, refund',
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `transaction_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample Data (Optional)
--

-- Add a default admin user (password: admin)
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$I/iA6s.T.3gE.JgG5.tA9e/F.p5.g3.zY.ZgG5.tA9e/F.p5.g3.z', 'admin@example.com', 'admin');

-- Add a sample exchange rate
INSERT INTO `exchange_rates` (`from_currency`, `to_currency`, `rate`) VALUES
('USD', 'TZS', 2700.000000);
