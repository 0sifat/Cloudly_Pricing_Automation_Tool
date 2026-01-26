<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class S3Model {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    // Save multiple S3 services (standard_storage, data_transfer, glacier)
    public function saveConfig($project_id, $services) {
        // Get project region
        $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $project_region = $project['region'] ?? '';
        
        // If single config (backward compatibility), convert to array
        if (!isset($services[0]) && isset($services['service_type'])) {
            $services = [$services];
        }
        
        // Delete existing configs
        $stmt = $this->conn->prepare("DELETE FROM s3_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        foreach ($services as $service) {
            if (empty($service['service_type'])) {
                continue; // Skip invalid services
            }
            
            // Ensure region is set from project
            if (empty($service['region'])) {
                $service['region'] = $project_region;
            }
            
            // Calculate cost for this service
            $unit_cost = $this->calculator->calculateS3($service);
            $total_cost = $unit_cost;
            
            $storage_amount = floatval($service['storage_amount'] ?? 0);
            $storage_unit = $service['storage_unit'] ?? 'GB';
            $inbound_amount = floatval($service['inbound_amount'] ?? 0);
            $inbound_unit = $service['inbound_unit'] ?? 'GB';
            $data_transfer_amount = floatval($service['data_transfer_amount'] ?? 0);
            $data_transfer_unit = $service['data_transfer_unit'] ?? 'GB';
            
            $stmt = $this->conn->prepare("INSERT INTO s3_configs (project_id, service_type, storage_amount, storage_unit, inbound_amount, inbound_unit, data_transfer_amount, data_transfer_unit, unit_cost, total_cost, region) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsdsdsdds", 
                $project_id, 
                $service['service_type'],
                $storage_amount,
                $storage_unit,
                $inbound_amount,
                $inbound_unit,
                $data_transfer_amount,
                $data_transfer_unit,
                $unit_cost,
                $total_cost,
                $service['region']
            );
            $stmt->execute();
        }
        
        return ['success' => true];
    }
    
    // Get all S3 services for a project
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }
        // Return array of configs (backward compatible: return first if only one, or null if empty)
        if (count($configs) === 0) {
            return null;
        } elseif (count($configs) === 1) {
            return $configs[0];
        } else {
            return $configs;
        }
    }
    
    // Get all configs as array (always returns array)
    public function getAllConfigs($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM s3_configs WHERE project_id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }
        return $configs; // Always returns array, even if empty
    }
}






