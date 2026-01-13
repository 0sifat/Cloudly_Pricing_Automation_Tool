<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cloudly AWS Ask</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .login-tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .login-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background: #f5f5f5;
            border: none;
            font-size: 16px;
            transition: all 0.3s;
        }
        .login-tab.active {
            background: #fff;
            border-bottom: 3px solid #007bff;
            font-weight: bold;
        }
        .login-tab:hover {
            background: #e9ecef;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Cloudly AWS Ask - Hello</h1>
                <h2>Login</h2>
            </div>
            
            <div class="login-tabs">
                <button class="login-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'admin') ? 'active' : ''; ?>" onclick="switchTab('user')">User Login</button>
                <button class="login-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'admin') ? 'active' : ''; ?>" onclick="switchTab('admin')">Admin Login</button>
            </div>
            
            <!-- User Login Form -->
            <div id="userLogin" class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] !== 'admin') ? 'active' : ''; ?>">
                <form id="loginForm" method="POST" action="../controllers/auth_controller.php">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Login</button>
                    </div>
                    <div class="form-footer">
                        <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                    </div>
                    <?php if (isset($_GET['error']) && (!isset($_GET['tab']) || $_GET['tab'] !== 'admin')): ?>
                        <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Admin Login Form -->
            <div id="adminLogin" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'admin') ? 'active' : ''; ?>">
                <form id="adminLoginForm" method="POST" action="../controllers/admin_auth_controller.php">
                    <input type="hidden" name="action" value="admin_login">
                    <div class="form-group">
                        <label for="admin_username">Admin Username</label>
                        <input type="text" id="admin_username" name="username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="password" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Login as Admin</button>
                    </div>
                    <?php
                    // Show first-time login hint only if sharif doesn't exist
                    require_once __DIR__ . '/../config/database.php';
                    require_once __DIR__ . '/../models/User.php';
                    $userModel = new User();
                    if (!$userModel->userExists('sharif')):
                    ?>
                    <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2196F3; font-size: 12px; color: #1976D2;">
                        <strong>First time setup:</strong> Use username: <code>sharif</code> and password: <code>password@</code>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error']) && isset($_GET['tab']) && $_GET['tab'] === 'admin'): ?>
                        <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // Update tabs
            document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            if (tab === 'user') {
                document.querySelector('.login-tab:first-child').classList.add('active');
                document.getElementById('userLogin').classList.add('active');
            } else {
                document.querySelector('.login-tab:last-child').classList.add('active');
                document.getElementById('adminLogin').classList.add('active');
            }
        }
        
        // Auto-switch to admin tab if tab=admin in URL
        <?php if (isset($_GET['tab']) && $_GET['tab'] === 'admin'): ?>
            switchTab('admin');
        <?php endif; ?>
    </script>
</body>
</html>






