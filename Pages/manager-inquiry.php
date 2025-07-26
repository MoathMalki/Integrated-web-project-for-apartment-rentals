<?php
// Manager inquiry page - Advanced flat search for managers
requireUserType('manager');

// Get sorting preferences
$sort_prefs = getSortingPreference();
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : $sort_prefs['column'];
$current_order = isset($_GET['order']) ? $_GET['order'] : $sort_prefs['order'];

// Handle form submission and build search filters
$search_filters = [];
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['search'])) {
    $search_performed = true;
    
    // Get filters from POST or GET
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (!empty($source['period_from'])) {
        $search_filters['period_from'] = $source['period_from'];
    }
    if (!empty($source['period_to'])) {
        $search_filters['period_to'] = $source['period_to'];
    }
    if (!empty($source['location'])) {
        $search_filters['location'] = $source['location'];
    }
    if (!empty($source['specific_date'])) {
        $search_filters['specific_date'] = $source['specific_date'];
    }
    if (!empty($source['owner_name'])) {
        $search_filters['owner_name'] = $source['owner_name'];
    }
    if (!empty($source['customer_name'])) {
        $search_filters['customer_name'] = $source['customer_name'];
    }
    if (!empty($source['flat_status'])) {
        $search_filters['flat_status'] = $source['flat_status'];
    }
    if (!empty($source['rental_status'])) {
        $search_filters['rental_status'] = $source['rental_status'];
    }
    
    // Perform search
    $search_results = performManagerInquiry($pdo, $search_filters, $current_sort, $current_order);
}

// Handle sorting links
if (isset($_GET['sort'])) {
    // Toggle order if same column, otherwise use ASC
    $new_order = ($current_sort === $_GET['sort'] && $current_order === 'ASC') ? 'DESC' : 'ASC';
    
    // Rebuild URL with current filters
    $url_params = $_GET;
    $url_params['sort'] = $_GET['sort'];
    $url_params['order'] = $new_order;
    $url_params['search'] = '1';
    unset($url_params['page']); // Remove page parameter
    
    header('Location: main.php?page=manager-inquiry&' . http_build_query($url_params));
    exit;
}

// Manager inquiry function
function performManagerInquiry($pdo, $filters, $sort_by = 'f.created_at', $sort_order = 'DESC') {
    $sql = "SELECT f.*, 
                   uo.name as owner_name, uo.email as owner_email, uo.mobile_number as owner_mobile,
                   uc.name as customer_name, uc.email as customer_email,
                   r.rental_start_date, r.rental_end_date, r.status as rental_status,
                   r.total_amount as rental_amount,
                   CASE 
                       WHEN r.rental_id IS NULL THEN 'available'
                       WHEN CURDATE() < r.rental_start_date THEN 'upcoming'
                       WHEN CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN 'current'
                       ELSE 'past'
                   END as current_rental_status
            FROM flats f 
            JOIN owners o ON f.owner_id = o.owner_id 
            JOIN users uo ON o.user_id = uo.user_id 
            LEFT JOIN rentals r ON f.flat_id = r.flat_id AND r.status IN ('confirmed', 'active', 'completed')
            LEFT JOIN customers c ON r.customer_id = c.customer_id
            LEFT JOIN users uc ON c.user_id = uc.user_id
            WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['period_from']) && !empty($filters['period_to'])) {
        $sql .= " AND ((f.available_from <= :period_to AND f.available_to >= :period_from) 
                      OR (r.rental_start_date <= :period_to AND r.rental_end_date >= :period_from))";
        $params[':period_from'] = $filters['period_from'];
        $params[':period_to'] = $filters['period_to'];
    }
    
    if (!empty($filters['location'])) {
        $sql .= " AND f.location LIKE :location";
        $params[':location'] = '%' . $filters['location'] . '%';
    }
    
    if (!empty($filters['specific_date'])) {
        $sql .= " AND (
                      (f.available_from <= :specific_date AND f.available_to >= :specific_date)
                      OR (r.rental_start_date <= :specific_date AND r.rental_end_date >= :specific_date)
                  )";
        $params[':specific_date'] = $filters['specific_date'];
    }
    
    if (!empty($filters['owner_name'])) {
        $sql .= " AND uo.name LIKE :owner_name";
        $params[':owner_name'] = '%' . $filters['owner_name'] . '%';
    }
    
    if (!empty($filters['customer_name'])) {
        $sql .= " AND uc.name LIKE :customer_name";
        $params[':customer_name'] = '%' . $filters['customer_name'] . '%';
    }
    
    if (!empty($filters['flat_status'])) {
        $sql .= " AND f.status = :flat_status";
        $params[':flat_status'] = $filters['flat_status'];
    }
    
    if (!empty($filters['rental_status'])) {
        switch ($filters['rental_status']) {
            case 'available':
                $sql .= " AND r.rental_id IS NULL AND f.status = 'approved'";
                break;
            case 'rented':
                $sql .= " AND r.rental_id IS NOT NULL";
                break;
            case 'current':
                $sql .= " AND r.rental_id IS NOT NULL AND CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date";
                break;
            case 'past':
                $sql .= " AND r.rental_id IS NOT NULL AND CURDATE() > r.rental_end_date";
                break;
        }
    }
    
    // If no filters are selected, show all rented flats by default
    if (empty($filters)) {
        $sql .= " AND r.rental_id IS NOT NULL";
    }
    
    $sql .= " ORDER BY $sort_by $sort_order";
    
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetchAll();
}

// Get statistics for dashboard
$stats_sql = "SELECT 
                COUNT(DISTINCT f.flat_id) as total_flats,
                COUNT(DISTINCT CASE WHEN f.status = 'approved' THEN f.flat_id END) as approved_flats,
                COUNT(DISTINCT CASE WHEN f.status = 'pending_approval' THEN f.flat_id END) as pending_flats,
                COUNT(DISTINCT CASE WHEN r.rental_id IS NOT NULL AND CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN f.flat_id END) as currently_rented,
                COUNT(DISTINCT CASE WHEN r.rental_id IS NULL AND f.status = 'approved' THEN f.flat_id END) as available_flats
              FROM flats f 
              LEFT JOIN rentals r ON f.flat_id = r.flat_id AND r.status IN ('confirmed', 'active', 'completed')";

$stats = executeQuery($pdo, $stats_sql)->fetch();

// CSV export logic
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($search_results)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="flat_inquiry_results_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    // Output headers
    fputcsv($output, ['Flat Reference', 'Monthly Cost', 'Start Date', 'End Date', 'Location', 'Owner', 'Customer', 'Status']);
    foreach ($search_results as $result) {
        fputcsv($output, [
            $result['flat_reference'],
            $result['monthly_cost'],
            $result['rental_start_date'],
            $result['rental_end_date'],
            $result['location'],
            $result['owner_name'],
            $result['customer_name'],
            $result['current_rental_status'],
        ]);
    }
    fclose($output);
    exit;
}
?>

<div class="manager-inquiry-page">
    <div class="page-header">
        <h1>Manager Inquiry</h1>
        <p>Search and filter flats based on various criteria</p>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card total">
            <h3><?php echo $stats['total_flats']; ?></h3>
            <p>Total Flats</p>
            <span class="stat-icon">🏢</span>
        </div>
        <div class="stat-card approved">
            <h3><?php echo $stats['approved_flats']; ?></h3>
            <p>Approved Flats</p>
            <span class="stat-icon">✅</span>
        </div>
        <div class="stat-card pending">
            <h3><?php echo $stats['pending_flats']; ?></h3>
            <p>Pending Approval</p>
            <span class="stat-icon">⏳</span>
        </div>
        <div class="stat-card rented">
            <h3><?php echo $stats['currently_rented']; ?></h3>
            <p>Currently Rented</p>
            <span class="stat-icon">🏠</span>
        </div>
        <div class="stat-card available">
            <h3><?php echo $stats['available_flats']; ?></h3>
            <p>Available for Rent</p>
            <span class="stat-icon">🔓</span>
        </div>
    </div>

    <!-- Advanced Search Form -->
    <div class="inquiry-form">
        <h2>🔍 Advanced Search & Inquiry</h2>
        <p>Use the filters below to search and analyze flat data. Leave filters empty to show all rented flats.</p>
        
        <form method="POST" class="search-form">
            <div class="filter-sections">
                <!-- Date Range Filters -->
                <fieldset>
                    <legend>📅 Date & Period Filters</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="period_from">Available During Period - From</label>
                            <input type="date" id="period_from" name="period_from" 
                                   value="<?php echo htmlspecialchars($search_filters['period_from'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="period_to">To</label>
                            <input type="date" id="period_to" name="period_to" 
                                   value="<?php echo htmlspecialchars($search_filters['period_to'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="specific_date">Available on Specific Date</label>
                            <input type="date" id="specific_date" name="specific_date" 
                                   value="<?php echo htmlspecialchars($search_filters['specific_date'] ?? ''); ?>">
                        </div>
                    </div>
                </fieldset>
                
                <!-- Location & Property Filters -->
                <fieldset>
                    <legend>📍 Location & Property Filters</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($search_filters['location'] ?? ''); ?>"
                                   placeholder="e.g., Ramallah, Al-Bireh">
                        </div>
                        
                        <div class="form-group">
                            <label for="flat_status">Flat Status</label>
                            <select id="flat_status" name="flat_status">
                                <option value="">All Statuses</option>
                                <option value="pending_approval" <?php echo (isset($search_filters['flat_status']) && $search_filters['flat_status'] === 'pending_approval') ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo (isset($search_filters['flat_status']) && $search_filters['flat_status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo (isset($search_filters['flat_status']) && $search_filters['flat_status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="rental_status">Rental Status</label>
                            <select id="rental_status" name="rental_status">
                                <option value="">All Rental Statuses</option>
                                <option value="available" <?php echo (isset($search_filters['rental_status']) && $search_filters['rental_status'] === 'available') ? 'selected' : ''; ?>>Available for Rent</option>
                                <option value="rented" <?php echo (isset($search_filters['rental_status']) && $search_filters['rental_status'] === 'rented') ? 'selected' : ''; ?>>Currently Rented</option>
                                <option value="current" <?php echo (isset($search_filters['rental_status']) && $search_filters['rental_status'] === 'current') ? 'selected' : ''; ?>>Active Rentals</option>
                                <option value="past" <?php echo (isset($search_filters['rental_status']) && $search_filters['rental_status'] === 'past') ? 'selected' : ''; ?>>Past Rentals</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
                
                <!-- People Filters -->
                <fieldset>
                    <legend>👥 Owner & Customer Filters</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_name">Owner Name</label>
                            <input type="text" id="owner_name" name="owner_name" 
                                   value="<?php echo htmlspecialchars($search_filters['owner_name'] ?? ''); ?>"
                                   placeholder="Search by owner name">
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_name">Customer Name</label>
                            <input type="text" id="customer_name" name="customer_name" 
                                   value="<?php echo htmlspecialchars($search_filters['customer_name'] ?? ''); ?>"
                                   placeholder="Search by renter name">
                        </div>
                    </div>
                </fieldset>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">🔍 Search & Analyze</button>
                <a href="main.php?page=manager-inquiry" class="btn btn-secondary">Clear All Filters</a>
                <a href="main.php?page=manager-inquiry&rental_status=current" class="btn btn-success">📊 View Current Rentals</a>
                <a href="main.php?page=manager-inquiry&rental_status=available" class="btn btn-info">🔓 View Available Flats</a>
                <a href="main.php?page=manager-inquiry&flat_status=pending_approval" class="btn btn-warning">⏳ View Pending Approvals</a>
            </div>
        </form>
    </div>

    <!-- Search Results -->
    <div class="results-section">
        <?php if ($search_performed): ?>
            <h2>📋 Search Results</h2>
            
            <?php if (!empty($search_results)): ?>
                <div class="results-summary">
                    <p>Found <strong><?php echo count($search_results); ?></strong> result<?php echo count($search_results) !== 1 ? 's' : ''; ?> matching your criteria</p>
                    
                    <!-- Applied Filters Summary -->
                    <?php if (!empty($search_filters)): ?>
                        <div class="applied-filters">
                            <h4>🏷️ Applied Filters:</h4>
                            <div class="filter-tags">
                                <?php foreach ($search_filters as $key => $value): ?>
                                    <?php if (!empty($value)): ?>
                                        <span class="filter-tag"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>: <?php echo htmlspecialchars($value); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="table-container">
                    <form method="get" style="margin-bottom:1rem;">
                        <?php foreach ($_GET as $key => $value): if ($key !== 'export') { ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php } endforeach; ?>
                        <button type="submit" name="export" value="csv" class="btn btn-success">Export to CSV</button>
                    </form>
                    <table class="inquiry-results-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort=flat_reference&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                       class="sort-link">
                                        Flat Reference
                                        <?php if ($current_sort === 'flat_reference'): ?>
                                            <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=f.monthly_cost&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                       class="sort-link">
                                        Monthly Cost
                                        <?php if ($current_sort === 'f.monthly_cost'): ?>
                                            <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=rental_start_date&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                       class="sort-link">
                                        Start Date
                                        <?php if ($current_sort === 'rental_start_date'): ?>
                                            <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=rental_end_date&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                       class="sort-link">
                                        End Date
                                        <?php if ($current_sort === 'rental_end_date'): ?>
                                            <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=f.location&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                       class="sort-link">
                                        Location
                                        <?php if ($current_sort === 'f.location'): ?>
                                            <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Owner</th>
                                <th>Customer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results as $result): ?>
                                <tr class="status-<?php echo $result['current_rental_status']; ?>">
                                    <td>
                                        <?php if ($result['flat_reference']): ?>
                                            <a href="main.php?page=flat-detail&id=<?php echo $result['flat_id']; ?>" 
                                               class="flat-reference-link" target="_blank">
                                                <?php echo htmlspecialchars($result['flat_reference']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="pending-ref">Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatCurrency($result['monthly_cost']); ?></td>
                                    <td>
                                        <?php if ($result['rental_start_date']): ?>
                                            <?php echo formatDate($result['rental_start_date']); ?>
                                        <?php else: ?>
                                            <span class="not-rented">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['rental_end_date']): ?>
                                            <?php echo formatDate($result['rental_end_date']); ?>
                                        <?php else: ?>
                                            <span class="not-rented">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['location']); ?></td>
                                    <td>
                                        <a href="main.php?page=user-info&id=<?php echo $result['owner_id']; ?>" class="user-link" target="_blank">
                                            <?php echo htmlspecialchars($result['owner_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (isset($result['customer_id']) && $result['customer_id'] && !empty($result['customer_name'])): ?>
                                            <a href="main.php?page=user-info&id=<?php echo $result['customer_id']; ?>" class="user-link" target="_blank">
                                                <?php echo htmlspecialchars($result['customer_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="not-rented">Not Rented</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="status-info">
                                            <span class="flat-status status-<?php echo $result['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?>
                                            </span>
                                            <?php if ($result['current_rental_status'] !== 'available'): ?>
                                                <span class="rental-status status-<?php echo $result['current_rental_status']; ?>">
                                                    <?php echo ucfirst($result['current_rental_status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                <div class="no-results">
                    <h3>🔍 No Results Found</h3>
                    <p>No flats match your current search criteria. Try adjusting your filters:</p>
                    <ul>
                        <li>🗓️ Expand your date range</li>
                        <li>📍 Try a broader location search</li>
                        <li>📊 Change the rental status filter</li>
                        <li>👥 Verify owner/customer names are spelled correctly</li>
                    </ul>
                    <div class="suggestion-actions">
                        <button onclick="loadPresetSearch('all_flats')" class="btn btn-primary">Show All Flats</button>
                        <button onclick="loadPresetSearch('current_rentals')" class="btn btn-success">Show Current Rentals</button>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="search-prompt">
                <h3>🚀 Ready to Analyze?</h3>
                <p>Use the advanced search form above to query flat data. You can:</p>
                
                <div class="search-examples">
                    <div class="example-card">
                        <h4>📅 Date-Based Queries</h4>
                        <ul>
                            <li>Find flats available during specific periods</li>
                            <li>Check availability on particular dates</li>
                            <li>Analyze rental patterns over time</li>
                        </ul>
                    </div>
                    
                    <div class="example-card">
                        <h4>📍 Location Analysis</h4>
                        <ul>
                            <li>Filter by city or area</li>
                            <li>Compare rental activity by location</li>
                            <li>Identify popular neighborhoods</li>
                        </ul>
                    </div>
                    
                    <div class="example-card">
                        <h4>👥 People-Based Search</h4>
                        <ul>
                            <li>Find flats by specific owners</li>
                            <li>Look up rentals by customer name</li>
                            <li>Analyze owner/customer activity</li>
                        </ul>
                    </div>
                    
                    <div class="example-card">
                        <h4>📊 Status Filtering</h4>
                        <ul>
                            <li>View only approved/pending flats</li>
                            <li>Filter by rental status</li>
                            <li>Track flat lifecycle stages</li>
                        </ul>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button onclick="loadPresetSearch('current_rentals')" class="btn btn-success">📊 View Current Rentals</button>
                    <button onclick="loadPresetSearch('available_flats')" class="btn btn-info">🔓 View Available Flats</button>
                    <button onclick="loadPresetSearch('pending_approval')" class="btn btn-warning">⏳ View Pending Approvals</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

