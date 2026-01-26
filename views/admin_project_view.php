<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Project.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$userModel = new User();
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$project_id = intval($_GET['project_id'] ?? 0);
if (!$project_id) {
    header("Location: admin_dashboard.php?error=Project ID required");
    exit;
}

// Get project data
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

$projectModel = new Project();
$project = $projectModel->getProject($project_id);

if (!$project) {
    header("Location: admin_dashboard.php?error=Project not found");
    exit;
}

// Get creator user
$creator = $userModel->getUserById($project['user_id']);

// Get all service data
$ec2Model = new EC2Model();
$ebsModel = new EBSModel();
$vpcModel = new VPCModel();
$s3Model = new S3Model();
$rdsModel = new RDSModel();
$eksModel = new EKSModel();
$ecrModel = new ECRModel();
$lbModel = new LoadBalancerModel();
$wafModel = new WAFModel();
$route53Model = new Route53Model();

$ec2_instances = $ec2Model->getInstances($project_id);
$ebs_volumes = $ebsModel->getVolumes($project_id);
$vpc_configs = $vpcModel->getAllConfigs($project_id);
$s3_configs = $s3Model->getAllConfigs($project_id);
$rds_config = $rdsModel->getConfig($project_id);
$eks_config = $eksModel->getConfig($project_id);
$ecr_config = $ecrModel->getConfig($project_id);
$lb_config = $lbModel->getConfig($project_id);
$waf_config = $wafModel->getConfig($project_id);
$route53_config = $route53Model->getConfig($project_id);

// Calculate totals
$projectModel->updateProjectTotals($project_id);
global $conn;
$stmt = $conn->prepare("SELECT * FROM project_totals WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
if (!$totals) {
    $totals = ['total_unit_cost' => 0, 'total_estimated_cost' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Invoice - <?php echo htmlspecialchars($project['project_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
        }
        .invoice-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 30px;
            background: #2a2a2a;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #ff6b35;
        }
        .invoice-header h1 {
            color: #fff;
            font-size: 32px;
            margin-bottom: 5px;
        }
        .invoice-header h2 {
            color: #ccc;
            font-size: 18px;
            font-weight: normal;
        }
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-box {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        .info-box h3 {
            color: #ff6b35;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-box p {
            color: #fff;
            margin: 8px 0;
        }
        .service-section {
            margin-bottom: 30px;
        }
        .service-section h3 {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
            font-size: 18px;
        }
        .service-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid rgba(255, 107, 53, 0.2);
            background: #1a1a1a;
        }
        .service-table th {
            background: #2a2a2a;
            color: #fff;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid rgba(255, 107, 53, 0.3);
            font-weight: 600;
        }
        .service-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 107, 53, 0.1);
            color: #fff;
        }
        .service-table tr:last-child td {
            border-bottom: none;
        }
        .service-table tr.subtotal {
            background: rgba(255, 107, 53, 0.1);
            font-weight: bold;
        }
        .total-section {
            margin-top: 30px;
            padding: 25px;
            background: #1a1a1a;
            border-radius: 8px;
            border: 2px solid rgba(255, 107, 53, 0.3);
        }
        .total-section h3 {
            color: #ff6b35;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 107, 53, 0.2);
            color: #fff;
        }
        .total-row:last-child {
            border-bottom: none;
            font-size: 24px;
            font-weight: bold;
            color: #ff6b35;
            margin-top: 10px;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.4);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="../assets/cloudlybangladesh_logo.jpg" alt="Cloudly Logo" class="header-logo">
                <h1>Cloudly AWS Ask - Admin Panel</h1>
            </div>
            <div class="user-info">
                <div class="user-profile-dropdown">
                    <button class="user-profile-btn" onclick="toggleProfileMenu(event)">
                        <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div id="profileMenu" class="profile-menu">
                        <a href="admin_dashboard.php">
                            <span style="margin-right: 8px;">🏠</span>Dashboard
                        </a>
                        <a href="../controllers/logout.php">
                            <span style="margin-right: 8px;">🚪</span>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="invoice-container">
        <a href="admin_dashboard.php?tab=projects" class="back-btn">← Back to Projects</a>
        
        <div class="invoice-header">
            <h1>Cloudly AWS Ask</h1>
            <h2>Cost Estimate & Invoice</h2>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3>Project Information</h3>
                <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                <p><strong>Report Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>Created At:</strong> <?php echo $project['created_at']; ?></p>
            </div>
            <div class="info-box">
                <h3>Contact Information</h3>
                <p><strong>Created By:</strong> <?php echo htmlspecialchars($creator['username'] ?? 'Unknown'); ?></p>
                <p><strong>Salesman Name:</strong> <?php echo htmlspecialchars($project['salesman_name']); ?></p>
            </div>
        </div>

        <?php if (!empty($ec2_instances)): ?>
        <div class="service-section">
            <h3>EC2 Instances</h3>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Instance Type</th>
                        <th>Quantity</th>
                        <th>OS</th>
                        <th>Region</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ec2_total = 0;
                    foreach ($ec2_instances as $inst): 
                        $stmt = $conn->prepare("SELECT unit_cost, total_cost FROM ec2_instances WHERE id = ?");
                        $stmt->bind_param("i", $inst['id']);
                        $stmt->execute();
                        $costs = $stmt->get_result()->fetch_assoc();
                        $ec2_total += $costs['total_cost'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($inst['instance_type']); ?></td>
                        <td><?php echo $inst['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($inst['operating_system']); ?></td>
                        <td><?php echo htmlspecialchars($inst['region']); ?></td>
                        <td>$<?php echo number_format($costs['unit_cost'], 2); ?></td>
                        <td>$<?php echo number_format($costs['total_cost'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal">
                        <td colspan="5">EC2 Subtotal</td>
                        <td>$<?php echo number_format($ec2_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($ebs_volumes)): ?>
        <div class="service-section">
            <h3>EBS Volumes</h3>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Server Type</th>
                        <th>Server Name</th>
                        <th>Volume Type</th>
                        <th>Size (GB)</th>
                        <th>IOPS</th>
                        <th>Throughput</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ebs_total = 0;
                    foreach ($ebs_volumes as $vol): 
                        $ebs_total += $vol['total_cost'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vol['server_type']); ?></td>
                        <td><?php echo htmlspecialchars($vol['server_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($vol['volume_type']); ?></td>
                        <td><?php echo $vol['size_gb']; ?></td>
                        <td><?php echo $vol['iops']; ?></td>
                        <td><?php echo $vol['throughput']; ?> MB/s</td>
                        <td>$<?php echo number_format($vol['unit_cost'], 2); ?></td>
                        <td>$<?php echo number_format($vol['total_cost'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal">
                        <td colspan="7">EBS Subtotal</td>
                        <td>$<?php echo number_format($ebs_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($vpc_configs) && is_array($vpc_configs)): 
            $vpc_total = 0;
            foreach ($vpc_configs as $vpc_config) {
                $vpc_total += $vpc_config['total_cost'] ?? 0;
            }
        ?>
        <div class="service-section">
            <h3>VPC Services</h3>
            <?php foreach ($vpc_configs as $vpc_config): 
                $service_type = $vpc_config['service_type'] ?? '';
                $region = $vpc_config['region'] ?? '';
                $total_cost = $vpc_config['total_cost'] ?? 0;
                
                if ($service_type === 'site_to_site_vpn') {
                    $service_name = 'Site-to-Site VPN';
                    $vpn_connections = intval($vpc_config['vpn_connections'] ?? 0);
                    $vpn_duration_unit = $vpc_config['vpn_duration_unit'] ?? 'hours_per_day';
                    $unit_display = str_replace('_', ' ', $vpn_duration_unit);
                    $description = $vpn_connections . ' connection(s) - 24 ' . $unit_display;
                } elseif ($service_type === 'data_transfer') {
                    $service_name = 'VPC Data Transfer';
                    $inbound_amount = floatval($vpc_config['inbound_amount'] ?? 0);
                    $inbound_unit = strtoupper($vpc_config['inbound_unit'] ?? 'GB');
                    $outbound_amount = floatval($vpc_config['data_transfer_amount'] ?? 0);
                    $outbound_unit = strtoupper($vpc_config['data_transfer_unit'] ?? 'GB');
                    $description = 'Inbound: ' . number_format($inbound_amount, 2) . ' ' . $inbound_unit . ' (free) / Outbound: ' . number_format($outbound_amount, 2) . ' ' . $outbound_unit;
                } elseif ($service_type === 'public_ipv4') {
                    $service_name = 'Public IPv4 Address';
                    $in_use_count = intval($vpc_config['in_use_ipv4_count'] ?? 0);
                    $idle_count = intval($vpc_config['idle_ipv4_count'] ?? 0);
                    $description = 'In-use: ' . $in_use_count . ' / Idle: ' . $idle_count;
                } elseif ($service_type === 'nat_gateway') {
                    $service_name = 'NAT Gateway';
                    $nat_gateway_count = intval($vpc_config['nat_gateway_count'] ?? 0);
                    $nat_data_processed = floatval($vpc_config['nat_data_processed'] ?? 0);
                    $nat_data_unit = strtoupper($vpc_config['nat_data_unit'] ?? 'GB');
                    $description = $nat_gateway_count . ' gateway(s) - ' . number_format($nat_data_processed, 2) . ' ' . $nat_data_unit . ' processed';
                } else {
                    $service_name = 'Unknown VPC Service';
                    $description = '';
                }
            ?>
            <table class="service-table" style="margin-bottom: 15px;">
                <tr>
                    <td><strong>Service:</strong></td>
                    <td><?php echo htmlspecialchars($service_name); ?></td>
                </tr>
                <tr>
                    <td><strong>Details:</strong></td>
                    <td><?php echo htmlspecialchars($description); ?></td>
                </tr>
                <tr>
                    <td><strong>Region:</strong></td>
                    <td><?php echo htmlspecialchars($region); ?></td>
                </tr>
                <tr>
                    <td><strong>Cost:</strong></td>
                    <td>$<?php echo number_format($total_cost, 2); ?></td>
                </tr>
            </table>
            <?php endforeach; ?>
            <table class="service-table">
                <tr class="subtotal">
                    <td><strong>VPC Services Subtotal</strong></td>
                    <td>$<?php echo number_format($vpc_total, 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($s3_configs) && is_array($s3_configs)): 
            $s3_total = 0;
            foreach ($s3_configs as $s3_config) {
                $s3_total += $s3_config['total_cost'] ?? 0;
            }
        ?>
        <div class="service-section">
            <h3>S3 Services</h3>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Description</th>
                        <th>Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($s3_configs as $s3_config): 
                        $service_type = $s3_config['service_type'] ?? '';
                        $service_name = '';
                        $description = '';
                        
                        if ($service_type === 'standard_storage') {
                            $service_name = 'S3 Standard Storage';
                            $amount = floatval($s3_config['storage_amount'] ?? 0);
                            $unit = strtoupper($s3_config['storage_unit'] ?? 'GB');
                            $description = number_format($amount, 2) . ' ' . $unit . ' per month';
                        } elseif ($service_type === 'data_transfer') {
                            $service_name = 'S3 Data Transfer';
                            $inbound_amount = floatval($s3_config['inbound_amount'] ?? 0);
                            $inbound_unit = strtoupper($s3_config['inbound_unit'] ?? 'GB');
                            $outbound_amount = floatval($s3_config['data_transfer_amount'] ?? 0);
                            $outbound_unit = strtoupper($s3_config['data_transfer_unit'] ?? 'GB');
                            $description = 'Inbound: ' . number_format($inbound_amount, 2) . ' ' . $inbound_unit . ' (free) / Outbound: ' . number_format($outbound_amount, 2) . ' ' . $outbound_unit;
                        } elseif ($service_type === 'glacier') {
                            $service_name = 'S3 Glacier Deep Archive';
                            $amount = floatval($s3_config['storage_amount'] ?? 0);
                            $unit = strtoupper($s3_config['storage_unit'] ?? 'GB');
                            $description = number_format($amount, 2) . ' ' . $unit . ' per month';
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($service_name); ?></strong></td>
                        <td><?php echo htmlspecialchars($description); ?></td>
                        <td>$<?php echo number_format($s3_config['total_cost'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="subtotal">
                        <td colspan="2"><strong>S3 Subtotal</strong></td>
                        <td><strong>$<?php echo number_format($s3_total, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($rds_config): ?>
        <div class="service-section">
            <h3>RDS</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Engine:</strong></td>
                    <td><?php echo htmlspecialchars($rds_config['engine']); ?></td>
                    <td><strong>Instance Type:</strong></td>
                    <td><?php echo htmlspecialchars($rds_config['instance_type']); ?></td>
                </tr>
                <tr>
                    <td><strong>Quantity:</strong></td>
                    <td><?php echo $rds_config['quantity']; ?></td>
                    <td><strong>Storage (GB):</strong></td>
                    <td><?php echo $rds_config['storage_gb']; ?></td>
                </tr>
                <tr>
                    <td><strong>Storage Type:</strong></td>
                    <td><?php echo htmlspecialchars($rds_config['storage_type']); ?></td>
                    <td><strong>Multi-AZ:</strong></td>
                    <td><?php echo $rds_config['multi_az'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td><strong>Backup Retention:</strong></td>
                    <td><?php echo $rds_config['backup_retention']; ?> days</td>
                    <td><strong>Region:</strong></td>
                    <td><?php echo htmlspecialchars($rds_config['region']); ?></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">RDS Subtotal</td>
                    <td>$<?php echo number_format($rds_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($eks_config): ?>
        <div class="service-section">
            <h3>EKS</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Cluster Count:</strong></td>
                    <td><?php echo $eks_config['cluster_count']; ?></td>
                    <td><strong>Node Group Count:</strong></td>
                    <td><?php echo $eks_config['node_group_count']; ?></td>
                </tr>
                <tr>
                    <td><strong>Instance Type:</strong></td>
                    <td><?php echo htmlspecialchars($eks_config['instance_type']); ?></td>
                    <td><strong>Node Count:</strong></td>
                    <td><?php echo $eks_config['node_count']; ?></td>
                </tr>
                <tr>
                    <td><strong>Region:</strong></td>
                    <td><?php echo htmlspecialchars($eks_config['region']); ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">EKS Subtotal</td>
                    <td>$<?php echo number_format($eks_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($ecr_config): ?>
        <div class="service-section">
            <h3>ECR</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Storage (GB):</strong></td>
                    <td><?php echo $ecr_config['storage_gb']; ?></td>
                    <td><strong>Data Transfer (GB):</strong></td>
                    <td><?php echo $ecr_config['data_transfer_gb']; ?></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">ECR Subtotal</td>
                    <td>$<?php echo number_format($ecr_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($lb_config): ?>
        <div class="service-section">
            <h3>Load Balancer</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Load Balancer Type:</strong></td>
                    <td><?php echo htmlspecialchars($lb_config['load_balancer_type']); ?></td>
                    <td><strong>Quantity:</strong></td>
                    <td><?php echo $lb_config['quantity']; ?></td>
                </tr>
                <tr>
                    <td><strong>Data Processed (GB):</strong></td>
                    <td><?php echo $lb_config['data_processed_gb']; ?></td>
                    <td><strong>Region:</strong></td>
                    <td><?php echo htmlspecialchars($lb_config['region']); ?></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">Load Balancer Subtotal</td>
                    <td>$<?php echo number_format($lb_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($waf_config): ?>
        <div class="service-section">
            <h3>WAF</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Web ACL Count:</strong></td>
                    <td><?php echo $waf_config['web_acl_count']; ?></td>
                    <td><strong>Rules Count:</strong></td>
                    <td><?php echo $waf_config['rules_count']; ?></td>
                </tr>
                <tr>
                    <td><strong>Requests (Million):</strong></td>
                    <td><?php echo $waf_config['requests_million']; ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">WAF Subtotal</td>
                    <td>$<?php echo number_format($waf_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($route53_config): ?>
        <div class="service-section">
            <h3>Route 53</h3>
            <table class="service-table">
                <tr>
                    <td><strong>Hosted Zones:</strong></td>
                    <td><?php echo $route53_config['hosted_zones']; ?></td>
                    <td><strong>Queries (Million):</strong></td>
                    <td><?php echo $route53_config['queries_million']; ?></td>
                </tr>
                <tr>
                    <td><strong>Health Checks:</strong></td>
                    <td><?php echo $route53_config['health_checks']; ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="subtotal">
                    <td colspan="3">Route 53 Subtotal</td>
                    <td>$<?php echo number_format($route53_config['total_cost'], 2); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div class="total-section">
            <h3>Total Estimated Cost</h3>
            <div class="total-row">
                <span>Total Unit Cost:</span>
                <span>$<?php echo number_format($totals['total_unit_cost'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>Total Estimated Cost (Monthly):</span>
                <span>$<?php echo number_format($totals['total_estimated_cost'], 2); ?></span>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; 2025 Cloudly Infotech Limited. All rights reserved.</p>
            <p>Bangladesh's First Premier AWS & GCP Partner</p>
        </div>
    </footer>

    <script>
        function toggleProfileMenu(event) {
            if (event) {
                event.stopPropagation();
            }
            const dropdown = document.querySelector('.user-profile-dropdown');
            const menu = document.getElementById('profileMenu');
            dropdown.classList.toggle('active');
            menu.classList.toggle('active');
        }
        
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.user-profile-dropdown');
            const menu = document.getElementById('profileMenu');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
                menu.classList.remove('active');
            }
        });
    </script>
</body>
</html>
