<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init.php';

class PDFGenerator {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        // Ensure invoices directory exists and is writable
        ensureInvoicesDirectory();
    }
    
    public function generateInvoice($project_id, $user, $project, $format = 'html') {
        // Get all service data
        require_once __DIR__ . '/EC2Model.php';
        require_once __DIR__ . '/EBSModel.php';
        require_once __DIR__ . '/VPCModel.php';
        require_once __DIR__ . '/S3Model.php';
        require_once __DIR__ . '/RDSModel.php';
        require_once __DIR__ . '/EKSModel.php';
        require_once __DIR__ . '/ECRModel.php';
        require_once __DIR__ . '/LoadBalancerModel.php';
        require_once __DIR__ . '/WAFModel.php';
        require_once __DIR__ . '/Route53Model.php';
        require_once __DIR__ . '/Project.php';
        
        $ec2Model = new EC2Model();
        $ebsModel = new EBSModel();
        $vpcModel = new VPCModel();
        $s3Model = new S3Model();
        $rdsModel = new RDSModel();
        $eksModel = new EKSModel();
        $ecrModel = new ECRModel();
        $lbModel = new LoadBalancerModel();
        $wafModel = new WAFModel();
        $route53Model = new Route53Model();
        $projectModel = new Project();
        
        $ec2_instances = $ec2Model->getInstances($project_id);
        $ebs_volumes = $ebsModel->getVolumes($project_id);
        $vpc_config = $vpcModel->getConfig($project_id);
        $s3_config = $s3Model->getConfig($project_id);
        $rds_config = $rdsModel->getConfig($project_id);
        $eks_config = $eksModel->getConfig($project_id);
        $ecr_config = $ecrModel->getConfig($project_id);
        $lb_config = $lbModel->getConfig($project_id);
        $waf_config = $wafModel->getConfig($project_id);
        $route53_config = $route53Model->getConfig($project_id);
        
        // Calculate totals with monthly conversion
        $projectModel->updateProjectTotals($project_id);
        $stmt = $this->conn->prepare("SELECT * FROM project_totals WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        
        // Recalculate total - all costs are now stored as monthly
        $monthly_total = 0;
        
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
                $monthly_total += $pm_row['pm_total'];
            } else {
                // Use base instance cost (already monthly)
                $monthly_total += $row['unit_cost'] * $row['quantity'];
            }
        }
        
        // All other services are already monthly
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM ebs_volumes WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM vpc_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM s3_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM rds_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM eks_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM ecr_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM load_balancer_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM waf_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        $stmt = $this->conn->prepare("SELECT SUM(total_cost) as total FROM route53_configs WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $monthly_total += $row['total'] ?? 0;
        
        // Update totals with monthly calculation
        $totals['total_estimated_cost'] = $monthly_total;
        
        // Try to use TCPDF if available, otherwise use HTML output with download
        if ($format === 'pdf' && class_exists('TCPDF')) {
            return $this->generateWithTCPDF($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals);
        } else {
            return $this->generateWithHTML($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals);
        }
    }
    
    private function generateWithTCPDF($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals) {
        // TCPDF implementation
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Cloudly AWS Ask');
        $pdf->SetAuthor('Cloudly');
        $pdf->SetTitle('Cost Estimate & Invoice - ' . $project['project_name']);
        $pdf->SetSubject('AWS Cost Estimate');
        
        // Disable header and footer to remove any URLs
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        $pdf->AddPage();
        
        // Generate content
        $html = $this->generateHTMLContent($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals);
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_name']) . '_' . date('Y-m-d') . '.pdf';
        $pdf_path = 'invoices/' . $filename;
        
        // Ensure invoices directory is ready (already done in constructor, but double-check)
        $invoice_dir = __DIR__ . '/../invoices/';
        ensureInvoicesDirectory();
        
        // Only save to file if directory is writable
        if (is_writable($invoice_dir)) {
            $pdf->Output($invoice_dir . $filename, 'F'); // F = File
        } else {
            error_log("Invoices directory is not writable: " . $invoice_dir);
        }
        
        // Always output for download
        $pdf->Output($filename, 'D'); // D = Download
        
        return $pdf_path;
    }
    
    private function generateWithHTML($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals) {
        // Generate HTML content
        $html = $this->generateHTMLContent($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals);
        
        // Add JavaScript to automatically trigger print and download
        $html = str_replace('</body>', '
        <script>
            window.onload = function() {
                // Auto-trigger print dialog immediately
                setTimeout(function() {
                    // Remove any URL from page before printing
                    var style = document.createElement("style");
                    style.textContent = "@page { margin: 1cm; size: A4; marks: none; @top-center { content: \"\"; } @bottom-center { content: \"\"; } @top-right { content: \"\"; } @bottom-right { content: \"\"; } } @media print { @page { margin: 1cm; size: A4; marks: none; } }";
                    document.head.appendChild(style);
                    // Remove any links that might show URLs
                    var links = document.querySelectorAll("a");
                    links.forEach(function(link) {
                        link.style.textDecoration = "none";
                        link.style.color = "inherit";
                    });
                    window.print();
                }, 500);
            };
        </script>
        </body>', $html);
        
        // Output HTML with proper headers - browser will handle PDF conversion
        $filename = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['project_name']) . '_' . date('Y-m-d') . '.html';
        $pdf_path = 'invoices/' . $filename;
        
        // Ensure invoices directory is ready (already done in constructor, but double-check)
        $invoice_dir = __DIR__ . '/../invoices/';
        ensureInvoicesDirectory();
        
        // Save HTML file only if directory is writable
        if (is_writable($invoice_dir)) {
            $file_path = $invoice_dir . $filename;
            if (file_put_contents($file_path, $html) === false) {
                error_log("Failed to write invoice file: " . $file_path);
            }
        } else {
            error_log("Invoices directory is not writable: " . $invoice_dir);
            // Continue anyway - we'll still output the HTML
        }
        
        // Use print media query styling - check if headers already sent
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo $html;
        exit;
    }
    
    // Helper function to format region name
    private function formatRegionName($region_code) {
        $regions = [
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'af-south-1' => 'Africa (Cape Town)',
            'ap-east-1' => 'Asia Pacific (Hong Kong)',
            'ap-south-2' => 'Asia Pacific (Hyderabad)',
            'ap-southeast-3' => 'Asia Pacific (Jakarta)',
            'ap-southeast-5' => 'Asia Pacific (Malaysia)',
            'ap-southeast-4' => 'Asia Pacific (Melbourne)',
            'ap-south-1' => 'Asia Pacific (Mumbai)',
            'ap-southeast-6' => 'Asia Pacific (New Zealand)',
            'ap-northeast-3' => 'Asia Pacific (Osaka)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-east-2' => 'Asia Pacific (Taipei)',
            'ap-southeast-7' => 'Asia Pacific (Thailand)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ca-central-1' => 'Canada (Central)',
            'ca-west-1' => 'Canada West (Calgary)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-west-2' => 'Europe (London)',
            'eu-south-1' => 'Europe (Milan)',
            'eu-west-3' => 'Europe (Paris)',
            'eu-south-2' => 'Europe (Spain)',
            'eu-north-1' => 'Europe (Stockholm)',
            'eu-central-2' => 'Europe (Zurich)',
            'il-central-1' => 'Israel (Tel Aviv)',
            'mx-central-1' => 'Mexico (Central)',
            'me-south-1' => 'Middle East (Bahrain)',
            'me-central-1' => 'Middle East (UAE)',
            'sa-east-1' => 'South America (São Paulo)'
        ];
        return $regions[$region_code] ?? $region_code;
    }
    
    private function generateHTMLContent($user, $project, $ec2_instances, $ebs_volumes, $vpc_config, $s3_config, $rds_config, $eks_config, $ecr_config, $lb_config, $waf_config, $route53_config, $totals) {
        // Get region from project
        $project_region = $project['region'] ?? '';
        $region_display = $this->formatRegionName($project_region);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="robots" content="noindex, nofollow">
            <title>Cost Estimate & Invoice - Cloudly AWS Ask</title>
            <style>
                @media print {
                    @page { 
                        margin: 1cm; 
                        size: A4;
                        marks: none;
                        /* Remove URL from header and footer */
                        @top-center { content: ""; }
                        @bottom-center { content: ""; }
                        @top-right { content: ""; }
                        @bottom-right { content: ""; }
                        @top-left { content: ""; }
                        @bottom-left { content: ""; }
                    }
                    body { padding: 0; margin: 0; }
                    .no-print { display: none !important; }
                    /* Remove any URL from print */
                    a[href]:after { content: none !important; }
                    /* Hide any browser-added URLs */
                    *::before, *::after { content: none !important; }
                }
                body { font-family: 'Segoe UI', Arial, sans-serif; padding: 15px; margin: 0; background: white; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #ff6b35; padding-bottom: 15px; }
                .header-logo { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 8px; }
                .header-logo img { height: 45px; }
                .header h1 { color: #ff6b35; font-size: 24px; margin: 0; font-weight: 600; }
                .header h2 { color: #666; font-size: 14px; margin: 4px 0; font-weight: 400; }
                .info-section { display: table; width: 100%; margin-bottom: 20px; }
                .info-box { display: table-cell; width: 50%; padding: 12px; background: linear-gradient(135deg, #fff5f2 0%, #ffe8e0 100%); vertical-align: top; border-radius: 5px; margin: 4px; }
                .info-box h3 { color: #333; margin-bottom: 8px; font-size: 12px; font-weight: 600; }
                .info-box p { color: #666; margin: 4px 0; font-size: 11px; }
                .service-section { margin-bottom: 20px; page-break-inside: avoid; }
                .service-section h3 { background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%); color: white; padding: 8px 12px; margin: 0; font-size: 12px; font-weight: 600; border-radius: 5px 5px 0 0; }
                .service-table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 9px; }
                .service-table th { background: #fff5f2; padding: 8px; text-align: left; border-bottom: 2px solid #ff6b35; font-weight: 600; font-size: 9px; }
                .service-table td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 9px; }
                .service-table tr:last-child td { border-bottom: none; }
                .service-table tr:hover { background: #fff5f2; }
                .total-section { margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #fff5f2 0%, #ffe8e0 100%); border-radius: 5px; border: 2px solid #ff6b35; }
                .total-section h3 { color: #333; margin-bottom: 12px; font-size: 16px; font-weight: 600; }
                .total-row { display: table; width: 100%; padding: 10px 0; border-bottom: 1px solid #ddd; }
                .total-row span { display: table-cell; }
                .total-row span:last-child { text-align: right; font-weight: bold; }
                .total-row:last-child { border-bottom: none; font-size: 20px; color: #ff6b35; font-weight: 700; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 10px; border-top: 1px solid #ddd; padding-top: 15px; }
                @media print {
                    body { padding: 10px; }
                    .service-section { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="header-logo">
                    <?php 
                    $logo_path = __DIR__ . '/../assets/cloudlybangladesh_logo.jpg';
                    if (file_exists($logo_path)) {
                        $logo_data = base64_encode(file_get_contents($logo_path));
                        echo '<img src="data:image/jpeg;base64,' . $logo_data . '" alt="Cloudly Logo" style="height: 50px;">';
                    }
                    ?>
                    <h1>Cloudly AWS Ask</h1>
                </div>
                <h2>Cost Estimate & Invoice</h2>
            </div>

            <div class="info-section">
                <div class="info-box">
                    <h3>Project Information</h3>
                    <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                    <p><strong>Salesman Name:</strong> <?php echo htmlspecialchars($project['salesman_name']); ?></p>
                    <p><strong>Region:</strong> <?php echo htmlspecialchars($region_display); ?></p>
                    <p><strong>Report Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
                <div class="info-box">
                    <h3>Contact Information</h3>
                    <p><strong>User:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                </div>
            </div>

            <?php if (!empty($ec2_instances)): ?>
            <div class="service-section">
                <h3>EC2 Instances</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Service</th>
                            <th>No. of Count</th>
                            <th>Type & Size</th>
                            <th>Estimated Monthly Cost (USD)</th>
                            <th>Region</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ec2_total = 0;
                        $ebs_total = 0;
                        
                        // Pre-fetch all EC2 costs and specs in one query
                        $ec2_ids = array_column($ec2_instances, 'id');
                        $ec2_costs = [];
                        $ec2_specs = [];
                        if (!empty($ec2_ids)) {
                            $placeholders = str_repeat('?,', count($ec2_ids) - 1) . '?';
                            // Get all EC2 specs
                            $stmt = $this->conn->prepare("SELECT id, vcpu, memory_gb, unit_cost, quantity FROM ec2_instances WHERE id IN ($placeholders)");
                            $stmt->bind_param(str_repeat('i', count($ec2_ids)), ...$ec2_ids);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $ec2_specs[$row['id']] = $row;
                            }
                            
                            // Get all pricing model costs
                            $stmt = $this->conn->prepare("SELECT ec2_instance_id, SUM(total_cost) as pm_total FROM ec2_pricing_models WHERE ec2_instance_id IN ($placeholders) GROUP BY ec2_instance_id");
                            $stmt->bind_param(str_repeat('i', count($ec2_ids)), ...$ec2_ids);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $ec2_costs[$row['ec2_instance_id']] = floatval($row['pm_total']);
                            }
                        }
                        
                        // Group EBS volumes by EC2 instance ID and server_name
                        // This ensures EBS volumes with different server_name values are shown separately
                        $ebs_by_instance_and_name = [];
                        foreach ($ebs_volumes as $vol) {
                            $ec2_id = $vol['ec2_instance_id'] ?? null;
                            $server_name = $vol['server_name'] ?? '';
                            if ($ec2_id) {
                                // Create composite key: ec2_id + server_name
                                $key = $ec2_id . '_' . $server_name;
                                if (!isset($ebs_by_instance_and_name[$key])) {
                                    $ebs_by_instance_and_name[$key] = [
                                        'ec2_id' => $ec2_id,
                                        'server_name' => $server_name,
                                        'volumes' => []
                                    ];
                                }
                                $ebs_by_instance_and_name[$key]['volumes'][] = $vol;
                                $ebs_total += $vol['total_cost'];
                            }
                        }
                        
                        // Create a map of EC2 instances for quick lookup
                        $ec2_map = [];
                        foreach ($ec2_instances as $inst) {
                            $ec2_map[$inst['id']] = $inst;
                        }
                        
                        // Track which EC2 instances have been counted to avoid double-counting
                        $ec2_counted = [];
                        
                        // Process each unique EC2 + server_name combination
                        foreach ($ebs_by_instance_and_name as $group):
                            $ec2_id = $group['ec2_id'];
                            $server_name = $group['server_name'];
                            $instance_ebs = $group['volumes'];
                            
                            // Get EC2 instance details
                            if (!isset($ec2_map[$ec2_id])) {
                                continue; // Skip if EC2 instance not found
                            }
                            $inst = $ec2_map[$ec2_id];
                            
                            // Use pre-fetched costs
                            if (isset($ec2_costs[$inst['id']]) && $ec2_costs[$inst['id']] > 0) {
                                $monthly_total_cost = $ec2_costs[$inst['id']];
                            } else {
                                $spec = $ec2_specs[$inst['id']] ?? null;
                                $monthly_unit_cost = $spec['unit_cost'] ?? 0;
                                $monthly_total_cost = $monthly_unit_cost * ($spec['quantity'] ?? $inst['quantity']);
                            }
                            
                            // Only count EC2 cost once per instance (even if it appears in multiple groups)
                            if (!isset($ec2_counted[$inst['id']])) {
                                $ec2_total += $monthly_total_cost;
                                $ec2_counted[$inst['id']] = true;
                            }
                            
                            $spec = $ec2_specs[$inst['id']] ?? [];
                            $vcpu = $spec['vcpu'] ?? 0;
                            $memory_gb = $spec['memory_gb'] ?? 0;
                            
                            $has_ebs = !empty($instance_ebs);
                            $rowspan = $has_ebs ? count($instance_ebs) + 1 : 1;
                            
                            // Determine display name: use server_name if available, otherwise instance type
                            $display_name = !empty($server_name) ? htmlspecialchars($server_name) : htmlspecialchars($inst['instance_type']);
                        ?>
                        <tr>
                            <td rowspan="<?php echo $rowspan; ?>" style="vertical-align: top; font-weight: 600; font-size: 9px;">
                                <?php echo $display_name; ?>
                            </td>
                            <td>EC2</td>
                            <td><?php echo $inst['quantity']; ?></td>
                            <td>On Demand <?php echo strtolower($inst['operating_system']); ?> <?php echo htmlspecialchars($inst['instance_type']); ?> Instance (<?php echo $vcpu; ?> vCPU / <?php echo number_format($memory_gb, 0); ?> GB RAM)</td>
                            <td>$<?php echo number_format($monthly_total_cost, 2); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>" style="vertical-align: top;"><?php echo htmlspecialchars($this->formatRegionName($inst['region'])); ?></td>
                        </tr>
                        <?php 
                        // Show EBS volumes for this instance + server_name combination
                        foreach ($instance_ebs as $vol): 
                            $snapshot_display = $vol['snapshot_frequency'] ?? 'none';
                            if ($snapshot_display !== 'none') {
                                $snapshot_display = ucfirst(str_replace('_', ' ', $snapshot_display));
                                if (isset($vol['snapshot_storage_gb']) && $vol['snapshot_storage_gb'] > 0) {
                                    $snapshot_display .= ' (' . $vol['snapshot_storage_gb'] . ' GB)';
                                }
                            } else {
                                $snapshot_display = '';
                            }
                        ?>
                        <tr>
                            <td>EBS</td>
                            <td>1</td>
                            <td><?php echo $vol['size_gb']; ?> GB - <?php echo htmlspecialchars($vol['volume_type']); ?> EBS Storage Cost<?php echo $snapshot_display ? ' (' . htmlspecialchars($snapshot_display) . ')' : ''; ?></td>
                            <td>$<?php echo number_format($vol['total_cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <?php 
                        // Show EC2 instances that don't have any EBS volumes attached
                        foreach ($ec2_instances as $inst):
                            $has_ebs_attached = false;
                            foreach ($ebs_by_instance_and_name as $group) {
                                if ($group['ec2_id'] == $inst['id']) {
                                    $has_ebs_attached = true;
                                    break;
                                }
                            }
                            
                            if (!$has_ebs_attached):
                                // Use pre-fetched costs
                                if (isset($ec2_costs[$inst['id']]) && $ec2_costs[$inst['id']] > 0) {
                                    $monthly_total_cost = $ec2_costs[$inst['id']];
                                } else {
                                    $spec = $ec2_specs[$inst['id']] ?? null;
                                    $monthly_unit_cost = $spec['unit_cost'] ?? 0;
                                    $monthly_total_cost = $monthly_unit_cost * ($spec['quantity'] ?? $inst['quantity']);
                                }
                                $ec2_total += $monthly_total_cost;
                                
                                $spec = $ec2_specs[$inst['id']] ?? [];
                                $vcpu = $spec['vcpu'] ?? 0;
                                $memory_gb = $spec['memory_gb'] ?? 0;
                                
                                $display_name = htmlspecialchars($inst['instance_type']);
                        ?>
                        <tr>
                            <td style="vertical-align: top; font-weight: 600; font-size: 9px;">
                                <?php echo $display_name; ?>
                            </td>
                            <td>EC2</td>
                            <td><?php echo $inst['quantity']; ?></td>
                            <td>On Demand <?php echo strtolower($inst['operating_system']); ?> <?php echo htmlspecialchars($inst['instance_type']); ?> Instance (<?php echo $vcpu; ?> vCPU / <?php echo number_format($memory_gb, 0); ?> GB RAM)</td>
                            <td>$<?php echo number_format($monthly_total_cost, 2); ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($inst['region'])); ?></td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="4">Total</td>
                            <td>$<?php echo number_format($ec2_total + $ebs_total, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php 
            // Show EBS volumes that are not associated with any EC2 instance
            $unassociated_ebs = [];
            foreach ($ebs_volumes as $vol) {
                if (empty($vol['ec2_instance_id'])) {
                    $unassociated_ebs[] = $vol;
                }
            }
            if (!empty($unassociated_ebs)): 
            ?>
            <div class="service-section">
                <h3>EBS Volumes (Standalone)</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Server Type</th>
                            <th>Server Name</th>
                            <th>Volume Type</th>
                            <th>Size (GB)</th>
                            <th>Snapshot</th>
                            <th>Total Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $standalone_ebs_total = 0;
                        foreach ($unassociated_ebs as $vol): 
                            $standalone_ebs_total += $vol['total_cost'];
                            $snapshot_display = $vol['snapshot_frequency'] ?? 'none';
                            if ($snapshot_display !== 'none') {
                                $snapshot_display = ucfirst(str_replace('_', ' ', $snapshot_display));
                                if (isset($vol['snapshot_storage_gb']) && $vol['snapshot_storage_gb'] > 0) {
                                    $snapshot_display .= ' (' . $vol['snapshot_storage_gb'] . ' GB)';
                                }
                            } else {
                                $snapshot_display = 'None';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vol['server_type']); ?></td>
                            <td><?php echo htmlspecialchars($vol['server_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($vol['volume_type']); ?></td>
                            <td><?php echo $vol['size_gb']; ?></td>
                            <td><?php echo htmlspecialchars($snapshot_display); ?></td>
                            <td>$<?php echo number_format($vol['total_cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="5">Standalone EBS Subtotal</td>
                            <td>$<?php echo number_format($standalone_ebs_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($vpc_config): ?>
            <div class="service-section">
                <h3>VPC</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Count</th>
                            <th>Region</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>VPC</td>
                            <td><?php echo $vpc_config['vpc_count']; ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($vpc_config['region'])); ?></td>
                            <td>$0.00</td>
                        </tr>
                        <?php if ($vpc_config['nat_gateway_count'] > 0): ?>
                        <tr>
                            <td>NAT Gateway</td>
                            <td><?php echo $vpc_config['nat_gateway_count']; ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($vpc_config['region'])); ?></td>
                            <td>$<?php 
                                // VPC costs are already stored as monthly in total_cost
                                require_once __DIR__ . '/PricingModel.php';
                                $pricingModel = new PricingModel();
                                $nat_price = $pricingModel->getVPCPrice('nat_gateway', $vpc_config['region']);
                                $nat_monthly = $nat_price['price_per_hour'] * 730 * $vpc_config['nat_gateway_count'];
                                echo number_format($nat_monthly, 2);
                            ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($vpc_config['vpc_endpoint_count'] > 0): ?>
                        <tr>
                            <td>VPC Endpoint</td>
                            <td><?php echo $vpc_config['vpc_endpoint_count']; ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($vpc_config['region'])); ?></td>
                            <td>$<?php 
                                // VPC costs are already stored as monthly in total_cost
                                $endpoint_price = $pricingModel->getVPCPrice('vpc_endpoint', $vpc_config['region']);
                                $endpoint_monthly = $endpoint_price['price_per_hour'] * 730 * $vpc_config['vpc_endpoint_count'];
                                echo number_format($endpoint_monthly, 2);
                            ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3">VPC Subtotal</td>
                            <td>$<?php echo number_format($vpc_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($s3_config): ?>
            <div class="service-section">
                <h3>S3 Storage</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Storage Class</th>
                            <th>Storage (GB)</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($s3_config['storage_class']); ?></td>
                            <td><?php echo $s3_config['storage_gb']; ?></td>
                            <td>$<?php echo number_format($s3_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="2">S3 Subtotal</td>
                            <td>$<?php echo number_format($s3_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php 
            // Handle RDS - can be single config or array of configs
            $rds_configs = is_array($rds_config) && isset($rds_config[0]) ? $rds_config : ($rds_config ? [$rds_config] : []);
            if (!empty($rds_configs)): 
            ?>
            <div class="service-section">
                <h3>RDS Instances</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Engine</th>
                            <th>Instance Type</th>
                            <th>Quantity</th>
                            <th>Storage (GB)</th>
                            <th>Region</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rds_total = 0;
                        foreach ($rds_configs as $rds): 
                            // RDS costs are already calculated as monthly in PricingCalculator
                            $rds_total += $rds['total_cost'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rds['engine']); ?></td>
                            <td><?php echo htmlspecialchars($rds['instance_type']); ?></td>
                            <td><?php echo $rds['quantity'] ?? 1; ?></td>
                            <td><?php echo $rds['storage_gb']; ?></td>
                            <td><?php 
                                $rds_region = $rds['region'] ?? $project['region'] ?? '';
                                if (empty($rds_region) || $rds_region == '0') {
                                    $rds_region = $project['region'] ?? '';
                                }
                                echo htmlspecialchars($this->formatRegionName($rds_region)); 
                            ?></td>
                            <td>$<?php echo number_format($rds['total_cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="5">RDS Subtotal</td>
                            <td>$<?php echo number_format($rds_total, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($eks_config): ?>
            <div class="service-section">
                <h3>EKS</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Cluster Count</th>
                            <th>Node Group Count</th>
                            <th>Instance Type</th>
                            <th>Node Count</th>
                            <th>Region</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $eks_config['cluster_count']; ?></td>
                            <td><?php echo $eks_config['node_group_count']; ?></td>
                            <td><?php echo htmlspecialchars($eks_config['instance_type']); ?></td>
                            <td><?php echo $eks_config['node_count']; ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($eks_config['region'])); ?></td>
                            <td>$<?php echo number_format($eks_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="5">EKS Subtotal</td>
                            <td>$<?php echo number_format($eks_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($ecr_config): ?>
            <div class="service-section">
                <h3>ECR</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Storage (GB)</th>
                            <th>Data Transfer (GB)</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $ecr_config['storage_gb']; ?></td>
                            <td><?php echo $ecr_config['data_transfer_gb']; ?></td>
                            <td>$<?php echo number_format($ecr_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="2">ECR Subtotal</td>
                            <td>$<?php echo number_format($ecr_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($lb_config): ?>
            <div class="service-section">
                <h3>Load Balancer</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Load Balancer Type</th>
                            <th>Quantity</th>
                            <th>Region</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($lb_config['load_balancer_type']); ?></td>
                            <td><?php echo $lb_config['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($this->formatRegionName($lb_config['region'])); ?></td>
                            <td>$<?php echo number_format($lb_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3">Load Balancer Subtotal</td>
                            <td>$<?php echo number_format($lb_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($waf_config): ?>
            <div class="service-section">
                <h3>WAF</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Web ACL Count</th>
                            <th>Rules Count</th>
                            <th>Requests (Million)</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $waf_config['web_acl_count']; ?></td>
                            <td><?php echo $waf_config['rules_count']; ?></td>
                            <td><?php echo $waf_config['requests_million']; ?></td>
                            <td>$<?php echo number_format($waf_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3">WAF Subtotal</td>
                            <td>$<?php echo number_format($waf_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($route53_config): ?>
            <div class="service-section">
                <h3>Route 53</h3>
                <table class="service-table">
                    <thead>
                        <tr>
                            <th>Hosted Zones</th>
                            <th>Queries (Million)</th>
                            <th>Health Checks</th>
                            <th>Total Estimated Monthly Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo $route53_config['hosted_zones']; ?></td>
                            <td><?php echo $route53_config['queries_million']; ?></td>
                            <td><?php echo $route53_config['health_checks']; ?></td>
                            <td>$<?php echo number_format($route53_config['total_cost'], 2); ?></td>
                        </tr>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3">Route 53 Subtotal</td>
                            <td>$<?php echo number_format($route53_config['total_cost'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="total-section">
                <h3>Total Estimated Cost</h3>
                <div class="total-row" style="font-size: 20px; font-weight: 700; color: #ff6b35;">
                    <span>Total Estimated Monthly Cost (USD):</span>
                    <span>$<?php echo number_format($totals['total_estimated_cost'], 2); ?></span>
                </div>
            </div>

            <div class="footer">
                <p>This is an estimate. Actual costs may vary.</p>
                <p>&copy; <?php echo date('Y'); ?> Cloudly. All rights reserved.</p>
                <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

