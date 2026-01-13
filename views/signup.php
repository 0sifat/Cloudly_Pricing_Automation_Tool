<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Cloudly AWS Ask</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Cloudly AWS Ask</h1>
                <h2>Sign Up</h2>
            </div>
            <form id="signupForm" method="POST" action="../controllers/auth_controller.php">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="mobile_number">Mobile Number</label>
                    <input type="tel" id="mobile_number" name="mobile_number" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">Sign Up</button>
                </div>
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message" id="errorMessage"><?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php if (strpos($_GET['error'], 'already taken') !== false || strpos($_GET['error'], 'already exists') !== false): ?>
                        <script>
                            window.onload = function() {
                                alert('<?php echo htmlspecialchars(addslashes($_GET['error'])); ?>');
                            };
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>






