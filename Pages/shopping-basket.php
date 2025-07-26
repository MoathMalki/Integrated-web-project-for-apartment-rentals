<?php
// Shopping basket page - View saved flats for potential rental
requireUserType('customer');

$customer_id = $_SESSION['customer_id'];
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_item']) && isset($_POST['basket_id'])) {
        // Remove item from basket
        removeFromBasket($pdo, $_POST['basket_id'], $customer_id);
        $message = 'Item removed from your basket.';
        header('Location: main.php?page=shopping-basket&removed=1');
        exit;
    }
    
    if (isset($_POST['update_dates']) && isset($_POST['basket_id'])) {
        // Update rental dates
        $basket_id = $_POST['basket_id'];
        $start_date = $_POST['rental_start_date'];
        $end_date = $_POST['rental_end_date'];
        
        if (empty($start_date) || empty($end_date)) {
            $error = 'Please select both start and end dates.';
        } elseif (strtotime($end_date) <= strtotime($start_date)) {
            $error = 'End date must be after start date.';
        } elseif (strtotime($start_date) < strtotime('today')) {
            $error = 'Start date cannot be in the past.';
        } else {
            $update_sql = "UPDATE shopping_basket 
                          SET rental_start_date = :start_date, rental_end_date = :end_date 
                          WHERE basket_id = :basket_id AND customer_id = :customer_id";
            executeQuery($pdo, $update_sql, [
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':basket_id' => $basket_id,
                ':customer_id' => $customer_id
            ]);
            $message = 'Rental dates updated successfully.';
            header('Location: main.php?page=shopping-basket&updated=1');
            exit;
        }
    }
    
    if (isset($_POST['clear_basket'])) {
        // Clear entire basket
        $clear_sql = "DELETE FROM shopping_basket WHERE customer_id = :customer_id";
        executeQuery($pdo, $clear_sql, [':customer_id' => $customer_id]);
        $message = 'Shopping basket cleared.';
        header('Location: main.php?page=shopping-basket&cleared=1');
        exit;
    }
}

// Get basket items
$basket_items = getBasketItems($pdo, $customer_id);

// Calculate total estimated cost
$total_estimated_cost = 0;
foreach ($basket_items as $item) {
    if ($item['rental_start_date'] && $item['rental_end_date']) {
        $total_estimated_cost += calculateRentalCost(
            $item['monthly_cost'], 
            $item['rental_start_date'], 
            $item['rental_end_date']
        );
    }
}

// Check for URL messages
if (isset($_GET['removed'])) {
    $message = 'Item removed from your basket.';
} elseif (isset($_GET['updated'])) {
    $message = 'Rental dates updated successfully.';
} elseif (isset($_GET['cleared'])) {
    $message = 'Shopping basket cleared.';
}
?>

<div class="shopping-basket-page">
    <div class="page-header">
        <h1>🛒 Shopping Basket</h1>
        <p>Review and manage flats you're considering for rental</p>
    </div>

    <!-- Basket Statistics -->
    <div class="basket-stats">
        <div class="stat-card items">
            <h3><?php echo count($basket_items); ?></h3>
            <p>Items in Basket</p>
            <span class="stat-icon">🏠</span>
        </div>
        <div class="stat-card cost">
            <h3><?php echo formatCurrency($total_estimated_cost); ?></h3>
            <p>Estimated Total Cost</p>
            <span class="stat-icon">💰</span>
        </div>
        <div class="stat-card ready">
            <h3><?php 
                $ready_items = array_filter($basket_items, function($item) {
                    return $item['rental_start_date'] && $item['rental_end_date'];
                });
                echo count($ready_items);
            ?></h3>
            <p>Ready to Rent</p>
            <span class="stat-icon">✅</span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="success-message">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($basket_items)): ?>
        <div class="empty-basket">
            <div class="empty-state">
                <h2>🛒 Your Basket is Empty</h2>
                <p>You haven't added any flats to your basket yet. Start browsing to find your perfect rental!</p>
                
                <div class="empty-benefits">
                    <h3>Why use the shopping basket?</h3>
                    <ul>
                        <li>🔖 <strong>Save for Later:</strong> Keep track of flats you're interested in</li>
                        <li>⚖️ <strong>Compare Options:</strong> Easily compare different properties</li>
                        <li>📅 <strong>Plan Ahead:</strong> Set potential rental dates</li>
                        <li>💰 <strong>Estimate Costs:</strong> Calculate total rental expenses</li>
                        <li>🚀 <strong>Quick Rental:</strong> Faster checkout when you're ready to rent</li>
                    </ul>
                </div>
                
                <div class="empty-actions">
                    <a href="main.php?page=search" class="btn btn-primary">🔍 Search Available Flats</a>
                    <a href="main.php" class="btn btn-secondary">🏠 Go Home</a>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Basket Actions -->
        <div class="basket-actions">
            <div class="action-info">
                <p>💡 <strong>Tip:</strong> Set rental dates for accurate cost calculations. Items with dates are ready for quick rental!</p>
            </div>
            
            <?php if (count($basket_items) > 1): ?>
                <?php if (isset($_GET['confirm_clear'])): ?>
                    <div class="confirmation-dialog">
                        <p>Are you sure you want to clear your entire basket?</p>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="clear_basket" class="btn btn-danger">
                                Yes, Clear Basket
                            </button>
                        </form>
                        <a href="main.php?page=shopping-basket" class="btn btn-secondary">Cancel</a>
                    </div>
                <?php else: ?>
                    <a href="main.php?page=shopping-basket&confirm_clear=1" class="btn btn-danger">
                        🗑️ Clear Basket
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Basket Items -->
        <div class="basket-items">
            <?php foreach ($basket_items as $item): ?>
                <div class="basket-item">
                    <div class="item-content">
                        <!-- Flat Image -->
                        <div class="item-image">
                            <?php if ($item['main_photo']): ?>
                                <img src="uploads/flats/<?php echo $item['main_photo']; ?>" 
                                     alt="Flat <?php echo $item['flat_reference']; ?>">
                            <?php else: ?>
                                <img src="images/default-flat.jpg" alt="No Image Available">
                            <?php endif; ?>
                        </div>

                        <!-- Flat Details -->
                        <div class="item-details">
                            <div class="item-header">
                                <h3>
                                    <a href="main.php?page=flat-detail&id=<?php echo $item['flat_id']; ?>" 
                                       class="flat-reference-link" target="_blank">
                                        Flat <?php echo htmlspecialchars($item['flat_reference']); ?>
                                    </a>
                                </h3>
                                <span class="item-price"><?php echo formatCurrency($item['monthly_cost']); ?>/month</span>
                            </div>
                            
                            <p class="item-location">📍 <?php echo htmlspecialchars($item['location']); ?></p>
                            <p class="item-added">Added: <?php echo formatDate($item['created_at']); ?></p>
                        </div>

                        <!-- Rental Dates -->
                        <div class="item-dates">
                            <h4>📅 Rental Period</h4>
                            <?php
                            $durationText = 'Invalid dates';
                            $totalText = '<strong>—</strong>';
                            if (
                                isset($_POST['calculate']) &&
                                $_POST['calculate'] == $item['basket_id'] &&
                                isset($_POST['rental_start_date'], $_POST['rental_end_date'])
                            ) {
                                $start = strtotime($_POST['rental_start_date']);
                                $end = strtotime($_POST['rental_end_date']);
                                $monthlyRent = $item['monthly_cost'];
                                if ($start && $end && $end > $start) {
                                    $diffDays = ceil(($end - $start) / (60 * 60 * 24));
                                    $months = max(1, ceil($diffDays / 30));
                                    $totalCost = $monthlyRent * $months;
                                    $durationText = $months . ' month' . ($months !== 1 ? 's' : '');
                                    $totalText = '<strong>₪' . number_format($totalCost, 2) . '</strong>';
                                }
                            }
                            ?>
                            <form method="post" action="">
                                <input type="hidden" name="basket_id" value="<?php echo $item['basket_id']; ?>">
                                <input type="hidden" name="monthly_rent" value="<?php echo $item['monthly_cost']; ?>">
                                <label>Start Date:
                                    <input type="date" name="rental_start_date" id="start_<?php echo $item['basket_id']; ?>"
                                        value="<?php echo isset($_POST['rental_start_date']) && $_POST['basket_id'] == $item['basket_id'] ? htmlspecialchars($_POST['rental_start_date']) : ''; ?>"
                                        required>
                                </label>
                                <label>End Date:
                                    <input type="date" name="rental_end_date" id="end_<?php echo $item['basket_id']; ?>"
                                        value="<?php echo isset($_POST['rental_end_date']) && $_POST['basket_id'] == $item['basket_id'] ? htmlspecialchars($_POST['rental_end_date']) : ''; ?>"
                                        min="<?php echo isset($_POST['rental_start_date']) && $_POST['basket_id'] == $item['basket_id'] ? date('Y-m-d', strtotime($_POST['rental_start_date'] . ' +1 day')) : ''; ?>"
                                        required>
                                </label>
                                <button type="submit" name="calculate" value="<?php echo $item['basket_id']; ?>">Calculate</button>
                            </form>
                            
                            <div>
                                <span id="duration_<?php echo $item['basket_id']; ?>"><?php echo $durationText; ?></span>
                                <span id="total_<?php echo $item['basket_id']; ?>"><?php echo $totalText; ?></span>
                            </div>
                        </div>

                        <!-- Item Actions -->
                        <div class="item-actions">
                            <?php if ($item['rental_start_date'] && $item['rental_end_date']): ?>
                                <a href="main.php?page=rent-flat&flat_id=<?php echo $item['flat_id']; ?>" 
                                   class="btn btn-success">
                                    🏠 Rent Now
                                </a>
                            <?php endif; ?>
                            
                            <a href="main.php?page=flat-detail&id=<?php echo $item['flat_id']; ?>" 
                               class="btn btn-primary" target="_blank">
                                👁️ View Details
                            </a>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Remove this item from your basket?')">
                                <input type="hidden" name="basket_id" value="<?php echo $item['basket_id']; ?>">
                                <button type="submit" name="remove_item" class="btn btn-danger btn-small">
                                    🗑️ Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Basket Summary -->
        <div class="basket-summary">
            <h3>📊 Basket Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <span>Total Items:</span>
                    <span><?php echo count($basket_items); ?></span>
                </div>
                <div class="summary-item">
                    <span>Items with Dates:</span>
                    <span><?php echo count($ready_items); ?></span>
                </div>
                <div class="summary-item total">
                    <span><strong>Estimated Total Cost:</strong></span>
                    <span><strong><?php echo formatCurrency($total_estimated_cost); ?></strong></span>
                </div>
            </div>
            
            <?php if (count($ready_items) > 0): ?>
                <div class="summary-actions">
                    <p>🎉 You have <?php echo count($ready_items); ?> item<?php echo count($ready_items) !== 1 ? 's' : ''; ?> ready for rental!</p>
                </div>
            <?php else: ?>
                <div class="summary-note">
                    <p>💡 Set rental dates for your items to see accurate costs and enable quick rental.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- Continue Shopping -->
    <div class="continue-shopping">
        <h3>Continue Shopping</h3>
        <div class="shopping-actions">
            <a href="main.php?page=search" class="action-card">
                <span class="action-icon">🔍</span>
                <h4>Search More Flats</h4>
                <p>Find additional properties to add to your basket</p>
            </a>
            
            <a href="main.php?page=my-rentals" class="action-card">
                <span class="action-icon">📋</span>
                <h4>My Rentals</h4>
                <p>View your current and past rental agreements</p>
            </a>
            
            <a href="main.php?page=messages" class="action-card">
                <span class="action-icon">💬</span>
                <h4>Messages</h4>
                <p>Check for important notifications</p>
            </a>
        </div>
    </div>
</div>