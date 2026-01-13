<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class RDSModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    // Updated to support multiple RDS instances like EC2
    public function saveConfig($project_id, $instances) {
        try {
            // If single config object (backward compatibility), convert to array
            if (!isset($instances[0]) && isset($instances['engine'])) {
                $instances = [$instances];
            }
            
            // Delete existing configs
            $stmt = $this->conn->prepare("DELETE FROM rds_configs WHERE project_id = ?");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            // Get project region
            $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $project = $result->fetch_assoc();
            $project_region = $project['region'] ?? '';
            
            foreach ($instances as $config) {
                // Validate required fields
                if (empty($config['engine']) || empty($config['instance_type'])) {
                    continue; // Skip invalid configs
                }
                
                // Ensure region is set from project
                if (empty($config['region'])) {
                    $config['region'] = $project_region;
                }
                
                if (empty($config['region'])) {
                    continue; // Skip if still no region
                }
                
                $unit_cost = $this->calculator->calculateRDS($config);
                $total_cost = $unit_cost * ($config['quantity'] ?? 1);
                
                $vcpu = intval($config['vcpu'] ?? 0);
                $memory_gb = floatval($config['memory_gb'] ?? 0);
                $storage_gb = intval($config['storage_gb'] ?? 20);
                $storage_type = $config['storage_type'] ?? 'gp2';
                $backup_retention = intval($config['backup_retention'] ?? 7);
                
                $stmt = $this->conn->prepare("INSERT INTO rds_configs (project_id, engine, instance_type, quantity, storage_gb, storage_type, multi_az, backup_retention, region, vcpu, memory_gb, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
                }
                
                $multi_az = isset($config['multi_az']) && ($config['multi_az'] === true || $config['multi_az'] == 1 || $config['multi_az'] === '1') ? 1 : 0;
                $quantity = intval($config['quantity'] ?? 1);
                
                // Ensure all values are properly set
                $engine = $config['engine'];
                $instance_type = $config['instance_type'];
                $region = $config['region'];
                
                // Type string for 13 parameters: i=project_id, s=engine, s=instance_type, i=quantity, i=storage_gb, s=storage_type, i=multi_az, i=backup_retention, s=region, i=vcpu, d=memory_gb, d=unit_cost, d=total_cost
                // Correct type string: "issiisiiisidd" (13 characters matching 13 parameters)
                $stmt->bind_param("issiisiiisidd", $project_id, $engine, $instance_type, $quantity, $storage_gb, $storage_type, $multi_az, $backup_retention, $region, $vcpu, $memory_gb, $unit_cost, $total_cost);
                
                if (!$stmt->execute()) {
                    return ['success' => false, 'error' => 'Database execute error: ' . $stmt->error];
                }
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM rds_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $row['multi_az'] = (bool)$row['multi_az'];
            $configs[] = $row;
        }
        // Return array of configs (backward compatible: return first if only one)
        return count($configs) === 1 ? $configs[0] : $configs;
    }
    
    public function getAllConfigs($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM rds_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $row['multi_az'] = (bool)$row['multi_az'];
            $configs[] = $row;
        }
        return $configs;
    }
}





