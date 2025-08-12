<?php
/**
 * Database Update Script for SMM Panel
 * 
 * This script updates the database schema to the latest version
 * while preserving all existing data.
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'smm_panel';

// Connect to database
try {
    $db = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }
    
    // Set charset to utf8mb4
    $db->set_charset("utf8mb4");
    
    // Start transaction
    $db->begin_transaction();
    
    echo "Starting database update...\n";
    
    // 1. Add new columns to api_providers if they don't exist
    $columns_to_add = [
        'api_email' => "ALTER TABLE api_providers ADD COLUMN IF NOT EXISTS api_email VARCHAR(255) DEFAULT NULL AFTER api_key",
        'api_version' => "ALTER TABLE api_providers ADD COLUMN IF NOT EXISTS api_version ENUM('v1', 'v2') DEFAULT 'v2' AFTER api_email",
        'provider_type' => "ALTER TABLE api_providers ADD COLUMN IF NOT EXISTS provider_type VARCHAR(50) DEFAULT 'other_v2' AFTER api_version"
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        if (!$db->query("SHOW COLUMNS FROM api_providers LIKE '$column'")) {
            echo "Adding column $column to api_providers...\n";
            if (!$db->query($sql)) {
                throw new Exception("Failed to add column $column: " . $db->error);
            }
        }
    }
    
    // 2. Update existing providers to use v2 if not set
    $db->query("UPDATE api_providers SET api_version = 'v2' WHERE api_version IS NULL OR api_version = ''");
    
    // 3. Add exchange_rates table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS exchange_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usd_to_tzs DECIMAL(10,4) NOT NULL,
        tzs_to_usd DECIMAL(10,8) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // 4. Insert default exchange rate if none exists
    $db->query("INSERT IGNORE INTO exchange_rates (usd_to_tzs, tzs_to_usd, status) 
                VALUES (2700.0000, 0.00037037, 'active')");
    
    // 5. Add balance_currency to users table if it doesn't exist
    if (!$db->query("SHOW COLUMNS FROM users LIKE 'balance_currency'")) {
        $db->query("ALTER TABLE users ADD COLUMN balance_currency VARCHAR(3) DEFAULT 'usd' AFTER balance");
    }
    
    // 6. Create payment_requests table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS payment_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'usd',
        payment_method VARCHAR(50) NOT NULL,
        transaction_id VARCHAR(100) DEFAULT NULL,
        screenshot_path VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // 7. Add indexes for better performance
    $indexes = [
        'idx_orders_user' => 'CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id)',
        'idx_orders_status' => 'CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)',
        'idx_services_provider' => 'CREATE INDEX IF NOT EXISTS idx_services_provider ON services(provider_id)',
        'idx_payment_requests_user' => 'CREATE INDEX IF NOT EXISTS idx_payment_requests_user ON payment_requests(user_id)',
        'idx_payment_requests_status' => 'CREATE INDEX IF NOT EXISTS idx_payment_requests_status ON payment_requests(status)'
    ];
    
    foreach ($indexes as $name => $sql) {
        echo "Creating index $name...\n";
        if (!$db->query($sql)) {
            echo "Warning: Could not create index $name - " . $db->error . "\n";
        }
    }
    
    // Commit all changes
    $db->commit();
    echo "\nDatabase update completed successfully!\n";
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($db)) {
        $db->rollback();
    }
    die("Error: " . $e->getMessage());
} finally {
    if (isset($db)) {
        $db->close();
    }
}
?>
