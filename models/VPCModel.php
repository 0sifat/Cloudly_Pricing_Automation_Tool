<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class VPCModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    // Save multiple VPC services (site_to_site_vpn, data_transfer, public_ipv4, nat_gateway)
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
        $stmt = $this->conn->prepare("DELETE FROM vpc_configs WHERE project_id = ?");
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
            $unit_cost = $this->calculator->calculateVPC($service);
            $total_cost = $unit_cost;
            
            // Extract fields based on service type
            $vpn_connections = intval($service['vpn_connections'] ?? 0);
            $vpn_duration = floatval($service['vpn_duration'] ?? 24.00);
            $vpn_duration_unit = $service['vpn_duration_unit'] ?? 'hours_per_day';
            $inbound_amount = floatval($service['inbound_amount'] ?? 0);
            $inbound_unit = $service['inbound_unit'] ?? 'GB';
            $data_transfer_amount = floatval($service['data_transfer_amount'] ?? 0);
            $data_transfer_unit = $service['data_transfer_unit'] ?? 'GB';
            $in_use_ipv4_count = intval($service['in_use_ipv4_count'] ?? 0);
            $idle_ipv4_count = intval($service['idle_ipv4_count'] ?? 0);
            $nat_gateway_count = intval($service['nat_gateway_count'] ?? 0);
            $nat_data_processed = floatval($service['nat_data_processed'] ?? 0);
            $nat_data_unit = $service['nat_data_unit'] ?? 'GB';
            
            $stmt = $this->conn->prepare("INSERT INTO vpc_configs (project_id, service_type, region, vpn_connections, vpn_duration, vpn_duration_unit, inbound_amount, inbound_unit, data_transfer_amount, data_transfer_unit, in_use_ipv4_count, idle_ipv4_count, nat_gateway_count, nat_data_processed, nat_data_unit, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issidsdsdiiiidsdd", 
                $project_id, 
                $service['service_type'],
                $service['region'],
                $vpn_connections,
                $vpn_duration,
                $vpn_duration_unit,
                $inbound_amount,
                $inbound_unit,
                $data_transfer_amount,
                $data_transfer_unit,
                $in_use_ipv4_count,
                $idle_ipv4_count,
                $nat_gateway_count,
                $nat_data_processed,
                $nat_data_unit,
                $unit_cost,
                $total_cost
            );
            $stmt->execute();
        }
        
        return ['success' => true];
    }
    
    // Get all VPC services for a project
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM vpc_configs WHERE project_id = ?");
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
        $stmt = $this->conn->prepare("SELECT * FROM vpc_configs WHERE project_id = ? ORDER BY id ASC");
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
