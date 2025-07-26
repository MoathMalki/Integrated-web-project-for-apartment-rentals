<?php
// Home page - Display promotional materials and featured flats

// Get featured flats (latest approved flats)
$featured_sql = "SELECT f.*, u.name as owner_name, fp.photo_path as main_photo
                 FROM flats f 
                 JOIN owners o ON f.owner_id = o.owner_id 
                 JOIN users u ON o.user_id = u.user_id 
                 LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id AND fp.is_main_photo = 1
                 WHERE f.flat_id IN (7, 8, 9, 10, 11)
                 ORDER BY f.created_date DESC";

$featured_flats = executeQuery($pdo, $featured_sql)->fetchAll();

// Get statistics
$stats_sql = "SELECT 
                 (SELECT COUNT(*) FROM flats WHERE status = 'approved') as total_flats,
                 (SELECT COUNT(*) FROM customers) as total_customers,
                 (SELECT COUNT(*) FROM owners) as total_owners,
                 (SELECT COUNT(*) FROM rentals WHERE status = 'active') as active_rentals";
$stats = executeQuery($pdo, $stats_sql)->fetch();
?>

<div class="home-page">
    <!-- Welcome Section -->
    <section class="welcome-section">
        <div class="welcome-content">
            <h1>Welcome to Birzeit Flat Rent</h1>
            <p class="lead">Your trusted partner for finding the perfect flat in Ramallah and surrounding areas. 
               We connect property owners with potential tenants, making the rental process simple, secure, and transparent.</p>
            
            <div class="cta-buttons">
                <a href="main.php?page=search" class="btn btn-primary btn-large">🔍 Search Flats</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="main.php?page=register" class="btn btn-success btn-large">📝 Register Now</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_flats']; ?></h3>
                <p>Available Flats</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_customers']; ?></h3>
                <p>Happy Customers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_owners']; ?></h3>
                <p>Property Owners</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['active_rentals']; ?></h3>
                <p>Active Rentals</p>
            </div>
        </div>
    </section>

    <!-- Featured Flats Section -->
    <section class="featured-flats">
        <h2>🌟 Recently Added Flats</h2>
        <p>Discover our latest property listings, carefully selected for quality and value.</p>
        
        <?php if (!empty($featured_flats)): ?>
            <div class="flats-grid">
                <?php foreach ($featured_flats as $flat): ?>
                    <div class="flat-card featured">
                        <figure class="flat-image">
                            <?php if ($flat['main_photo']): ?>
                                <img src="<?php echo getUploadedFileUrl('uploads/flats/' . $flat['main_photo']); ?>" 
                                     alt="Flat <?php echo $flat['flat_reference']; ?>">
                            <?php else: ?>
                                <img src="<?php echo getUploadedFileUrl('images/default-flat.jpg'); ?>" alt="No Image Available">
                            <?php endif; ?>
                            <figcaption>
                                <strong><?php echo formatCurrency($flat['monthly_cost']); ?>/month</strong>
                            </figcaption>
                        </figure>
                        
                        <div class="flat-info">
                            <h3>
                                <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" 
                                   class="flat-reference-link">
                                    <?php echo $flat['flat_reference'] ?? 'Pending Approval'; ?>
                                </a>
                            </h3>
                            <p class="location">📍 <?php echo htmlspecialchars($flat['location']); ?></p>
                            <div class="flat-features">
                                <span>🛏️ <?php echo $flat['bedrooms']; ?> bedrooms</span>
                                <span>🚿 <?php echo $flat['bathrooms']; ?> bathrooms</span>
                                <?php if ($flat['furnished']): ?>
                                    <span>🪑 Furnished</span>
                                <?php endif; ?>
                            </div>
                            <p class="owner-info">Owner: <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="view-all">
                <a href="main.php?page=search" class="btn btn-primary">View All Available Flats</a>
            </div>
        <?php else: ?>
            <div class="no-flats">
                <p>No flats are currently available. Please check back later!</p>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'owner'): ?>
                    <a href="main.php?page=offer-flat" class="btn btn-success">List Your Flat</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Services Section -->
    <section class="services-section">
        <h2>Our Services</h2>
        <div class="services-grid">
            <div class="service-card">
                <h3>🔍 Advanced Search</h3>
                <p>Find your perfect flat with our comprehensive search filters including location, price, amenities, and more.</p>
            </div>
            <div class="service-card">
                <h3>📅 Easy Booking</h3>
                <p>Schedule property viewings and complete rental agreements entirely online with our streamlined process.</p>
            </div>
            <div class="service-card">
                <h3>🔒 Secure Transactions</h3>
                <p>All payments and personal information are protected with enterprise-level security measures.</p>
            </div>
            <div class="service-card">
                <h3>💬 24/7 Support</h3>
                <p>Our dedicated support team is available around the clock to assist with any questions or concerns.</p>
            </div>
        </div>
    </section>

    <!-- Quick Actions Section -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="actions-grid">
                <?php if ($_SESSION['user_type'] === 'customer'): ?>
                    <a href="main.php?page=search" class="action-card">
                        <h3>🔍 Search Flats</h3>
                        <p>Find your next home</p>
                    </a>
                    <a href="main.php?page=my-rentals" class="action-card">
                        <h3>🏠 My Rentals</h3>
                        <p>View your rental history</p>
                    </a>
                    <a href="main.php?page=messages" class="action-card">
                        <h3>💬 Messages</h3>
                        <p>Check notifications</p>
                    </a>
                <?php elseif ($_SESSION['user_type'] === 'owner'): ?>
                    <a href="main.php?page=offer-flat" class="action-card">
                        <h3>➕ List New Flat</h3>
                        <p>Add a property for rent</p>
                    </a>
                    <a href="main.php?page=my-flats" class="action-card">
                        <h3>🏢 My Properties</h3>
                        <p>Manage your listings</p>
                    </a>
                    <a href="main.php?page=messages" class="action-card">
                        <h3>💬 Messages</h3>
                        <p>Check booking requests</p>
                    </a>
                <?php elseif ($_SESSION['user_type'] === 'manager'): ?>
                    <a href="main.php?page=pending-approvals" class="action-card">
                        <h3>⏳ Pending Approvals</h3>
                        <p>Review new listings</p>
                    </a>
                    <a href="main.php?page=manager-inquiry" class="action-card">
                        <h3>📊 Flats Inquiry</h3>
                        <p>Search all flats</p>
                    </a>
                    <a href="main.php?page=messages" class="action-card">
                        <h3>💬 Messages</h3>
                        <p>System notifications</p>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>