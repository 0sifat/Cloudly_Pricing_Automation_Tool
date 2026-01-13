<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Project.php';

$projectModel = new Project();
$project_id = $_GET['project_id'] ?? null;
$project = null;

if ($project_id) {
    $project = $projectModel->getProject($project_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cloudly AWS Ask</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="../assets/cloudlybangladesh_logo.jpg" alt="Cloudly Logo" class="header-logo">
                <h1>Cloudly AWS Ask</h1>
            </div>
            <div class="user-info">
                <div class="user-profile-dropdown">
                    <button class="user-profile-btn" onclick="toggleProfileMenu(event)">
                        <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div id="profileMenu" class="profile-menu">
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <a href="admin_dashboard.php">
                            <span style="margin-right: 8px;">👤</span>Switch to Admin Panel
                        </a>
                        <?php endif; ?>
                        <a href="#" onclick="openProfileModal(); toggleProfileMenu(); return false;">
                            <span style="margin-right: 8px;">⚙️</span>Profile Settings
                        </a>
                        <a href="../controllers/logout.php">
                            <span style="margin-right: 8px;">🚪</span>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="project-section">
                <h3>Project Setup</h3>
                <form id="projectForm">
                    <input type="hidden" id="project_id" value="<?php echo $project_id ?? ''; ?>">
                    <div class="form-group">
                        <label>Project Name</label>
                        <div class="input-with-logo">
                            <img src="../assets/cloudlybangladesh_logo.jpg" alt="Logo" class="input-logo">
                            <input type="text" id="project_name" value="<?php echo htmlspecialchars($project['project_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Salesman Name</label>
                        <input type="text" id="salesman_name" value="<?php echo htmlspecialchars($project['salesman_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Region</label>
                        <select id="project_region" required>
                            <option value="">Select Region</option>
                            <option value="us-east-1" <?php echo (isset($project['region']) && $project['region'] == 'us-east-1') ? 'selected' : ''; ?>>US East (N. Virginia)</option>
                            <option value="us-east-2" <?php echo (isset($project['region']) && $project['region'] == 'us-east-2') ? 'selected' : ''; ?>>US East (Ohio)</option>
                            <option value="us-west-1" <?php echo (isset($project['region']) && $project['region'] == 'us-west-1') ? 'selected' : ''; ?>>US West (N. California)</option>
                            <option value="us-west-2" <?php echo (isset($project['region']) && $project['region'] == 'us-west-2') ? 'selected' : ''; ?>>US West (Oregon)</option>
                            <option value="af-south-1" <?php echo (isset($project['region']) && $project['region'] == 'af-south-1') ? 'selected' : ''; ?>>Africa (Cape Town)</option>
                            <option value="ap-east-1" <?php echo (isset($project['region']) && $project['region'] == 'ap-east-1') ? 'selected' : ''; ?>>Asia Pacific (Hong Kong)</option>
                            <option value="ap-south-2" <?php echo (isset($project['region']) && $project['region'] == 'ap-south-2') ? 'selected' : ''; ?>>Asia Pacific (Hyderabad)</option>
                            <option value="ap-southeast-3" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-3') ? 'selected' : ''; ?>>Asia Pacific (Jakarta)</option>
                            <option value="ap-southeast-5" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-5') ? 'selected' : ''; ?>>Asia Pacific (Malaysia)</option>
                            <option value="ap-southeast-4" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-4') ? 'selected' : ''; ?>>Asia Pacific (Melbourne)</option>
                            <option value="ap-south-1" <?php echo (isset($project['region']) && $project['region'] == 'ap-south-1') ? 'selected' : ''; ?>>Asia Pacific (Mumbai)</option>
                            <option value="ap-southeast-6" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-6') ? 'selected' : ''; ?>>Asia Pacific (New Zealand)</option>
                            <option value="ap-northeast-3" <?php echo (isset($project['region']) && $project['region'] == 'ap-northeast-3') ? 'selected' : ''; ?>>Asia Pacific (Osaka)</option>
                            <option value="ap-northeast-2" <?php echo (isset($project['region']) && $project['region'] == 'ap-northeast-2') ? 'selected' : ''; ?>>Asia Pacific (Seoul)</option>
                            <option value="ap-southeast-1" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-1') ? 'selected' : ''; ?>>Asia Pacific (Singapore)</option>
                            <option value="ap-southeast-2" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-2') ? 'selected' : ''; ?>>Asia Pacific (Sydney)</option>
                            <option value="ap-east-2" <?php echo (isset($project['region']) && $project['region'] == 'ap-east-2') ? 'selected' : ''; ?>>Asia Pacific (Taipei)</option>
                            <option value="ap-southeast-7" <?php echo (isset($project['region']) && $project['region'] == 'ap-southeast-7') ? 'selected' : ''; ?>>Asia Pacific (Thailand)</option>
                            <option value="ap-northeast-1" <?php echo (isset($project['region']) && $project['region'] == 'ap-northeast-1') ? 'selected' : ''; ?>>Asia Pacific (Tokyo)</option>
                            <option value="ca-central-1" <?php echo (isset($project['region']) && $project['region'] == 'ca-central-1') ? 'selected' : ''; ?>>Canada (Central)</option>
                            <option value="ca-west-1" <?php echo (isset($project['region']) && $project['region'] == 'ca-west-1') ? 'selected' : ''; ?>>Canada West (Calgary)</option>
                            <option value="eu-central-1" <?php echo (isset($project['region']) && $project['region'] == 'eu-central-1') ? 'selected' : ''; ?>>Europe (Frankfurt)</option>
                            <option value="eu-west-1" <?php echo (isset($project['region']) && $project['region'] == 'eu-west-1') ? 'selected' : ''; ?>>Europe (Ireland)</option>
                            <option value="eu-west-2" <?php echo (isset($project['region']) && $project['region'] == 'eu-west-2') ? 'selected' : ''; ?>>Europe (London)</option>
                            <option value="eu-south-1" <?php echo (isset($project['region']) && $project['region'] == 'eu-south-1') ? 'selected' : ''; ?>>Europe (Milan)</option>
                            <option value="eu-west-3" <?php echo (isset($project['region']) && $project['region'] == 'eu-west-3') ? 'selected' : ''; ?>>Europe (Paris)</option>
                            <option value="eu-south-2" <?php echo (isset($project['region']) && $project['region'] == 'eu-south-2') ? 'selected' : ''; ?>>Europe (Spain)</option>
                            <option value="eu-north-1" <?php echo (isset($project['region']) && $project['region'] == 'eu-north-1') ? 'selected' : ''; ?>>Europe (Stockholm)</option>
                            <option value="eu-central-2" <?php echo (isset($project['region']) && $project['region'] == 'eu-central-2') ? 'selected' : ''; ?>>Europe (Zurich)</option>
                            <option value="il-central-1" <?php echo (isset($project['region']) && $project['region'] == 'il-central-1') ? 'selected' : ''; ?>>Israel (Tel Aviv)</option>
                            <option value="mx-central-1" <?php echo (isset($project['region']) && $project['region'] == 'mx-central-1') ? 'selected' : ''; ?>>Mexico (Central)</option>
                            <option value="me-south-1" <?php echo (isset($project['region']) && $project['region'] == 'me-south-1') ? 'selected' : ''; ?>>Middle East (Bahrain)</option>
                            <option value="me-central-1" <?php echo (isset($project['region']) && $project['region'] == 'me-central-1') ? 'selected' : ''; ?>>Middle East (UAE)</option>
                            <option value="sa-east-1" <?php echo (isset($project['region']) && $project['region'] == 'sa-east-1') ? 'selected' : ''; ?>>South America (São Paulo)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Save Project</button>
                </form>
            </div>

            <div class="services-menu">
                <h3>AWS Services</h3>
                <button class="service-btn" data-service="ec2">EC2</button>
                <button class="service-btn service-btn-nested" data-service="ebs">└─ EBS</button>
                <button class="service-btn" data-service="vpc">VPC</button>
                <button class="service-btn" data-service="s3">S3</button>
                <button class="service-btn" data-service="rds">RDS</button>
                <button class="service-btn" data-service="eks">EKS</button>
                <button class="service-btn" data-service="ecr">ECR</button>
                <button class="service-btn" data-service="loadbalancer">Load Balancer</button>
                <button class="service-btn" data-service="waf">WAF</button>
                <button class="service-btn" data-service="route53">Route 53</button>
            </div>

            <div class="actions-section">
                <button id="calculateBtn" class="btn-calculate">Calculate Total Cost</button>
                <button id="generatePdfBtn" class="btn-pdf">Generate PDF Invoice</button>
            </div>
        </div>

        <div class="main-content">
            <div id="serviceForms">
                <!-- Service forms will be loaded here -->
                <div class="welcome-message">
                    <h2>Welcome to Cloudly AWS Ask</h2>
                    <p>Select a service from the sidebar to get started with your AWS cost estimation.</p>
                </div>
            </div>

            <div id="costSummary" class="cost-summary" style="display: none;">
                <h3>Cost Summary</h3>
                <div id="summaryContent"></div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; 2025 Cloudly Infotech Limited. All rights reserved.</p>
            <p>Bangladesh's First Premier AWS & GCP Partner</p>
        </div>
    </footer>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProfileModal()">&times;</span>
            <h2>Profile Settings</h2>
            <form id="profileForm">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="profile_username" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="tel" id="profile_mobile" required>
                </div>
                <div class="form-group">
                    <label>Current Password (leave blank if not changing)</label>
                    <input type="password" id="profile_current_password">
                </div>
                <div class="form-group">
                    <label>New Password (leave blank if not changing)</label>
                    <input type="password" id="profile_new_password">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="profile_confirm_password">
                </div>
                <button type="submit" class="btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/services.js"></script>
    <script>
        // Make service functions globally available after scripts are loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof loadEC2Form !== 'undefined') {
                window.loadEC2Form = loadEC2Form;
            }
            if (typeof loadEBSForm !== 'undefined') {
                window.loadEBSForm = loadEBSForm;
            }
            if (typeof loadVPCForm !== 'undefined') {
                window.loadVPCForm = loadVPCForm;
            }
            if (typeof loadS3Form !== 'undefined') {
                window.loadS3Form = loadS3Form;
            }
            if (typeof loadRDSForm !== 'undefined') {
                window.loadRDSForm = loadRDSForm;
            }
            if (typeof loadEKSForm !== 'undefined') {
                window.loadEKSForm = loadEKSForm;
            }
            if (typeof loadECRForm !== 'undefined') {
                window.loadECRForm = loadECRForm;
            }
            if (typeof loadLOADBALANCERForm !== 'undefined') {
                window.loadLOADBALANCERForm = loadLOADBALANCERForm;
            }
            if (typeof loadWAFForm !== 'undefined') {
                window.loadWAFForm = loadWAFForm;
            }
            if (typeof loadROUTE53Form !== 'undefined') {
                window.loadROUTE53Form = loadROUTE53Form;
            }
        });
    </script>
</body>
</html>

