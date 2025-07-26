<?php
// Flat detail page
$flat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$flat_id) {
    header('Location: main.php?page=search');
    exit;
}

// Verify database connection and check flat_photos table
try {
    // Check if the flat exists
    $flat_check_sql = "SELECT f.*, o.owner_id 
                       FROM flats f 
                       JOIN owners o ON f.owner_id = o.owner_id 
                       WHERE f.flat_id = :flat_id";
    $flat_check_stmt = executeQuery($pdo, $flat_check_sql, [':flat_id' => $flat_id]);
    $flat_exists = $flat_check_stmt->fetch();
    echo "<!-- Debug: Flat check result: " . ($flat_exists ? "Found" : "Not found") . " -->\n";
    
    // Get all photos for debugging
    $all_photos_sql = "SELECT flat_id, photo_path, is_main_photo FROM flat_photos ORDER BY flat_id";
    $all_photos_stmt = executeQuery($pdo, $all_photos_sql, []);
    $all_photos = $all_photos_stmt->fetchAll();
    echo "<!-- Debug: All photos in database: " . htmlspecialchars(json_encode($all_photos)) . " -->\n";
    
    // Get specific flat photos
    $test_sql = "SELECT COUNT(*) FROM flat_photos WHERE flat_id = :flat_id";
    $test_stmt = executeQuery($pdo, $test_sql, [':flat_id' => $flat_id]);
    $photo_count = $test_stmt->fetchColumn();
    echo "<!-- Debug: Database connection test successful. Found {$photo_count} photos for flat {$flat_id} -->\n";
} catch (PDOException $e) {
    echo "<!-- Debug: Database error: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

// Get flat details
$flat = getFlatDetails($pdo, $flat_id);
if (!$flat) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'owner') {
        $_SESSION['error_message'] = "You don't have access to this flat or it doesn't exist.";
        header('Location: main.php?page=my-flats');
    } else {
        header('Location: main.php?page=search');
    }
    exit;
}

// Get flat photos
$photos = getFlatPhotos($pdo, $flat_id);

// Get marketing information
$marketing_info = getFlatMarketing($pdo, $flat_id);

// Check if flat is available for rent
$is_available = $flat['status'] === 'approved';
$rental_check_sql = "SELECT * FROM rentals 
                     WHERE flat_id = :flat_id 
                     AND status IN ('active', 'confirmed') 
                     AND CURDATE() BETWEEN rental_start_date AND rental_end_date";
$rental_stmt = executeQuery($pdo, $rental_check_sql, [':flat_id' => $flat_id]);
$current_rental = $rental_stmt->fetch();

if ($current_rental) {
    $is_available = false;
}

// Handle add to basket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_basket'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
        $_SESSION['redirect_after_login'] = "main.php?page=flat-detail&id=$flat_id";
        header('Location: main.php?page=login');
        exit;
    }
    
    $customer_id = $_SESSION['customer_id'];
    if (addToBasket($pdo, $customer_id, $flat_id)) {
        $basket_message = "Flat added to your basket successfully!";
    } else {
        $basket_message = "This flat is already in your basket.";
    }
}
?>

<div class="flat-detail-page">
    <!-- Flat Header -->
    <div class="flat-header">
        <div class="flat-title">
            <h1>Flat <?php echo htmlspecialchars($flat['flat_reference']); ?></h1>
            <div class="flat-status">
                <?php if ($flat['status'] === 'pending_approval'): ?>
                    <span class="status-pending">⏳ Pending Approval</span>
                <?php elseif ($is_available): ?>
                    <span class="status-available">✅ Available for Rent</span>
                <?php else: ?>
                    <span class="status-unavailable">❌ Currently Rented</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flat-price">
            <span class="price"><?php echo formatCurrency($flat['monthly_cost']); ?></span>
            <span class="period">per month</span>
        </div>
    </div>

    <?php if (isset($basket_message)): ?>
        <div class="basket-message">
            <p><?php echo htmlspecialchars($basket_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="flat-content">
        <!-- Flat Card - Photos and Description -->
        <div class="flatcard">
            <!-- Photos Section -->
            <div class="flat-photos">
                <?php 
                echo "<!-- Debug: Displaying photos for flat ID: " . $flat_id . " -->\n";
                echo "<!-- Debug: Number of photos found: " . count($photos) . " -->\n";
                
                // Debug: Print the entire photos array
                echo "<!-- Debug: Photos data: " . htmlspecialchars(print_r($photos, true)) . " -->\n";
                
                if (!empty($photos)): 
                    echo "<!-- Debug: Photos array is not empty -->\n";
                ?>
                    <div class="photo-gallery">
                        <?php foreach (
                            $photos as $index => $photo): 
                            $photo_url = 'uploads/flats/' . $photo['photo_path'];
                        ?>
                            <figure class="photo-item <?php echo $index === 0 ? 'main-photo' : ''; ?>">
                                <a href="<?php echo htmlspecialchars($photo_url); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Flat Photo <?php echo $index + 1; ?>">
                                </a>
                                <figcaption>
                                    <?php echo $photo['is_main_photo'] ? 'Main Photo' : 'Photo ' . ($index + 1); ?>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php else: 
                    echo "<!-- Debug: No photos found for flat ID: " . $flat_id . " -->\n";
                ?>
                    <figure class="no-photos">
                        <img src="images/default-flat.jpg" alt="No Photos Available">
                        <figcaption>No photos available</figcaption>
                    </figure>
                <?php endif; ?>
            </div>

            <!-- Description Section -->
            <div class="flat-description">
                <h2>Property Details</h2>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>📍 Address:</strong>
                        <span><?php echo htmlspecialchars($flat['address']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>💰 Monthly Rent:</strong>
                        <span><?php echo formatCurrency($flat['monthly_cost']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>🛏️ Bedrooms:</strong>
                        <span><?php echo $flat['bedrooms']; ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>🚿 Bathrooms:</strong>
                        <span><?php echo $flat['bathrooms']; ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>📐 Size:</strong>
                        <span><?php echo $flat['size_sqm']; ?> square meters</span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>📅 Available From:</strong>
                        <span><?php echo formatDate($flat['available_from']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <strong>📅 Available Until:</strong>
                        <span><?php echo formatDate($flat['available_to']); ?></span>
                    </div>
                </div>

                <!-- Amenities -->
                <div class="amenities-section">
                    <h3>🏠 Amenities & Features</h3>
                    <div class="amenities-grid">
                        <div class="amenity <?php echo $flat['furnished'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">🪑</span>
                            <span>Furnished</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_heating'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">🔥</span>
                            <span>Heating System</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_air_conditioning'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">❄️</span>
                            <span>Air Conditioning</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_access_control'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">🔒</span>
                            <span>Access Control</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_parking'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">🚗</span>
                            <span>Parking</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_backyard'] !== 'none' ? 'available' : 'not-available'; ?>">
                            <span class="icon">🌳</span>
                            <span>Backyard (<?php echo ucfirst($flat['has_backyard']); ?>)</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_playground'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">🎮</span>
                            <span>Playground</span>
                        </div>
                        
                        <div class="amenity <?php echo $flat['has_storage'] ? 'available' : 'not-available'; ?>">
                            <span class="icon">📦</span>
                            <span>Storage</span>
                        </div>
                    </div>
                </div>

                <!-- Rental Conditions -->
                <?php if ($flat['rental_conditions']): ?>
                    <div class="conditions-section">
                        <h3>📋 Rental Conditions</h3>
                        <div class="conditions-text">
                            <?php echo nl2br(htmlspecialchars($flat['rental_conditions'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Owner Information -->
                <div class="owner-section">
                    <h3>👤 Property Owner</h3>
                    <div class="owner-card">
                        <div class="owner-info">
                            <h4><?php echo htmlspecialchars($flat['owner_name']); ?></h4>
                            <p class="owner-location">📍 <?php echo htmlspecialchars($flat['owner_city']); ?></p>
                            <div class="contact-info">
                                <a href="tel:<?php echo $flat['owner_mobile']; ?>" class="contact-link">
                                    📞 <?php echo htmlspecialchars($flat['owner_mobile']); ?>
                                </a>
                                <a href="mailto:<?php echo $flat['owner_email']; ?>" class="contact-link">
                                    ✉️ <?php echo htmlspecialchars($flat['owner_email']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($is_available && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer'): ?>
                    <div class="action-buttons">
                        <a href="main.php?page=preview-appointment&flat_id=<?php echo $flat_id; ?>" 
                           class="btn btn-primary">📅 Request Preview Appointment</a>
                        <a href="main.php?page=rent-flat&flat_id=<?php echo $flat_id; ?>" 
                           class="btn btn-success">🏠 Rent This Flat</a>
                        
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="add_to_basket" class="btn btn-secondary">
                                🛒 Add to Basket
                            </button>
                        </form>
                    </div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <div class="login-prompt">
                        <p>Please <a href="main.php?page=login">login</a> as a customer to rent this flat or request a viewing.</p>
                        <a href="main.php?page=register&type=customer" class="btn btn-primary">Register as Customer</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Marketing Information Aside -->
        <aside class="marketing-aside">
            <h3>🗺️ Area Information</h3>
            
            <?php if (!empty($marketing_info)): ?>
                <div class="marketing-content">
                    <?php foreach ($marketing_info as $info): ?>
                        <div class="marketing-item">
                            <h4><?php echo htmlspecialchars($info['title']); ?></h4>
                            <p><?php echo htmlspecialchars($info['description']); ?></p>
                            <?php if ($info['url_link']): ?>
                                <a href="<?php echo htmlspecialchars($info['url_link']); ?>" 
                                   target="_blank" rel="noopener" class="external-link">
                                    🔗 More Information
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="default-marketing">
                    <div class="marketing-item">
                        <h4>🏫 Education</h4>
                        <p>Located near quality schools and Birzeit University</p>
                    </div>
                    
                    <div class="marketing-item">
                        <h4>🛒 Shopping</h4>
                        <p>Close to shopping centers and local markets</p>
                    </div>
                    
                    <div class="marketing-item">
                        <h4>🚌 Transportation</h4>
                        <p>Good public transport connections</p>
                    </div>
                    
                    <div class="marketing-item">
                        <h4>🏥 Healthcare</h4>
                        <p>Medical facilities and pharmacies nearby</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Side Navigation -->
            <div class="side-navigation">
                <h4>Quick Actions</h4>
                <nav class="side-nav">
                    <?php if ($is_available && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer'): ?>
                        <a href="main.php?page=preview-appointment&flat_id=<?php echo $flat_id; ?>" class="nav-item">
                            📅 Request Viewing
                        </a>
                        <a href="main.php?page=rent-flat&flat_id=<?php echo $flat_id; ?>" class="nav-item">
                            🏠 Rent Flat
                        </a>
                    <?php endif; ?>
                    
                    <a href="main.php?page=search" class="nav-item">
                        🔍 Back to Search
                    </a>
                    
                    <a href="javascript:window.print()" class="nav-item">
                        🖨️ Print Details
                    </a>
                    
                    <a href="javascript:window.history.back()" class="nav-item">
                        ↩️ Go Back
                    </a>
                </nav>
            </div>
        </aside>
    </div>
</div>