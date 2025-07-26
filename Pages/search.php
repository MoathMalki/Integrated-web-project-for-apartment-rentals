<?php
// Search flats page with filters and sortable results

// Clear any old sorting cookies
if (isset($_COOKIE['sort_column']) && $_COOKIE['sort_column'] === 'rent_cost_per_month') {
    setcookie('sort_column', '', time() - 3600, '/');
    setcookie('sort_order', '', time() - 3600, '/');
}

// Get sorting preferences from cookies
$sort_prefs = getSortingPreference();
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : $sort_prefs['column'];
$current_order = isset($_GET['order']) ? $_GET['order'] : $sort_prefs['order'];

// If the sort column is still the old name, update it
if ($current_sort === 'rent_cost_per_month') {
    $current_sort = 'monthly_cost';
}

// Handle form submission
$search_filters = [];
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['search'])) {
    $search_performed = true;
    
    // Get filters from POST or GET
    $source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (!empty($source['min_price'])) {
        $search_filters['min_price'] = floatval($source['min_price']);
    }
    if (!empty($source['max_price'])) {
        $search_filters['max_price'] = floatval($source['max_price']);
    }
    if (!empty($source['location'])) {
        $search_filters['location'] = $source['location'];
    }
    if (!empty($source['bedrooms'])) {
        $search_filters['bedrooms'] = intval($source['bedrooms']);
    }
    if (!empty($source['bathrooms'])) {
        $search_filters['bathrooms'] = intval($source['bathrooms']);
    }
    if (isset($source['furnished']) && $source['furnished'] !== '') {
        $search_filters['furnished'] = $source['furnished'] === '1';
    }
    
    // Add sorting to filters
    $search_filters['sort_by'] = $current_sort;
    $search_filters['sort_order'] = $current_order;
    
    // Save sorting preference
    setSortingPreference($current_sort, $current_order);
    
    // Perform search
    $search_results = searchFlats($pdo, $search_filters);
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
    
    header('Location: main.php?page=search&' . http_build_query($url_params));
    exit;
}
?>

<div class="search-page">
    <h1>🔍 Search Available Flats</h1>
    <p>Find your perfect flat using our advanced search filters. All displayed flats are currently available for rent.</p>

    <section class="search-section">
        <!-- Search Form -->
        <div class="search-form">
            <h2>Search Filters</h2>
            <form method="POST" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="min_price">Minimum Price (₪/month)</label>
                        <input type="number" id="min_price" name="min_price" 
                               value="<?php echo $search_filters['min_price'] ?? ''; ?>" 
                               min="0" step="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_price">Maximum Price (₪/month)</label>
                        <input type="number" id="max_price" name="max_price" 
                               value="<?php echo $search_filters['max_price'] ?? ''; ?>" 
                               min="0" step="100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($search_filters['location'] ?? ''); ?>" 
                               placeholder="e.g., Ramallah, Al-Bireh">
                    </div>
                    
                    <div class="form-group">
                        <label for="furnished">Furnished</label>
                        <select id="furnished" name="furnished">
                            <option value="">Any</option>
                            <option value="1" <?php echo (isset($search_filters['furnished']) && $search_filters['furnished']) ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo (isset($search_filters['furnished']) && !$search_filters['furnished']) ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="bedrooms">Minimum Bedrooms</label>
                        <select id="bedrooms" name="bedrooms">
                            <option value="">Any</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                        <?php echo (isset($search_filters['bedrooms']) && $search_filters['bedrooms'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>+ bedroom<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bathrooms">Minimum Bathrooms</label>
                        <select id="bathrooms" name="bathrooms">
                            <option value="">Any</option>
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <option value="<?php echo $i; ?>" 
                                        <?php echo (isset($search_filters['bathrooms']) && $search_filters['bathrooms'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>+ bathroom<?php echo $i > 1 ? 's' : ''; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">🔍 Search Flats</button>
                    <a href="main.php?page=search" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Search Results -->
        <div class="results-section">
            <?php if ($search_performed): ?>
                <h2>Search Results</h2>
                
                <?php if (!empty($search_results)): ?>
                    <p>Found <?php echo count($search_results); ?> available flat<?php echo count($search_results) !== 1 ? 's' : ''; ?></p>
                    
                    <div class="table-container">
                        <table class="search-results-table">
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
                                        <a href="?sort=monthly_cost&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                           class="sort-link">
                                            Monthly Cost
                                            <?php if ($current_sort === 'monthly_cost'): ?>
                                                <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=available_from&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                           class="sort-link">
                                            Available From
                                            <?php if ($current_sort === 'available_from'): ?>
                                                <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=location&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                           class="sort-link">
                                            Location
                                            <?php if ($current_sort === 'location'): ?>
                                                <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=bedrooms&<?php echo http_build_query(array_merge($_GET, ['search' => '1'])); ?>" 
                                           class="sort-link">
                                            Bedrooms
                                            <?php if ($current_sort === 'bedrooms'): ?>
                                                <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results as $flat): ?>
                                    <tr>
                                        <td>
                                            <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" 
                                               class="flat-reference-link" target="_blank">
                                                <?php echo htmlspecialchars($flat['flat_reference']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatCurrency($flat['monthly_cost']); ?></td>
                                        <td><?php echo formatDate($flat['available_from']); ?></td>
                                        <td><?php echo htmlspecialchars($flat['location']); ?></td>
                                        <td><?php echo $flat['bedrooms']; ?></td>
                                        <td class="photo-cell">
                                            <a href="main.php?page=flat-detail&id=<?php echo $flat['flat_id']; ?>" target="_blank">
                                                <?php if ($flat['main_photo']): ?>
                                                    <img src="<?php echo getUploadedFileUrl('uploads/flats/' . $flat['main_photo']); ?>" 
                                                         alt="Flat <?php echo $flat['flat_reference']; ?>" 
                                                         class="table-photo">
                                                <?php else: ?>
                                                    <img src="<?php echo getUploadedFileUrl('images/default-flat.jpg'); ?>" 
                                                         alt="No Image" 
                                                         class="table-photo">
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Additional Actions -->
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer'): ?>
                        <div class="search-actions">
                            <p><em>💡 Click on any flat reference to view detailed information and photos.</em></p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-results">
                        <h3>No flats found</h3>
                        <p>No flats match your current search criteria. Try adjusting your filters:</p>
                        <ul>
                            <li>Increase your maximum price range</li>
                            <li>Try a different location or remove location filter</li>
                            <li>Reduce the minimum number of bedrooms/bathrooms</li>
                            <li>Change the furnished requirement</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="search-prompt">
                    <h3>Ready to find your perfect flat?</h3>
                    <p>Use the filters above to search through our available properties. You can search by:</p>
                    <ul>
                        <li>📍 <strong>Location</strong> - Find flats in your preferred area</li>
                        <li>💰 <strong>Price Range</strong> - Set your budget</li>
                        <li>🛏️ <strong>Bedrooms & Bathrooms</strong> - Match your space needs</li>
                        <li>🪑 <strong>Furnished Status</strong> - Choose furnished or unfurnished</li>
                    </ul>
                    <p>All search results are sorted by monthly rental rate by default, but you can click on any column header to sort by that field.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

