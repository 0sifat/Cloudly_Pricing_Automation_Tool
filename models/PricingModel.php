<?php
require_once __DIR__ . '/../config/database.php';

class PricingModel {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    // EC2 Pricing (OS-based: linux, windows, redhat)
    public function getEC2Price($instance_type, $region, $os = 'linux') {
        $stmt = $this->conn->prepare("SELECT on_demand_price_per_hour FROM ec2_instance_pricing WHERE instance_type = ? AND region = ? AND os = ?");
        $stmt->bind_param("sss", $instance_type, $region, $os);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return floatval($row['on_demand_price_per_hour']);
        }
        return 0;
    }
    
    public function getEC2InstanceDetails($instance_type, $region, $os = 'linux') {
        $stmt = $this->conn->prepare("SELECT vcpu, memory_gb, on_demand_price_per_hour FROM ec2_instance_pricing WHERE instance_type = ? AND region = ? AND os = ?");
        $stmt->bind_param("sss", $instance_type, $region, $os);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'vcpu' => intval($row['vcpu']),
                'memory_gb' => floatval($row['memory_gb']),
                'price' => floatval($row['on_demand_price_per_hour'])
            ];
        }
        return null;
    }
    
    public function setEC2Price($instance_type, $region, $price, $vcpu = 0, $memory_gb = 0, $os = 'linux') {
        $stmt = $this->conn->prepare("INSERT INTO ec2_instance_pricing (instance_type, region, os, vcpu, memory_gb, on_demand_price_per_hour) VALUES (?, ?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE vcpu = ?, memory_gb = ?, on_demand_price_per_hour = ?");
        $stmt->bind_param("sssiddidd", $instance_type, $region, $os, $vcpu, $memory_gb, $price, $vcpu, $memory_gb, $price);
        return $stmt->execute();
    }
    
    // EBS Pricing (snapshot pricing removed - hardcoded at 0.05 per GB/month)
    public function getEBSPrice($volume_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps 
            FROM ebs_volume_pricing WHERE volume_type = ? AND region = ?");
        $stmt->bind_param("ss", $volume_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb' => floatval($row['price_per_gb_per_month']),
                'iops_price' => floatval($row['iops_price_per_iops']),
                'throughput_price' => floatval($row['throughput_price_per_mbps'])
            ];
        }
        return ['price_per_gb' => 0, 'iops_price' => 0, 'throughput_price' => 0];
    }
    
    public function setEBSPrice($volume_type, $region, $price_per_gb, $iops_price = 0, $throughput_price = 0) {
        $stmt = $this->conn->prepare("INSERT INTO ebs_volume_pricing (volume_type, region, price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, iops_price_per_iops = ?, throughput_price_per_mbps = ?");
        $stmt->bind_param("ssdddddd", $volume_type, $region, $price_per_gb, $iops_price, $throughput_price, $price_per_gb, $iops_price, $throughput_price);
        return $stmt->execute();
    }
    
    // S3 Pricing
    // Legacy method for backward compatibility
    public function getS3Price($storage_class, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month, request_price_per_1000, data_transfer_price_per_gb FROM s3_storage_pricing WHERE storage_class = ? AND region = ?");
        $stmt->bind_param("ss", $storage_class, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb' => floatval($row['price_per_gb_per_month']),
                'request_price' => floatval($row['request_price_per_1000']),
                'data_transfer_price' => floatval($row['data_transfer_price_per_gb'])
            ];
        }
        // Return 0 if no price found for this region
        return ['price_per_gb' => 0, 'request_price' => 0, 'data_transfer_price' => 0];
    }
    
    // Get S3 Standard Storage price (per GB per month)
    public function getS3StandardStoragePrice($region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month FROM s3_standard_storage_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb_per_month' => floatval($row['price_per_gb_per_month'])
            ];
        }
        return [
            'price_per_gb_per_month' => 0.023 // Default
        ];
    }
    
    // Get S3 Data Transfer price (Outbound Internet - per GB)
    public function getS3DataTransferPrice($region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb FROM s3_data_transfer_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb' => floatval($row['price_per_gb'])
            ];
        }
        return [
            'price_per_gb' => 0.09 // Default
        ];
    }
    
    // Get S3 Glacier Deep Archive price (per GB per month)
    public function getS3GlacierPrice($region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month FROM s3_glacier_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb_per_month' => floatval($row['price_per_gb_per_month'])
            ];
        }
        return [
            'price_per_gb_per_month' => 0.00099 // Default
        ];
    }
    
    // Set S3 Standard Storage price
    public function setS3StandardStoragePrice($region, $price_per_gb_per_month) {
        $stmt = $this->conn->prepare("INSERT INTO s3_standard_storage_pricing (region, price_per_gb_per_month) VALUES (?, ?) ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?");
        $stmt->bind_param("sdd", $region, $price_per_gb_per_month, $price_per_gb_per_month);
        return $stmt->execute();
    }
    
    // Set S3 Data Transfer price
    public function setS3DataTransferPrice($region, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO s3_data_transfer_pricing (region, price_per_gb) VALUES (?, ?) ON DUPLICATE KEY UPDATE price_per_gb = ?");
        $stmt->bind_param("sdd", $region, $price_per_gb, $price_per_gb);
        return $stmt->execute();
    }
    
    // Set S3 Glacier price
    public function setS3GlacierPrice($region, $price_per_gb_per_month) {
        $stmt = $this->conn->prepare("INSERT INTO s3_glacier_pricing (region, price_per_gb_per_month) VALUES (?, ?) ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?");
        $stmt->bind_param("sdd", $region, $price_per_gb_per_month, $price_per_gb_per_month);
        return $stmt->execute();
    }
    
    // Get all S3 Standard Storage prices
    public function getAllS3StandardStoragePricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_standard_storage_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    // Get all S3 Data Transfer prices
    public function getAllS3DataTransferPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_data_transfer_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    // Get all S3 Glacier prices
    public function getAllS3GlacierPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_glacier_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function setS3Price($storage_class, $region, $price_per_gb, $request_price = 0, $data_transfer_price = 0) {
        $stmt = $this->conn->prepare("INSERT INTO s3_storage_pricing (storage_class, region, price_per_gb_per_month, request_price_per_1000, data_transfer_price_per_gb) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, request_price_per_1000 = ?, data_transfer_price_per_gb = ?");
        $stmt->bind_param("ssdddddd", $storage_class, $region, $price_per_gb, $request_price, $data_transfer_price, $price_per_gb, $request_price, $data_transfer_price);
        return $stmt->execute();
    }
    
    // RDS Pricing (Single AZ / Multi-AZ on instance; storage has one rate per type+region)
    public function getRDSInstancePrice($instance_type, $engine, $region, $multi_az = false) {
        $deployment_type = $multi_az ? 'multi_az' : 'single_az';
        $stmt = $this->conn->prepare("SELECT on_demand_price_per_hour FROM rds_instance_pricing WHERE instance_type = ? AND engine = ? AND region = ? AND deployment_type = ?");
        $stmt->bind_param("ssss", $instance_type, $engine, $region, $deployment_type);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return floatval($row['on_demand_price_per_hour']);
        }
        return 0;
    }
    
    public function getRDSInstanceDetails($instance_type, $engine, $region, $multi_az = false) {
        $deployment_type = $multi_az ? 'multi_az' : 'single_az';
        $stmt = $this->conn->prepare("SELECT vcpu, memory_gb, on_demand_price_per_hour FROM rds_instance_pricing WHERE instance_type = ? AND engine = ? AND region = ? AND deployment_type = ?");
        $stmt->bind_param("ssss", $instance_type, $engine, $region, $deployment_type);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'vcpu' => intval($row['vcpu']),
                'memory_gb' => floatval($row['memory_gb']),
                'price' => floatval($row['on_demand_price_per_hour'])
            ];
        }
        return null;
    }
    
    public function getRDSStoragePrice($storage_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month FROM rds_storage_pricing WHERE storage_type = ? AND region = ?");
        $stmt->bind_param("ss", $storage_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return ['price_per_gb' => floatval($row['price_per_gb_per_month'])];
        }
        return ['price_per_gb' => 0];
    }
    
    // VPC Pricing
    // VPC Site-to-Site VPN Pricing
    public function getVPCSiteToSiteVPNPrice($region, $unit) {
        $stmt = $this->conn->prepare("SELECT price_per_connection_per_unit FROM vpc_site_to_site_vpn_pricing WHERE region = ? AND unit = ?");
        $stmt->bind_param("ss", $region, $unit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return ['price_per_connection_per_unit' => floatval($row['price_per_connection_per_unit'])];
        }
        return ['price_per_connection_per_unit' => 0];
    }
    
    public function setVPCSiteToSiteVPNPrice($region, $unit, $price_per_connection_per_unit) {
        $stmt = $this->conn->prepare("INSERT INTO vpc_site_to_site_vpn_pricing (region, unit, price_per_connection_per_unit) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price_per_connection_per_unit = ?");
        $stmt->bind_param("ssdd", $region, $unit, $price_per_connection_per_unit, $price_per_connection_per_unit);
        return $stmt->execute();
    }
    
    // VPC Data Transfer Pricing
    public function getVPCDataTransferPrice($region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb FROM vpc_data_transfer_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return ['price_per_gb' => floatval($row['price_per_gb'])];
        }
        return ['price_per_gb' => 0];
    }
    
    public function setVPCDataTransferPrice($region, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO vpc_data_transfer_pricing (region, price_per_gb) VALUES (?, ?) ON DUPLICATE KEY UPDATE price_per_gb = ?");
        $stmt->bind_param("sdd", $region, $price_per_gb, $price_per_gb);
        return $stmt->execute();
    }
    
    // VPC Public IPv4 Address Pricing
    public function getVPCPublicIPv4Price($region) {
        $stmt = $this->conn->prepare("SELECT price_per_in_use_per_hour, price_per_idle_per_hour FROM vpc_public_ipv4_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_in_use_per_hour' => floatval($row['price_per_in_use_per_hour']),
                'price_per_idle_per_hour' => floatval($row['price_per_idle_per_hour'])
            ];
        }
        return ['price_per_in_use_per_hour' => 0, 'price_per_idle_per_hour' => 0];
    }
    
    public function setVPCPublicIPv4Price($region, $price_per_in_use_per_hour, $price_per_idle_per_hour) {
        $stmt = $this->conn->prepare("INSERT INTO vpc_public_ipv4_pricing (region, price_per_in_use_per_hour, price_per_idle_per_hour) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price_per_in_use_per_hour = ?, price_per_idle_per_hour = ?");
        $stmt->bind_param("sdddd", $region, $price_per_in_use_per_hour, $price_per_idle_per_hour, $price_per_in_use_per_hour, $price_per_idle_per_hour);
        return $stmt->execute();
    }
    
    // VPC NAT Gateway Pricing
    public function getVPCNATGatewayPrice($region) {
        $stmt = $this->conn->prepare("SELECT price_per_gateway_per_hour, price_per_gb FROM vpc_nat_gateway_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gateway_per_hour' => floatval($row['price_per_gateway_per_hour']),
                'price_per_gb' => floatval($row['price_per_gb'])
            ];
        }
        return ['price_per_gateway_per_hour' => 0, 'price_per_gb' => 0];
    }
    
    public function setVPCNATGatewayPrice($region, $price_per_gateway_per_hour, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO vpc_nat_gateway_pricing (region, price_per_gateway_per_hour, price_per_gb) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE price_per_gateway_per_hour = ?, price_per_gb = ?");
        $stmt->bind_param("sdddd", $region, $price_per_gateway_per_hour, $price_per_gb, $price_per_gateway_per_hour, $price_per_gb);
        return $stmt->execute();
    }
    
    // WAF Pricing
    public function getWAFPrice($pricing_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_unit, unit FROM waf_pricing WHERE pricing_type = ? AND region = ?");
        $stmt->bind_param("ss", $pricing_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return ['price' => floatval($row['price_per_unit']), 'unit' => $row['unit']];
        }
        // Return 0 if no price found for this region
        return ['price' => 0, 'unit' => 'month'];
    }
    
    // Load Balancer Pricing
    public function getLoadBalancerPrice($load_balancer_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_hour, price_per_gb FROM load_balancer_pricing WHERE load_balancer_type = ? AND region = ?");
        $stmt->bind_param("ss", $load_balancer_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_hour' => floatval($row['price_per_hour']),
                'price_per_gb' => floatval($row['price_per_gb'])
            ];
        }
        // Return 0 if no price found for this region
        return ['price_per_hour' => 0, 'price_per_gb' => 0];
    }
    
    // EKS Pricing
    public function getEKSPrice($pricing_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_hour FROM eks_pricing WHERE pricing_type = ? AND region = ?");
        $stmt->bind_param("ss", $pricing_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return floatval($row['price_per_hour']);
        }
        // Return 0 if no price found for this region
        return 0;
    }
    
    // ECR Pricing
    public function getECRPrice($pricing_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month, price_per_gb FROM ecr_pricing WHERE pricing_type = ? AND region = ?");
        $stmt->bind_param("ss", $pricing_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb_per_month' => floatval($row['price_per_gb_per_month']),
                'price_per_gb' => floatval($row['price_per_gb'])
            ];
        }
        // Return 0 if no price found for this region
        return ['price_per_gb_per_month' => 0, 'price_per_gb' => 0];
    }
    
    // Route53 Pricing
    public function getRoute53Price($pricing_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_unit, unit FROM route53_pricing WHERE pricing_type = ? AND region = ?");
        $stmt->bind_param("ss", $pricing_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return ['price' => floatval($row['price_per_unit']), 'unit' => $row['unit']];
        }
        // Return 0 if no price found for this region
        return ['price' => 0, 'unit' => 'month'];
    }
    
    // Get all pricing for a service (for admin management)
    public function getAllEC2Pricing($region) {
        $stmt = $this->conn->prepare("SELECT instance_type, os, vcpu, memory_gb, region, on_demand_price_per_hour FROM ec2_instance_pricing WHERE region = ? ORDER BY instance_type, os");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = [
                'instance_type' => $row['instance_type'],
                'os' => $row['os'] ?? 'linux',
                'vcpu' => intval($row['vcpu'] ?? 0),
                'memory_gb' => floatval($row['memory_gb'] ?? 0),
                'region' => $row['region'],
                'on_demand_price_per_hour' => floatval($row['on_demand_price_per_hour'] ?? 0)
            ];
        }
        return $prices;
    }
    
    public function getAllEBSPricing($region) {
        $stmt = $this->conn->prepare("SELECT volume_type, region, price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps
            FROM ebs_volume_pricing WHERE region = ? ORDER BY volume_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllS3Pricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_storage_pricing WHERE region = ? ORDER BY storage_class");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllRDSPricing($region) {
        $stmt = $this->conn->prepare("SELECT instance_type, engine, deployment_type, vcpu, memory_gb, region, on_demand_price_per_hour FROM rds_instance_pricing WHERE region = ? ORDER BY instance_type, engine, deployment_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = [
                'instance_type' => $row['instance_type'],
                'engine' => $row['engine'],
                'deployment_type' => $row['deployment_type'] ?? 'single_az',
                'vcpu' => intval($row['vcpu'] ?? 0),
                'memory_gb' => floatval($row['memory_gb'] ?? 0),
                'region' => $row['region'],
                'on_demand_price_per_hour' => floatval($row['on_demand_price_per_hour'] ?? 0)
            ];
        }
        return $prices;
    }
    
    public function getAllVPCPricing($region) {
        $prices = [];
        
        // Get Site-to-Site VPN pricing for all units
        $stmt = $this->conn->prepare("SELECT * FROM vpc_site_to_site_vpn_pricing WHERE region = ? ORDER BY unit");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $prices['site_to_site_vpn'][] = $row;
        }
        
        // Get Data Transfer pricing
        $stmt = $this->conn->prepare("SELECT * FROM vpc_data_transfer_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $prices['data_transfer'] = $row;
        }
        
        // Get Public IPv4 pricing
        $stmt = $this->conn->prepare("SELECT * FROM vpc_public_ipv4_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $prices['public_ipv4'] = $row;
        }
        
        // Get NAT Gateway pricing
        $stmt = $this->conn->prepare("SELECT * FROM vpc_nat_gateway_pricing WHERE region = ?");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $prices['nat_gateway'] = $row;
        }
        
        return $prices;
    }
    
    public function getAllWAFPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM waf_pricing WHERE region = ? ORDER BY pricing_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllLoadBalancerPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM load_balancer_pricing WHERE region = ? ORDER BY load_balancer_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllEKSPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM eks_pricing WHERE region = ? ORDER BY pricing_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllECRPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM ecr_pricing WHERE region = ? ORDER BY pricing_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    public function getAllRoute53Pricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM route53_pricing WHERE region = ? ORDER BY pricing_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
        return $prices;
    }
    
    // Setter methods for all services
    public function setRDSInstancePrice($instance_type, $engine, $region, $price, $vcpu = 0, $memory_gb = 0, $deployment_type = 'single_az') {
        $stmt = $this->conn->prepare("INSERT INTO rds_instance_pricing (instance_type, engine, region, deployment_type, vcpu, memory_gb, on_demand_price_per_hour) VALUES (?, ?, ?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE vcpu = ?, memory_gb = ?, on_demand_price_per_hour = ?");
        $stmt->bind_param("ssssiddidd", $instance_type, $engine, $region, $deployment_type, $vcpu, $memory_gb, $price, $vcpu, $memory_gb, $price);
        return $stmt->execute();
    }
    
    public function setRDSStoragePrice($storage_type, $region, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO rds_storage_pricing (storage_type, region, price_per_gb_per_month) VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?");
        $stmt->bind_param("ssdd", $storage_type, $region, $price_per_gb, $price_per_gb);
        return $stmt->execute();
    }
    
    // Legacy method - kept for backward compatibility
    public function setVPCPrice($service_name, $region, $price_per_hour, $price_per_gb, $unit = 'hour') {
        // This method is deprecated - use specific VPC pricing methods instead
        return true;
    }
    
    public function setWAFPrice($pricing_type, $region, $price, $unit = 'month') {
        $stmt = $this->conn->prepare("INSERT INTO waf_pricing (pricing_type, region, price_per_unit, unit) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_unit = ?, unit = ?");
        $stmt->bind_param("ssdsss", $pricing_type, $region, $price, $unit, $price, $unit);
        return $stmt->execute();
    }
    
    public function setLoadBalancerPrice($load_balancer_type, $region, $price_per_hour, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO load_balancer_pricing (load_balancer_type, region, price_per_hour, price_per_gb) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_hour = ?, price_per_gb = ?");
        $stmt->bind_param("ssdddd", $load_balancer_type, $region, $price_per_hour, $price_per_gb, $price_per_hour, $price_per_gb);
        return $stmt->execute();
    }
    
    public function setEKSPrice($pricing_type, $region, $price_per_hour) {
        $stmt = $this->conn->prepare("INSERT INTO eks_pricing (pricing_type, region, price_per_hour) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_hour = ?");
        $stmt->bind_param("ssdd", $pricing_type, $region, $price_per_hour, $price_per_hour);
        return $stmt->execute();
    }
    
    public function setECRPrice($pricing_type, $region, $price_per_gb_per_month, $price_per_gb) {
        $stmt = $this->conn->prepare("INSERT INTO ecr_pricing (pricing_type, region, price_per_gb_per_month, price_per_gb) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, price_per_gb = ?");
        $stmt->bind_param("ssdddd", $pricing_type, $region, $price_per_gb_per_month, $price_per_gb, $price_per_gb_per_month, $price_per_gb);
        return $stmt->execute();
    }
    
    public function setRoute53Price($pricing_type, $region, $price, $unit = 'month') {
        $stmt = $this->conn->prepare("INSERT INTO route53_pricing (pricing_type, region, price_per_unit, unit) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_unit = ?, unit = ?");
        $stmt->bind_param("ssdsss", $pricing_type, $region, $price, $unit, $price, $unit);
        return $stmt->execute();
    }
}



