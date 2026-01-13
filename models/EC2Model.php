<?php
require_once __DIR__ . '/../config/database.php';

class EC2Model {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function saveInstances($project_id, $instances) {
        try {
            // Delete existing instances for this project
            $stmt = $this->conn->prepare("DELETE FROM ec2_pricing_models WHERE ec2_instance_id IN (SELECT id FROM ec2_instances WHERE project_id = ?)");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            $stmt = $this->conn->prepare("DELETE FROM ec2_instances WHERE project_id = ?");
            if (!$stmt) {
                return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
            }
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            foreach ($instances as $instance) {
                // Validate required fields
                if (empty($instance['instance_type']) || empty($instance['region']) || empty($instance['operating_system'])) {
                    continue; // Skip invalid instances
                }
                
                // Insert EC2 instance
                $vcpu = intval($instance['vcpu'] ?? 0);
                $memory_gb = floatval($instance['memory_gb'] ?? 0);
                $quantity = intval($instance['quantity'] ?? 1);
                $stmt = $this->conn->prepare("INSERT INTO ec2_instances (project_id, instance_type, quantity, operating_system, region, vcpu, memory_gb, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    return ['success' => false, 'error' => 'Database prepare error: ' . $this->conn->error];
                }
                $unit_cost_hourly = $this->calculateUnitCost($instance);
                // Convert hourly to monthly (730 hours per month)
                $unit_cost_monthly = $unit_cost_hourly * 730;
                $total_cost = $unit_cost_monthly * $quantity;
                // Parameters: project_id(i), instance_type(s), quantity(i), operating_system(s), region(s), vcpu(i), memory_gb(d), unit_cost_monthly(d), total_cost(d)
                // Type string: "isissiddd" = 9 characters (i-s-i-s-s-i-d-d-d)
                // Verify all variables are set
                $instance_type = $instance['instance_type'] ?? '';
                $operating_system = $instance['operating_system'] ?? '';
                $region = $instance['region'] ?? '';
                // Construct type string explicitly to ensure 9 characters
                // Parameters: project_id(i), instance_type(s), quantity(i), operating_system(s), region(s), vcpu(i), memory_gb(d), unit_cost_monthly(d), total_cost(d)
                $type_string = "i" . "s" . "i" . "s" . "s" . "i" . "d" . "d" . "d";
                if (strlen($type_string) !== 9) {
                    return ['success' => false, 'error' => 'Type string length mismatch: expected 9, got ' . strlen($type_string)];
                }
                $stmt->bind_param($type_string, $project_id, $instance_type, $quantity, $operating_system, $region, $vcpu, $memory_gb, $unit_cost_monthly, $total_cost);
                if (!$stmt->execute()) {
                    return ['success' => false, 'error' => 'Database execute error: ' . $stmt->error];
                }
                $ec2_instance_id = $this->conn->insert_id;
                
                // Insert pricing models
                $pricing_models = $instance['pricing_models'] ?? [];
                if (!empty($pricing_models) && is_array($pricing_models)) {
                    foreach ($pricing_models as $pm) {
                        if (empty($pm['model'])) {
                            continue; // Skip invalid pricing models
                        }
                        $pm_cost_hourly = $this->calculatePricingModelCost($instance, $pm);
                        // Convert hourly to monthly (730 hours per month)
                        $pm_cost_monthly = $pm_cost_hourly * 730;
                        $spot_discount = $pm['spot_discount'] ?? '70';
                        $reservation_term = intval($pm['reservation_term'] ?? 0);
                        $payment_option = $pm['payment_option'] ?? 'no_upfront';
                        $utilization = $pm['utilization'] ?? '100';
                        $stmt2 = $this->conn->prepare("INSERT INTO ec2_pricing_models (ec2_instance_id, pricing_model, reservation_term, payment_option, utilization, spot_discount, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt2) {
                            continue; // Skip if prepare fails
                        }
                        $pm_total = $pm_cost_monthly * $quantity;
                        // Parameters: ec2_instance_id(i), pricing_model(s), reservation_term(i), payment_option(s), utilization(s), spot_discount(s), unit_cost(d), total_cost(d)
                        // Type string: "isissssdd" = 8 characters (i-s-i-s-s-s-d-d)
                        $pricing_model = $pm['model'] ?? '';
                        $type_string_pm = "i" . "s" . "i" . "s" . "s" . "s" . "d" . "d";
                        if (strlen($type_string_pm) !== 8) {
                            continue; // Skip if type string is wrong
                        }
                        $stmt2->bind_param($type_string_pm, $ec2_instance_id, $pricing_model, $reservation_term, $payment_option, $utilization, $spot_discount, $pm_cost_monthly, $pm_total);
                        if (!$stmt2->execute()) {
                            // Log error but continue with other pricing models
                            error_log("Error inserting pricing model: " . $stmt2->error);
                            continue;
                        }
                    }
                }
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error saving instances: ' . $e->getMessage()];
        }
    }
    
    public function getInstances($project_id) {
        $stmt = $this->conn->prepare("SELECT * FROM ec2_instances WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $instances = [];
        
        while ($row = $result->fetch_assoc()) {
            $stmt2 = $this->conn->prepare("SELECT * FROM ec2_pricing_models WHERE ec2_instance_id = ?");
            $stmt2->bind_param("i", $row['id']);
            $stmt2->execute();
            $pm_result = $stmt2->get_result();
            $pricing_models = [];
            while ($pm = $pm_result->fetch_assoc()) {
                $pricing_models[] = [
                    'model' => $pm['pricing_model'],
                    'reservation_term' => $pm['reservation_term'],
                    'payment_option' => $pm['payment_option'],
                    'utilization' => $pm['utilization'],
                    'spot_discount' => $pm['spot_discount'] ?? '70'
                ];
            }
            
            $instances[] = [
                'id' => $row['id'],
                'instance_type' => $row['instance_type'],
                'quantity' => $row['quantity'],
                'operating_system' => $row['operating_system'],
                'region' => $row['region'],
                'vcpu' => intval($row['vcpu'] ?? 0),
                'memory_gb' => floatval($row['memory_gb'] ?? 0),
                'pricing_models' => $pricing_models,
                'display_name' => $row['instance_type'] . ' (x' . $row['quantity'] . ') - ' . $row['operating_system']
            ];
        }
        
        return $instances;
    }
    
    private function calculateUnitCost($instance) {
        require_once __DIR__ . '/PricingModel.php';
        $pricingModel = new PricingModel();
        $region = $instance['region'] ?? '';
        if (empty($region)) {
            return 0.01; // Fallback if no region
        }
        return $pricingModel->getEC2Price($instance['instance_type'], $region);
    }
    
    private function calculatePricingModelCost($instance, $pm) {
        $base_cost = $this->calculateUnitCost($instance);
        
        switch ($pm['model']) {
            case 'spot':
                $spot_discount = floatval($pm['spot_discount'] ?? 70) / 100;
                return $base_cost * (1 - $spot_discount); // Use configured discount
            case 'compute_savings_plan':
            case 'ec2_savings_plan':
                $discount = $pm['reservation_term'] == 3 ? 0.52 : 0.42; // 52% for 3yr, 42% for 1yr
                $utilization = floatval($pm['utilization'] ?? 100) / 100;
                return $base_cost * (1 - $discount) * $utilization;
            case 'on_demand':
                $utilization = floatval($pm['utilization'] ?? 100) / 100;
                return $base_cost * $utilization;
            default:
                return $base_cost;
        }
    }
}

