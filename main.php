<?php
session_start();
require_once 'database.inc.php';
require_once 'functions.php';

// Get current page from URL parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'guest';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Handle logout
if ($page === 'logout') {
    session_destroy();
    header('Location: main.php');
    exit;
}

// Get user information if logged in
$user_info = null;
if ($user_id) {
    $user_info = getUserInfo($pdo, $user_id);
}

// Get page title
function getPageTitle($page) {
    $titles = [
        'home' => 'Home',
        'search' => 'Search Flats',
        'about' => 'About Us',
        'register' => 'Register',
        'login' => 'Login',
        'profile' => 'User Profile',
        'messages' => 'Messages',
        'my-rentals' => 'My Rentals',
        'offer-flat' => 'Offer Flat',
        'flat-detail' => 'Flat Details',
        'rent-flat' => 'Rent Flat',
        'preview-appointment' => 'Preview Appointment',
        'manager-inquiry' => 'Flats Inquiry',
        'shopping-basket' => 'Shopping Basket',
        'pending-approvals' => 'Pending Approvals'
    ];
    return isset($titles[$page]) ? $titles[$page] : 'Birzeit Flat Rent';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getPageTitle($page); ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page-container">
        <div class="sidebar">
            <!-- Sidebar content will go here -->
            <ul>
                <li><a href="main.php" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a></li>
                <li><a href="main.php?page=search" class="<?php echo $page === 'search' ? 'active' : ''; ?>">Search Flats</a></li>
                <?php if ($user_type === 'guest'): ?>
                    <li><a href="main.php?page=register&type=customer" class="<?php echo $page === 'register' ? 'active' : ''; ?>">Register as Customer</a></li>
                    <li><a href="main.php?page=register&type=owner" class="<?php echo $page === 'register' ? 'active' : ''; ?>">Register as Owner</a></li>
                    <li><a href="main.php?page=login" class="<?php echo $page === 'login' ? 'active' : ''; ?>">Login</a></li>
                <?php endif; ?>
                <?php if ($user_type === 'customer'): ?>
                    <li><a href="main.php?page=my-rentals" class="<?php echo $page === 'my-rentals' ? 'active' : ''; ?>">My Rentals</a></li>
                    <li><a href="main.php?page=messages" class="<?php echo $page === 'messages' ? 'active' : ''; ?>">Messages</a></li>
                <?php endif; ?>
                <?php if ($user_type === 'owner'): ?>
                    <li><a href="main.php?page=offer-flat" class="<?php echo $page === 'offer-flat' ? 'active' : ''; ?>">Offer Flat</a></li>
                    <li><a href="main.php?page=my-flats" class="<?php echo $page === 'my-flats' ? 'active' : ''; ?>">My Flats</a></li>
                    <li><a href="main.php?page=messages" class="<?php echo $page === 'messages' ? 'active' : ''; ?>">Messages</a></li>
                <?php endif; ?>
                <?php if ($user_type === 'manager'): ?>
                    <li><a href="main.php?page=manager-inquiry" class="<?php echo $page === 'manager-inquiry' ? 'active' : ''; ?>">Flats Inquiry</a></li>
                    <li><a href="main.php?page=pending-approvals" class="<?php echo $page === 'pending-approvals' ? 'active' : ''; ?>">Pending Approvals</a></li>
                    <li><a href="main.php?page=messages" class="<?php echo $page === 'messages' ? 'active' : ''; ?>">Messages</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="content-area">
            <!-- Header Section -->
            <header>
                <div class="header-left">
                    <img src="images/rent.avif" alt="Birzeit Flat Rent" class="logo">
                    <div>
                        <h1 class="company-name">Birzeit Flat Rent</h1>
                        <p>Your Trusted Flat Rental Partner</p>
                    </div>
                </div>
                
                <div class="header-right">
                    <a href="main.php?page=about" class="about-link">About Us</a>
                    
                    <?php if ($user_info): ?>
                        <!-- User Card -->
                        <div class="user-card <?php echo $user_type; ?>">
                            <img src="<?php echo $user_info['photo'] ?? 'images/default-user.png'; ?>" 
                                 alt="User Photo" class="user-photo">
                            <div>
                                <div><?php echo htmlspecialchars($user_info['name']); ?></div>
                                <small><?php echo ucfirst($user_type); ?></small>
                            </div>
                            <a href="main.php?page=profile" class="profile-link">Profile</a>
                        </div>
                        
                        <?php if ($user_type === 'customer'): ?>
                            <a href="main.php?page=shopping-basket" class="shopping-basket">
                                🛒 Basket
                            </a>
                        <?php endif; ?>
                        
                        <a href="main.php?page=logout" class="btn btn-danger">Logout</a>
                    <?php else: ?>
                        <a href="main.php?page=register&type=customer" class="btn btn-success">Register as Customer</a>
                        <a href="main.php?page=register&type=owner" class="btn btn-success">Register as Owner</a>
                        <a href="main.php?page=login" class="btn btn-primary">Login</a>
                    <?php endif; ?>
                </div>
            </header>
            
            <!-- Navigation Section -->
            <!-- Moved to sidebar for a cleaner main content area if a sidebar is present -->
            
            <!-- Main Content Area -->
            <main>
                <?php
                // Include the appropriate page content
                $page_path = "Pages/$page.php";
                if (file_exists($page_path)) {
                    include $page_path;
                } else {
                    include 'Pages/404.php';
                }
                ?>
            </main>
            
            <!-- Footer Section -->
            <footer>
                <div class="footer-content">
                    <div>
                        <img src="images/logo-small.png" alt="Birzeit Flat Rent" class="footer-logo">
                    </div>
                    <div>
                        <p>&copy; <?php echo date('Y'); ?> Birzeit Flat Rent.</p>
                        <p>📍 Ramallah, Palestine | 📞 +568082707 | ✉️ Moathcompany@birzeitflat.com</p>
                    </div>
                    <div>
                        <a href="main.php?page=contact" class="contact-link">Contact Us</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>