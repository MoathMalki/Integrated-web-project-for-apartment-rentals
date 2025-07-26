<?php
// My Flats page - Owner's flat management
requireUserType('owner');

$owner_id = $_SESSION['owner_id'];

// Clear any old sorting cookies
if (isset($_COOKIE['sort_column']) && $_COOKIE['sort_column'] === 'rent_cost_per_month') {
    setcookie('sort_column', '', time() - 3600, '/');
    setcookie('sort_order', '', time() - 3600, '/');
}

// Get sorting preferences
$sort_prefs = getSortingPreference();
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_date';
$current_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// If the sort column is still the old name, update it
if ($current_sort === 'rent_cost_per_month') {
    $current_sort = 'monthly_cost';
} elseif ($current_sort === 'created_at') {
    $current_sort = 'created_date';
}

// Handle sorting
if (isset($_GET['sort'])) {
    $new_order = ($current_sort === $_GET['sort'] && $current_order === 'ASC') ? 'DESC' : 'ASC';
    setSortingPreference($_GET['sort'], $new_order);
    
    $url_params = $_GET;
    $url_params['sort'] = $_GET['sort'];
    $url_params['order'] = $new_order;
    unset($url_params['page']);
    
    header('Location: main.php?page=my-flats&' . http_build_query($url_params));
    exit;
}

// Get owner's flats with rental information
$flats_sql = "SELECT f.*, 
                     COUNT(fp.photo_id) as photo_count,
                     r.rental_id, r.customer_id, r.rental_start_date, r.rental_end_date, 
                     r.status as rental_status, r.total_amount,
                     uc.name as customer_name, uc.email as customer_email,
                     CASE 
                         WHEN r.rental_id IS NULL THEN 'available'
                         WHEN CURDATE() < r.rental_start_date THEN 'upcoming'
                         WHEN CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN 'current'
                         ELSE 'past'
                     END as current_rental_status,
                     COUNT(pt.timetable_id) as total_appointments,
                     COUNT(CASE WHEN pt.is_booked = 1 THEN 1 END) as booked_appointments
              FROM flats f 
              LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id
              LEFT JOIN rentals r ON f.flat_id = r.flat_id AND r.status IN ('confirmed', 'active', 'completed')
              LEFT JOIN customers c ON r.customer_id = c.customer_id
              LEFT JOIN users uc ON c.user_id = uc.user_id
              LEFT JOIN preview_timetables pt ON f.flat_id = pt.flat_id
              WHERE f.owner_id = :owner_id 
              GROUP BY f.flat_id
              ORDER BY f.$current_sort $current_order";

$flats = executeQuery($pdo, $flats_sql, [':owner_id' => $owner_id])->fetchAll();

// Categorize flats
$pending_flats = array_filter($flats, function($flat) {
    return $flat['status'] === 'pending_approval';
});

$approved_flats = array_filter($flats, function($flat) {
    return $flat['status'] === 'approved';
});

$rented_flats = array_filter($flats, function($flat) {
    return $flat['current_rental_status'] !== 'available' && $flat['rental_id'];
});

$available_flats = array_filter($flats, function($flat) {
    return $flat['status'] === 'approved' && $flat['current_rental_status'] === 'available';
});

// Get statistics
$total_flats = count($flats);
$total_pending = count($pending_flats);
$total_approved = count($approved_flats);
$total_rented = count($rented_flats);
$total_available = count($available_flats);

// Calculate total revenue
$total_revenue = 0;
foreach ($rented_flats as $flat) {
    if ($flat['current_rental_status'] === 'current') {
        $total_revenue += $flat['monthly_cost'];
    }
}
?>

<div class="my-flats-page">
    <div class="page-header">
        <h1>🏢 My Properties</h1>
        <p>Manage your flat listings and rental activity</p>
    </div>

    <!-- Property Statistics -->
    <div class="flats-stats">
        <div class="stat-card total">
            <h3><?php echo $total_flats; ?></h3>
            <p>Total Properties</p>
            <span class="stat-icon">🏢</span>
        </div>
        <div class="stat-card pending">
            <h3><?php echo $total_pending; ?></h3>
            <p>Pending Approval</p>
            <span class="stat-icon">⏳</span>
        </div>
        <div class="stat-card approved">
            <h3><?php echo $total_approved; ?></h3>
            <p>Approved & Live</p>
            <span class="stat-icon">✅</span>
        </div>
        <div class="stat-card rented">
            <h3><?php echo $total_rented; ?></h3>
            <p>Currently Rented</p>
            <span class="stat-icon">🔑</span>
        </div>
        <div class="stat-card available">
            <h3><?php echo $total_available; ?></h3>
            <p>Available for Rent</p>
            <span class="stat-icon">🔓</span>
        </div>
        <div class="stat-card revenue">
            <h3><?php echo formatCurrency($total_revenue); ?></h3>
            <p>Monthly Revenue</p>
            <span class="stat-icon">💰</span>
        </div>
    </div>

    <?php if (empty($flats)): ?>
        <div class="no-flats">
            <div class="empty-state">
                <h2>🏠 No Properties Listed Yet</h2>
                <p>You haven't listed any properties for rent yet. Start by adding your first flat!</p>
                
                <div class="benefits-info">
                    <h3>Why list your property with us?</h3>
                    <ul>
                        <li>🎯 <strong>Targeted Audience:</strong> Reach customers actively looking for rentals</li>
                        <li>📱 <strong>Easy Management:</strong> Manage listings and bookings online</li>
                        <li>🔒 <strong>Secure Process:</strong> Verified customers and secure payments</li>
                        <li>💬 <strong>Direct Communication:</strong> Chat directly with potential tenants</li>
                        <li>📊 <strong>Analytics:</strong> Track views and rental performance</li>
                    </ul>
                </div>
                
                <div class="empty-actions">
                    <a href="main.php?page=offer-flat" class="btn btn-primary">➕ List Your First Property</a>
                    <a href="main.php?page=about" class="btn btn-secondary">📖 Learn More</a>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-info">
                <h3>⚡ Quick Actions</h3>
                <p>Manage your properties efficiently with these shortcuts</p>
            </div>
            <div class="actions-buttons">
                <a href="main.php?page=offer-flat" class="btn btn-success">➕ Add New Property</a>
                <a href="main.php?page=messages" class="btn btn-info">💬 Check Messages</a>
                <a href="main.php?page=my-flats&category=all" class="btn btn-secondary category-btn<?php echo (!isset($_GET['category']) || $_GET['category'] === 'all') ? ' active' : ''; ?>">📊 All Properties</a>
                <a href="main.php?page=my-flats&category=rented" class="btn btn-warning category-btn<?php echo (isset($_GET['category']) && $_GET['category'] === 'rented') ? ' active' : ''; ?>">🔑 Rented</a>
                <a href="main.php?page=my-flats&category=available" class="btn btn-primary category-btn<?php echo (isset($_GET['category']) && $_GET['category'] === 'available') ? ' active' : ''; ?>">🔓 Available</a>
            </div>
        </div>

        <!-- Properties Table -->
        <div class="flats-section">
            <div class="section-header">
                <h2>📋 Properties Overview</h2>
                <?php
                $category = isset($_GET['category']) ? $_GET['category'] : 'all';
                $displayed_flats = $flats;
                
                if ($category === 'rented') {
                    $displayed_flats = array_filter($flats, function($flat) {
                        return $flat['current_rental_status'] !== 'available' && $flat['rental_id'];
                    });
                } elseif ($category === 'available') {
                    $displayed_flats = array_filter($flats, function($flat) {
                        return $flat['status'] === 'approved' && $flat['current_rental_status'] === 'available';
                    });
                } elseif ($category === 'pending_approval') {
                    $displayed_flats = array_filter($flats, function($flat) {
                        return $flat['status'] === 'pending_approval';
                    });
                }
                ?>
                <p>Showing <?php echo count($displayed_flats); ?> propert<?php echo count($displayed_flats) !== 1 ? 'ies' : 'y'; ?> with sorting and filtering options</p>
            </div>

            <div class="table-container">
                <table class="flats-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=flat_reference&category=<?php echo $category; ?>" class="sort-link">
                                    Reference
                                    <?php if ($current_sort === 'flat_reference'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=location&category=<?php echo $category; ?>" class="sort-link">
                                    Location
                                    <?php if ($current_sort === 'location'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=monthly_cost&category=<?php echo $category; ?>" class="sort-link">
                                    Monthly Cost
                                    <?php if ($current_sort === 'monthly_cost'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Property Details</th>
                            <th>Rental Status</th>
                            <th>Customer</th>
                            <th>
                                <a href="?sort=status&category=<?php echo $category; ?>" class="sort-link">
                                    Approval Status
                                    <?php if ($current_sort === 'status'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayed_flats as $flat): ?>
                            <tr class="flat-row status-<?php echo $flat['status']; ?> rental-<?php echo $flat['current_rental_status']; ?>" data-category="<?php echo $flat['current_rental_status']; ?>">
                                <td>
                                    <?php if ($flat['flat_reference']): ?>
                                        <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" 
                                           class="flat-reference-link" target="_blank">
                                            <?php echo htmlspecialchars($flat['flat_reference']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="pending-ref">Pending Approval</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="location-info">
                                        <strong><?php echo htmlspecialchars($flat['location']); ?></strong>
                                        <small><?php echo htmlspecialchars(substr($flat['address'], 0, 50)) . '...'; ?></small>
                                    </div>
                                </td>
                                <td><?php echo formatCurrency($flat['monthly_cost']); ?></td>
                                <td>
                                    <div class="property-details">
                                        <span>🛏️ <?php echo $flat['bedrooms']; ?> bed</span>
                                        <span>🚿 <?php echo $flat['bathrooms']; ?> bath</span>
                                        <span>📐 <?php echo $flat['size_sqm']; ?>m²</span>
                                        <div class="media-info">
                                            <span>📷 <?php echo $flat['photo_count']; ?> photos</span>
                                            <span>📅 <?php echo $flat['total_appointments']; ?> slots</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="rental-status">
                                        <span class="status-badge rental-<?php echo $flat['current_rental_status']; ?>">
                                            <?php 
                                            switch($flat['current_rental_status']) {
                                                case 'available': echo '🔓 Available'; break;
                                                case 'current': echo '🔑 Rented'; break;
                                                case 'upcoming': echo '⏰ Upcoming'; break;
                                                case 'past': echo '📋 Past'; break;
                                            }
                                            ?>
                                        </span>
                                        <?php if ($flat['rental_start_date']): ?>
                                            <small><?php echo formatDate($flat['rental_start_date']); ?> - <?php echo formatDate($flat['rental_end_date']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($flat['customer_name']): ?>
                                        <a href="main.php?page=user-info&id=<?php echo $flat['customer_id']; ?>" class="customer-link" target="_blank">
                                            <?php echo htmlspecialchars($flat['customer_name']); ?>
                                        </a>
                                        <?php if ($flat['current_rental_status'] === 'current'): ?>
                                            <small class="revenue-info">💰 <?php echo formatCurrency($flat['monthly_cost']); ?>/month</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-customer">Not Rented</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge approval-<?php echo $flat['status']; ?>">
                                        <?php 
                                        switch($flat['status']) {
                                            case 'pending_approval': echo '⏳ Pending'; break;
                                            case 'approved': echo '✅ Approved'; break;
                                            case 'rejected': echo '❌ Rejected'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" 
                                           class="btn btn-small btn-info" target="_blank" title="View Details">
                                            👁️
                                        </a>
                                        
                                        <?php if ($flat['status'] === 'approved'): ?>
                                            <a href="main.php?page=my-flats&category=<?php echo $category; ?>&details=<?php echo $flat['flat_id']; ?>" class="details-link">
                                                <?php echo (isset($_GET['details']) && $_GET['details'] == $flat['flat_id']) ? 'Hide Details' : 'Show Details'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php if (isset($_GET['details']) && $_GET['details'] == $flat['flat_id']): ?>
                            <tr id="details-<?php echo $flat['flat_id']; ?>" class="details-row">
                                <td colspan="8">
                                    <div class="expanded-details">
                                        <div class="details-grid">
                                            <div class="detail-section">
                                                <h4>📍 Property Information</h4>
                                                <p><strong>Full Address:</strong> <?php echo htmlspecialchars($flat['address']); ?></p>
                                                <p><strong>Available Period:</strong> <?php echo formatDate($flat['available_from']); ?> to <?php echo formatDate($flat['available_to']); ?></p>
                                                <p><strong>Features:</strong> 
                                                    <?php if ($flat['furnished']): ?>🪑 Furnished <?php endif; ?>
                                                    <?php if ($flat['has_parking']): ?>🚗 Parking <?php endif; ?>
                                                    <?php if ($flat['has_heating']): ?>🔥 Heating <?php endif; ?>
                                                    <?php if ($flat['has_air_conditioning']): ?>❄️ AC <?php endif; ?>
                                                </p>
                                            </div>
                                            
                                            <div class="detail-section">
                                                <h4>📊 Activity Summary</h4>
                                                <p><strong>Photos:</strong> <?php echo $flat['photo_count']; ?> uploaded</p>
                                                <p><strong>Appointments:</strong> <?php echo $flat['booked_appointments']; ?>/<?php echo $flat['total_appointments']; ?> booked</p>
                                                <p><strong>Listed:</strong> <?php echo formatDate($flat['created_at']); ?></p>
                                                <p><strong>Last Updated:</strong> <?php echo formatDate($flat['updated_at']); ?></p>
                                            </div>
                                            
                                            <?php if ($flat['rental_id']): ?>
                                                <div class="detail-section">
                                                    <h4>💰 Rental Information</h4>
                                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($flat['customer_name']); ?></p>
                                                    <p><strong>Rental Period:</strong> <?php echo formatDate($flat['rental_start_date']); ?> - <?php echo formatDate($flat['rental_end_date']); ?></p>
                                                    <p><strong>Total Amount:</strong> <?php echo formatCurrency($flat['total_amount']); ?></p>
                                                    <p><strong>Status:</strong> <?php echo ucfirst($flat['rental_status']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-section">
            <h3>📈 Performance Summary</h3>
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>🎯 Listing Performance</h4>
                    <div class="performance-stats">
                        <div class="stat">
                            <span class="stat-label">Approval Rate:</span>
                            <span class="stat-value"><?php echo $total_flats ? round(($total_approved / $total_flats) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Occupancy Rate:</span>
                            <span class="stat-value"><?php echo $total_approved ? round(($total_rented / $total_approved) * 100, 1) : 0; ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <h4>💰 Revenue Overview</h4>
                    <div class="revenue-stats">
                        <div class="stat">
                            <span class="stat-label">Current Monthly:</span>
                            <span class="stat-value"><?php echo formatCurrency($total_revenue); ?></span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Average Rent:</span>
                            <span class="stat-value"><?php 
                                $avg_rent = $total_flats ? array_sum(array_column($flats, 'monthly_cost')) / $total_flats : 0;
                                echo formatCurrency($avg_rent); 
                            ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <h4>📅 Next Actions</h4>
                    <div class="next-actions">
                        <?php if ($total_pending > 0): ?>
                            <p>⏳ <?php echo $total_pending; ?> property<?php echo $total_pending !== 1 ? 'ies' : 'y'; ?> pending approval</p>
                        <?php endif; ?>
                        
                        <?php if ($total_available > 0): ?>
                            <p>🔓 <?php echo $total_available; ?> property<?php echo $total_available !== 1 ? 'ies' : 'y'; ?> available for rent</p>
                        <?php endif; ?>
                        
                        <?php if ($total_rented > 0): ?>
                            <p>💬 Check messages for rental inquiries</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Customer Card Modal -->
<div id="customerModal" class="modal" onclick="closeCustomerModal()">
    <div class="modal-content customer-card-modal" onclick="event.stopPropagation()">
        <span class="close" onclick="closeCustomerModal()">&times;</span>
        <div class="customer-card-content">
            <div class="customer-header">
                <h3 id="customerName"></h3>
                <p>Customer</p>
            </div>
            <div class="customer-contact">
                <div class="contact-item">
                    <span class="contact-icon">✉️</span>
                    <a id="customerEmail" href="#" class="contact-link"></a>
                </div>
            </div>
        </div>
    </div>
</div>
