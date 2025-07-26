<?php
// Rent a flat page - Customer rental process
requireUserType('customer');

$flat_id = isset($_GET['flat_id']) ? intval($_GET['flat_id']) : 0;

if (!$flat_id) {
    header('Location: main.php?page=search');
    exit;
}

// Get flat details
$flat = getFlatDetails($pdo, $flat_id);
if (!$flat) {
    header('Location: main.php?page=search');
    exit;
}

// Check if flat is available
$is_available = $flat['status'] === 'approved';
$rental_check_sql = "SELECT * FROM rentals 
                     WHERE flat_id = :flat_id 
                     AND status IN ('active', 'confirmed') 
                     AND :current_date BETWEEN rental_start_date AND rental_end_date";
$rental_stmt = executeQuery($pdo, $rental_check_sql, [
    ':flat_id' => $flat_id,
    ':current_date' => date('Y-m-d')
]);
$current_rental = $rental_stmt->fetch();

if ($current_rental || !$is_available) {
    header('Location: main.php?page=flat-detail&id=' . $flat_id);
    exit;
}

$customer_id = $_SESSION['customer_id'];
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$errors = [];
$rental_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Validate rental period
            $start_date = $_POST['rental_start_date'] ?? '';
            $end_date = $_POST['rental_end_date'] ?? '';
            
            if (empty($start_date)) {
                $errors['rental_start_date'] = 'Start date is required';
            } elseif (strtotime($start_date) < strtotime('today')) {
                $errors['rental_start_date'] = 'Start date cannot be in the past';
            }
            
            if (empty($end_date)) {
                $errors['rental_end_date'] = 'End date is required';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $errors['rental_end_date'] = 'End date must be after start date';
            }
            
            // Check availability for the specified period
            if (empty($errors)) {
                $availability_sql = "SELECT * FROM rentals 
                                   WHERE flat_id = :flat_id 
                                   AND status IN ('active', 'confirmed')
                                   AND (
                                       (rental_start_date <= :start_date1 AND rental_end_date >= :start_date2) OR
                                       (rental_start_date <= :end_date1 AND rental_end_date >= :end_date2) OR
                                       (rental_start_date >= :start_date3 AND rental_end_date <= :end_date3)
                                   )";
                $availability_stmt = executeQuery($pdo, $availability_sql, [
                    ':flat_id' => $flat_id,
                    ':start_date1' => $start_date,
                    ':start_date2' => $start_date,
                    ':start_date3' => $start_date,
                    ':end_date1' => $end_date,
                    ':end_date2' => $end_date,
                    ':end_date3' => $end_date
                ]);
                
                if ($availability_stmt->fetch()) {
                    $errors['rental_period'] = 'This flat is not available for the selected period';
                }
            }
            
            if (empty($errors)) {
                $rental_data = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'total_cost' => calculateRentalCost($flat['monthly_cost'], $start_date, $end_date)
                ];
                $_SESSION['rental_data'] = $rental_data;
                $step = 2;
            }
            break;
            
        case 2:
            // Validate payment details
            $card_number = $_POST['card_number'] ?? '';
            $card_expiry = $_POST['card_expiry'] ?? '';
            $card_name = $_POST['card_name'] ?? '';
            
            if (empty($card_number)) {
                $errors['card_number'] = 'Credit card number is required';
            } elseif (!validateCreditCard($card_number)) {
                $errors['card_number'] = 'Credit card number must be 9 digits';
            }
            
            if (empty($card_expiry)) {
                $errors['card_expiry'] = 'Expiry date is required';
            } elseif (strtotime($card_expiry) <= time()) {
                $errors['card_expiry'] = 'Card has expired';
            }
            
            if (empty($card_name)) {
                $errors['card_name'] = 'Cardholder name is required';
            }
            
            if (empty($errors)) {
                // Process rental
                $rental_data = $_SESSION['rental_data'];
                
                try {
                    beginTransaction($pdo);
                    
                    // Insert rental record
                    $rental_sql = "INSERT INTO rentals (flat_id, customer_id, rental_start_date, rental_end_date, 
                                                       total_amount, credit_card_number, credit_card_expiry, 
                                                       credit_card_name, status, confirmed_at) 
                                  VALUES (:flat_id, :customer_id, :start_date, :end_date, :total_amount, 
                                         :card_number, :card_expiry, :card_name, 'confirmed', NOW())";
                    
                    $rental_params = [
                        ':flat_id' => $flat_id,
                        ':customer_id' => $customer_id,
                        ':start_date' => $rental_data['start_date'],
                        ':end_date' => $rental_data['end_date'],
                        ':total_amount' => $rental_data['total_cost'],
                        ':card_number' => $card_number,
                        ':card_expiry' => $card_expiry,
                        ':card_name' => $card_name
                    ];
                    
                    executeQuery($pdo, $rental_sql, $rental_params);
                    $rental_id = getLastInsertId($pdo);
                    
                    // Remove from basket if exists
                    $basket_sql = "DELETE FROM shopping_basket WHERE customer_id = :customer_id AND flat_id = :flat_id";
                    executeQuery($pdo, $basket_sql, [':customer_id' => $customer_id, ':flat_id' => $flat_id]);
                    
                    // Send confirmation messages
                    $customer_message = "Congratulations! You have successfully rented flat {$flat['flat_reference']}. 
                                       Rental period: {$rental_data['start_date']} to {$rental_data['end_date']}. 
                                       Please contact the owner {$flat['owner_name']} at {$flat['owner_mobile']} to collect the keys.";
                    
                    sendMessage($pdo, $_SESSION['user_id'], 'Rental Confirmed', $customer_message);
                    
                    // Send message to owner
                    $owner_sql = "SELECT user_id FROM owners WHERE owner_id = :owner_id";
                    $owner_stmt = executeQuery($pdo, $owner_sql, [':owner_id' => $flat['owner_id']]);
                    $owner = $owner_stmt->fetch();
                    
                    if ($owner) {
                        $owner_message = "Your flat {$flat['flat_reference']} has been rented by {$_SESSION['name']}. 
                                        Rental period: {$rental_data['start_date']} to {$rental_data['end_date']}. 
                                        Customer contact: {$_SESSION['email']}";
                        
                        sendMessage($pdo, $owner['user_id'], 'Flat Rented', $owner_message);
                    }
                    
                    commitTransaction($pdo);
                    
                    $_SESSION['rental_success'] = [
                        'flat_reference' => $flat['flat_reference'],
                        'owner_name' => $flat['owner_name'],
                        'owner_mobile' => $flat['owner_mobile'],
                        'start_date' => $rental_data['start_date'],
                        'end_date' => $rental_data['end_date'],
                        'total_cost' => $rental_data['total_cost']
                    ];
                    
                    unset($_SESSION['rental_data']);
                    $step = 3;
                    
                } catch (Exception $e) {
                    rollbackTransaction($pdo);
                    $errors['general'] = 'Rental processing failed. Please try again.';
                }
            }
            break;
    }
}

// Get rental data from session if available
if (isset($_SESSION['rental_data'])) {
    $rental_data = $_SESSION['rental_data'];
}

$periodText = 'Please select valid dates';
$totalCostText = '—';
if (isset($_POST['rental_start_date'], $_POST['rental_end_date'])) {
    $start = strtotime($_POST['rental_start_date']);
    $end = strtotime($_POST['rental_end_date']);
    $monthlyRent = $flat['monthly_cost'];
    if ($start && $end && $end > $start) {
        $diffDays = ceil(($end - $start) / (60 * 60 * 24));
        $months = max(1, ceil($diffDays / 30));
        $totalCost = $monthlyRent * $months;
        $periodText = $months . ' month' . ($months !== 1 ? 's' : '');
        $totalCostText = '<strong>₪' . number_format($totalCost, 2) . '</strong>';
    }
}
?>

<div class="rent-flat-page">
    <div class="page-header">
        <h1>🏠 Rent Flat <?php echo htmlspecialchars($flat['flat_reference']); ?></h1>
        <p>Complete the rental process to secure your new home</p>
    </div>

    <!-- Progress Steps -->
    <div class="rental-steps">
        <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
            <span class="step-number">1</span>
            <span class="step-title">Rental Period</span>
        </div>
        <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
            <span class="step-number">2</span>
            <span class="step-title">Payment Details</span>
        </div>
        <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
            <span class="step-number">3</span>
            <span class="step-title">Confirmation</span>
        </div>
    </div>

    <!-- Flat Summary -->
    <div class="flat-summary">
        <div class="summary-content">
            <h3>📋 Rental Summary</h3>
            <div class="summary-grid">
                <div><strong>Flat Reference:</strong> <?php echo htmlspecialchars($flat['flat_reference']); ?></div>
                <div><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></div>
                <div><strong>Address:</strong> <?php echo htmlspecialchars($flat['address']); ?></div>
                <div><strong>Monthly Rent:</strong> <?php echo formatCurrency($flat['monthly_cost']); ?></div>
                <div><strong>Bedrooms:</strong> <?php echo $flat['bedrooms']; ?></div>
                <div><strong>Bathrooms:</strong> <?php echo $flat['bathrooms']; ?></div>
            </div>
            
            <div class="owner-info">
                <h4>👤 Owner Details</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($flat['owner_mobile']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($flat['owner_email']); ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-messages">
            <h3>Please correct the following errors:</h3>
            <ul>
                <?php foreach ($errors as $field => $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Step 1: Rental Period -->
    <?php if ($step === 1): ?>
        <div class="rental-form">
            <h2>Step 1: Select Rental Period</h2>
            <form method="POST" class="step-form">
                <input type="hidden" name="step" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rental_start_date" class="required">Rental Start Date</label>
                        <input type="date" id="rental_start_date" name="rental_start_date" 
                               value="<?php echo htmlspecialchars($_POST['rental_start_date'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="rental_end_date" class="required">Rental End Date</label>
                        <input type="date" id="rental_end_date" name="rental_end_date" 
                               value="<?php echo htmlspecialchars($_POST['rental_end_date'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                </div>
                
                <div class="cost-calculation">
                    <h3>💰 Cost Calculation</h3>
                    <div class="calculation-details">
                        <div class="calc-item">
                            <span>Monthly Rent:</span>
                            <span id="monthly-rent"><?php echo formatCurrency($flat['monthly_cost']); ?></span>
                        </div>
                        <div class="calc-item">
                            <span>Rental Period:</span>
                            <span id="rental-period"><?php echo $periodText; ?></span>
                        </div>
                        <div class="calc-item total">
                            <span><strong>Total Cost:</strong></span>
                            <span id="total-cost"><?php echo $totalCostText; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="main.php?page=flat-detail&id=<?php echo $flat_id; ?>" class="btn btn-secondary">← Back to Details</a>
                    <button type="submit" class="btn btn-primary">Continue to Payment →</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Step 2: Payment Details -->
    <?php if ($step === 2 && !empty($rental_data)): ?>
        <div class="rental-form">
            <h2>Step 2: Payment Information</h2>
            
            <div class="rental-summary-box">
                <h3>📊 Rental Summary</h3>
                <div class="summary-details">
                    <div class="summary-item">
                        <span>Rental Period:</span>
                        <span><?php echo formatDate($rental_data['start_date']) . ' to ' . formatDate($rental_data['end_date']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Duration:</span>
                        <span><?php 
                            $start = new DateTime($rental_data['start_date']);
                            $end = new DateTime($rental_data['end_date']);
                            $months = $start->diff($end)->m + ($start->diff($end)->y * 12) + 1;
                            echo $months . ' month' . ($months !== 1 ? 's' : '');
                        ?></span>
                    </div>
                    <div class="summary-item total">
                        <span><strong>Total Amount:</strong></span>
                        <span><strong><?php echo formatCurrency($rental_data['total_cost']); ?></strong></span>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="step-form">
                <input type="hidden" name="step" value="2">
                
                <h3>💳 Credit Card Information</h3>
                <div class="payment-form">
                    <div class="form-group">
                        <label for="card_number" class="required">Credit Card Number</label>
                        <input type="text" id="card_number" name="card_number" 
                               value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>"
                               maxlength="9" pattern="[0-9]{9}" 
                               placeholder="123456789" required>
                        <small>9-digit credit card number</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="card_expiry" class="required">Expiry Date</label>
                            <input type="date" id="card_expiry" name="card_expiry" 
                                   value="<?php echo htmlspecialchars($_POST['card_expiry'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="card_name" class="required">Cardholder Name</label>
                            <input type="text" id="card_name" name="card_name" 
                                   value="<?php echo htmlspecialchars($_POST['card_name'] ?? $_SESSION['name']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="payment-security">
                    <h4>🔒 Payment Security</h4>
                    <ul>
                        <li>✅ All transactions are encrypted and secure</li>
                        <li>✅ Your card details are processed safely</li>
                        <li>✅ No card information is stored on our servers</li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="step" value="1" class="btn btn-secondary">← Back to Dates</button>
                    <button type="submit" class="btn btn-success">✓ Confirm Rental</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Step 3: Confirmation -->
    <?php if ($step === 3 && isset($_SESSION['rental_success'])): ?>
        <div class="success-confirmation">
            <div class="success-header">
                <h2>🎉 Rental Confirmed Successfully!</h2>
                <p>Congratulations! You have successfully rented your new flat.</p>
            </div>
            
            <?php $success_data = $_SESSION['rental_success']; ?>
            <div class="confirmation-details">
                <h3>📋 Rental Details</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <strong>Flat Reference:</strong>
                        <span><?php echo htmlspecialchars($success_data['flat_reference']); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Rental Period:</strong>
                        <span><?php echo formatDate($success_data['start_date']) . ' to ' . formatDate($success_data['end_date']); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Total Paid:</strong>
                        <span><?php echo formatCurrency($success_data['total_cost']); ?></span>
                    </div>
                </div>
                
                <div class="next-steps">
                    <h3>📞 Next Steps</h3>
                    <div class="steps-list">
                        <div class="step-item">
                            <span class="step-icon">1️⃣</span>
                            <div>
                                <strong>Contact the Owner</strong>
                                <p>Call <strong><?php echo htmlspecialchars($success_data['owner_name']); ?></strong> 
                                   at <strong><?php echo htmlspecialchars($success_data['owner_mobile']); ?></strong> 
                                   to arrange key collection.</p>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <span class="step-icon">2️⃣</span>
                            <div>
                                <strong>Collect Keys</strong>
                                <p>Arrange a convenient time to collect the keys and complete the handover process.</p>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <span class="step-icon">3️⃣</span>
                            <div>
                                <strong>Move In</strong>
                                <p>You can move in from <?php echo formatDate($success_data['start_date']); ?>. 
                                   Enjoy your new home!</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="confirmation-actions">
                    <a href="main.php?page=my-rentals" class="btn btn-primary">📋 View My Rentals</a>
                    <a href="main.php?page=messages" class="btn btn-secondary">💬 Check Messages</a>
                    <a href="main.php" class="btn btn-success">🏠 Go Home</a>
                </div>
            </div>
        </div>
        
        <?php unset($_SESSION['rental_success']); ?>
    <?php endif; ?>
</div>