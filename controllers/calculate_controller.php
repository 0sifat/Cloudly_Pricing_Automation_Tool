<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../config/database.php';

$project_id = intval($_GET['project_id'] ?? 0);

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID is required']);
    exit;
}

$projectModel = new Project();
$summary = $projectModel->updateProjectTotals($project_id);

// Get breakdown by service
global $conn;
$breakdown = [];

// EC2 - use pricing model costs if available, otherwise base cost (both are now monthly)
$ec2_total = 0;
$stmt = $conn->prepare("SELECT id, unit_cost, quantity FROM ec2_instances WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Check if pricing models exist
    $stmt2 = $conn->prepare("SELECT SUM(total_cost) as pm_total FROM ec2_pricing_models WHERE ec2_instance_id = ?");
    $stmt2->bind_param("i", $row['id']);
    $stmt2->execute();
    $pm_result = $stmt2->get_result();
    $pm_row = $pm_result->fetch_assoc();
    
    if ($pm_row && $pm_row['pm_total'] > 0) {
        // Use pricing model costs (already monthly)
        $ec2_total += $pm_row['pm_total'];
    } else {
        // Use base instance cost (already monthly)
        $ec2_total += $row['unit_cost'] * $row['quantity'];
    }
}
$breakdown['EC2'] = floatval($ec2_total);

// EBS
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM ebs_volumes WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['EBS'] = floatval($row['total'] ?? 0);

// VPC
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM vpc_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['VPC'] = floatval($row['total'] ?? 0);

// S3
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM s3_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['S3'] = floatval($row['total'] ?? 0);

// RDS
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM rds_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['RDS'] = floatval($row['total'] ?? 0);

// EKS
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM eks_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['EKS'] = floatval($row['total'] ?? 0);

// ECR
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM ecr_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['ECR'] = floatval($row['total'] ?? 0);

// Load Balancer
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM load_balancer_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['Load Balancer'] = floatval($row['total'] ?? 0);

// WAF
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM waf_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['WAF'] = floatval($row['total'] ?? 0);

// Route53
$stmt = $conn->prepare("SELECT SUM(total_cost) as total FROM route53_configs WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$breakdown['Route53'] = floatval($row['total'] ?? 0);

echo json_encode([
    'success' => true,
    'summary' => [
        'total_unit_cost' => floatval($summary['total_unit_cost']),
        'total_estimated_cost' => floatval($summary['total_estimated_cost']),
        'breakdown' => $breakdown
    ]
]);






