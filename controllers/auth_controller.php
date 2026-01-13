<?php
session_start();
require_once __DIR__ . '/../models/User.php';

// Helper function to get base path for redirects
function getBasePath() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname(dirname($script));
    $path = rtrim($path, '/');
    return $path ? $path : '';
}

$base_path = getBasePath();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user = new User();
    
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        
        if (empty($username) || empty($password) || empty($mobile_number)) {
            header("Location: " . $base_path . "/views/signup.php?error=" . urlencode('All fields are required'));
            exit;
        }
        
        // Check if username already exists
        if ($user->userExists($username)) {
            header("Location: " . $base_path . "/views/signup.php?error=" . urlencode('Username already taken. Please choose a different username.'));
            exit;
        }
        
        $result = $user->register($username, $password, $mobile_number);
        
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = 0;
            header("Location: " . $base_path . "/views/dashboard.php");
            exit;
        } else {
            header("Location: " . $base_path . "/views/signup.php?error=" . urlencode($result['error']));
            exit;
        }
    } elseif ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            header("Location: " . $base_path . "/views/login.php?error=" . urlencode('Username and password are required'));
            exit;
        }
        
        $result = $user->login($username, $password);
        
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            $_SESSION['is_admin'] = $result['user']['is_admin'] ?? 0;
            header("Location: " . $base_path . "/views/dashboard.php");
            exit;
        } else {
            header("Location: " . $base_path . "/views/login.php?error=" . urlencode($result['error']));
            exit;
        }
    }
}

header("Location: " . $base_path . "/views/login.php");
exit;






