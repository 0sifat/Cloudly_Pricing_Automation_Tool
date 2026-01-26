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
    // VPC Pricing - handles 4 separate services: site_to_site_vpn, data_transfer, public_ipv4, nat_gateway
    public function calculateVPC($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $service_type = $config['service_type'] ?? '';
        
        switch ($service_type) {
            case 'site_to_site_vpn':
                // Site-to-Site VPN - per connection per unit
                $vpn_connections = intval($config['vpn_connections'] ?? 0);
                $vpn_duration = floatval($config['vpn_duration'] ?? 24.00); // Fixed at 24
                $vpn_duration_unit = $config['vpn_duration_unit'] ?? 'hours_per_day';
                
                $vpn_price = $this->pricingModel->getVPCSiteToSiteVPNPrice($region, $vpn_duration_unit);
                $price_per_connection_per_unit = $vpn_price['price_per_connection_per_unit'];
                
                // Calculate based on unit
                if ($vpn_duration_unit === 'hours_per_day') {
                    // Price per connection per day * 30 days
                    $cost = $vpn_connections * $price_per_connection_per_unit * 30;
                } elseif ($vpn_duration_unit === 'hours_per_week') {
                    // Price per connection per week * 4.33 weeks
                    $cost = $vpn_connections * $price_per_connection_per_unit * 4.33;
                } elseif ($vpn_duration_unit === 'hours_per_month') {
                    // Price per connection per month
                    $cost = $vpn_connections * $price_per_connection_per_unit;
                }
                break;
                
            case 'data_transfer':
                // Data Transfer - Inbound is free (0.00), Outbound has fixed pricing
                $inbound_amount = floatval($config['inbound_amount'] ?? 0);
                $inbound_unit = strtoupper($config['inbound_unit'] ?? 'GB');
                $outbound_amount = floatval($config['data_transfer_amount'] ?? 0);
                $outbound_unit = strtoupper($config['data_transfer_unit'] ?? 'GB');
                
                // Inbound is always free
                $inbound_cost = 0.00;
                
                // Convert outbound to GB if TB
                if ($outbound_unit === 'TB') {
                    $outbound_amount_gb = $outbound_amount * 1024;
                } else {
                    $outbound_amount_gb = $outbound_amount;
                }
                
                // Outbound pricing
                $outbound_price = $this->pricingModel->getVPCDataTransferPrice($region);
                $outbound_cost = $outbound_amount_gb * $outbound_price['price_per_gb'];
                
                $cost = $inbound_cost + $outbound_cost;
                break;
                
            case 'public_ipv4':
                // Public IPv4 Address - separate pricing for in-use and idle
                $in_use_count = intval($config['in_use_ipv4_count'] ?? 0);
                $idle_count = intval($config['idle_ipv4_count'] ?? 0);
                
                $ipv4_price = $this->pricingModel->getVPCPublicIPv4Price($region);
                $price_per_in_use_per_hour = $ipv4_price['price_per_in_use_per_hour'];
                $price_per_idle_per_hour = $ipv4_price['price_per_idle_per_hour'];
                
                // Calculate monthly cost (730 hours per month)
                $in_use_cost = $in_use_count * $price_per_in_use_per_hour * 730;
                $idle_cost = $idle_count * $price_per_idle_per_hour * 730;
                
                $cost = $in_use_cost + $idle_cost;
                break;
                
            case 'nat_gateway':
                // NAT Gateway - per gateway per hour + per GB processed
                $nat_gateway_count = intval($config['nat_gateway_count'] ?? 0);
                $nat_data_processed = floatval($config['nat_data_processed'] ?? 0);
                $nat_data_unit = strtoupper($config['nat_data_unit'] ?? 'GB');
                
                // Convert to GB if TB
                if ($nat_data_unit === 'TB') {
                    $nat_data_processed_gb = $nat_data_processed * 1024;
                } else {
                    $nat_data_processed_gb = $nat_data_processed;
                }
                
                $nat_price = $this->pricingModel->getVPCNATGatewayPrice($region);
                $price_per_gateway_per_hour = $nat_price['price_per_gateway_per_hour'];
                $price_per_gb = $nat_price['price_per_gb'];
                
                // Gateway hourly cost (730 hours per month) + data processing cost
                $gateway_cost = $nat_gateway_count * $price_per_gateway_per_hour * 730;
                $data_cost = $nat_data_processed_gb * $price_per_gb;
                
                $cost = $gateway_cost + $data_cost;
                break;
                
            default:
                $cost = 0;
                break;
        }
        
        return $cost;
    }
    
    // S3 Pricing - handles 3 separate services: standard_storage, data_transfer, glacier
    public function calculateS3($config) {
        $cost = 0;
        $region = $config['region'] ?? '';
        if (empty($region)) return 0;
        
        $service_type = $config['service_type'] ?? '';
        
        switch ($service_type) {
            case 'standard_storage':
                // S3 Standard Storage - monthly calculation
                $storage_amount = floatval($config['storage_amount'] ?? 0);
                $storage_unit = strtoupper($config['storage_unit'] ?? 'GB');
                
                // Convert to GB if TB
                if ($storage_unit === 'TB') {
                    $storage_amount_gb = $storage_amount * 1024;
                } else {
                    $storage_amount_gb = $storage_amount;
                }
                
                $price = $this->pricingModel->getS3StandardStoragePrice($region);
                $cost = $storage_amount_gb * $price['price_per_gb_per_month'];
                break;
                
            case 'data_transfer':
                // Data Transfer - Inbound is free (0.00), Outbound has fixed pricing
                $inbound_amount = floatval($config['inbound_amount'] ?? 0);
                $inbound_unit = strtoupper($config['inbound_unit'] ?? 'GB');
                $outbound_amount = floatval($config['data_transfer_amount'] ?? 0);
                $outbound_unit = strtoupper($config['data_transfer_unit'] ?? 'GB');
                
                // Inbound is always free
                $inbound_cost = 0.00;
                
                // Convert outbound to GB if TB
                if ($outbound_unit === 'TB') {
                    $outbound_amount_gb = $outbound_amount * 1024;
                } else {
                    $outbound_amount_gb = $outbound_amount;
                }
                
                // Outbound pricing (fixed 0.05-0.09 per GB)
                $outbound_price = $this->pricingModel->getS3DataTransferPrice($region);
                $outbound_cost = $outbound_amount_gb * $outbound_price['price_per_gb'];
                
                $cost = $inbound_cost + $outbound_cost;
                break;
                
            case 'glacier':
                // S3 Glacier Deep Archive - monthly calculation
                $storage_amount = floatval($config['storage_amount'] ?? 0);
                $storage_unit = strtoupper($config['storage_unit'] ?? 'GB');
                
                // Convert to GB if TB
                if ($storage_unit === 'TB') {
                    $storage_amount_gb = $storage_amount * 1024;
                } else {
                    $storage_amount_gb = $storage_amount;
                }
                
                $price = $this->pricingModel->getS3GlacierPrice($region);
                $cost = $storage_amount_gb * $price['price_per_gb_per_month'];
                break;
                
            default:
                $cost = 0;
                break;
        }
        
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
    
    // EBS Pricing with snapshot breakdown (hardcoded snapshot price: 0.05 per GB/month)
    public function calculateEBSWithSnapshot($config) {
        $storage_cost = 0;
        $snapshot_cost = 0;
        $region = $config['region'] ?? '';
        
        if (empty($region)) {
            return ['storage_cost' => 0, 'snapshot_cost' => 0, 'total_cost' => 0];
        }
        
        $ebs_price = $this->pricingModel->getEBSPrice($config['volume_type'], $region);
        $size_gb = intval($config['size_gb'] ?? 0);
        $instance_count = intval($config['ec2_instance_count'] ?? 1); // Number of EC2 instances using this EBS
        
        // EBS Storage Cost = EBS Volume Size in GB × EBS Price per GB per Month × Instance count
        $storage_cost_per_month = $size_gb * $ebs_price['price_per_gb'] * $instance_count;
        
        // IOPS cost for io1/io2 volumes (per month)
        if ($config['volume_type'] == 'io1' || $config['volume_type'] == 'io2') {
            $iops = intval($config['iops'] ?? 3000);
            if ($iops > 3000) {
                $storage_cost_per_month += ($iops - 3000) * $ebs_price['iops_price'] / 1000 * $instance_count;
            }
        }
        
        // Throughput cost for gp3 volumes (per month)
        if ($config['volume_type'] == 'gp3') {
            $iops = intval($config['iops'] ?? 3000);
            $throughput = intval($config['throughput'] ?? 125);
            
            if ($iops > 3000) {
                $storage_cost_per_month += ($iops - 3000) * $ebs_price['iops_price'] * $instance_count;
            }
            if ($throughput > 125) {
                $storage_cost_per_month += ($throughput - 125) * $ebs_price['throughput_price'] * $instance_count;
            }
        }
        
        $storage_cost = $storage_cost_per_month; // Already monthly
        
        // Snapshot cost calculation (hardcoded price: 0.05 per GB/month)
        $snapshot_frequency = $config['snapshot_frequency'] ?? 'none';
        $snapshot_storage_gb = intval($config['snapshot_storage_gb'] ?? 0);
        $snapshot_price_per_gb = 0.05; // Hardcoded
        
        // Fixed snapshot counts per month
        $snapshot_counts = [
            'hourly' => 729,
            'daily' => 30,
            '2x_daily' => 59.83,
            '3x_daily' => 90.25,
            '4x_daily' => 120.67,
            '6x_daily' => 120.67,
            'weekly' => 3,
            'monthly' => 1
        ];
        
        if ($snapshot_frequency !== 'none' && $snapshot_storage_gb > 0 && isset($snapshot_counts[$snapshot_frequency])) {
            $total_snapshots = $snapshot_counts[$snapshot_frequency];
            
            // Total EBS Snapshot Cost = (EBS Volume Size in GB × 0.05) + 
            //                          (Total Number of Snapshots × Changed Data Size per Snapshot in GB × 0.05 × 50%) × Instance Count
            $initial_snapshot_cost = $size_gb * $snapshot_price_per_gb;
            $incremental_snapshot_cost = $total_snapshots * $snapshot_storage_gb * $snapshot_price_per_gb * 0.5; // 50% partial month factor
            $snapshot_cost_per_month = ($initial_snapshot_cost + $incremental_snapshot_cost) * $instance_count;
            
            $snapshot_cost = $snapshot_cost_per_month; // Already monthly
        }
        
        $total_cost = $storage_cost + $snapshot_cost;
        
        return [
            'storage_cost' => $storage_cost,
            'snapshot_cost' => $snapshot_cost,
            'total_cost' => $total_cost
        ];
    }
}






