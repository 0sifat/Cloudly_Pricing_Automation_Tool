<?php
require_once __DIR__ . '/init.php';

$host = 'localhost';              // Docker service name
$db   = 'aws_calc';
$user = 'root';        // from docker-compose
$pass = '';    // from docker-compose
$port = 3307;              // internal MySQL port

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
