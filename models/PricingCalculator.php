<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingModel.php';

class PricingCalculator {
    private $conn;
    private $pricingModel;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->pricingModel = new PricingModel();
    }
    
    // VPC Pricing
    public function calculateVPC($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        // NAT Gateway pricing
        $nat_price = $this->pricingModel->getVPCPrice('nat_gateway', $region);
        $cost += $config['nat_gateway_count'] * $nat_price['price_per_hour'] * 730;
        
        // VPC Endpoint pricing
        $endpoint_price = $this->pricingModel->getVPCPrice('vpc_endpoint', $region);
        $cost += $config['vpc_endpoint_count'] * $endpoint_price['price_per_hour'] * 730;
        
        // Data transfer pricing
        $data_transfer_price = $this->pricingModel->getVPCPrice('data_transfer', $region);
        $cost += $config['data_transfer_gb'] * $data_transfer_price['price_per_gb'];
        
        return $cost;
    }
    
    // S3 Pricing
    public function calculateS3($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        $storage_class = $config['storage_class'] ?? 'standard';
        
        $s3_price = $this->pricingModel->getS3Price($storage_class, $region);
        
        $cost += $config['storage_gb'] * $s3_price['price_per_gb'];
        $cost += $config['requests_million'] * $s3_price['request_price'];
        $cost += $config['data_transfer_gb'] * $s3_price['data_transfer_price'];
        
        return $cost;
    }
    
    // RDS Pricing
    public function calculateRDS($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        $engine = $config['engine'] ?? 'mysql';
        
        // Instance pricing (Single AZ / Multi-AZ price set per deployment on instance in admin)
        $instance_price_per_hour = $this->pricingModel->getRDSInstancePrice($config['instance_type'], $engine, $region, !empty($config['multi_az']));
        $instance_cost = $instance_price_per_hour * 730;
        $cost += $instance_cost * $config['quantity'];
        
        // Storage cost (price per GB per month; one rate per storage_type+region, no deployment)
        $storage_price = $this->pricingModel->getRDSStoragePrice($config['storage_type'], $region);
        $cost += $config['storage_gb'] * $storage_price['price_per_gb'];
        
        return $cost;
    }
    
    // EKS Pricing
    public function calculateEKS($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $cluster_price_per_hour = $this->pricingModel->getEKSPrice('cluster', $region);
        $cost += $config['cluster_count'] * $cluster_price_per_hour * 730;
        
        // Node group pricing (EC2 instances) - only if instance_type is provided
        if (!empty($config['instance_type'])) {
            $node_price_per_hour = $this->pricingModel->getEC2Price($config['instance_type'], $region);
            $node_cost = $node_price_per_hour * 730;
            $cost += $node_cost * $config['node_count'] * $config['node_group_count'];
        }
        
        return $cost;
    }
    
    // ECR Pricing
    public function calculateECR($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $ecr_price = $this->pricingModel->getECRPrice('storage', $region);
        $cost += $config['storage_gb'] * $ecr_price['price_per_gb_per_month'];
        
        $data_transfer_price = $this->pricingModel->getECRPrice('data_transfer', $region);
        $cost += $config['data_transfer_gb'] * $data_transfer_price['price_per_gb'];
        
        return $cost;
    }
    
    // Load Balancer Pricing
    public function calculateLoadBalancer($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $lb_price = $this->pricingModel->getLoadBalancerPrice($config['load_balancer_type'], $region);
        $cost += $lb_price['price_per_hour'] * 730 * $config['quantity'];
        $cost += $config['data_processed_gb'] * $lb_price['price_per_gb'];
        
        return $cost;
    }
    
    // WAF Pricing
    public function calculateWAF($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $web_acl_price = $this->pricingModel->getWAFPrice('web_acl', $region);
        $cost += $config['web_acl_count'] * $web_acl_price['price'];
        
        $rule_price = $this->pricingModel->getWAFPrice('rule', $region);
        $cost += $config['rules_count'] * $rule_price['price'];
        
        $request_price = $this->pricingModel->getWAFPrice('request', $region);
        $cost += $config['requests_million'] * $request_price['price'];
        
        return $cost;
    }
    
    // Route53 Pricing
    public function calculateRoute53($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $hosted_zone_price = $this->pricingModel->getRoute53Price('hosted_zone', $region);
        $cost += $config['hosted_zones'] * $hosted_zone_price['price'];
        
        $query_price = $this->pricingModel->getRoute53Price('query', $region);
        $cost += $config['queries_million'] * $query_price['price'];
        
        $health_check_price = $this->pricingModel->getRoute53Price('health_check', $region);
        $cost += $config['health_checks'] * $health_check_price['price'];
        
        return $cost;
    }
    
    // EBS Pricing (legacy - returns just the total cost)
    public function calculateEBS($config) {
        $result = $this->calculateEBSWithSnapshot($config);
        return $result['total_cost'];
    }
    
    // EBS Pricing with snapshot breakdown (AWS method)
    public function calculateEBSWithSnapshot($config) {
        $storage_cost = 0;
        $snapshot_cost = 0;
        $region = $config['region'] ?? '';
        
        if (empty($region)) {
            return ['storage_cost' => 0, 'snapshot_cost' => 0, 'total_cost' => 0];
        }
        
        $ebs_price = $this->pricingModel->getEBSPrice($config['volume_type'], $region);
        
        // Calculate instance months: EC2 hours / 730 hours in a month
        $ec2_instance_hours = floatval($config['ec2_instance_hours'] ?? 730);
        $instance_months = $ec2_instance_hours / 730.0;
        
        // Storage cost: size_gb × price_per_gb_per_month × instance_months
        $storage_cost_per_month = $config['size_gb'] * $ebs_price['price_per_gb'];
        
        // IOPS cost for io1/io2 volumes (per month)
        if ($config['volume_type'] == 'io1' || $config['volume_type'] == 'io2') {
            $iops = intval($config['iops'] ?? 3000);
            if ($iops > 3000) {
                $storage_cost_per_month += ($iops - 3000) * $ebs_price['iops_price'] / 1000;
            }
        }
        
        // Throughput cost for gp3 volumes (per month)
        if ($config['volume_type'] == 'gp3') {
            $iops = intval($config['iops'] ?? 3000);
            $throughput = intval($config['throughput'] ?? 125);
            
            // gp3 baseline is 3000 IOPS and 125 MB/s
            if ($iops > 3000) {
                $storage_cost_per_month += ($iops - 3000) * $ebs_price['iops_price'];
            }
            if ($throughput > 125) {
                $storage_cost_per_month += ($throughput - 125) * $ebs_price['throughput_price'];
            }
        }
        
        // Apply instance months to storage cost
        $storage_cost = $storage_cost_per_month * $instance_months;
        
        // Snapshot cost calculation (AWS method)
        $snapshot_frequency = $config['snapshot_frequency'] ?? 'none';
        $snapshot_storage_gb = intval($config['snapshot_storage_gb'] ?? 0);
        $size_gb = intval($config['size_gb'] ?? 0);
        
        if ($snapshot_frequency !== 'none' && $snapshot_storage_gb > 0) {
            $snapshot_price_per_gb = 0;
            
            // Get snapshot price based on frequency
            switch ($snapshot_frequency) {
                case 'hourly':
                    $snapshot_price_per_gb = $ebs_price['snapshot_hourly'];
                    $snapshots_per_month = 730; // 24 hours × 30.4 days
                    break;
                case 'daily':
                    $snapshot_price_per_gb = $ebs_price['snapshot_daily'];
                    $snapshots_per_month = 30; // ~30 days per month
                    break;
                case '2x_daily':
                    $snapshot_price_per_gb = $ebs_price['snapshot_2x_daily'];
                    $snapshots_per_month = 60; // 2 × 30
                    break;
                case '3x_daily':
                    $snapshot_price_per_gb = $ebs_price['snapshot_3x_daily'];
                    $snapshots_per_month = 90; // 3 × 30
                    break;
                case '4x_daily':
                    $snapshot_price_per_gb = $ebs_price['snapshot_4x_daily'];
                    $snapshots_per_month = 120; // 4 × 30
                    break;
                case '6x_daily':
                    $snapshot_price_per_gb = $ebs_price['snapshot_6x_daily'];
                    $snapshots_per_month = 180; // 6 × 30
                    break;
                case 'weekly':
                    $snapshot_price_per_gb = $ebs_price['snapshot_weekly'];
                    $snapshots_per_month = 4; // ~4 weeks per month
                    break;
                case 'monthly':
                    $snapshot_price_per_gb = $ebs_price['snapshot_monthly'];
                    $snapshots_per_month = 1; // 1 per month
                    break;
                default:
                    $snapshot_price_per_gb = 0;
                    $snapshots_per_month = 0;
            }
            
            if ($snapshot_price_per_gb > 0 && $snapshots_per_month > 0) {
                // AWS Snapshot Cost Calculation Method:
                // 1. Initial snapshot cost = size_gb × snapshot_price_per_gb
                $initial_snapshot_cost = $size_gb * $snapshot_price_per_gb;
                
                // 2. Monthly cost of each snapshot = snapshot_storage_gb × snapshot_price_per_gb
                $monthly_snapshot_cost = $snapshot_storage_gb * $snapshot_price_per_gb;
                
                // 3. Discount for partial storage month = monthly_cost × 50%
                $discounted_monthly_cost = $monthly_snapshot_cost * 0.5;
                
                // 4. Incremental snapshot cost = discounted_cost × number_of_snapshots
                $incremental_snapshot_cost = $discounted_monthly_cost * $snapshots_per_month;
                
                // 5. Total snapshot cost = initial + incremental
                $total_snapshot_cost_per_month = $initial_snapshot_cost + $incremental_snapshot_cost;
                
                // 6. Apply instance months
                $snapshot_cost = $total_snapshot_cost_per_month * $instance_months;
            }
        }
        
        $total_cost = $storage_cost + $snapshot_cost;
        
        return [
            'storage_cost' => $storage_cost,
            'snapshot_cost' => $snapshot_cost,
            'total_cost' => $total_cost
        ];
    }
}






