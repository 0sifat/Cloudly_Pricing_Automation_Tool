<?php
require_once __DIR__ . '/../config/database.php';

class PricingModel {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    // EC2 Pricing
    public function getEC2Price($instance_type, $region) {
        $stmt = $this->conn->prepare("SELECT on_demand_price_per_hour FROM ec2_instance_pricing WHERE instance_type = ? AND region = ?");
        $stmt->bind_param("ss", $instance_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return floatval($row['on_demand_price_per_hour']);
        }
        // Return 0 if no price found for this region
        return 0;
    }
    
    public function getEC2InstanceDetails($instance_type, $region) {
        $stmt = $this->conn->prepare("SELECT vcpu, memory_gb, on_demand_price_per_hour FROM ec2_instance_pricing WHERE instance_type = ? AND region = ?");
        $stmt->bind_param("ss", $instance_type, $region);
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
    
    public function setEC2Price($instance_type, $region, $price, $vcpu = 0, $memory_gb = 0) {
        $stmt = $this->conn->prepare("INSERT INTO ec2_instance_pricing (instance_type, region, vcpu, memory_gb, on_demand_price_per_hour) VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE vcpu = ?, memory_gb = ?, on_demand_price_per_hour = ?");
        $stmt->bind_param("ssiddidd", $instance_type, $region, $vcpu, $memory_gb, $price, $vcpu, $memory_gb, $price);
        return $stmt->execute();
    }
    
    // EBS Pricing
    public function getEBSPrice($volume_type, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps, 
            snapshot_price_per_gb_hourly, snapshot_price_per_gb_daily, snapshot_price_per_gb_2x_daily,
            snapshot_price_per_gb_3x_daily, snapshot_price_per_gb_4x_daily, snapshot_price_per_gb_6x_daily,
            snapshot_price_per_gb_weekly, snapshot_price_per_gb_monthly
            FROM ebs_volume_pricing WHERE volume_type = ? AND region = ?");
        $stmt->bind_param("ss", $volume_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb' => floatval($row['price_per_gb_per_month']),
                'iops_price' => floatval($row['iops_price_per_iops']),
                'throughput_price' => floatval($row['throughput_price_per_mbps']),
                'snapshot_hourly' => floatval($row['snapshot_price_per_gb_hourly'] ?? 0),
                'snapshot_daily' => floatval($row['snapshot_price_per_gb_daily'] ?? 0),
                'snapshot_2x_daily' => floatval($row['snapshot_price_per_gb_2x_daily'] ?? 0),
                'snapshot_3x_daily' => floatval($row['snapshot_price_per_gb_3x_daily'] ?? 0),
                'snapshot_4x_daily' => floatval($row['snapshot_price_per_gb_4x_daily'] ?? 0),
                'snapshot_6x_daily' => floatval($row['snapshot_price_per_gb_6x_daily'] ?? 0),
                'snapshot_weekly' => floatval($row['snapshot_price_per_gb_weekly'] ?? 0),
                'snapshot_monthly' => floatval($row['snapshot_price_per_gb_monthly'] ?? 0)
            ];
        }
        // Return 0 if no price found for this region
        return [
            'price_per_gb' => 0, 'iops_price' => 0, 'throughput_price' => 0,
            'snapshot_hourly' => 0, 'snapshot_daily' => 0, 'snapshot_2x_daily' => 0,
            'snapshot_3x_daily' => 0, 'snapshot_4x_daily' => 0, 'snapshot_6x_daily' => 0,
            'snapshot_weekly' => 0, 'snapshot_monthly' => 0
        ];
    }
    
    public function setEBSPrice($volume_type, $region, $price_per_gb, $iops_price = 0, $throughput_price = 0, 
        $snapshot_hourly = 0, $snapshot_daily = 0, $snapshot_2x_daily = 0, $snapshot_3x_daily = 0, 
        $snapshot_4x_daily = 0, $snapshot_6x_daily = 0, $snapshot_weekly = 0, $snapshot_monthly = 0) {
        $stmt = $this->conn->prepare("INSERT INTO ebs_volume_pricing (volume_type, region, price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps,
            snapshot_price_per_gb_hourly, snapshot_price_per_gb_daily, snapshot_price_per_gb_2x_daily,
            snapshot_price_per_gb_3x_daily, snapshot_price_per_gb_4x_daily, snapshot_price_per_gb_6x_daily,
            snapshot_price_per_gb_weekly, snapshot_price_per_gb_monthly) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, iops_price_per_iops = ?, throughput_price_per_mbps = ?,
            snapshot_price_per_gb_hourly = ?, snapshot_price_per_gb_daily = ?, snapshot_price_per_gb_2x_daily = ?,
            snapshot_price_per_gb_3x_daily = ?, snapshot_price_per_gb_4x_daily = ?, snapshot_price_per_gb_6x_daily = ?,
            snapshot_price_per_gb_weekly = ?, snapshot_price_per_gb_monthly = ?");
        // INSERT: 13 placeholders (volume_type, region, and 11 price fields)
        // UPDATE: 11 placeholders (only price fields, not volume_type/region)
        // Total: 24 placeholders = 2 strings + 22 doubles
        $stmt->bind_param("ssdddddddddddddddddddddd", 
            $volume_type, $region, $price_per_gb, $iops_price, $throughput_price,
            $snapshot_hourly, $snapshot_daily, $snapshot_2x_daily, $snapshot_3x_daily,
            $snapshot_4x_daily, $snapshot_6x_daily, $snapshot_weekly, $snapshot_monthly,
            $price_per_gb, $iops_price, $throughput_price,
            $snapshot_hourly, $snapshot_daily, $snapshot_2x_daily, $snapshot_3x_daily,
            $snapshot_4x_daily, $snapshot_6x_daily, $snapshot_weekly, $snapshot_monthly);
        return $stmt->execute();
    }
    
    // S3 Pricing
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
    
    public function setS3Price($storage_class, $region, $price_per_gb, $request_price = 0, $data_transfer_price = 0) {
        $stmt = $this->conn->prepare("INSERT INTO s3_storage_pricing (storage_class, region, price_per_gb_per_month, request_price_per_1000, data_transfer_price_per_gb) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, request_price_per_1000 = ?, data_transfer_price_per_gb = ?");
        $stmt->bind_param("ssdddddd", $storage_class, $region, $price_per_gb, $request_price, $data_transfer_price, $price_per_gb, $request_price, $data_transfer_price);
        return $stmt->execute();
    }
    
    // RDS Pricing
    public function getRDSInstancePrice($instance_type, $engine, $region) {
        $stmt = $this->conn->prepare("SELECT on_demand_price_per_hour FROM rds_instance_pricing WHERE instance_type = ? AND engine = ? AND region = ?");
        $stmt->bind_param("sss", $instance_type, $engine, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return floatval($row['on_demand_price_per_hour']);
        }
        // Return 0 if no price found for this region
        return 0;
    }
    
    public function getRDSInstanceDetails($instance_type, $engine, $region) {
        $stmt = $this->conn->prepare("SELECT vcpu, memory_gb, on_demand_price_per_hour FROM rds_instance_pricing WHERE instance_type = ? AND engine = ? AND region = ?");
        $stmt->bind_param("sss", $instance_type, $engine, $region);
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
        $stmt = $this->conn->prepare("SELECT price_per_gb_per_month, iops_price_per_iops FROM rds_storage_pricing WHERE storage_type = ? AND region = ?");
        $stmt->bind_param("ss", $storage_type, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_gb' => floatval($row['price_per_gb_per_month']),
                'iops_price' => floatval($row['iops_price_per_iops'])
            ];
        }
        // Return 0 if no price found for this region
        return ['price_per_gb' => 0, 'iops_price' => 0];
    }
    
    // VPC Pricing
    public function getVPCPrice($service_name, $region) {
        $stmt = $this->conn->prepare("SELECT price_per_hour, price_per_gb, unit FROM vpc_pricing WHERE service_name = ? AND region = ?");
        $stmt->bind_param("ss", $service_name, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return [
                'price_per_hour' => floatval($row['price_per_hour']),
                'price_per_gb' => floatval($row['price_per_gb']),
                'unit' => $row['unit']
            ];
        }
        // Return 0 if no price found for this region
        return ['price_per_hour' => 0, 'price_per_gb' => 0, 'unit' => 'hour'];
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
        $stmt = $this->conn->prepare("SELECT instance_type, vcpu, memory_gb, region, on_demand_price_per_hour FROM ec2_instance_pricing WHERE region = ? ORDER BY instance_type");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = [
                'instance_type' => $row['instance_type'],
                'vcpu' => intval($row['vcpu'] ?? 0),
                'memory_gb' => floatval($row['memory_gb'] ?? 0),
                'region' => $row['region'],
                'on_demand_price_per_hour' => floatval($row['on_demand_price_per_hour'] ?? 0)
            ];
        }
        return $prices;
    }
    
    public function getAllEBSPricing($region) {
        $stmt = $this->conn->prepare("SELECT volume_type, region, price_per_gb_per_month, iops_price_per_iops, throughput_price_per_mbps,
            snapshot_price_per_gb_hourly, snapshot_price_per_gb_daily, snapshot_price_per_gb_2x_daily,
            snapshot_price_per_gb_3x_daily, snapshot_price_per_gb_4x_daily, snapshot_price_per_gb_6x_daily,
            snapshot_price_per_gb_weekly, snapshot_price_per_gb_monthly
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
        $stmt = $this->conn->prepare("SELECT instance_type, engine, vcpu, memory_gb, region, on_demand_price_per_hour FROM rds_instance_pricing WHERE region = ? ORDER BY instance_type, engine");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = [
                'instance_type' => $row['instance_type'],
                'engine' => $row['engine'],
                'vcpu' => intval($row['vcpu'] ?? 0),
                'memory_gb' => floatval($row['memory_gb'] ?? 0),
                'region' => $row['region'],
                'on_demand_price_per_hour' => floatval($row['on_demand_price_per_hour'] ?? 0)
            ];
        }
        return $prices;
    }
    
    public function getAllVPCPricing($region) {
        $stmt = $this->conn->prepare("SELECT * FROM vpc_pricing WHERE region = ? ORDER BY service_name");
        $stmt->bind_param("s", $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
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
    public function setRDSInstancePrice($instance_type, $engine, $region, $price, $vcpu = 0, $memory_gb = 0) {
        $stmt = $this->conn->prepare("INSERT INTO rds_instance_pricing (instance_type, engine, region, vcpu, memory_gb, on_demand_price_per_hour) VALUES (?, ?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE vcpu = ?, memory_gb = ?, on_demand_price_per_hour = ?");
        $stmt->bind_param("sssiddidd", $instance_type, $engine, $region, $vcpu, $memory_gb, $price, $vcpu, $memory_gb, $price);
        return $stmt->execute();
    }
    
    public function setRDSStoragePrice($storage_type, $region, $price_per_gb, $iops_price = 0) {
        $stmt = $this->conn->prepare("INSERT INTO rds_storage_pricing (storage_type, region, price_per_gb_per_month, iops_price_per_iops) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_gb_per_month = ?, iops_price_per_iops = ?");
        $stmt->bind_param("ssdddd", $storage_type, $region, $price_per_gb, $iops_price, $price_per_gb, $iops_price);
        return $stmt->execute();
    }
    
    public function setVPCPrice($service_name, $region, $price_per_hour, $price_per_gb, $unit = 'hour') {
        $stmt = $this->conn->prepare("INSERT INTO vpc_pricing (service_name, region, price_per_hour, price_per_gb, unit) 
                                      VALUES (?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE price_per_hour = ?, price_per_gb = ?, unit = ?");
        $stmt->bind_param("ssddssdd", $service_name, $region, $price_per_hour, $price_per_gb, $unit, $price_per_hour, $price_per_gb, $unit);
        return $stmt->execute();
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



