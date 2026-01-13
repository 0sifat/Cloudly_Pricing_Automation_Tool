<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class ECRModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    public function saveConfig($project_id, $config) {
        // Get project region
        $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $project_region = $project['region'] ?? '';
        
        // Ensure region is set from project
        if (empty($config['region'])) {
            $config['region'] = $project_region;
        }
        
        // Delete existing config
        $stmt = $this->conn->prepare("DELETE FROM ecr_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        $unit_cost = $this->calculator->calculateECR($config);
        $total_cost = $unit_cost;
        
        $stmt = $this->conn->prepare("INSERT INTO ecr_configs (project_id, storage_gb, data_transfer_gb, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidd", $project_id, $config['storage_gb'], $config['data_transfer_gb'], $unit_cost, $total_cost);
        $stmt->execute();
        
        return ['success' => true];
    }
    
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM ecr_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
}






