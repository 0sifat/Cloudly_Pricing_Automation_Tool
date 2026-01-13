<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Project.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$userModel = new User();
$projectModel = new Project();

// Verify admin status
if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header("Location: login.php?error=Access denied");
    exit;
}

$users = $userModel->getAllUsers();
$projects = $projectModel->getAllProjects();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cloudly AWS Ask</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .admin-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            gap: 20px;
            flex: 1;
        }
        .admin-sidebar {
            width: 280px;
            background: #2a2a2a;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            height: fit-content;
            position: sticky;
            top: 20px;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }
        .admin-sidebar h3 {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 107, 53, 0.3);
        }
        .admin-tab {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: #1a1a1a;
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: left;
            color: #fff !important;
        }
        .admin-tab:hover {
            background: rgba(255, 107, 53, 0.1);
            border-color: #ff6b35;
            transform: translateX(5px);
        }
        .admin-tab.active {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            border-color: #ff6b35;
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
        }
        .admin-content {
            flex: 1;
            background: #2a2a2a;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 107, 53, 0.2);
            min-height: 600px;
        }
        .admin-content * {
            color: #fff !important;
        }
        .admin-content p, .admin-content span, .admin-content td, .admin-content th {
            color: #fff !important;
        }
        .admin-content input[type="text"],
        .admin-content input[type="password"],
        .admin-content input[type="tel"],
        .admin-content input[type="number"],
        .admin-content select {
            color: #fff !important;
            background: #1a1a1a !important;
        }
        .admin-content input::placeholder {
            color: #999 !important;
            opacity: 1;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-content h2 {
            color: #fff !important;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
        }
        .tab-content p {
            color: #fff;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 15px;
        }
        .tab-content h3 {
            color: #fff;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
        }
        .admin-table th, .admin-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 107, 53, 0.2);
        }
        .admin-table th {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .admin-table td {
            color: #fff !important;
            font-size: 14px;
        }
        .admin-table td span {
            color: inherit !important;
        }
        .admin-table tr:hover {
            background: rgba(255, 107, 53, 0.1);
        }
        .admin-table tr:last-child td {
            border-bottom: none;
        }
        .btn-admin {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary-admin {
            background: linear-gradient(135deg, #ff6b35 0%, #ff4757 100%);
            color: white;
        }
        .btn-primary-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.4);
        }
        .btn-success-admin {
            background: #28a745;
            color: white;
        }
        .btn-success-admin:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-danger-admin {
            background: #dc3545;
            color: white;
        }
        .btn-danger-admin:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .form-group-admin {
            margin-bottom: 20px;
        }
        .form-group-admin label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #fff !important;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group-admin input, .form-group-admin select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff !important;
            font-size: 15px;
            font-weight: 500;
            min-height: 45px;
            transition: all 0.3s;
        }
        .form-group-admin input[type="text"],
        .form-group-admin input[type="password"],
        .form-group-admin input[type="tel"],
        .form-group-admin input[type="number"] {
            color: #fff !important;
        }
        .form-group-admin input::placeholder {
            color: #999 !important;
            opacity: 1;
        }
        .form-group-admin input:focus, .form-group-admin select:focus {
            outline: none;
            border-color: #ff6b35;
            background: #1f1f1f;
            transform: translateY(-1px);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
        }
        .form-group-admin input::placeholder {
            color: #999;
        }
        .form-group-admin small {
            color: #ccc;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .alert-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border-color: #ffc107;
            color: #ffc107;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: #28a745;
            color: #28a745;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: #dc3545;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <img src="../assets/cloudlybangladesh_logo.jpg" alt="Cloudly Logo" class="header-logo">
                <h1>Cloudly AWS Ask - Admin Panel</h1>
            </div>
            <div class="user-info">
                <div class="user-profile-dropdown">
                    <button class="user-profile-btn" onclick="toggleProfileMenu(event)">
                        <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div id="profileMenu" class="profile-menu">
                        <a href="#" onclick="switchTab('profile'); toggleProfileMenu(); return false;">
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

    <div class="admin-container">
            <div class="admin-sidebar">
            <h3>Admin Menu</h3>
            <button class="admin-tab active" onclick="switchTab('profile')">My Profile</button>
            <button class="admin-tab" onclick="switchTab('users')">User Management</button>
            <button class="admin-tab" onclick="switchTab('projects')">Project List</button>
            <button class="admin-tab" onclick="switchTab('pricing')">Pricing Management</button>
            <a href="../views/dashboard.php" class="admin-tab" style="text-decoration: none; display: block; margin-top: 10px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; text-align: center; padding: 12px 15px; border-radius: 5px; font-weight: 500;">
                🔄 Switch to User Panel
            </a>
        </div>

        <div class="admin-content">
            <!-- My Profile Tab -->
            <div id="profileTab" class="tab-content active">
                <h2>My Profile</h2>
                <?php if (isset($_GET['first_login'])): ?>
                    <div class="alert-box alert-warning">
                        <strong style="color: #ffc107;">⚠️ First Login Detected</strong><br>
                        <span style="color: #ffc107;">This is your first login with default credentials. For security, please update your username and password immediately.</span>
                    </div>
                <?php endif; ?>
                <p style="color: #fff;">Manage your account settings. Update your username, mobile number, and password. All admins can update their own profiles.</p>
                
                <div style="max-width: 600px; margin-top: 20px;">
                    <form id="profileForm" onsubmit="updateProfile(event)">
                        <div class="form-group-admin">
                            <label style="color: #fff !important;">Username:</label>
                            <input type="text" id="profile_username" name="username" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" required style="color: #fff !important; background: #1a1a1a !important;">
                        </div>
                        
                        <div class="form-group-admin">
                            <label style="color: #fff !important;">Mobile Number:</label>
                            <?php 
                            $current_user = $userModel->getUserById($_SESSION['user_id']);
                            ?>
                            <input type="text" id="profile_mobile" name="mobile_number" value="<?php echo htmlspecialchars($current_user['mobile_number'] ?? ''); ?>" style="color: #fff !important; background: #1a1a1a !important;">
                        </div>
                        
                        <div class="form-group-admin">
                            <label style="color: #fff !important;">New Password (leave blank to keep current):</label>
                            <input type="password" id="profile_password" name="password" placeholder="Enter new password" style="color: #fff !important; background: #1a1a1a !important;">
                            <small style="color: #ccc !important;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group-admin">
                            <label style="color: #fff !important;">Confirm New Password:</label>
                            <input type="password" id="profile_confirm_password" name="confirm_password" placeholder="Confirm new password" style="color: #fff !important; background: #1a1a1a !important;">
                        </div>
                        
                        <div class="form-group-admin">
                            <button type="submit" class="btn-admin btn-success-admin">Update Profile</button>
                        </div>
                        
                        <div id="profileMessage" style="margin-top: 15px;"></div>
                    </form>
                </div>
            </div>

            <!-- User Management Tab -->
            <div id="usersTab" class="tab-content">
                <h2>User Management</h2>
                <p style="color: #fff;">View all users and manage admin privileges. You can grant or revoke admin access for any user.</p>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Mobile Number</th>
                            <th>Admin Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="color: #fff !important;"><?php echo $u['id']; ?></td>
                            <td style="color: #fff !important;"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td style="color: #fff !important;"><?php echo htmlspecialchars($u['mobile_number']); ?></td>
                            <td style="color: #fff !important;"><?php echo $u['is_admin'] ? '<span style="color: #28a745 !important; font-weight: 600;">Admin</span>' : '<span style="color: #fff !important;">User</span>'; ?></td>
                            <td style="color: #fff !important;"><?php echo $u['created_at']; ?></td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-admin <?php echo $u['is_admin'] ? 'btn-danger-admin' : 'btn-success-admin'; ?>" 
                                            onclick="toggleAdmin(<?php echo $u['id']; ?>, <?php echo $u['is_admin'] ? 0 : 1; ?>)">
                                        <?php echo $u['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color: #ff6b35 !important; font-weight: 600;">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Project List Tab -->
            <div id="projectsTab" class="tab-content">
                <h2>All Projects</h2>
                <p style="color: #fff;">View all projects created by users. Click "View" to see project details and configurations.</p>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project Name</th>
                            <th>Created By</th>
                            <th>Salesman Name</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td style="color: #fff !important;"><?php echo $p['id']; ?></td>
                            <td style="color: #fff !important;"><?php echo htmlspecialchars($p['project_name']); ?></td>
                            <td style="color: #fff !important;"><?php 
                                $creator = $userModel->getUserById($p['user_id']);
                                echo htmlspecialchars($creator['username'] ?? 'Unknown');
                            ?></td>
                            <td style="color: #fff !important;"><?php echo htmlspecialchars($p['salesman_name']); ?></td>
                            <td style="color: #fff !important;"><?php echo $p['created_at']; ?></td>
                            <td>
                                <a href="admin_project_view.php?project_id=<?php echo $p['id']; ?>" target="_blank" class="btn-admin btn-primary-admin">View Invoice</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pricing Management Tab -->
            <div id="pricingTab" class="tab-content">
                <h2>Pricing Management</h2>
                <p style="color: #fff; margin-bottom: 30px;">Manage AWS service pricing. Prices are stored in the database and used for all calculations. Click on any service to manage its pricing.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">EC2 Instance Pricing</h3>
                        <a href="admin_pricing.php?service=ec2" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage EC2 Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">EBS Volume Pricing</h3>
                        <a href="admin_pricing.php?service=ebs" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage EBS Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">S3 Storage Pricing</h3>
                        <a href="admin_pricing.php?service=s3" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage S3 Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">RDS Instance Pricing</h3>
                        <a href="admin_pricing.php?service=rds" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage RDS Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">VPC Pricing</h3>
                        <a href="admin_pricing.php?service=vpc" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage VPC Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">WAF Pricing</h3>
                        <a href="admin_pricing.php?service=waf" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage WAF Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">Load Balancer Pricing</h3>
                        <a href="admin_pricing.php?service=lb" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage Load Balancer Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">EKS Pricing</h3>
                        <a href="admin_pricing.php?service=eks" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage EKS Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">ECR Pricing</h3>
                        <a href="admin_pricing.php?service=ecr" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage ECR Pricing</a>
                    </div>
                    
                    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 2px solid rgba(255, 107, 53, 0.3);">
                        <h3 style="color: #fff; margin-bottom: 15px; font-size: 18px;">Route53 Pricing</h3>
                        <a href="admin_pricing.php?service=route53" class="btn-admin btn-primary-admin" style="width: 100%; text-align: center;">Manage Route53 Pricing</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update sidebar tabs
            document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
            const tabButtons = document.querySelectorAll('.admin-tab');
            const tabMap = {
                'profile': 0,
                'users': 1,
                'projects': 2,
                'pricing': 3
            };
            
            if (tabMap.hasOwnProperty(tab)) {
                tabButtons[tabMap[tab]].classList.add('active');
            }
            
            // Update content tabs
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            const contentId = tab + 'Tab';
            const content = document.getElementById(contentId);
            if (content) {
                content.classList.add('active');
            }
            
        }
        
        function toggleAdmin(userId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus ? 'grant' : 'revoke') + ' admin privileges?')) {
                fetch('../controllers/admin_controller.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=toggle_admin&user_id=' + userId + '&is_admin=' + newStatus
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
        
        function updateProfile(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'update_profile');
            
            const messageDiv = document.getElementById('profileMessage');
            messageDiv.innerHTML = '<div style="color: #ff6b35; padding: 10px;">Updating profile...</div>';
            
            fetch('../controllers/admin_profile_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<div class="alert-box alert-success">' + data.message + '</div>';
                    
                    // Clear password fields
                    document.getElementById('profile_password').value = '';
                    document.getElementById('profile_confirm_password').value = '';
                    
                    // Reload after 1.5 seconds to show updated username and remove first_login flag
                    setTimeout(() => {
                        window.location.href = '../views/admin_dashboard.php';
                    }, 1500);
                } else {
                    messageDiv.innerHTML = '<div class="alert-box alert-danger">Error: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<div class="alert-box alert-danger">Error: ' + error.message + '</div>';
            });
        }
        
        function toggleProfileMenu(event) {
            if (event) {
                event.stopPropagation();
            }
            const dropdown = document.querySelector('.user-profile-dropdown');
            const menu = document.getElementById('profileMenu');
            dropdown.classList.toggle('active');
            menu.classList.toggle('active');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.user-profile-dropdown');
            const menu = document.getElementById('profileMenu');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
                menu.classList.remove('active');
            }
        });
    </script>
    
    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; 2025 Cloudly Infotech Limited. All rights reserved.</p>
            <p>Bangladesh's First Premier AWS & GCP Partner</p>
        </div>
    </footer>
</body>
</html>




