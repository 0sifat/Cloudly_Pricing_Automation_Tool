<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$userModel = new User();

// Verify admin status
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $user_id = intval($_SESSION['user_id']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        
        // Validate username
        if (empty($username)) {
            echo json_encode(['success' => false, 'error' => 'Username is required']);
            exit;
        }
        
        // Check if username already exists (excluding current user)
        if ($userModel->checkUsernameExists($username, $user_id)) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }
        
        // Validate password if provided
        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
                exit;
            }
            
            if ($password !== $confirm_password) {
                echo json_encode(['success' => false, 'error' => 'Passwords do not match']);
                exit;
            }
        }
        
        // Update profile
        $result = $userModel->updateProfile($user_id, $username, $password ?: null, $mobile_number ?: null);
        
        if ($result['success']) {
            // Update session username
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to update profile']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}



