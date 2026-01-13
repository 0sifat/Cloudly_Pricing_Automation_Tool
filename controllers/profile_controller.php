<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../models/User.php';

$user = new User();
$action = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    $user_id = $_SESSION['user_id'];
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Get current user
    $current_user = $user->getUserById($user_id);
    
    // Verify current password if new password is provided
    if (!empty($new_password)) {
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'error' => 'Current password is required to change password']);
            exit;
        }
        
        // Verify current password
        require_once __DIR__ . '/../config/database.php';
        global $conn;
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user_data['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }
    }
    
    // Update user profile
    require_once __DIR__ . '/../config/database.php';
    global $conn;
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET mobile_number = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $mobile_number, $hashed_password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET mobile_number = ? WHERE id = ?");
        $stmt->bind_param("si", $mobile_number, $user_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

