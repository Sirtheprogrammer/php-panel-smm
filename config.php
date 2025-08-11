<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smm_panel');
define('SMMGUO_API_KEY', '8bebacbf714fff4b25e37804d27fdfe2');
define('SMMGUO_API_URL', 'https://smmguo.com/api/v2');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
?>

