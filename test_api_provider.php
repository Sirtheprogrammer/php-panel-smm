<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test configuration
$testConfig = [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'smm_panel_test',
    'db_port' => 3306,
    'db_socket' => null // Let MySQL use default socket
];

// Function to display errors in a readable format
function displayError($message, $error = null) {
    echo "\n❌ ERROR: $message\n";
    if ($error) {
        echo "Details: $error\n";
    }
    echo "\n";
}

// First connect without database to create it if needed
$conn = @new mysqli(
    $testConfig['db_host'],
    $testConfig['db_user'],
    $testConfig['db_pass'],
    '',
    $testConfig['port'] ?? 3306,
    $testConfig['socket'] ?? null
);

if ($conn->connect_error) {
    // Try with default parameters if the first attempt fails
    $conn = @new mysqli(
        $testConfig['db_host'],
        $testConfig['db_user'],
        $testConfig['db_pass']
    );
    
    if ($conn->connect_error) {
        displayError("Failed to connect to MySQL server", $conn->connect_error);
        displayError("Tried with:", print_r($testConfig, true));
        displayError("PHP Version: " . phpversion());
        die("\nPlease check your MySQL server is running and the credentials are correct.\n");
    }
}

echo "✅ Connected to MySQL server\n";

// Create database if not exists
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$testConfig['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    displayError("Failed to create database", $conn->error);
    die("\n");
}

echo "✅ Database '{$testConfig['db_name']}' ready\n";

// Select the database
if (!$conn->select_db($testConfig['db_name'])) {
    displayError("Failed to select database '{$testConfig['db_name']}'", $conn->error);
    die("\n");
}

// Create api_providers table if not exists
$createTableSQL = "
    DROP TABLE IF EXISTS `api_providers`;
    CREATE TABLE `api_providers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `api_url` VARCHAR(512) NOT NULL,
        `api_key` VARCHAR(255) NOT NULL,
        `api_email` VARCHAR(255) DEFAULT NULL,
        `api_version` VARCHAR(10) DEFAULT 'v2',
        `provider_type` VARCHAR(50) DEFAULT 'other_v2',
        `status` ENUM('active','inactive') DEFAULT 'active',
        `priority` INT(11) DEFAULT 5,
        `description` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`),
        UNIQUE KEY `api_url` (`api_url`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Execute the SQL
if ($conn->multi_query($createTableSQL)) {
    // Flush multi_queries
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        displayError("Error in SQL execution", $conn->error);
        die("\n");
    }
    
    echo "✅ Database tables created successfully\n";
} else {
    displayError("Failed to create tables", $conn->error);
    die("\n");
}

// Clear any remaining results from multi_query
while ($conn->more_results() && $conn->next_result()) {
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

// Set charset
$conn->set_charset('utf8mb4');

// Assign to global $db
$db = $conn;

// Include required files
require_once 'MultiProviderApi.php';

// Test adding a new v2 API provider
function testAddProvider() {
    global $db;
    
    echo "=== Testing Add Provider ===\n";
    
    $apiManager = new MultiProviderApi($db);
    
    // Test data for a v2 API provider
    $testData = [
        'name' => 'Test SMMGUO v2',
        'api_url' => 'https://api.smmguo.com/api/v2',
        'api_key' => 'test_api_key_123',
        'api_email' => 'test@example.com',
        'provider_type' => 'smmguo',
        'priority' => 1,
        'status' => 'active'
    ];
    
    // Test adding the provider
    $providerId = $apiManager->addProvider(
        $testData['name'],
        $testData['api_url'],
        $testData['api_key'],
        $testData['api_email'],
        $testData['provider_type'],
        $testData['priority'],
        $testData['status']
    );
    
    if ($providerId === false) {
        echo "❌ Failed to add provider: " . $apiManager->getLastError() . "\n";
        return false;
    }
    
    echo "✅ Successfully added provider with ID: $providerId\n";
    
    // Verify the provider was added
    $stmt = $db->prepare("SELECT * FROM api_providers WHERE id = ?");
    $stmt->bind_param("i", $providerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "❌ Provider not found in database\n";
        return false;
    }
    
    $provider = $result->fetch_assoc();
    echo "✅ Verified provider in database\n";
    
    // Clean up
    $db->query("DELETE FROM api_providers WHERE id = $providerId");
    echo "✅ Cleaned up test provider\n";
    
    return true;
}

// Run the test
testAddProvider();

echo "\n=== Test Complete ===\n";
?>
