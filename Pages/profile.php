<?php
// User profile page - View and update user information
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message = '';
$error = '';

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] === 0) {
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Only JPG, PNG, and WebP images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 5MB.';
        } else {
            $upload_dir = __DIR__ . '/../uploads/profiles/';
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update database with new photo path
                try {
                    $photo_path = 'uploads/profiles/' . $new_filename;
                    $update_sql = "UPDATE users SET photo = :photo WHERE user_id = :user_id";
                    executeQuery($pdo, $update_sql, [
                        ':photo' => $photo_path,
                        ':user_id' => $user_id
                    ]);
                    $message = 'Profile photo updated successfully!';
                    
                    // Update session
                    $_SESSION['user_photo'] = $photo_path;
                } catch (Exception $e) {
                    $error = 'Failed to update profile photo: ' . $e->getMessage();
                    // Clean up uploaded file if database update fails
                    unlink($upload_path);
                }
            } else {
                $error = 'Failed to upload photo. Please try again.';
            }
        }
    } else {
        $error = 'Error uploading file. Error code: ' . $file['error'];
    }
}

// Get current user information with additional details
function getUserProfile($pdo, $user_id, $user_type) {
    $sql = "SELECT u.*";
    
    if ($user_type === 'customer') {
        $sql .= ", c.customer_id as type_id";
    } elseif ($user_type === 'owner') {
        $sql .= ", o.owner_id as type_id, o.bank_name, o.bank_branch, o.account_number";
    } elseif ($user_type === 'manager') {
        $sql .= ", m.manager_id as type_id";
    }
    
    $sql .= " FROM users u";
    
    switch ($user_type) {
        case 'customer':
            $sql .= " LEFT JOIN customers c ON u.user_id = c.user_id";
            break;
        case 'owner':
            $sql .= " LEFT JOIN owners o ON u.user_id = o.user_id";
            break;
        case 'manager':
            $sql .= " LEFT JOIN managers m ON u.user_id = m.user_id";
            break;
    }
    
    $sql .= " WHERE u.user_id = :user_id";
    
    $stmt = executeQuery($pdo, $sql, [':user_id' => $user_id]);
    return $stmt->fetch();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $errors = validateProfileUpdate($_POST, $user_type);
    
    if (empty($errors)) {
        try {
            beginTransaction($pdo);
            
            // Update users table
            $user_sql = "UPDATE users SET 
                        name = :name, 
                        flat_house_no = :flat_house_no, 
                        street_name = :street_name, 
                        city = :city, 
                        postal_code = :postal_code, 
                        date_of_birth = :date_of_birth, 
                        email = :email, 
                        mobile_number = :mobile_number, 
                        telephone_number = :telephone_number,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE user_id = :user_id";
            
            $user_params = [
                ':name' => $_POST['name'],
                ':flat_house_no' => $_POST['flat_house_no'],
                ':street_name' => $_POST['street_name'],
                ':city' => $_POST['city'],
                ':postal_code' => $_POST['postal_code'],
                ':date_of_birth' => $_POST['date_of_birth'],
                ':email' => $_POST['email'],
                ':mobile_number' => $_POST['mobile_number'],
                ':telephone_number' => $_POST['telephone_number'],
                ':user_id' => $user_id
            ];
            
            executeQuery($pdo, $user_sql, $user_params);
            
            // Update owner-specific information if applicable
            if ($user_type === 'owner') {
                $owner_sql = "UPDATE owners SET 
                             bank_name = :bank_name, 
                             bank_branch = :bank_branch, 
                             account_number = :account_number 
                             WHERE user_id = :user_id";
                
                $owner_params = [
                    ':bank_name' => $_POST['bank_name'],
                    ':bank_branch' => $_POST['bank_branch'],
                    ':account_number' => $_POST['account_number'],
                    ':user_id' => $user_id
                ];
                
                executeQuery($pdo, $owner_sql, $owner_params);
            }
            
            // Update session name if changed
            $_SESSION['name'] = $_POST['name'];
            $_SESSION['email'] = $_POST['email'];
            
            commitTransaction($pdo);
            $message = 'Profile updated successfully!';
            
        } catch (Exception $e) {
            rollbackTransaction($pdo);
            $error = 'Failed to update profile. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $password_errors = [];
    
    if (empty($_POST['current_password'])) {
        $password_errors[] = 'Current password is required';
    }
    
    if (empty($_POST['new_password'])) {
        $password_errors[] = 'New password is required';
    } elseif (!validatePassword($_POST['new_password'])) {
        $password_errors[] = 'New password must be 6-15 characters, start with a digit, and end with a lowercase letter';
    }
    
    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        $password_errors[] = 'New passwords do not match';
    }
    
    if (empty($password_errors)) {
        // Verify current password
        $verify_sql = "SELECT password_hash FROM users WHERE user_id = :user_id";
        $verify_stmt = executeQuery($pdo, $verify_sql, [':user_id' => $user_id]);
        $current_hash = $verify_stmt->fetchColumn();
        
        if (password_verify($_POST['current_password'], $current_hash)) {
            // Update password
            $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id";
            executeQuery($pdo, $update_sql, [
                ':password_hash' => $new_hash,
                ':user_id' => $user_id
            ]);
            
            $message = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    } else {
        $error = implode('<br>', $password_errors);
    }
}

// Profile validation function
function validateProfileUpdate($data, $user_type) {
    $errors = [];
    
    // Required fields
    $required_fields = ['name', 'flat_house_no', 'street_name', 'city', 'postal_code', 
                       'date_of_birth', 'email', 'mobile_number', 'telephone_number'];
    
    if ($user_type === 'owner') {
        $required_fields = array_merge($required_fields, ['bank_name', 'bank_branch', 'account_number']);
    }
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Specific validations
    if (!empty($data['name']) && !preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
        $errors[] = 'Name must contain only letters and spaces';
    }
    
    if (!empty($data['email']) && !validateEmail($data['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (!empty($data['mobile_number']) && !validateMobile($data['mobile_number'])) {
        $errors[] = 'Mobile number must be in format 05XXXXXXXX';
    }
    
    if (!empty($data['date_of_birth'])) {
        $birth_date = new DateTime($data['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        if ($age < 18) {
            $errors[] = 'You must be at least 18 years old';
        }
    }
    
    return $errors;
}

// Get user profile information
$user_profile = getUserProfile($pdo, $user_id, $user_type);

// Get user statistics
$user_stats = [];
if ($user_type === 'customer') {
    $stats_sql = "SELECT 
                    COUNT(DISTINCT r.rental_id) as total_rentals,
                    COUNT(DISTINCT CASE WHEN CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN r.rental_id END) as current_rentals,
                    COUNT(DISTINCT b.basket_id) as basket_items,
                    COUNT(CASE WHEN m.is_read = 0 THEN 1 END) as unread_messages
                  FROM customers c
                  LEFT JOIN rentals r ON c.customer_id = r.customer_id AND r.status IN ('confirmed', 'active', 'completed')
                  LEFT JOIN shopping_basket b ON c.customer_id = b.customer_id
                  LEFT JOIN messages m ON c.user_id = m.user_id
                  WHERE c.user_id = :user_id";
    
} elseif ($user_type === 'owner') {
    $stats_sql = "SELECT 
                    COUNT(DISTINCT f.flat_id) as total_flats,
                    COUNT(DISTINCT CASE WHEN f.status = 'approved' THEN f.flat_id END) as approved_flats,
                    COUNT(DISTINCT CASE WHEN f.status = 'pending_approval' THEN f.flat_id END) as pending_flats,
                    COUNT(DISTINCT CASE WHEN r.rental_id IS NOT NULL AND CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN f.flat_id END) as rented_flats,
                    COUNT(CASE WHEN m.is_read = 0 THEN 1 END) as unread_messages
                  FROM owners o
                  LEFT JOIN flats f ON o.owner_id = f.owner_id
                  LEFT JOIN rentals r ON f.flat_id = r.flat_id AND r.status IN ('confirmed', 'active', 'completed')
                  LEFT JOIN messages m ON o.user_id = m.user_id
                  WHERE o.user_id = :user_id";
    
} else { // manager
    $stats_sql = "SELECT 
                    COUNT(DISTINCT f.flat_id) as total_flats,
                    COUNT(DISTINCT CASE WHEN f.status = 'pending_approval' THEN f.flat_id END) as pending_approvals,
                    COUNT(DISTINCT r.rental_id) as total_rentals,
                    COUNT(CASE WHEN m.is_read = 0 THEN 1 END) as unread_messages
                  FROM flats f
                  LEFT JOIN rentals r ON f.flat_id = r.flat_id AND r.status IN ('confirmed', 'active', 'completed')
                  LEFT JOIN messages m ON m.user_id = :user_id";
}

$user_stats = executeQuery($pdo, $stats_sql, [':user_id' => $user_id])->fetch();
?>

<div class="profile-page">
    <div class="page-header">
        <h1>👤 User Profile</h1>
        <p>Manage your personal information and account settings</p>
    </div>

    <!-- User Statistics -->
    <div class="profile-stats">
        <?php if ($user_type === 'customer'): ?>
            <div class="stat-card">
                <h3><?php echo $user_stats['total_rentals']; ?></h3>
                <p>Total Rentals</p>
                <span class="stat-icon">🏠</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['current_rentals']; ?></h3>
                <p>Current Rentals</p>
                <span class="stat-icon">🔑</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['basket_items']; ?></h3>
                <p>Basket Items</p>
                <span class="stat-icon">🛒</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['unread_messages']; ?></h3>
                <p>Unread Messages</p>
                <span class="stat-icon">💬</span>
            </div>
        <?php elseif ($user_type === 'owner'): ?>
            <div class="stat-card">
                <h3><?php echo $user_stats['total_flats']; ?></h3>
                <p>Listed Flats</p>
                <span class="stat-icon">🏢</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['approved_flats']; ?></h3>
                <p>Approved Flats</p>
                <span class="stat-icon">✅</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['pending_flats']; ?></h3>
                <p>Pending Approval</p>
                <span class="stat-icon">⏳</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['rented_flats']; ?></h3>
                <p>Currently Rented</p>
                <span class="stat-icon">🔑</span>
            </div>
        <?php else: // manager ?>
            <div class="stat-card">
                <h3><?php echo $user_stats['total_flats']; ?></h3>
                <p>Total Flats</p>
                <span class="stat-icon">🏢</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['pending_approvals']; ?></h3>
                <p>Pending Approvals</p>
                <span class="stat-icon">⏳</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['total_rentals']; ?></h3>
                <p>Total Rentals</p>
                <span class="stat-icon">📊</span>
            </div>
            <div class="stat-card">
                <h3><?php echo $user_stats['unread_messages']; ?></h3>
                <p>Unread Messages</p>
                <span class="stat-icon">💬</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="success-message">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>

    <div class="profile-content">
        <!-- Profile Information Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?php echo !empty($user_profile['photo']) ? htmlspecialchars($user_profile['photo']) : 'images/default-user.png'; ?>" 
                         alt="Profile Picture" class="user-photo">
                    
                    <!-- Photo Upload Form -->
                    <form method="POST" enctype="multipart/form-data" class="photo-upload-form">
                        <div class="photo-upload">
                            <label for="profile_photo" class="photo-upload-label">
                                📷 Change Photo
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" 
                                   onchange="this.form.submit()" style="display: none;">
                        </div>
                    </form>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user_profile['name']); ?></h2>
                    <p class="user-type-badge <?php echo $user_type; ?>">
                        <?php echo ucfirst($user_type); ?>
                    </p>
                    <p class="user-id">
                        <?php if ($user_type === 'customer'): ?>
                            Customer ID: <?php echo htmlspecialchars($user_profile['type_id']); ?>
                        <?php elseif ($user_type === 'owner'): ?>
                            Owner ID: <?php echo htmlspecialchars($user_profile['type_id']); ?>
                        <?php else: ?>
                            Manager ID: <?php echo htmlspecialchars($user_profile['type_id']); ?>
                        <?php endif; ?>
                    </p>
                    <p class="join-date">Member since: <?php echo formatDate($user_profile['created_at']); ?></p>
                </div>
            </div>
        </div>

        <!-- Profile Edit Form -->
        <div class="profile-form-section">
            <h3>📝 Edit Profile Information</h3>
            
            <form method="POST" class="profile-form">
                <input type="hidden" name="update_profile" value="1">
                
                <!-- Basic Information -->
                <fieldset>
                    <legend>👤 Personal Information</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="national_id" class="readonly-label">National ID (Read Only)</label>
                            <input type="text" id="national_id" value="<?php echo htmlspecialchars($user_profile['national_id']); ?>" readonly class="readonly-field">
                        </div>
                        
                        <div class="form-group">
                            <label for="name" class="required">Full Name</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user_profile['name']); ?>" 
                                   pattern="[a-zA-Z\s]+" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth" class="required">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($user_profile['date_of_birth']); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Address Information -->
                <fieldset>
                    <legend>📍 Address Information</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="flat_house_no" class="required">Flat/House Number</label>
                            <input type="text" id="flat_house_no" name="flat_house_no" 
                                   value="<?php echo htmlspecialchars($user_profile['flat_house_no']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="street_name" class="required">Street Name</label>
                            <input type="text" id="street_name" name="street_name" 
                                   value="<?php echo htmlspecialchars($user_profile['street_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="required">City</label>
                            <input type="text" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($user_profile['city']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code" class="required">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" 
                                   value="<?php echo htmlspecialchars($user_profile['postal_code']); ?>" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Contact Information -->
                <fieldset>
                    <legend>📞 Contact Information</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mobile_number" class="required">Mobile Number</label>
                            <input type="tel" id="mobile_number" name="mobile_number" 
                                   value="<?php echo htmlspecialchars($user_profile['mobile_number']); ?>" 
                                   pattern="05[0-9]{8}" placeholder="05XXXXXXXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone_number" class="required">Telephone Number</label>
                            <input type="tel" id="telephone_number" name="telephone_number" 
                                   value="<?php echo htmlspecialchars($user_profile['telephone_number']); ?>" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Owner-specific fields -->
                <?php if ($user_type === 'owner'): ?>
                    <fieldset>
                        <legend>🏦 Bank Information</legend>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bank_name" class="required">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" 
                                       value="<?php echo htmlspecialchars($user_profile['bank_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bank_branch" class="required">Bank Branch</label>
                                <input type="text" id="bank_branch" name="bank_branch" 
                                       value="<?php echo htmlspecialchars($user_profile['bank_branch']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="account_number" class="required">Account Number</label>
                            <input type="text" id="account_number" name="account_number" 
                                   value="<?php echo htmlspecialchars($user_profile['account_number']); ?>" required>
                        </div>
                    </fieldset>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Update Profile</button>
                    <button type="reset" class="btn btn-secondary">🔄 Reset Changes</button>
                </div>
            </form>
        </div>

        <!-- Password Change Section -->
        <div class="password-section">
            <h3>🔒 Change Password</h3>
            
            <form method="POST" class="password-form">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-group">
                    <label for="current_password" class="required">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password" class="required">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>6-15 characters, must start with a digit and end with a lowercase letter</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="required">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-warning">🔐 Change Password</button>
                </div>
            </form>
        </div>

        <!-- Account Information -->
        <div class="account-info-section">
            <h3>ℹ️ Account Information</h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <strong>Account Created:</strong>
                    <span><?php echo formatDateTime($user_profile['created_at']); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Last Updated:</strong>
                    <span><?php echo formatDateTime($user_profile['updated_at']); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>Account Type:</strong>
                    <span class="user-type-badge <?php echo $user_type; ?>"><?php echo ucfirst($user_type); ?></span>
                </div>
                
                <div class="info-item">
                    <strong>User ID:</strong>
                    <span><?php echo $user_profile['user_id']; ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <h3>⚡ Quick Actions</h3>
            <div class="actions-grid">
                <?php if ($user_type === 'customer'): ?>
                    <a href="main.php?page=my-rentals" class="action-card">
                        <span class="action-icon">🏠</span>
                        <h4>My Rentals</h4>
                        <p>View rental history</p>
                    </a>
                    
                    <a href="main.php?page=shopping-basket" class="action-card">
                        <span class="action-icon">🛒</span>
                        <h4>Shopping Basket</h4>
                        <p>Check saved flats</p>
                    </a>
                    
                    <a href="main.php?page=search" class="action-card">
                        <span class="action-icon">🔍</span>
                        <h4>Search Flats</h4>
                        <p>Find new properties</p>
                    </a>
                    
                <?php elseif ($user_type === 'owner'): ?>
                    <a href="main.php?page=my-flats" class="action-card">
                        <span class="action-icon">🏢</span>
                        <h4>My Properties</h4>
                        <p>Manage your flats</p>
                    </a>
                    
                    <a href="main.php?page=offer-flat" class="action-card">
                        <span class="action-icon">➕</span>
                        <h4>List New Flat</h4>
                        <p>Add property for rent</p>
                    </a>
                    
                <?php else: // manager ?>
                    <a href="main.php?page=pending-approvals" class="action-card">
                        <span class="action-icon">⏳</span>
                        <h4>Pending Approvals</h4>
                        <p>Review new listings</p>
                    </a>
                    
                    <a href="main.php?page=manager-inquiry" class="action-card">
                        <span class="action-icon">📊</span>
                        <h4>Flats Inquiry</h4>
                        <p>Advanced search</p>
                    </a>
                <?php endif; ?>
                
                <a href="main.php?page=messages" class="action-card">
                    <span class="action-icon">💬</span>
                    <h4>Messages</h4>
                    <p>Check notifications</p>
                </a>
            </div>
        </div>
    </div>
</div>
