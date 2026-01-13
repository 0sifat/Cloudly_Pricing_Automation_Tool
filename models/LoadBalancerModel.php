<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class LoadBalancerModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    public function saveConfig($project_id, $config) {
        try {
            // Get project region
            $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $project = $result->fetch_assoc();
            $project_region = $project['region'] ?? '';
            
            // Ensure region is set from project
            if (empty($config['region'])) {
                $config['region'] = $project_region;
            }
            
            // Validate required fields
            if (empty($config['load_balancer_type'])) {
                return ['success' => false, 'error' => 'Load balancer type is required'];
            }
            
            // Delete existing config
            $stmt = $this->conn->prepare("DELETE FROM load_balancer_configs WHERE project_id = ?");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            $unit_cost = $this->calculator->calculateLoadBalancer($config);
            $total_cost = $unit_cost;
            
            $load_balancer_type = $config['load_balancer_type'] ?? 'application';
            $quantity = intval($config['quantity'] ?? 1);
            $region = $config['region'] ?? '';
            $data_processed_gb = intval($config['data_processed_gb'] ?? 0);
            
            $stmt = $this->conn->prepare("INSERT INTO load_balancer_configs (project_id, load_balancer_type, quantity, region, data_processed_gb, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            // Parameters: project_id(i), load_balancer_type(s), quantity(i), region(s), data_processed_gb(i), unit_cost(d), total_cost(d)
            // Type string: "isisidd" = 7 characters (i-s-i-s-i-d-d)
            $stmt->bind_param("isisidd", $project_id, $load_balancer_type, $quantity, $region, $data_processed_gb, $unit_cost, $total_cost);
            
            if (!$stmt->execute()) {
                return ['success' => false, 'error' => 'Database execute error: ' . $stmt->error];
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM load_balancer_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
}






