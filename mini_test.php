<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing MySQL connection...\n";

$configs = [
    'default' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => ''
    ],
    'socket' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'socket' => '/opt/lampp/var/mysql/mysql.sock'
    ]
];

foreach ($configs as $name => $config) {
    echo "\nTrying config: $name\n";
    echo "-------------------\n";
    
    try {
        $conn = new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            null,
            null,
            $config['socket'] ?? null
        );
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        echo "✅ Success!\n";
        echo "Server version: " . $conn->server_info . "\n";
        $conn->close();
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "\nPHP Version: " . phpversion() . "\n";
?>

<!-- Simple HTML form to test if PHP is working -->
<h3>If you see this, PHP is working!</h3>
