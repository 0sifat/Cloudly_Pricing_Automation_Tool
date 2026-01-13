<?php
// Start output buffering to catch any PHP errors/warnings
ob_start();
// Turn off error display but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

// Set JSON header early
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../models/EC2Model.php';
require_once __DIR__ . '/../models/EBSModel.php';
require_once __DIR__ . '/../models/VPCModel.php';
require_once __DIR__ . '/../models/S3Model.php';
require_once __DIR__ . '/../models/RDSModel.php';
require_once __DIR__ . '/../models/EKSModel.php';
require_once __DIR__ . '/../models/ECRModel.php';
require_once __DIR__ . '/../models/LoadBalancerModel.php';
require_once __DIR__ . '/../models/WAFModel.php';
require_once __DIR__ . '/../models/Route53Model.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = intval($_POST['project_id'] ?? 0);
    
    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'Project ID is required']);
        exit;
    }
    
    switch ($action) {
        case 'save_ec2':
            try {
                // Clear any previous output
                ob_clean();
                $instances = json_decode($_POST['instances'] ?? '[]', true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'error' => 'Invalid JSON data: ' . json_last_error_msg()]);
                    exit;
                }
                $model = new EC2Model();
                $result = $model->saveInstances($project_id, $instances);
                // Get any output that might have been generated
                $output = ob_get_clean();
                if (!empty($output)) {
                    // If there was output, it's likely an error
                    echo json_encode(['success' => false, 'error' => 'Unexpected output: ' . substr($output, 0, 200)]);
                    exit;
                }
                echo json_encode($result);
                exit;
            } catch (Throwable $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error saving EC2 instances: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'save_ebs':
            $volumes = json_decode($_POST['volumes'] ?? '[]', true);
            $model = new EBSModel();
            $result = $model->saveVolumes($project_id, $volumes);
            echo json_encode($result);
            break;
            
        case 'save_vpc':
            $config = json_decode($_POST['config'] ?? '{}', true);
            $model = new VPCModel();
            $result = $model->saveConfig($project_id, $config);
            echo json_encode($result);
            break;
            
        case 'save_s3':
            $config = json_decode($_POST['config'] ?? '{}', true);
            $model = new S3Model();
            $result = $model->saveConfig($project_id, $config);
            echo json_encode($result);
            break;
            
        case 'save_rds':
            try {
                ob_clean(); // Clear any output before processing
                $config = json_decode($_POST['config'] ?? '[]', true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
                    break;
                }
                $model = new RDSModel();
                $result = $model->saveConfig($project_id, $config);
                ob_end_clean();
                echo json_encode($result);
            } catch (Exception $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error saving RDS: ' . $e->getMessage()]);
            }
            break;
            
        case 'save_eks':
            try {
                // Clean any previous output
                ob_clean();
                $config = json_decode($_POST['config'] ?? '{}', true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
                    exit;
                }
                $model = new EKSModel();
                $result = $model->saveConfig($project_id, $config);
                ob_end_clean();
                echo json_encode($result);
            } catch (Exception $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error saving EKS: ' . $e->getMessage()]);
            } catch (Error $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Fatal error saving EKS: ' . $e->getMessage()]);
            }
            exit;
            break;
            
        case 'save_ecr':
            $config = json_decode($_POST['config'] ?? '{}', true);
            $model = new ECRModel();
            $result = $model->saveConfig($project_id, $config);
            echo json_encode($result);
            break;
            
        case 'save_loadbalancer':
            try {
                // Clean any previous output
                ob_clean();
                $config = json_decode($_POST['config'] ?? '{}', true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    ob_end_clean();
                    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
                    exit;
                }
                $model = new LoadBalancerModel();
                $result = $model->saveConfig($project_id, $config);
                ob_end_clean();
                echo json_encode($result);
            } catch (Exception $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error saving LoadBalancer: ' . $e->getMessage()]);
            } catch (Error $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Fatal error saving LoadBalancer: ' . $e->getMessage()]);
            }
            exit;
            break;
            
        case 'save_waf':
            $config = json_decode($_POST['config'] ?? '{}', true);
            $model = new WAFModel();
            $result = $model->saveConfig($project_id, $config);
            echo json_encode($result);
            break;
            
        case 'save_route53':
            $config = json_decode($_POST['config'] ?? '{}', true);
            $model = new Route53Model();
            $result = $model->saveConfig($project_id, $config);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    // Handle endpoints that don't require project_id first
    switch ($action) {
        case 'get_ec2_instances_by_region':
            $region = $_GET['region'] ?? '';
            if (empty($region)) {
                echo json_encode(['success' => false, 'error' => 'Region is required']);
                exit;
            }
            require_once __DIR__ . '/../models/PricingModel.php';
            $pricingModel = new PricingModel();
            $instances = $pricingModel->getAllEC2Pricing($region);
            echo json_encode(['success' => true, 'instances' => $instances]);
            exit;
            
        case 'get_rds_instances_by_region':
            $region = $_GET['region'] ?? '';
            $engine = $_GET['engine'] ?? '';
            if (empty($region)) {
                echo json_encode(['success' => false, 'error' => 'Region is required']);
                exit;
            }
            require_once __DIR__ . '/../models/PricingModel.php';
            $pricingModel = new PricingModel();
            $allRDS = $pricingModel->getAllRDSPricing($region);
            // Filter by engine if provided
            if (!empty($engine)) {
                $allRDS = array_filter($allRDS, function($rds) use ($engine) {
                    return $rds['engine'] === $engine;
                });
            }
            echo json_encode(['success' => true, 'instances' => array_values($allRDS)]);
            exit;
            
        case 'get_user_profile':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            $user = $userModel->getUserById($_SESSION['user_id']);
            echo json_encode(['success' => true, 'user' => $user]);
            exit;
    }
    
    // All other endpoints require project_id
    $project_id = intval($_GET['project_id'] ?? 0);
    
    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'Project ID is required']);
        exit;
    }
    
    switch ($action) {
        case 'get_ec2':
            $model = new EC2Model();
            $instances = $model->getInstances($project_id);
            echo json_encode(['success' => true, 'instances' => $instances]);
            break;
            
        case 'get_ebs':
            $model = new EBSModel();
            $volumes = $model->getVolumes($project_id);
            echo json_encode(['success' => true, 'volumes' => $volumes]);
            break;
            
        case 'get_vpc':
            $model = new VPCModel();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_s3':
            $model = new S3Model();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_rds':
            $model = new RDSModel();
            $config = $model->getAllConfigs($project_id);
            // Return as array (backward compatible: single config will be wrapped)
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_eks':
            $model = new EKSModel();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_ecr':
            $model = new ECRModel();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_loadbalancer':
            $model = new LoadBalancerModel();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_waf':
            $model = new WAFModel();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'get_route53':
            $model = new Route53Model();
            $config = $model->getConfig($project_id);
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

