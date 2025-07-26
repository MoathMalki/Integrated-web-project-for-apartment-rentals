<?php
// Login page
$error_message = '';
$success_message = '';

// Handle demo account filling
if (isset($_POST['fill_demo'])) {
    $email = $_POST['demo_email'] ?? '';
    $password = $_POST['demo_password'] ?? '';
    $_POST['email'] = $email;
    $_POST['password'] = $password;
}

// Check for messages from other pages
if (isset($_SESSION['login_message'])) {
    $success_message = $_SESSION['login_message'];
    unset($_SESSION['login_message']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        $user = authenticateUser($pdo, $email, $password);
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            
            // Set specific ID based on user type
            if ($user['user_type'] === 'customer' && $user['customer_id']) {
                $_SESSION['customer_id'] = $user['customer_id'];
            } elseif ($user['user_type'] === 'owner' && $user['owner_id']) {
                $_SESSION['owner_id'] = $user['owner_id'];
            } elseif ($user['user_type'] === 'manager' && $user['manager_id']) {
                $_SESSION['manager_id'] = $user['manager_id'];
            }
            
            // Remember me functionality
            if ($remember_me) {
                setcookie('remember_email', $email, time() + (86400 * 30), '/'); // 30 days
            } else {
                setcookie('remember_email', '', time() - 3600, '/'); // Delete cookie
            }
            
            // Redirect to intended page or home
            $redirect_to = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'main.php';
            unset($_SESSION['redirect_after_login']);
            
            // Send welcome message
            if ($user['user_type'] !== 'manager') {
                sendMessage($pdo, $user['user_id'], 'Welcome Back!', 
                           "Welcome back to Birzeit Flat Rent, {$user['name']}! We're glad to see you again.");
            }
            
            header("Location: $redirect_to");
            exit;
        } else {
            $error_message = 'Invalid email or password. Please try again.';
        }
    }
}

// Pre-fill email if remembered
$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';
?>

<div class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Login to Your Account</h1>
            <p>Welcome back! Please sign in to continue.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <strong>⚠️ Login Failed</strong><br>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message">
                <strong>✅ Success</strong><br>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="email" class="required">Email Address</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($remembered_email); ?>" 
                       required autofocus>
                <div class="input-icon">📧</div>
            </div>

            <div class="form-group">
                <label for="password" class="required">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="input-icon">🔒</div>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember_me" 
                           <?php echo $remembered_email ? 'checked' : ''; ?>>
                    <span class="checkmark"></span>
                    Remember me for 30 days
                </label>
                
                <a href="main.php?page=forgot-password" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                🚪 Sign In
            </button>
        </form>

        <div class="login-footer">
            <div class="divider">
                <span>New to Birzeit Flat Rent?</span>
            </div>
            
            <div class="register-options">
                <a href="main.php?page=register&type=customer" class="btn btn-outline">
                    👤 Register as Customer
                </a>
                <a href="main.php?page=register&type=owner" class="btn btn-outline">
                    🏠 Register as Owner
                </a>
            </div>
            
            <div class="demo-accounts">
                <h3>Demo Accounts for Testing</h3>
                <div class="demo-grid">
                    <div class="demo-account">
                        <h4>👤 Customer</h4>
                        <p><strong>Email:</strong> customer@test.com</p>
                        <p><strong>Password:</strong> 1password</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="demo_email" value="customer@test.com">
                            <input type="hidden" name="demo_password" value="1password">
                            <button type="submit" name="fill_demo" class="btn btn-small">Use Demo</button>
                        </form>
                    </div>
                    
                    <div class="demo-account">
                        <h4>🏠 Owner</h4>
                        <p><strong>Email:</strong> owner@test.com</p>
                        <p><strong>Password:</strong> 2password</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="demo_email" value="owner@test.com">
                            <input type="hidden" name="demo_password" value="2password">
                            <button type="submit" name="fill_demo" class="btn btn-small">Use Demo</button>
                        </form>
                    </div>
                    
                    <div class="demo-account">
                        <h4>⚙️ Manager</h4>
                        <p><strong>Email:</strong> manager@birzeitflat.com</p>
                        <p><strong>Password:</strong> 3password</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="demo_email" value="manager@birzeitflat.com">
                            <input type="hidden" name="demo_password" value="3password">
                            <button type="submit" name="fill_demo" class="btn btn-small">Use Demo</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Information Sidebar -->
    <aside class="security-info">
        <h3>🔒 Your Security Matters</h3>
        <div class="security-tips">
            <div class="tip">
                <h4>🛡️ Secure Connection</h4>
                <p>All data is encrypted using industry-standard SSL encryption.</p>
            </div>
            
            <div class="tip">
                <h4>🔐 Password Safety</h4>
                <p>We never store your password in plain text. Your account is protected with advanced hashing.</p>
            </div>
            
            <div class="tip">
                <h4>🚫 Privacy Protected</h4>
                <p>We never share your personal information with third parties.</p>
            </div>
            
            <div class="tip">
                <h4>📱 Device Security</h4>
                <p>Always log out when using shared or public computers.</p>
            </div>
        </div>
        
        <div class="help-links">
            <h4>Need Help?</h4>
            <ul>
                <li><a href="main.php?page=contact">Contact Support</a></li>
                <li><a href="main.php?page=faq">Frequently Asked Questions</a></li>
                <li><a href="main.php?page=privacy">Privacy Policy</a></li>
            </ul>
        </div>
    </aside>
</div>