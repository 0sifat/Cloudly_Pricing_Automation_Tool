<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';
$userModel = new User();

if ($action === 'toggle_admin') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $is_admin = intval($_POST['is_admin'] ?? 0);
    
    if ($user_id && $userModel->updateAdminStatus($user_id, $is_admin)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update admin status']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}



