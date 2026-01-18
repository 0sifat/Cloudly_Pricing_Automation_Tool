<?php
/**
 * Script to populate initial pricing data from hardcoded values
 * Run this once after setting up the database to populate initial prices
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PricingModel.php';

$pricingModel = new PricingModel();

echo "Populating initial pricing data...\n\n";

// EC2 Instance Prices (per hour)
$ec2_prices = [
    't3.micro' => 0.0104,
    't3.small' => 0.0208,
    't3.medium' => 0.0416,
    't3.large' => 0.0832,
    'm5.large' => 0.096,
    'm5.xlarge' => 0.192,
    'c5.large' => 0.085,
    'c5.xlarge' => 0.17,
    't4g.nano' => 0.0042,
    't4g.micro' => 0.0084,
    't4g.small' => 0.0168,
    't4g.medium' => 0.0336,
    't4g.large' => 0.0672,
    't4g.xlarge' => 0.1344,
    't4g.2xlarge' => 0.2688,
];

foreach ($ec2_prices as $instance_type => $price) {
    $pricingModel->setEC2Price($instance_type, 'us-east-1', $price);
    echo "Set EC2 $instance_type: \$$price/hour\n";
}

// EBS Volume Prices
$ebs_prices = [
    'gp2' => ['price_per_gb' => 0.10, 'iops_price' => 0, 'throughput_price' => 0],
    'gp3' => ['price_per_gb' => 0.08, 'iops_price' => 0, 'throughput_price' => 0.04],
    'io1' => ['price_per_gb' => 0.125, 'iops_price' => 0.065, 'throughput_price' => 0],
    'io2' => ['price_per_gb' => 0.125, 'iops_price' => 0.065, 'throughput_price' => 0],
    'st1' => ['price_per_gb' => 0.045, 'iops_price' => 0, 'throughput_price' => 0],
    'sc1' => ['price_per_gb' => 0.015, 'iops_price' => 0, 'throughput_price' => 0],
];

foreach ($ebs_prices as $volume_type => $prices) {
    $pricingModel->setEBSPrice($volume_type, 'us-east-1', $prices['price_per_gb'], $prices['iops_price'], $prices['throughput_price']);
    echo "Set EBS $volume_type: \${$prices['price_per_gb']}/GB/month\n";
}

// S3 Storage Prices
$s3_prices = [
    'standard' => ['price_per_gb' => 0.023, 'request_price' => 0.005, 'data_transfer_price' => 0.09],
    'intelligent_tiering' => ['price_per_gb' => 0.023, 'request_price' => 0.005, 'data_transfer_price' => 0.09],
    'standard_ia' => ['price_per_gb' => 0.0125, 'request_price' => 0.01, 'data_transfer_price' => 0.09],
    'onezone_ia' => ['price_per_gb' => 0.01, 'request_price' => 0.01, 'data_transfer_price' => 0.09],
    'glacier' => ['price_per_gb' => 0.004, 'request_price' => 0.005, 'data_transfer_price' => 0.09],
    'deep_archive' => ['price_per_gb' => 0.00099, 'request_price' => 0.005, 'data_transfer_price' => 0.09],
];

foreach ($s3_prices as $storage_class => $prices) {
    $pricingModel->setS3Price($storage_class, 'us-east-1', $prices['price_per_gb'], $prices['request_price'], $prices['data_transfer_price']);
    echo "Set S3 $storage_class: \${$prices['price_per_gb']}/GB/month\n";
}

// RDS Instance Prices (per hour)
$rds_prices = [
    ['instance_type' => 'db.t3.micro', 'engine' => 'mysql', 'price' => 0.017],
    ['instance_type' => 'db.t3.small', 'engine' => 'mysql', 'price' => 0.034],
    ['instance_type' => 'db.t3.medium', 'engine' => 'mysql', 'price' => 0.068],
    ['instance_type' => 'db.m5.large', 'engine' => 'mysql', 'price' => 0.171],
    ['instance_type' => 'db.m5.xlarge', 'engine' => 'mysql', 'price' => 0.342],
    ['instance_type' => 'db.r5.xlarge', 'engine' => 'mysql', 'price' => 0.48],
];

foreach ($rds_prices as $rds) {
    foreach (['single_az', 'multi_az'] as $deployment_type) {
        $stmt = $conn->prepare("INSERT INTO rds_instance_pricing (instance_type, engine, region, deployment_type, vcpu, memory_gb, on_demand_price_per_hour) VALUES (?, ?, 'us-east-1', ?, 0, 0, ?) ON DUPLICATE KEY UPDATE on_demand_price_per_hour = ?");
        $stmt->bind_param("sssdd", $rds['instance_type'], $rds['engine'], $deployment_type, $rds['price'], $rds['price']);
        $stmt->execute();
    }
    echo "Set RDS {$rds['instance_type']} ({$rds['engine']}): \${$rds['price']}/hour (Single AZ & Multi-AZ)\n";
}

// RDS Storage Prices (per GB/month; no deployment — one rate per storage_type+region)
$rds_storage = [
    'gp2' => ['price_per_gb' => 0.115],
    'gp3' => ['price_per_gb' => 0.115],
    'io1' => ['price_per_gb' => 0.125],
];

foreach ($rds_storage as $storage_type => $prices) {
    $stmt = $conn->prepare("INSERT INTO rds_storage_pricing (storage_type, region, price_per_gb_per_month) VALUES (?, 'us-east-1', ?) ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?");
    $stmt->bind_param("sdd", $storage_type, $prices['price_per_gb'], $prices['price_per_gb']);
    $stmt->execute();
    echo "Set RDS Storage $storage_type: \${$prices['price_per_gb']}/GB/month\n";
}

// VPC Prices
$vpc_services = [
    ['service_name' => 'nat_gateway', 'price_per_hour' => 0.045, 'price_per_gb' => 0, 'unit' => 'hour'],
    ['service_name' => 'vpc_endpoint', 'price_per_hour' => 0.01, 'price_per_gb' => 0, 'unit' => 'hour'],
    ['service_name' => 'data_transfer', 'price_per_hour' => 0, 'price_per_gb' => 0.01, 'unit' => 'gb'],
];

foreach ($vpc_services as $service) {
    $stmt = $conn->prepare("INSERT INTO vpc_pricing (service_name, region, price_per_hour, price_per_gb, unit) VALUES (?, 'us-east-1', ?, ?, ?) ON DUPLICATE KEY UPDATE price_per_hour = ?, price_per_gb = ?");
    $stmt->bind_param("sddssdd", $service['service_name'], $service['price_per_hour'], $service['price_per_gb'], $service['unit'], $service['price_per_hour'], $service['price_per_gb']);
    $stmt->execute();
    echo "Set VPC {$service['service_name']}: \${$service['price_per_hour']}/hour or \${$service['price_per_gb']}/GB\n";
}

// WAF Prices
$waf_prices = [
    ['pricing_type' => 'web_acl', 'price' => 5.00, 'unit' => 'month'],
    ['pricing_type' => 'rule', 'price' => 1.00, 'unit' => 'month'],
    ['pricing_type' => 'request', 'price' => 1.00, 'unit' => 'million'],
];

foreach ($waf_prices as $waf) {
    $stmt = $conn->prepare("INSERT INTO waf_pricing (pricing_type, region, price_per_unit, unit) VALUES (?, 'us-east-1', ?, ?) ON DUPLICATE KEY UPDATE price_per_unit = ?");
    $stmt->bind_param("sdds", $waf['pricing_type'], $waf['price'], $waf['unit'], $waf['price']);
    $stmt->execute();
    echo "Set WAF {$waf['pricing_type']}: \${$waf['price']}/{$waf['unit']}\n";
}

// Load Balancer Prices
$lb_prices = [
    ['load_balancer_type' => 'application', 'price_per_hour' => 0.0225, 'price_per_gb' => 0.008],
    ['load_balancer_type' => 'network', 'price_per_hour' => 0.0225, 'price_per_gb' => 0.008],
    ['load_balancer_type' => 'classic', 'price_per_hour' => 0.025, 'price_per_gb' => 0.008],
];

foreach ($lb_prices as $lb) {
    $stmt = $conn->prepare("INSERT INTO load_balancer_pricing (load_balancer_type, region, price_per_hour, price_per_gb) VALUES (?, 'us-east-1', ?, ?) ON DUPLICATE KEY UPDATE price_per_hour = ?, price_per_gb = ?");
    $stmt->bind_param("sdddd", $lb['load_balancer_type'], $lb['price_per_hour'], $lb['price_per_gb'], $lb['price_per_hour'], $lb['price_per_gb']);
    $stmt->execute();
    echo "Set Load Balancer {$lb['load_balancer_type']}: \${$lb['price_per_hour']}/hour, \${$lb['price_per_gb']}/GB\n";
}

// EKS Prices
$eks_prices = [
    ['pricing_type' => 'cluster', 'price_per_hour' => 0.10],
];

foreach ($eks_prices as $eks) {
    $stmt = $conn->prepare("INSERT INTO eks_pricing (pricing_type, region, price_per_hour) VALUES (?, 'us-east-1', ?) ON DUPLICATE KEY UPDATE price_per_hour = ?");
    $stmt->bind_param("sdd", $eks['pricing_type'], $eks['price_per_hour'], $eks['price_per_hour']);
    $stmt->execute();
    echo "Set EKS {$eks['pricing_type']}: \${$eks['price_per_hour']}/hour\n";
}

// ECR Prices
$ecr_prices = [
    ['pricing_type' => 'storage', 'price_per_gb_per_month' => 0.10, 'price_per_gb' => 0],
    ['pricing_type' => 'data_transfer', 'price_per_gb_per_month' => 0, 'price_per_gb' => 0.09],
];

foreach ($ecr_prices as $ecr) {
    $stmt = $conn->prepare("INSERT INTO ecr_pricing (pricing_type, region, price_per_gb_per_month, price_per_gb) VALUES (?, 'us-east-1', ?, ?) ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, price_per_gb = ?");
    $stmt->bind_param("sdddd", $ecr['pricing_type'], $ecr['price_per_gb_per_month'], $ecr['price_per_gb'], $ecr['price_per_gb_per_month'], $ecr['price_per_gb']);
    $stmt->execute();
    echo "Set ECR {$ecr['pricing_type']}: \${$ecr['price_per_gb_per_month']}/GB/month, \${$ecr['price_per_gb']}/GB\n";
}

// Route53 Prices
$route53_prices = [
    ['pricing_type' => 'hosted_zone', 'price' => 0.50, 'unit' => 'month'],
    ['pricing_type' => 'query', 'price' => 0.40, 'unit' => 'million'],
    ['pricing_type' => 'health_check', 'price' => 0.50, 'unit' => 'month'],
];

foreach ($route53_prices as $r53) {
    $stmt = $conn->prepare("INSERT INTO route53_pricing (pricing_type, region, price_per_unit, unit) VALUES (?, 'us-east-1', ?, ?) ON DUPLICATE KEY UPDATE price_per_unit = ?");
    $stmt->bind_param("sdds", $r53['pricing_type'], $r53['price'], $r53['unit'], $r53['price']);
    $stmt->execute();
    echo "Set Route53 {$r53['pricing_type']}: \${$r53['price']}/{$r53['unit']}\n";
}

echo "\n\nInitial pricing data populated successfully!\n";
echo "You can now manage prices through the admin panel.\n";



