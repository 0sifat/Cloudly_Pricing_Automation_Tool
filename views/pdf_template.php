<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cost Estimate & Invoice - Cloudly AWS Ask</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }
        .header h2 {
            color: #666;
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
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
        }
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box p {
            color: #666;
            margin: 5px 0;
        }
        .service-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .service-section h3 {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }
        .service-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }
        .service-table th {
            background: #f0f0f0;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        .service-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .service-table tr:last-child td {
            border-bottom: none;
        }
        .total-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 5px;
        }
        .total-section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .total-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            body {
                padding: 20px;
            }
            .service-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Cloudly AWS Ask</h1>
        <h2>Cost Estimate & Invoice</h2>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>Project Information</h3>
            <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
            <p><strong>Report Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        <div class="info-box">
            <h3>Contact Information</h3>
            <p><strong>User:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Given To:</strong> <?php echo htmlspecialchars($project['salesman_name']); ?></p>
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
                <tr style="background: #f0f0f0; font-weight: bold;">
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
                    <td>$<?php echo number_format($vol['unit_cost'], 2); ?></td>
                    <td>$<?php echo number_format($vol['total_cost'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f0f0f0; font-weight: bold;">
                    <td colspan="5">EBS Subtotal</td>
                    <td>$<?php echo number_format($ebs_total, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($vpc_config): ?>
    <div class="service-section">
        <h3>VPC</h3>
        <table class="service-table">
            <tr>
                <td><strong>Region:</strong></td>
                <td><?php echo htmlspecialchars($vpc_config['region']); ?></td>
                <td><strong>VPC Count:</strong></td>
                <td><?php echo $vpc_config['vpc_count']; ?></td>
            </tr>
            <tr>
                <td><strong>NAT Gateways:</strong></td>
                <td><?php echo $vpc_config['nat_gateway_count']; ?></td>
                <td><strong>VPC Endpoints:</strong></td>
                <td><?php echo $vpc_config['vpc_endpoint_count']; ?></td>
            </tr>
            <tr style="background: #f0f0f0; font-weight: bold;">
                <td colspan="3">VPC Subtotal</td>
                <td>$<?php echo number_format($vpc_config['total_cost'], 2); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($s3_config): ?>
    <div class="service-section">
        <h3>S3</h3>
        <table class="service-table">
            <tr>
                <td><strong>Storage Class:</strong></td>
                <td><?php echo htmlspecialchars($s3_config['storage_class']); ?></td>
                <td><strong>Storage (GB):</strong></td>
                <td><?php echo $s3_config['storage_gb']; ?></td>
            </tr>
            <tr style="background: #f0f0f0; font-weight: bold;">
                <td colspan="3">S3 Subtotal</td>
                <td>$<?php echo number_format($s3_config['total_cost'], 2); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Add similar sections for other services -->

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

    <div class="footer">
        <p>This is an estimate. Actual costs may vary.</p>
        <p>&copy; <?php echo date('Y'); ?> Cloudly. All rights reserved.</p>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>






