<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PricingCalculator.php';

class EBSModel {
    private $conn;
    private $calculator;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->calculator = new PricingCalculator();
    }
    
    public function saveVolumes($project_id, $volumes) {
        // Get project region
        $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $project_region = $project['region'] ?? '';
        
        // Delete existing volumes
        $stmt = $this->conn->prepare("DELETE FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        foreach ($volumes as $volume) {
            // Ensure region is set from project
            if (empty($volume['region'])) {
                $volume['region'] = $project_region;
            }
            
            // Calculate instance months: 730 hours / 730 hours = 1.00 instance months (monthly calculation)
            // Since all EC2 costs are already monthly, we use 1.0 for instance_months
            $volume['ec2_instance_hours'] = 730; // 730 hours = 1 month
            
            // Calculate costs using the updated calculator
            $costs = $this->calculator->calculateEBSWithSnapshot($volume);
            $storage_cost = $costs['storage_cost'];
            $snapshot_cost = $costs['snapshot_cost'];
            $unit_cost = $costs['storage_cost']; // Per unit storage cost
            $total_cost = $costs['total_cost'];
            
            // For gp2, set IOPS and throughput to 0 (not applicable)
            $iops = $volume['volume_type'] === 'gp2' ? 0 : intval($volume['iops'] ?? 0);
            $throughput = $volume['volume_type'] === 'gp2' ? 0 : intval($volume['throughput'] ?? 0);
            
            $stmt = $this->conn->prepare("INSERT INTO ebs_volumes (project_id, ec2_instance_id, server_type, server_name, volume_type, size_gb, iops, throughput, snapshot_frequency, snapshot_storage_gb, storage_cost, snapshot_cost, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ec2_id = $volume['ec2_instance_id'] ?? null;
            $snapshot_frequency = $volume['snapshot_frequency'] ?? 'none';
            $snapshot_storage_gb = intval($volume['snapshot_storage_gb'] ?? 0);
            $size_gb = intval($volume['size_gb'] ?? 0);
            $server_type = $volume['server_type'] ?? 'app_server';
            $server_name = $volume['server_name'] ?? '';
            $volume_type = $volume['volume_type'] ?? 'gp2';
            // Type string: i=int, s=string, d=double
            // project_id(i), ec2_id(i), server_type(s), server_name(s), volume_type(s), size_gb(i), iops(i), throughput(i), snapshot_frequency(s), snapshot_storage_gb(i), storage_cost(d), snapshot_cost(d), unit_cost(d), total_cost(d)
            $stmt->bind_param("iisssiiisidddd", 
                $project_id, 
                $ec2_id, 
                $server_type, 
                $server_name, 
                $volume_type, 
                $size_gb, 
                $iops, 
                $throughput,
                $snapshot_frequency,
                $snapshot_storage_gb,
                $storage_cost,
                $snapshot_cost,
                $unit_cost, 
                $total_cost
            );
            $stmt->execute();
        }
        
        return ['success' => true];
    }
    
    public function getVolumes($project_id) {
        // Get project region first
        $stmt = $this->conn->prepare("SELECT region FROM projects WHERE id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $project_region = $project['region'] ?? '';
        
        $stmt = $this->conn->prepare("SELECT * FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $volumes = [];
        
        while ($row = $result->fetch_assoc()) {
            $volumes[] = [
                'id' => $row['id'],
                'ec2_instance_id' => $row['ec2_instance_id'],
                'server_type' => $row['server_type'],
                'server_name' => $row['server_name'],
                'volume_type' => $row['volume_type'],
                'size_gb' => $row['size_gb'],
                'iops' => $row['iops'],
                'throughput' => $row['throughput'],
                'snapshot_frequency' => $row['snapshot_frequency'] ?? 'none',
                'snapshot_storage_gb' => $row['snapshot_storage_gb'] ?? 0,
                'storage_cost' => floatval($row['storage_cost'] ?? 0),
                'snapshot_cost' => floatval($row['snapshot_cost'] ?? 0),
                'unit_cost' => floatval($row['unit_cost']),
                'total_cost' => floatval($row['total_cost']),
                'region' => $project_region // Add region from project
            ];
        }
        
        return $volumes;
    }
    
    public function getEC2InstancesForProject($project_id) {
        $stmt = $this->conn->prepare("SELECT id, instance_type, quantity FROM ec2_instances WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $instances = [];
        
        while ($row = $result->fetch_assoc()) {
            $instances[] = [
                'id' => $row['id'],
                'label' => $row['instance_type'] . ' (x' . $row['quantity'] . ')'
            ];
        }
        
        return $instances;
    }
}

