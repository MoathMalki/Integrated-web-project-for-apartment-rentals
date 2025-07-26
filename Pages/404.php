<?php
// Set proper HTTP response code
http_response_code(404);

// No need for session_start() as it's already called in main.php
$page_title = "Page Not Found - 404";
?>

<div class="error-page">
    <div class="error-container">
        <div class="error-graphic">
            <h1 class="error-code">404</h1>
            <div class="error-icon">🏠</div>
        </div>
        
        <div class="error-content">
            <h2>Oops! Page Not Found</h2>
            <p class="error-message">
                The page you're looking for seems to have moved out! 
                Just like finding the perfect flat, sometimes you need to explore a few different options.
            </p>
            
            <div class="error-suggestions">
                <h3>Here's what you can do:</h3>
                <ul>
                    <li>Check the URL for any typos</li>
                    <li>Use the navigation menu to find what you're looking for</li>
                    <li>Search for available flats using our search feature</li>
                    <li>Go back to the homepage and start fresh</li>
                </ul>
            </div>
            
            <div class="error-actions">
                <a href="main.php" class="btn btn-primary">🏠 Go Home</a>
                <a href="main.php?page=search" class="btn btn-secondary">🔍 Search Flats</a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_type'] === 'customer'): ?>
                        <a href="main.php?page=my-rentals" class="btn btn-info">📋 My Rentals</a>
                    <?php elseif ($_SESSION['user_type'] === 'owner'): ?>
                        <a href="main.php?page=my-flats" class="btn btn-info">🏢 My Properties</a>
                    <?php elseif ($_SESSION['user_type'] === 'manager'): ?>
                        <a href="main.php?page=pending-approvals" class="btn btn-info">⏳ Pending Approvals</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="main.php?page=login" class="btn btn-info">🔐 Login</a>
                <?php endif; ?>
            </div>
            
            <div class="help-section">
                <h4>Still need help?</h4>
                <p>Contact our support team:</p>
                <div class="contact-info">
                    <span>📧 support@birzeitflats.com</span>
                    <span>📞 +970 2 298 2000</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="popular-links">
        <h3>Popular Pages</h3>
        <div class="quick-links">
            <a href="main.php?page=search" class="quick-link">
                <span class="link-icon">🔍</span>
                <span class="link-text">Search Flats</span>
            </a>
            
            <a href="main.php?page=about" class="quick-link">
                <span class="link-icon">ℹ️</span>
                <span class="link-text">About Us</span>
            </a>
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="main.php?page=register" class="quick-link">
                    <span class="link-icon">📝</span>
                    <span class="link-text">Register</span>
                </a>
                
                <a href="main.php?page=login" class="quick-link">
                    <span class="link-icon">🔐</span>
                    <span class="link-text">Login</span>
                </a>
            <?php else: ?>
                <?php if ($_SESSION['user_type'] === 'owner'): ?>
                    <a href="main.php?page=offer-flat" class="quick-link">
                        <span class="link-icon">➕</span>
                        <span class="link-text">Add Property</span>
                    </a>
                <?php endif; ?>
                
                <a href="main.php?page=messages" class="quick-link">
                    <span class="link-icon">💬</span>
                    <span class="link-text">Messages</span>
                </a>
                
                <a href="main.php?page=profile" class="quick-link">
                    <span class="link-icon">👤</span>
                    <span class="link-text">Profile</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="back-navigation">
        <button onclick="history.back()" class="btn btn-outline">← Go Back</button>
        <span class="separator">or</span>
        <a href="javascript:location.reload()" class="btn btn-outline">🔄 Refresh Page</a>
    </div>
</div>