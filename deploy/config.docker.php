<?php
// Docker-specific database configuration
// This file uses environment variables set in docker-compose.yml

$host = getenv('DB_HOST') ?: 'database';
$db = getenv('DB_NAME') ?: 'aws_calc';
$user = getenv('DB_USER') ?: 'app_user';
$pass = getenv('DB_PASS') ?: 'app_password';
$port = getenv('DB_PORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

