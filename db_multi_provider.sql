-- Multi-Provider API Support Database Schema
-- Run this to add multi-provider support to your existing database

-- API Providers table to store multiple API provider configurations
CREATE TABLE IF NOT EXISTS api_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    priority INT DEFAULT 1,
    success_rate DECIMAL(5,2) DEFAULT 100.00,
    last_check DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
);

-- Insert default provider (current SMMGUO)
INSERT INTO api_providers (name, api_url, api_key, status, priority) 
VALUES ('SMMGUO', 'https://smmguo.com/api/v2', '8bebacbf714fff4b25e37804d27fdfe2', 'active', 1)
ON DUPLICATE KEY UPDATE 
    api_url = VALUES(api_url),
    api_key = VALUES(api_key);

-- Update services table to support multiple providers
ALTER TABLE services 
ADD COLUMN IF NOT EXISTS provider_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS min_quantity INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS max_quantity INT DEFAULT 100000,
ADD COLUMN IF NOT EXISTS instructions TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS provider_service_id VARCHAR(50) DEFAULT NULL,
ADD FOREIGN KEY IF NOT EXISTS fk_services_provider (provider_id) REFERENCES api_providers(id);

-- Update the api_service_id to be provider-specific
UPDATE services SET provider_service_id = api_service_id WHERE provider_service_id IS NULL;

-- Provider service mapping for cross-provider service equivalents
CREATE TABLE IF NOT EXISTS provider_service_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    primary_service_id INT NOT NULL,
    backup_provider_id INT NOT NULL,
    backup_service_id VARCHAR(50) NOT NULL,
    priority INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (primary_service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (backup_provider_id) REFERENCES api_providers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mapping (primary_service_id, backup_provider_id)
);

-- Provider performance tracking
CREATE TABLE IF NOT EXISTS provider_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    total_orders INT DEFAULT 0,
    successful_orders INT DEFAULT 0,
    failed_orders INT DEFAULT 0,
    avg_response_time DECIMAL(8,3) DEFAULT 0.000,
    last_failure DATETIME DEFAULT NULL,
    last_success DATETIME DEFAULT NULL,
    date_recorded DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (provider_id) REFERENCES api_providers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_record (provider_id, date_recorded)
);

-- Update orders table to track which provider was used
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS provider_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS api_order_id VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS charge DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
ADD FOREIGN KEY IF NOT EXISTS fk_orders_provider (provider_id) REFERENCES api_providers(id);

-- Provider failover logs
CREATE TABLE IF NOT EXISTS provider_failover_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT DEFAULT NULL,
    original_provider_id INT NOT NULL,
    fallback_provider_id INT NOT NULL,
    reason TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (original_provider_id) REFERENCES api_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (fallback_provider_id) REFERENCES api_providers(id) ON DELETE CASCADE
);

-- Service synchronization logs
CREATE TABLE IF NOT EXISTS service_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    sync_type ENUM('manual', 'auto') DEFAULT 'manual',
    services_imported INT DEFAULT 0,
    services_updated INT DEFAULT 0,
    errors TEXT DEFAULT NULL,
    sync_duration DECIMAL(8,3) DEFAULT 0.000,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES api_providers(id) ON DELETE CASCADE
);
