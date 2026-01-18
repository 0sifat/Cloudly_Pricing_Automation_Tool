<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PricingModel.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$userModel = new User();
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$pricingModel = new PricingModel();
$service = $_GET['service'] ?? 'ec2';
$region = $_GET['region'] ?? '';

// Handle price updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_price') {
    $service_type = $_POST['service_type'];
    // Ensure region is from POST or GET (POST takes precedence for form submission)
    $region = $_POST['region'] ?? $_GET['region'] ?? $region ?? '';
    $result = false;

    switch ($service_type) {
        case 'ec2':
            $vcpu = intval($_POST['vcpu'] ?? 0);
            $memory_gb = floatval($_POST['memory_gb'] ?? 0);
            $result = $pricingModel->setEC2Price($_POST['instance_type'], $region, $_POST['price'], $vcpu, $memory_gb);
            break;
        case 'ebs':
            $result = $pricingModel->setEBSPrice(
                $_POST['volume_type'], 
                $region, 
                $_POST['price_per_gb'], 
                $_POST['iops_price'] ?? 0, 
                $_POST['throughput_price'] ?? 0,
                $_POST['snapshot_hourly'] ?? 0,
                $_POST['snapshot_daily'] ?? 0,
                $_POST['snapshot_2x_daily'] ?? 0,
                $_POST['snapshot_3x_daily'] ?? 0,
                $_POST['snapshot_4x_daily'] ?? 0,
                $_POST['snapshot_6x_daily'] ?? 0,
                $_POST['snapshot_weekly'] ?? 0,
                $_POST['snapshot_monthly'] ?? 0
            );
            break;
        case 's3':
            $result = $pricingModel->setS3Price($_POST['storage_class'], $region, $_POST['price_per_gb'], $_POST['request_price'] ?? 0, $_POST['data_transfer_price'] ?? 0);
            break;
        case 'rds_instance':
            $vcpu = intval($_POST['vcpu'] ?? 0);
            $memory_gb = floatval($_POST['memory_gb'] ?? 0);
            $result = $pricingModel->setRDSInstancePrice($_POST['instance_type'], $_POST['engine'], $region, $_POST['price'], $vcpu, $memory_gb, $_POST['deployment_type'] ?? 'single_az');
            break;
        case 'rds_storage':
            $result = $pricingModel->setRDSStoragePrice($_POST['storage_type'], $region, $_POST['price_per_gb']);
            break;
        case 'vpc':
            $result = $pricingModel->setVPCPrice($_POST['service_name'], $region, $_POST['price_per_hour'] ?? 0, $_POST['price_per_gb'] ?? 0, $_POST['unit'] ?? 'hour');
            break;
        case 'waf':
            $result = $pricingModel->setWAFPrice($_POST['pricing_type'], $region, $_POST['price'], $_POST['unit'] ?? 'month');
            break;
        case 'lb':
            $result = $pricingModel->setLoadBalancerPrice($_POST['load_balancer_type'], $region, $_POST['price_per_hour'], $_POST['price_per_gb']);
            break;
        case 'eks':
            $result = $pricingModel->setEKSPrice($_POST['pricing_type'], $region, $_POST['price_per_hour']);
            break;
        case 'ecr':
            $result = $pricingModel->setECRPrice($_POST['pricing_type'], $region, $_POST['price_per_gb_per_month'], $_POST['price_per_gb']);
            break;
        case 'route53':
            $result = $pricingModel->setRoute53Price($_POST['pricing_type'], $region, $_POST['price'], $_POST['unit'] ?? 'month');
            break;
    }

    if ($result) {
        // Redirect to preserve region and show success message; rds_instance and rds_storage both use RDS tab
        $svc = in_array($service_type, ['rds_instance', 'rds_storage']) ? 'rds' : $service_type;
        $redirect_url = "?service=" . urlencode($svc) . "&region=" . urlencode($region);
        if (!empty($region)) {
            header("Location: admin_pricing.php" . $redirect_url . "&success=1");
            exit;
        } else {
            $success_message = "Price updated successfully!";
        }
    } else {
        $error_message = "Failed to update price.";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Price updated successfully!";
}

// Get pricing data
$pricing_data = [];
if (!empty($region)) {
    switch ($service) {
        case 'ec2':
            $pricing_data = $pricingModel->getAllEC2Pricing($region);
            break;
    case 'ebs':
        $pricing_data = $pricingModel->getAllEBSPricing($region);
        break;
    case 's3':
        $pricing_data = $pricingModel->getAllS3Pricing($region);
        break;
    case 'rds':
        $pricing_data = $pricingModel->getAllRDSPricing($region);
        break;
    case 'vpc':
        $pricing_data = $pricingModel->getAllVPCPricing($region);
        break;
    case 'waf':
        $pricing_data = $pricingModel->getAllWAFPricing($region);
        break;
    case 'lb':
        $pricing_data = $pricingModel->getAllLoadBalancerPricing($region);
        break;
    case 'eks':
        $pricing_data = $pricingModel->getAllEKSPricing($region);
        break;
    case 'ecr':
        $pricing_data = $pricingModel->getAllECRPricing($region);
        break;
        case 'route53':
            $pricing_data = $pricingModel->getAllRoute53Pricing($region);
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Management - <?php echo strtoupper($service); ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        .pricing-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .pricing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .pricing-header h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
        }

        .pricing-header a {
            color: #ff6b35;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .pricing-header a:hover {
            color: #ff4757;
        }

        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
        }

        .pricing-table th,
        .pricing-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 107, 53, 0.2);
        }

        .pricing-table th {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .pricing-table td {
            color: #fff;
        }

        .pricing-table tr:hover {
            background: rgba(255, 107, 53, 0.1);
        }

        .pricing-table tr:last-child td {
            border-bottom: none;
        }

        .edit-form {
            display: none;
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .edit-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #fff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 107, 53, 0.3);
            border-radius: 6px;
            background: #2a2a2a;
            color: #fff;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff6b35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .service-selector {
            margin-bottom: 20px;
            background: #2a2a2a;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .service-selector a {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #1a1a1a;
            text-decoration: none;
            border-radius: 6px;
            color: #fff;
            border: 2px solid rgba(255, 107, 53, 0.3);
            transition: all 0.3s;
            font-weight: 500;
        }

        .service-selector a:hover {
            background: rgba(255, 107, 53, 0.1);
            border-color: #ff6b35;
            transform: translateY(-2px);
        }

        .service-selector a.active {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            border-color: #ff6b35;
        }

        .pricing-content {
            background: #2a2a2a;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .pricing-content h3 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="../assets/cloudlybangladesh_logo.jpg" alt="Cloudly Logo" class="header-logo">
                <h1>Cloudly AWS Ask - Pricing Management</h1>
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

    <div class="pricing-container">
        <div class="pricing-content">
            <div class="pricing-header">
                <h1>Pricing Management - <?php echo strtoupper($service); ?></h1>
                <div>
                    <a href="admin_dashboard.php">← Back to Dashboard</a>
                </div>
            </div>



            <?php if (isset($success_message)): ?>
                <div
                    style="background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #28a745; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div
                    style="background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="service-selector">
                <a href="?service=ec2&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'ec2' ? 'active' : ''; ?>">EC2</a>
                <a href="?service=ebs&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'ebs' ? 'active' : ''; ?>">EBS</a>
                <a href="?service=s3&region=<?php echo $region; ?>"
                    class="<?php echo $service === 's3' ? 'active' : ''; ?>">S3</a>
                <a href="?service=rds&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'rds' ? 'active' : ''; ?>">RDS</a>
                <a href="?service=vpc&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'vpc' ? 'active' : ''; ?>">VPC</a>
                <a href="?service=waf&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'waf' ? 'active' : ''; ?>">WAF</a>
                <a href="?service=lb&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'lb' ? 'active' : ''; ?>">Load Balancer</a>
                <a href="?service=eks&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'eks' ? 'active' : ''; ?>">EKS</a>
                <a href="?service=ecr&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'ecr' ? 'active' : ''; ?>">ECR</a>
                <a href="?service=route53&region=<?php echo $region; ?>"
                    class="<?php echo $service === 'route53' ? 'active' : ''; ?>">Route53</a>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="color: #fff; font-weight: 600; margin-right: 10px;">Region: </label>
                <select onchange="window.location.href='?service=<?php echo $service; ?>&region=' + this.value"
                    style="padding: 10px 15px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #1a1a1a; color: #fff; font-size: 14px;">
                    <option value="us-east-1" <?php echo $region === 'us-east-1' ? 'selected' : ''; ?>>US East (N.
                        Virginia)</option>
                    <option value="us-east-2" <?php echo $region === 'us-east-2' ? 'selected' : ''; ?>>US East (Ohio)
                    </option>
                    <option value="us-west-1" <?php echo $region === 'us-west-1' ? 'selected' : ''; ?>>US West (N.
                        California)</option>
                    <option value="us-west-2" <?php echo $region === 'us-west-2' ? 'selected' : ''; ?>>US West (Oregon)
                    </option>
                    <option value="af-south-1" <?php echo $region === 'af-south-1' ? 'selected' : ''; ?>>Africa (Cape
                        Town)</option>
                    <option value="ap-east-1" <?php echo $region === 'ap-east-1' ? 'selected' : ''; ?>>Asia Pacific (Hong
                        Kong)</option>
                    <option value="ap-south-1" <?php echo $region === 'ap-south-1' ? 'selected' : ''; ?>>Asia Pacific
                        (Mumbai)</option>
                    <option value="ap-south-2" <?php echo $region === 'ap-south-2' ? 'selected' : ''; ?>>Asia Pacific
                        (Hyderabad)</option>
                    <option value="ap-northeast-1" <?php echo $region === 'ap-northeast-1' ? 'selected' : ''; ?>>Asia
                        Pacific (Tokyo)</option>
                    <option value="ap-northeast-2" <?php echo $region === 'ap-northeast-2' ? 'selected' : ''; ?>>Asia
                        Pacific (Seoul)</option>
                    <option value="ap-northeast-3" <?php echo $region === 'ap-northeast-3' ? 'selected' : ''; ?>>Asia
                        Pacific (Osaka)</option>
                    <option value="ap-southeast-1" <?php echo $region === 'ap-southeast-1' ? 'selected' : ''; ?>>Asia
                        Pacific (Singapore)</option>
                    <option value="ap-southeast-2" <?php echo $region === 'ap-southeast-2' ? 'selected' : ''; ?>>Asia
                        Pacific (Sydney)</option>
                    <option value="ap-southeast-3" <?php echo $region === 'ap-southeast-3' ? 'selected' : ''; ?>>Asia
                        Pacific (Jakarta)</option>
                    <option value="ap-southeast-4" <?php echo $region === 'ap-southeast-4' ? 'selected' : ''; ?>>Asia
                        Pacific (Melbourne)</option>
                    <option value="ca-central-1" <?php echo $region === 'ca-central-1' ? 'selected' : ''; ?>>Canada
                        (Central)</option>
                    <option value="eu-central-1" <?php echo $region === 'eu-central-1' ? 'selected' : ''; ?>>Europe
                        (Frankfurt)</option>
                    <option value="eu-central-2" <?php echo $region === 'eu-central-2' ? 'selected' : ''; ?>>Europe
                        (Zurich)</option>
                    <option value="eu-west-1" <?php echo $region === 'eu-west-1' ? 'selected' : ''; ?>>Europe (Ireland)
                    </option>
                    <option value="eu-west-2" <?php echo $region === 'eu-west-2' ? 'selected' : ''; ?>>Europe (London)
                    </option>
                    <option value="eu-west-3" <?php echo $region === 'eu-west-3' ? 'selected' : ''; ?>>Europe (Paris)
                    </option>
                    <option value="eu-north-1" <?php echo $region === 'eu-north-1' ? 'selected' : ''; ?>>Europe
                        (Stockholm)</option>
                    <option value="eu-south-1" <?php echo $region === 'eu-south-1' ? 'selected' : ''; ?>>Europe (Milan)
                    </option>
                    <option value="eu-south-2" <?php echo $region === 'eu-south-2' ? 'selected' : ''; ?>>Europe (Spain)
                    </option>
                    <option value="me-south-1" <?php echo $region === 'me-south-1' ? 'selected' : ''; ?>>Middle East
                        (Bahrain)</option>
                    <option value="me-central-1" <?php echo $region === 'me-central-1' ? 'selected' : ''; ?>>Middle East
                        (UAE)</option>
                    <option value="sa-east-1" <?php echo $region === 'sa-east-1' ? 'selected' : ''; ?>>South America (São
                        Paulo)</option>
                    <option value="us-gov-east-1" <?php echo $region === 'us-gov-east-1' ? 'selected' : ''; ?>>AWS
                        GovCloud (US-East)</option>
                    <option value="us-gov-west-1" <?php echo $region === 'us-gov-west-1' ? 'selected' : ''; ?>>AWS
                        GovCloud (US-West)</option>
                </select>
            </div>

            <?php if ($service === 'ec2'): ?>
                <?php if (empty($region)): ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        Please select a region to view and manage EC2 instance pricing.
                    </div>
                <?php else: ?>
                <h3>EC2 Instance Pricing (per hour) - <?php echo htmlspecialchars($region); ?></h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Instance Type</th>
                            <th>vCPU</th>
                            <th>Memory (GB)</th>
                            <th>Region</th>
                            <th>Price per Hour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['instance_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['vcpu'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($price['memory_gb'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['on_demand_price_per_hour'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editEC2Price('<?php echo htmlspecialchars($price['instance_type']); ?>', <?php echo $price['on_demand_price_per_hour']; ?>, <?php echo $price['vcpu'] ?? 0; ?>, <?php echo $price['memory_gb'] ?? 0; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pricing_data)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #999;">No pricing data found for this region. Add prices using
                                    the form below.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($region)): ?>
                <div style="margin-top: 30px;">
                    <h3>Add/Update EC2 Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="ec2">
                        <div class="form-group">
                            <label>Instance Type (Manual Entry):</label>
                            <input type="text" name="instance_type" required placeholder="e.g., c5a.xlarge" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>vCPU:</label>
                            <input type="number" name="vcpu" min="0" step="1" required placeholder="e.g., 4" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>Memory (GB):</label>
                            <input type="number" name="memory_gb" min="0" step="0.01" required placeholder="e.g., 8.00" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($):</label>
                            <input type="number" name="price" step="0.000001" required placeholder="0.0104">
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php elseif ($service === 'ebs'): ?>
                <?php if (empty($region)): ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        Please select a region to view and manage EBS volume pricing.
                    </div>
                <?php else: ?>
                <h3>EBS Volume Pricing - <?php echo htmlspecialchars($region); ?></h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Volume Type</th>
                            <th>Region</th>
                            <th>Price/GB/Month</th>
                            <th>IOPS Price</th>
                            <th>Throughput Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pricing_data)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #999;">No pricing data found for this region. Add prices using the form below.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pricing_data as $price): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($price['volume_type']); ?></td>
                                    <td><?php echo htmlspecialchars($price['region']); ?></td>
                                    <td>$<?php echo number_format($price['price_per_gb_per_month'], 6); ?></td>
                                    <td>$<?php echo number_format($price['iops_price_per_iops'], 6); ?></td>
                                    <td>$<?php echo number_format($price['throughput_price_per_mbps'], 6); ?></td>
                                    <td>
                                        <button class="btn btn-primary"
                                            onclick="editEBSPrice('<?php echo htmlspecialchars($price['volume_type']); ?>', <?php echo $price['price_per_gb_per_month']; ?>, <?php echo $price['iops_price_per_iops']; ?>, <?php echo $price['throughput_price_per_mbps']; ?>, <?php echo $price['snapshot_price_per_gb_hourly'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_daily'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_2x_daily'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_3x_daily'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_4x_daily'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_6x_daily'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_weekly'] ?? 0; ?>, <?php echo $price['snapshot_price_per_gb_monthly'] ?? 0; ?>)">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($region)): ?>
                <div style="margin-top: 30px;">
                    <h3>Add/Update EBS Price</h3>
                    <form method="POST" action="?service=ebs&region=<?php echo urlencode($region); ?>" style="max-width: 700px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="ebs">
                        <div class="form-group">
                            <label>Volume Type:</label>
                            <select name="volume_type" id="ebs_volume_type" onchange="toggleEBSFields()" required>
                                <option value="gp2">gp2</option>
                                <option value="gp3">gp3</option>
                                <option value="io1">io1</option>
                                <option value="io2">io2</option>
                                <option value="st1">st1</option>
                                <option value="sc1">sc1</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per GB/Month ($):</label>
                            <input type="number" name="price_per_gb" id="ebs_price_per_gb" step="0.000001" required>
                        </div>
                        <div class="form-group" id="ebs_iops_group" style="display: none;">
                            <label>IOPS Price per IOPS ($) - for gp3/io1/io2:</label>
                            <input type="number" name="iops_price" id="ebs_iops_price" step="0.000001" value="0">
                        </div>
                        <div class="form-group" id="ebs_throughput_group" style="display: none;">
                            <label>Throughput Price per MB/s ($) - for gp3:</label>
                            <input type="number" name="throughput_price" id="ebs_throughput_price" step="0.000001" value="0">
                        </div>
                        <div class="form-group" style="margin-top: 20px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="enable_snapshot_pricing" onchange="toggleSnapshotPricing()" style="width: auto;">
                                <span style="color: #ff6b35; font-weight: 600;">Enable Snapshot Pricing (Optional)</span>
                            </label>
                        </div>
                        <div id="snapshot_pricing_section" style="display: none;">
                            <h4 style="color: #ff6b35; margin-top: 15px; margin-bottom: 15px;">Snapshot Pricing (per GB)</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div class="form-group">
                                <label>Hourly ($):</label>
                                <input type="number" name="snapshot_hourly" id="ebs_snapshot_hourly" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>Daily ($):</label>
                                <input type="number" name="snapshot_daily" id="ebs_snapshot_daily" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>2x Daily ($):</label>
                                <input type="number" name="snapshot_2x_daily" id="ebs_snapshot_2x_daily" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>3x Daily ($):</label>
                                <input type="number" name="snapshot_3x_daily" id="ebs_snapshot_3x_daily" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>4x Daily ($):</label>
                                <input type="number" name="snapshot_4x_daily" id="ebs_snapshot_4x_daily" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>6x Daily ($):</label>
                                <input type="number" name="snapshot_6x_daily" id="ebs_snapshot_6x_daily" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>Weekly ($):</label>
                                <input type="number" name="snapshot_weekly" id="ebs_snapshot_weekly" step="0.000001" value="0">
                            </div>
                            <div class="form-group">
                                <label>Monthly ($):</label>
                                <input type="number" name="snapshot_monthly" id="ebs_snapshot_monthly" step="0.000001" value="0">
                            </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
                <?php else: ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107; padding: 15px; border-radius: 8px; margin-top: 20px;">
                        Please select a region to add/update EBS pricing.
                    </div>
                <?php endif; ?>
            <?php elseif ($service === 's3'): ?>
                <h3 style="color: #fff; margin-top: 20px;">S3 Storage Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Storage Class</th>
                            <th>Region</th>
                            <th>Price per GB/Month</th>
                            <th>Request Price per 1000</th>
                            <th>Data Transfer Price per GB</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['storage_class']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb_per_month'], 6); ?></td>
                                <td>$<?php echo number_format($price['request_price_per_1000'], 6); ?></td>
                                <td>$<?php echo number_format($price['data_transfer_price_per_gb'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editS3Price('<?php echo htmlspecialchars($price['storage_class']); ?>', <?php echo $price['price_per_gb_per_month']; ?>, <?php echo $price['request_price_per_1000']; ?>, <?php echo $price['data_transfer_price_per_gb']; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update S3 Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="s3">
                        <div class="form-group">
                            <label>Storage Class:</label>
                            <select name="storage_class" required>
                                <option value="standard">Standard</option>
                                <option value="intelligent_tiering">Intelligent-Tiering</option>
                                <option value="standard_ia">Standard-IA</option>
                                <option value="onezone_ia">One Zone-IA</option>
                                <option value="glacier">Glacier</option>
                                <option value="deep_archive">Deep Archive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per GB/Month ($):</label>
                            <input type="number" name="price_per_gb" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Request Price per 1000 ($):</label>
                            <input type="number" name="request_price" step="0.000001" value="0.005">
                        </div>
                        <div class="form-group">
                            <label>Data Transfer Price per GB ($):</label>
                            <input type="number" name="data_transfer_price" step="0.000001" value="0.09">
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'rds'): ?>
                <?php if (empty($region)): ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        Please select a region to view and manage RDS instance pricing.
                    </div>
                <?php else: ?>
                <h3 style="color: #fff; margin-top: 20px;">RDS Instance Pricing (per hour) - <?php echo htmlspecialchars($region); ?></h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Instance Type</th>
                            <th>Engine</th>
                            <th>Deployment</th>
                            <th>vCPU</th>
                            <th>Memory (GB)</th>
                            <th>Region</th>
                            <th>Price per Hour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): 
                            $dep = isset($price['deployment_type']) ? $price['deployment_type'] : 'single_az';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['instance_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['engine']); ?></td>
                                <td><?php echo $dep === 'multi_az' ? 'Multi-AZ' : 'Single AZ'; ?></td>
                                <td><?php echo htmlspecialchars($price['vcpu'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($price['memory_gb'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['on_demand_price_per_hour'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editRDSInstancePrice('<?php echo htmlspecialchars($price['instance_type']); ?>', '<?php echo htmlspecialchars($price['engine']); ?>', <?php echo $price['on_demand_price_per_hour']; ?>, <?php echo $price['vcpu'] ?? 0; ?>, <?php echo $price['memory_gb'] ?? 0; ?>, '<?php echo htmlspecialchars($dep); ?>', '<?php echo htmlspecialchars($region); ?>')">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pricing_data)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #999;">No pricing data found for this region. Add prices using the form below.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($region)): ?>
                <div style="margin-top: 30px;">
                    <h3>Add/Update RDS Instance Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="rds_instance">
                        <input type="hidden" name="region" value="<?php echo htmlspecialchars($region); ?>">
                        <div class="form-group">
                            <label>Instance Type (Manual Entry):</label>
                            <input type="text" name="instance_type" required placeholder="e.g., db.t3.medium" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>Engine:</label>
                            <select name="engine" required>
                                <option value="mysql">MySQL</option>
                                <option value="postgresql">PostgreSQL</option>
                                <option value="oracle">Oracle</option>
                                <option value="mariadb">MariaDB</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Deployment:</label>
                            <select name="deployment_type" required>
                                <option value="single_az">Single AZ</option>
                                <option value="multi_az">Multi-AZ</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>vCPU:</label>
                            <input type="number" name="vcpu" min="0" step="1" required placeholder="e.g., 2" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>Memory (GB):</label>
                            <input type="number" name="memory_gb" min="0" step="0.01" required placeholder="e.g., 4.00" style="width: 100%; padding: 12px; border: 1px solid rgba(255, 107, 53, 0.3); border-radius: 6px; background: #2a2a2a; color: #fff; font-size: 14px;">
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($):</label>
                            <input type="number" name="price" step="0.000001" required>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
                <?php endif; ?>

                <p style="margin-top: 20px;"><a href="#rds-storage-pricing" style="color: #ff6b35; text-decoration: underline;">↓ RDS Storage Pricing (gp2, gp3, io1, io2 — set price per GB/month)</a></p>

                <div id="rds-storage-pricing" style="margin-top: 40px;">
                <h3 style="color: #fff; margin-top: 20px;">RDS Storage Pricing</h3>
                <?php if (empty($region)): ?>
                    <div style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107; padding: 15px; border-radius: 8px;">
                        Please select a region above to view and add RDS storage pricing (gp2, gp3, etc.).
                    </div>
                <?php else:
                    $rds_storage_data = [];
                    $stmt = $conn->prepare("SELECT * FROM rds_storage_pricing WHERE region = ? ORDER BY storage_type");
                    $stmt->bind_param("s", $region);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $rds_storage_data[] = $row;
                    }
                ?>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Storage Type</th>
                            <th>Region</th>
                            <th>Price per GB/Month</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rds_storage_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['storage_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb_per_month'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editRDSStoragePrice('<?php echo htmlspecialchars($price['storage_type']); ?>', <?php echo $price['price_per_gb_per_month']; ?>, '<?php echo htmlspecialchars($region); ?>')">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update RDS Storage Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="rds_storage">
                        <input type="hidden" name="region" value="<?php echo htmlspecialchars($region); ?>">
                        <div class="form-group">
                            <label>Storage Type (gp2, gp3, io1, io2):</label>
                            <select name="storage_type" required>
                                <option value="gp2">gp2</option>
                                <option value="gp3">gp3</option>
                                <option value="io1">io1</option>
                                <option value="io2">io2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per GB/Month ($):</label>
                            <input type="number" name="price_per_gb" step="0.000001" required placeholder="e.g. 0.115">
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
                <?php endif; ?>
                </div>
            <?php elseif ($service === 'vpc'): ?>
                <h3 style="color: #fff; margin-top: 20px;">VPC Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Region</th>
                            <th>Price per Hour</th>
                            <th>Price per GB</th>
                            <th>Unit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_hour'], 6); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb'], 6); ?></td>
                                <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editVPCPrice('<?php echo htmlspecialchars($price['service_name']); ?>', <?php echo $price['price_per_hour']; ?>, <?php echo $price['price_per_gb']; ?>, '<?php echo htmlspecialchars($price['unit']); ?>')">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update VPC Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="vpc">
                        <div class="form-group">
                            <label>Service Name:</label>
                            <select name="service_name" required>
                                <option value="nat_gateway">NAT Gateway</option>
                                <option value="vpc_endpoint">VPC Endpoint</option>
                                <option value="data_transfer">Data Transfer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($):</label>
                            <input type="number" name="price_per_hour" step="0.000001" value="0">
                        </div>
                        <div class="form-group">
                            <label>Price per GB ($):</label>
                            <input type="number" name="price_per_gb" step="0.000001" value="0">
                        </div>
                        <div class="form-group">
                            <label>Unit:</label>
                            <select name="unit">
                                <option value="hour">Hour</option>
                                <option value="gb">GB</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'waf'): ?>
                <h3 style="color: #fff; margin-top: 20px;">WAF Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Pricing Type</th>
                            <th>Region</th>
                            <th>Price per Unit</th>
                            <th>Unit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['pricing_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_unit'], 6); ?></td>
                                <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editWAFPrice('<?php echo htmlspecialchars($price['pricing_type']); ?>', <?php echo $price['price_per_unit']; ?>, '<?php echo htmlspecialchars($price['unit']); ?>')">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update WAF Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="waf">
                        <div class="form-group">
                            <label>Pricing Type:</label>
                            <select name="pricing_type" required>
                                <option value="web_acl">Web ACL</option>
                                <option value="rule">Rule</option>
                                <option value="request">Request</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per Unit ($):</label>
                            <input type="number" name="price" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Unit:</label>
                            <select name="unit">
                                <option value="month">Month</option>
                                <option value="million">Million</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'lb'): ?>
                <h3 style="color: #fff; margin-top: 20px;">Load Balancer Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Load Balancer Type</th>
                            <th>Region</th>
                            <th>Price per Hour</th>
                            <th>Price per GB</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['load_balancer_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_hour'], 6); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editLBPrice('<?php echo htmlspecialchars($price['load_balancer_type']); ?>', <?php echo $price['price_per_hour']; ?>, <?php echo $price['price_per_gb']; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update Load Balancer Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="lb">
                        <div class="form-group">
                            <label>Load Balancer Type:</label>
                            <select name="load_balancer_type" required>
                                <option value="application">Application</option>
                                <option value="network">Network</option>
                                <option value="classic">Classic</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($):</label>
                            <input type="number" name="price_per_hour" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Price per GB ($):</label>
                            <input type="number" name="price_per_gb" step="0.000001" required>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'eks'): ?>
                <h3 style="color: #fff; margin-top: 20px;">EKS Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Pricing Type</th>
                            <th>Region</th>
                            <th>Price per Hour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['pricing_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_hour'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editEKSPrice('<?php echo htmlspecialchars($price['pricing_type']); ?>', <?php echo $price['price_per_hour']; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update EKS Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="eks">
                        <div class="form-group">
                            <label>Pricing Type:</label>
                            <input type="text" name="pricing_type" required placeholder="e.g., cluster" value="cluster">
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($):</label>
                            <input type="number" name="price_per_hour" step="0.000001" required>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'ecr'): ?>
                <h3 style="color: #fff; margin-top: 20px;">ECR Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Pricing Type</th>
                            <th>Region</th>
                            <th>Price per GB/Month</th>
                            <th>Price per GB</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['pricing_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb_per_month'], 6); ?></td>
                                <td>$<?php echo number_format($price['price_per_gb'], 6); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editECRPrice('<?php echo htmlspecialchars($price['pricing_type']); ?>', <?php echo $price['price_per_gb_per_month']; ?>, <?php echo $price['price_per_gb']; ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px;">
                    <h3>Add/Update ECR Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="ecr">
                        <div class="form-group">
                            <label>Pricing Type:</label>
                            <select name="pricing_type" required>
                                <option value="storage">Storage</option>
                                <option value="data_transfer">Data Transfer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per GB/Month ($):</label>
                            <input type="number" name="price_per_gb_per_month" step="0.000001" value="0">
                        </div>
                        <div class="form-group">
                            <label>Price per GB ($):</label>
                            <input type="number" name="price_per_gb" step="0.000001" required>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php elseif ($service === 'route53'): ?>
                <h3>Route53 Pricing</h3>
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th>Pricing Type</th>
                            <th>Region</th>
                            <th>Price per Unit</th>
                            <th>Unit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing_data as $price): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['pricing_type']); ?></td>
                                <td><?php echo htmlspecialchars($price['region']); ?></td>
                                <td>$<?php echo number_format($price['price_per_unit'], 6); ?></td>
                                <td><?php echo htmlspecialchars($price['unit']); ?></td>
                                <td>
                                    <button class="btn btn-primary"
                                        onclick="editRoute53Price('<?php echo htmlspecialchars($price['pricing_type']); ?>', <?php echo $price['price_per_unit']; ?>, '<?php echo htmlspecialchars($price['unit']); ?>')">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div
                    style="margin-top: 30px; background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid rgba(255, 107, 53, 0.2);">
                    <h3 style="color: #fff; margin-bottom: 20px;">Add/Update Route53 Price</h3>
                    <form method="POST" style="max-width: 500px;">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="service_type" value="route53">
                        <div class="form-group">
                            <label>Pricing Type:</label>
                            <select name="pricing_type" required>
                                <option value="hosted_zone">Hosted Zone</option>
                                <option value="query">Query</option>
                                <option value="health_check">Health Check</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per Unit ($):</label>
                            <input type="number" name="price" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label>Unit:</label>
                            <select name="unit">
                                <option value="month">Month</option>
                                <option value="million">Million</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Save Price</button>
                    </form>
                </div>
            <?php else: ?>
                <p style="color: #fff;">Pricing management for <?php echo strtoupper($service); ?> is coming soon. Please
                    use the database directly or contact the administrator.</p>
            <?php endif; ?>
        </div>
    </div>

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

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.querySelector('.user-profile-dropdown');
            const menu = document.getElementById('profileMenu');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
                menu.classList.remove('active');
            }
        });
        
    </script>

    <script>
        function editEC2Price(instanceType, currentPrice, vcpu, memoryGb) {
            var newPrice = prompt('Enter new price per hour for ' + instanceType + ':', currentPrice);
            if (newPrice !== null && newPrice !== '') {
                var newVcpu = prompt('Enter vCPU count:', vcpu);
                if (newVcpu !== null && newVcpu !== '') {
                    var newMemory = prompt('Enter Memory (GB):', memoryGb);
                    if (newMemory !== null && newMemory !== '') {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                            '<input type="hidden" name="service_type" value="ec2">' +
                            '<input type="hidden" name="instance_type" value="' + instanceType + '">' +
                            '<input type="hidden" name="price" value="' + newPrice + '">' +
                            '<input type="hidden" name="vcpu" value="' + newVcpu + '">' +
                            '<input type="hidden" name="memory_gb" value="' + newMemory + '">';
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }
        }

        function toggleEBSFields() {
            var volumeType = document.getElementById('ebs_volume_type').value;
            var iopsGroup = document.getElementById('ebs_iops_group');
            var throughputGroup = document.getElementById('ebs_throughput_group');
            
            // Show IOPS for gp3, io1, io2
            if (volumeType === 'gp3' || volumeType === 'io1' || volumeType === 'io2') {
                iopsGroup.style.display = 'block';
            } else {
                iopsGroup.style.display = 'none';
                document.getElementById('ebs_iops_price').value = 0;
            }
            
            // Show Throughput only for gp3
            if (volumeType === 'gp3') {
                throughputGroup.style.display = 'block';
            } else {
                throughputGroup.style.display = 'none';
                document.getElementById('ebs_throughput_price').value = 0;
            }
        }
        
        function toggleSnapshotPricing() {
            var checkbox = document.getElementById('enable_snapshot_pricing');
            var section = document.getElementById('snapshot_pricing_section');
            if (checkbox.checked) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
                // Clear all snapshot pricing fields when disabled
                document.getElementById('ebs_snapshot_hourly').value = 0;
                document.getElementById('ebs_snapshot_daily').value = 0;
                document.getElementById('ebs_snapshot_2x_daily').value = 0;
                document.getElementById('ebs_snapshot_3x_daily').value = 0;
                document.getElementById('ebs_snapshot_4x_daily').value = 0;
                document.getElementById('ebs_snapshot_6x_daily').value = 0;
                document.getElementById('ebs_snapshot_weekly').value = 0;
                document.getElementById('ebs_snapshot_monthly').value = 0;
            }
        }
        
        // Call on page load to set initial state
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('ebs_volume_type')) {
                toggleEBSFields();
            }
        });

        function editEBSPrice(volumeType, pricePerGB, iopsPrice, throughputPrice, snapHourly, snapDaily, snap2xDaily, snap3xDaily, snap4xDaily, snap6xDaily, snapWeekly, snapMonthly) {
            // Fill the form with existing values
            document.getElementById('ebs_volume_type').value = volumeType;
            document.getElementById('ebs_price_per_gb').value = pricePerGB;
            document.getElementById('ebs_iops_price').value = iopsPrice;
            document.getElementById('ebs_throughput_price').value = throughputPrice;
            document.getElementById('ebs_snapshot_hourly').value = snapHourly || 0;
            document.getElementById('ebs_snapshot_daily').value = snapDaily || 0;
            document.getElementById('ebs_snapshot_2x_daily').value = snap2xDaily || 0;
            document.getElementById('ebs_snapshot_3x_daily').value = snap3xDaily || 0;
            document.getElementById('ebs_snapshot_4x_daily').value = snap4xDaily || 0;
            document.getElementById('ebs_snapshot_6x_daily').value = snap6xDaily || 0;
            document.getElementById('ebs_snapshot_weekly').value = snapWeekly || 0;
            document.getElementById('ebs_snapshot_monthly').value = snapMonthly || 0;
            // Toggle fields based on volume type
            toggleEBSFields();
            // Enable snapshot pricing if any snapshot price is set
            var hasSnapshotPricing = (snapHourly > 0 || snapDaily > 0 || snap2xDaily > 0 || snap3xDaily > 0 || 
                                     snap4xDaily > 0 || snap6xDaily > 0 || snapWeekly > 0 || snapMonthly > 0);
            if (hasSnapshotPricing) {
                document.getElementById('enable_snapshot_pricing').checked = true;
                toggleSnapshotPricing();
            }
            // Scroll to form
            document.getElementById('ebs_volume_type').scrollIntoView({ behavior: 'smooth' });
        }

        function editS3Price(storageClass, pricePerGB, requestPrice, dataTransferPrice) {
            var newPricePerGB = prompt('Enter new price per GB/month:', pricePerGB);
            if (newPricePerGB !== null && newPricePerGB !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                    '<input type="hidden" name="service_type" value="s3">' +
                    '<input type="hidden" name="storage_class" value="' + storageClass + '">' +
                    '<input type="hidden" name="price_per_gb" value="' + newPricePerGB + '">' +
                    '<input type="hidden" name="request_price" value="' + requestPrice + '">' +
                    '<input type="hidden" name="data_transfer_price" value="' + dataTransferPrice + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editRDSInstancePrice(instanceType, engine, currentPrice, vcpu, memoryGb, deploymentType, region) {
            var newPrice = prompt('Enter new price per hour for ' + instanceType + ' (' + engine + '):', currentPrice);
            if (newPrice !== null && newPrice !== '') {
                var newVcpu = prompt('Enter vCPU count:', vcpu);
                if (newVcpu !== null && newVcpu !== '') {
                    var newMemory = prompt('Enter Memory (GB):', memoryGb);
                    if (newMemory !== null && newMemory !== '') {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                            '<input type="hidden" name="service_type" value="rds_instance">' +
                            '<input type="hidden" name="instance_type" value="' + instanceType + '">' +
                            '<input type="hidden" name="engine" value="' + engine + '">' +
                            '<input type="hidden" name="price" value="' + newPrice + '">' +
                            '<input type="hidden" name="vcpu" value="' + newVcpu + '">' +
                            '<input type="hidden" name="memory_gb" value="' + newMemory + '">' +
                            '<input type="hidden" name="deployment_type" value="' + (deploymentType || 'single_az') + '">' +
                            '<input type="hidden" name="region" value="' + (region || '') + '">';
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }
        }

        function editRDSStoragePrice(storageType, pricePerGB, region) {
            var newPricePerGB = prompt('Enter new price per GB/month:', pricePerGB);
            if (newPricePerGB !== null && newPricePerGB !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                    '<input type="hidden" name="service_type" value="rds_storage">' +
                    '<input type="hidden" name="storage_type" value="' + storageType + '">' +
                    '<input type="hidden" name="price_per_gb" value="' + newPricePerGB + '">' +
                    '<input type="hidden" name="region" value="' + (region || '') + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editVPCPrice(serviceName, pricePerHour, pricePerGB, unit) {
            var newPricePerHour = prompt('Enter new price per hour:', pricePerHour);
            if (newPricePerHour !== null && newPricePerHour !== '') {
                var newPricePerGB = prompt('Enter new price per GB:', pricePerGB);
                if (newPricePerGB !== null) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                        '<input type="hidden" name="service_type" value="vpc">' +
                        '<input type="hidden" name="service_name" value="' + serviceName + '">' +
                        '<input type="hidden" name="price_per_hour" value="' + newPricePerHour + '">' +
                        '<input type="hidden" name="price_per_gb" value="' + newPricePerGB + '">' +
                        '<input type="hidden" name="unit" value="' + unit + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        function editWAFPrice(pricingType, price, unit) {
            var newPrice = prompt('Enter new price per ' + unit + ':', price);
            if (newPrice !== null && newPrice !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                    '<input type="hidden" name="service_type" value="waf">' +
                    '<input type="hidden" name="pricing_type" value="' + pricingType + '">' +
                    '<input type="hidden" name="price" value="' + newPrice + '">' +
                    '<input type="hidden" name="unit" value="' + unit + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editLBPrice(loadBalancerType, pricePerHour, pricePerGB) {
            var newPricePerHour = prompt('Enter new price per hour:', pricePerHour);
            if (newPricePerHour !== null && newPricePerHour !== '') {
                var newPricePerGB = prompt('Enter new price per GB:', pricePerGB);
                if (newPricePerGB !== null && newPricePerGB !== '') {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                        '<input type="hidden" name="service_type" value="lb">' +
                        '<input type="hidden" name="load_balancer_type" value="' + loadBalancerType + '">' +
                        '<input type="hidden" name="price_per_hour" value="' + newPricePerHour + '">' +
                        '<input type="hidden" name="price_per_gb" value="' + newPricePerGB + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        function editEKSPrice(pricingType, pricePerHour) {
            var newPrice = prompt('Enter new price per hour:', pricePerHour);
            if (newPrice !== null && newPrice !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                    '<input type="hidden" name="service_type" value="eks">' +
                    '<input type="hidden" name="pricing_type" value="' + pricingType + '">' +
                    '<input type="hidden" name="price_per_hour" value="' + newPrice + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editECRPrice(pricingType, pricePerGBPerMonth, pricePerGB) {
            var newPricePerGBPerMonth = prompt('Enter new price per GB/month:', pricePerGBPerMonth);
            if (newPricePerGBPerMonth !== null && newPricePerGBPerMonth !== '') {
                var newPricePerGB = prompt('Enter new price per GB:', pricePerGB);
                if (newPricePerGB !== null && newPricePerGB !== '') {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                        '<input type="hidden" name="service_type" value="ecr">' +
                        '<input type="hidden" name="pricing_type" value="' + pricingType + '">' +
                        '<input type="hidden" name="price_per_gb_per_month" value="' + newPricePerGBPerMonth + '">' +
                        '<input type="hidden" name="price_per_gb" value="' + newPricePerGB + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        function editRoute53Price(pricingType, price, unit) {
            var newPrice = prompt('Enter new price per ' + unit + ':', price);
            if (newPrice !== null && newPrice !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="update_price">' +
                    '<input type="hidden" name="service_type" value="route53">' +
                    '<input type="hidden" name="pricing_type" value="' + pricingType + '">' +
                    '<input type="hidden" name="price" value="' + newPrice + '">' +
                    '<input type="hidden" name="unit" value="' + unit + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    
    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; 2025 Cloudly Infotech Limited. All rights reserved.</p>
            <p>Bangladesh's First Premier AWS & GCP Partner</p>
        </div>
    </footer>
</body>

</html>



