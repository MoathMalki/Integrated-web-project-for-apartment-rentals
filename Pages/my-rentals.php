<?php
// My Rentals page - View customer's rental history
requireUserType('customer');

$customer_id = $_SESSION['customer_id'];

// Get sorting preferences
$sort_prefs = getSortingPreference();
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'rental_start_date';
$current_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Handle sorting
if (isset($_GET['sort'])) {
    $new_order = ($current_sort === $_GET['sort'] && $current_order === 'ASC') ? 'DESC' : 'ASC';
    setSortingPreference($_GET['sort'], $new_order);
    
    $url_params = $_GET;
    $url_params['sort'] = $_GET['sort'];
    $url_params['order'] = $new_order;
    unset($url_params['page']);
    
    header('Location: main.php?page=my-rentals&' . http_build_query($url_params));
    exit;
}

// Get user rentals with sorting
$rentals_sql = "SELECT r.*, f.flat_reference, f.location, f.monthly_cost, f.address,
                       u.name as owner_name, u.mobile_number as owner_mobile, u.email as owner_email,
                       u.city as owner_city,
                       CASE 
                           WHEN CURDATE() < r.rental_start_date THEN 'upcoming'
                           WHEN CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN 'current'
                           ELSE 'past'
                       END as rental_status
                FROM rentals r 
                JOIN flats f ON r.flat_id = f.flat_id 
                JOIN owners o ON f.owner_id = o.owner_id 
                JOIN users u ON o.user_id = u.user_id 
                WHERE r.customer_id = :customer_id 
                AND r.status IN ('confirmed', 'active', 'completed')
                ORDER BY r.$current_sort $current_order";

$rentals = executeQuery($pdo, $rentals_sql, [':customer_id' => $customer_id])->fetchAll();

// Categorize rentals
$current_rentals = array_filter($rentals, function($rental) {
    return $rental['rental_status'] === 'current';
});

$past_rentals = array_filter($rentals, function($rental) {
    return $rental['rental_status'] === 'past';
});

$upcoming_rentals = array_filter($rentals, function($rental) {
    return $rental['rental_status'] === 'upcoming';
});
?>

<div class="my-rentals-page">
    <div class="page-header">
        <h1>🏠 My Rentals</h1>
        <p>View and manage your rental history and current agreements</p>
    </div>

    <!-- Rental Statistics -->
    <div class="rental-stats">
        <div class="stat-card current">
            <h3><?php echo count($current_rentals); ?></h3>
            <p>Current Rentals</p>
            <span class="stat-icon">🏡</span>
        </div>
        <div class="stat-card upcoming">
            <h3><?php echo count($upcoming_rentals); ?></h3>
            <p>Upcoming Rentals</p>
            <span class="stat-icon">📅</span>
        </div>
        <div class="stat-card past">
            <h3><?php echo count($past_rentals); ?></h3>
            <p>Past Rentals</p>
            <span class="stat-icon">📋</span>
        </div>
        <div class="stat-card total">
            <h3><?php echo count($rentals); ?></h3>
            <p>Total Rentals</p>
            <span class="stat-icon">📊</span>
        </div>
    </div>

    <?php if (empty($rentals)): ?>
        <div class="no-rentals">
            <div class="empty-state">
                <h2>🏠 No Rentals Yet</h2>
                <p>You haven't rented any flats yet. Start by searching for your perfect home!</p>
                <div class="empty-actions">
                    <a href="main.php?page=search" class="btn btn-primary">🔍 Search Available Flats</a>
                    <a href="main.php?page=shopping-basket" class="btn btn-secondary">🛒 Check Your Basket</a>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Current Rentals Section -->
        <?php if (!empty($current_rentals)): ?>
            <section class="rentals-section current-section">
                <h2>🏡 Current Rentals</h2>
                <p>Properties you are currently renting</p>
                
                <div class="rentals-grid">
                    <?php foreach ($current_rentals as $rental): ?>
                        <div class="rental-card current-rental">
                            <div class="rental-header">
                                <h3>
                                    <a href="main.php?page=flat-detail&id=<?php echo $rental['flat_id']; ?>" 
                                       class="flat-reference-link" target="_blank">
                                        <?php echo htmlspecialchars($rental['flat_reference']); ?>
                                    </a>
                                </h3>
                                <span class="status-badge current">✅ Active</span>
                            </div>
                            
                            <div class="rental-details">
                                <p class="location">📍 <?php echo htmlspecialchars($rental['location']); ?></p>
                                <p class="address"><?php echo htmlspecialchars($rental['address']); ?></p>
                                <p class="rent">💰 <?php echo formatCurrency($rental['monthly_cost']); ?>/month</p>
                                <p class="period">📅 <?php echo formatDate($rental['rental_start_date']); ?> - <?php echo formatDate($rental['rental_end_date']); ?></p>
                            </div>
                            
                            <div class="owner-info">
                                <h4>👤 Owner Contact</h4>
                                <div class="owner-contact">
                                    <?php if (!empty($rental['owner_id'])): ?>
                                        <a href="main.php?page=user-info&id=<?php echo $rental['owner_id']; ?>" class="owner-link" target="_blank">
                                            <?php echo htmlspecialchars($rental['owner_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($rental['owner_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Upcoming Rentals Section -->
        <?php if (!empty($upcoming_rentals)): ?>
            <section class="rentals-section upcoming-section">
                <h2>📅 Upcoming Rentals</h2>
                <p>Properties you will start renting soon</p>
                
                <div class="rentals-grid">
                    <?php foreach ($upcoming_rentals as $rental): ?>
                        <div class="rental-card upcoming-rental">
                            <div class="rental-header">
                                <h3>
                                    <a href="main.php?page=flat-detail&id=<?php echo $rental['flat_id']; ?>" 
                                       class="flat-reference-link" target="_blank">
                                        <?php echo htmlspecialchars($rental['flat_reference']); ?>
                                    </a>
                                </h3>
                                <span class="status-badge upcoming">⏰ Upcoming</span>
                            </div>
                            
                            <div class="rental-details">
                                <p class="location">📍 <?php echo htmlspecialchars($rental['location']); ?></p>
                                <p class="address"><?php echo htmlspecialchars($rental['address']); ?></p>
                                <p class="rent">💰 <?php echo formatCurrency($rental['monthly_cost']); ?>/month</p>
                                <p class="period">📅 <?php echo formatDate($rental['rental_start_date']); ?> - <?php echo formatDate($rental['rental_end_date']); ?></p>
                                
                                <?php
                                $days_until_start = (strtotime($rental['rental_start_date']) - time()) / (60 * 60 * 24);
                                ?>
                                <p class="countdown">⏳ Starts in <?php echo ceil($days_until_start); ?> days</p>
                            </div>
                            
                            <div class="owner-info">
                                <h4>👤 Owner Contact</h4>
                                <div class="owner-contact">
                                    <?php if (!empty($rental['owner_id'])): ?>
                                        <a href="main.php?page=user-info&id=<?php echo $rental['owner_id']; ?>" class="owner-link" target="_blank">
                                            <?php echo htmlspecialchars($rental['owner_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($rental['owner_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- All Rentals Table -->
        <section class="rentals-section table-section">
            <h2>📋 Complete Rental History</h2>
            <p>Detailed view of all your rentals with sorting options</p>
            
            <div class="table-container">
                <table class="rentals-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=flat_reference" class="sort-link">
                                    Flat Reference
                                    <?php if ($current_sort === 'flat_reference'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=monthly_cost" class="sort-link">
                                    Monthly Cost
                                    <?php if ($current_sort === 'monthly_cost'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=rental_start_date" class="sort-link">
                                    Start Date
                                    <?php if ($current_sort === 'rental_start_date'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=rental_end_date" class="sort-link">
                                    End Date
                                    <?php if ($current_sort === 'rental_end_date'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=location" class="sort-link">
                                    Location
                                    <?php if ($current_sort === 'location'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Owner</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rentals as $rental): ?>
                            <tr class="status-<?php echo $rental['rental_status']; ?>">
                                <td>
                                    <a href="main.php?page=flat-detail&id=<?php echo $rental['flat_id']; ?>" 
                                       class="flat-reference-link" target="_blank">
                                        <?php echo htmlspecialchars($rental['flat_reference']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatCurrency($rental['monthly_cost']); ?></td>
                                <td><?php echo formatDate($rental['rental_start_date']); ?></td>
                                <td><?php echo formatDate($rental['rental_end_date']); ?></td>
                                <td><?php echo htmlspecialchars($rental['location']); ?></td>
                                <td>
                                    <?php if (!empty($rental['owner_id'])): ?>
                                        <a href="main.php?page=user-info&id=<?php echo $rental['owner_id']; ?>" class="owner-link" target="_blank">
                                            <?php echo htmlspecialchars($rental['owner_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($rental['owner_name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $rental['rental_status']; ?>">
                                        <?php 
                                        switch($rental['rental_status']) {
                                            case 'current': echo '✅ Current'; break;
                                            case 'upcoming': echo '⏰ Upcoming'; break;
                                            case 'past': echo '📋 Past'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid">
            <a href="main.php?page=search" class="action-card">
                <span class="action-icon">🔍</span>
                <h4>Search New Flats</h4>
                <p>Find your next rental</p>
            </a>
            
            <a href="main.php?page=shopping-basket" class="action-card">
                <span class="action-icon">🛒</span>
                <h4>Shopping Basket</h4>
                <p>Review saved flats</p>
            </a>
            
            <a href="main.php?page=messages" class="action-card">
                <span class="action-icon">💬</span>
                <h4>Messages</h4>
                <p>Check notifications</p>
            </a>
        </div>
    </div>
</div>