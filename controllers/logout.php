<?php
session_start();

// Helper function to get base path for redirects
function getBasePath() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname(dirname($script));
    $path = rtrim($path, '/');
    return $path ? $path : '';
}

$base_path = getBasePath();
session_destroy();
header("Location: " . $base_path . "/views/login.php");
exit;






