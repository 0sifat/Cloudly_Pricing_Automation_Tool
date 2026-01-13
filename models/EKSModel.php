<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class EKSModel {
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
            if (empty($config['cluster_count']) || empty($config['node_group_count']) || empty($config['node_count'])) {
                return ['success' => false, 'error' => 'Cluster count, node group count, and node count are required'];
            }
            
            // Delete existing config
            $stmt = $this->conn->prepare("DELETE FROM eks_configs WHERE project_id = ?");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            $unit_cost = $this->calculator->calculateEKS($config);
            $total_cost = $unit_cost;
            
            // instance_type is optional now, use empty string if not provided
            $instance_type = $config['instance_type'] ?? '';
            $cluster_count = intval($config['cluster_count'] ?? 1);
            $node_group_count = intval($config['node_group_count'] ?? 1);
            $node_count = intval($config['node_count'] ?? 1);
            $region = $config['region'] ?? '';
            
            $stmt = $this->conn->prepare("INSERT INTO eks_configs (project_id, cluster_count, node_group_count, instance_type, node_count, region, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            // Parameters: project_id(i), cluster_count(i), node_group_count(i), instance_type(s), node_count(i), region(s), unit_cost(d), total_cost(d)
            // Type string: "iiisisd" = 8 characters (i-i-i-s-i-s-d-d)
            $type_string = "i" . "i" . "i" . "s" . "i" . "s" . "d" . "d";
            $stmt->bind_param($type_string, $project_id, $cluster_count, $node_group_count, $instance_type, $node_count, $region, $unit_cost, $total_cost);
            
            if (!$stmt->execute()) {
                return ['success' => false, 'error' => 'Database execute error: ' . $stmt->error];
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function getConfig($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM eks_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }
}
