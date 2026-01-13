<?php
require_once __DIR__ . '/../config/database.php';

class Project {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function createProject($user_id, $project_name, $salesman_name, $region) {
        $stmt = $this->conn->prepare("INSERT INTO projects (user_id, project_name, salesman_name, region) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $project_name, $salesman_name, $region);
        
        if ($stmt->execute()) {
            $project_id = $this->conn->insert_id;
            // Initialize project totals
            $stmt2 = $this->conn->prepare("INSERT INTO project_totals (project_id) VALUES (?)");
            $stmt2->bind_param("i", $project_id);
            $stmt2->execute();
            return ['success' => true, 'project_id' => $project_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function getProject($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function updateProject($project_id, $project_name, $salesman_name, $region = null) {
        if ($region !== null) {
            $stmt = $this->conn->prepare("UPDATE projects SET project_name = ?, salesman_name = ?, region = ? WHERE id = ?");
            $stmt->bind_param("sssi", $project_name, $salesman_name, $region, $project_id);
        } else {
            $stmt = $this->conn->prepare("UPDATE projects SET project_name = ?, salesman_name = ? WHERE id = ?");
            $stmt->bind_param("ssi", $project_name, $salesman_name, $project_id);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'project_id' => $project_id];
        } else {
            return ['success' => false, 'error' => $stmt->error];
        }
    }
    
    public function updateProjectTotals($project_id) {
        // Calculate total costs from all services
        $total_unit = 0;
        $total_estimated = 0;
        
        // EC2 - use pricing model costs if available, otherwise base cost (both are now monthly)
        $stmt = $this->conn->prepare("SELECT id, unit_cost, quantity FROM ec2_instances WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Check if pricing models exist
            $stmt2 = $this->conn->prepare("SELECT SUM(total_cost) as pm_total FROM ec2_pricing_models WHERE ec2_instance_id = ?");
            $stmt2->bind_param("i", $row['id']);
            $stmt2->execute();
            $pm_result = $stmt2->get_result();
            $pm_row = $pm_result->fetch_assoc();
            
            if ($pm_row && $pm_row['pm_total'] > 0) {
                // Use pricing model costs (already monthly)
                $total_estimated += $pm_row['pm_total'];
            } else {
                // Use base instance cost (already monthly)
                $total_estimated += $row['unit_cost'] * $row['quantity'];
            }
        }
        
        // EBS
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // VPC
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM vpc_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // S3
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM s3_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // RDS
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM rds_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // EKS
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM eks_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // ECR
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM ecr_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // Load Balancer
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM load_balancer_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // WAF
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM waf_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // Route53
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM route53_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_estimated += $row['total'] ?? 0;
        
        // Update project totals
        $stmt = $this->conn->prepare("UPDATE project_totals SET total_unit_cost = ?, total_estimated_cost = ? WHERE project_id = ?");
        $stmt->bind_param("ddi", $total_unit, $total_estimated, $project_id);
        $stmt->execute();
        
        return ['total_unit_cost' => $total_unit, 'total_estimated_cost' => $total_estimated];
    }
    
    public function getAllProjects() {
        $stmt = $this->conn->prepare("SELECT * FROM projects ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        return $projects;
    }
}

?>

