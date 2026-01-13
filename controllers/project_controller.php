<?php
session_start();
require_once __DIR__ . '/../models/Project.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$projectModel = new Project();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $project_name = $_POST['project_name'] ?? '';
    $salesman_name = $_POST['salesman_name'] ?? '';
    $region = $_POST['region'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if (empty($project_name) || empty($salesman_name) || empty($region)) {
        echo json_encode(['success' => false, 'error' => 'Project name, salesman name, and region are required']);
        exit;
    }
    
    $result = $projectModel->createProject($user_id, $project_name, $salesman_name, $region);
    echo json_encode($result);
} elseif ($action === 'update') {
    $project_id = $_POST['project_id'] ?? 0;
    $project_name = $_POST['project_name'] ?? '';
    $salesman_name = $_POST['salesman_name'] ?? '';
    $region = $_POST['region'] ?? '';
    
    if (empty($project_name) || empty($salesman_name) || empty($region) || !$project_id) {
        echo json_encode(['success' => false, 'error' => 'All fields including region are required']);
        exit;
    }
    
    $result = $projectModel->updateProject($project_id, $project_name, $salesman_name, $region);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}





