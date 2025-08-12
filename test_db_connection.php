<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

// Test configuration
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'mysql'  // Try to connect to the default mysql database
];

// Try to connect
try {
    echo "Connecting to MySQL server...\n";
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Successfully connected to MySQL server\n";
    echo "MySQL Server version: " . $conn->server_version . "\n";
    echo "Host info: " . $conn->host_info . "\n";
    
    // List databases
    echo "\nAvailable databases:\n";
    $result = $conn->query("SHOW DATABASES");
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    // Additional diagnostic info
    echo "\nPHP Version: " . phpversion() . "\n";
    echo "MySQLi available: " . (function_exists('mysqli_connect') ? 'Yes' : 'No') . "\n";
    
    // Try to get more detailed error info
    if (function_exists('mysqli_connect_error')) {
        echo "MySQLi Error: " . mysqli_connect_error() . "\n";
    }
}
