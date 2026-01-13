<?php
require_once __DIR__ . '/init.php';

$host = 'db';              // Docker service name
$db   = 'aws_calc';
$user = 'app_user';        // from docker-compose
$pass = 'app_password';    // from docker-compose
$port = 3306;              // internal MySQL port

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}