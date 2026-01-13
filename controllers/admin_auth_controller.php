<?php
session_start();
require_once __DIR__ . '/../config/database.php';
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
    
    if ($action === 'admin_login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            header("Location: " . $base_path . "/views/login.php?error=" . urlencode('Username and password are required') . "&tab=admin");
            exit;
        }
        
        // Special handling for initial admin user 'sharif' - auto-create on first login
        if (strtolower($username) === 'sharif' && $password === 'password@') {
            // Try to login first
            $result = $user->login($username, $password);
            
            if ($result['success']) {
                // User exists and password is correct
                if ($result['user']['is_admin'] == 1) {
                    $_SESSION['user_id'] = $result['user']['id'];
                    $_SESSION['username'] = $result['user']['username'];
                    $_SESSION['is_admin'] = true;
                    header("Location: " . $base_path . "/views/admin_dashboard.php");
                    exit;
                } else {
                    header("Location: " . $base_path . "/views/login.php?error=" . urlencode('Access denied. Admin privileges required.') . "&tab=admin");
                    exit;
                }
            } else {
                // User doesn't exist or password is wrong
                if ($user->userExists($username)) {
                    // User exists but password is wrong
                    header("Location: " . $base_path . "/views/login.php?error=" . urlencode('Invalid password. Please use your updated password.') . "&tab=admin");
                    exit;
                } else {
                    // First time - create initial admin user
                    $create_result = $user->createInitialAdmin($username, $password);
                    
                    if ($create_result['success']) {
                        $_SESSION['user_id'] = $create_result['user_id'];
                        $_SESSION['username'] = $create_result['username'];
                        $_SESSION['is_admin'] = true;
                        $_SESSION['first_login'] = true;
                        header("Location: " . $base_path . "/views/admin_dashboard.php?first_login=1");
                        exit;
                    } else {
                        header("Location: " . $base_path . "/views/login.php?error=" . urlencode($create_result['error']) . "&tab=admin");
                        exit;
                    }
                }
            }
        } else {
            // Regular admin login for other users
            $result = $user->login($username, $password);
            
            if ($result['success']) {
                // Check if user is admin
                if ($result['user']['is_admin'] == 1) {
                    $_SESSION['user_id'] = $result['user']['id'];
                    $_SESSION['username'] = $result['user']['username'];
                    $_SESSION['is_admin'] = true;
                    header("Location: " . $base_path . "/views/admin_dashboard.php");
                    exit;
                } else {
                    header("Location: " . $base_path . "/views/login.php?error=" . urlencode('Access denied. Admin privileges required.') . "&tab=admin");
                    exit;
                }
            } else {
                header("Location: " . $base_path . "/views/login.php?error=" . urlencode($result['error']) . "&tab=admin");
                exit;
            }
        }
    }
}

header("Location: " . $base_path . "/views/login.php?tab=admin");
exit;



