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
    
    public function saveConfig($project_id, $config) {
        // Delete existing config
        $stmt = $this->conn->prepare("DELETE FROM vpc_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        $unit_cost = $this->calculator->calculateVPC($config);
        $total_cost = $unit_cost;
        
        $stmt = $this->conn->prepare("INSERT INTO vpc_configs (project_id, region, vpc_count, availability_zones, nat_gateway_count, vpc_endpoint_count, data_transfer_gb, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiiiiidd", $project_id, $config['region'], $config['vpc_count'], $config['availability_zones'], $config['nat_gateway_count'], $config['vpc_endpoint_count'], $config['data_transfer_gb'], $unit_cost, $total_cost);
        $stmt->execute();
        
        return ['success' => true];
    }
    
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM vpc_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
}






