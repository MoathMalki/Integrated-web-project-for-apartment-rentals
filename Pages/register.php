<?php
// Multi-step registration for customers and owners

// Clean expired sessions
cleanExpiredSessions($pdo);

// Initialize registration data
if (!isset($_SESSION['registration_step']) || (isset($_GET['type']) && $_GET['type'] !== $_SESSION['user_type'])) {
    // Reset registration session if user type changes or session not set
    $_SESSION['registration_step'] = 1;
    $_SESSION['registration_data'] = [];
    $_SESSION['user_type'] = isset($_GET['type']) ? $_GET['type'] : 'customer';
}

$current_step = $_SESSION['registration_step'];
$user_type = $_SESSION['user_type'];
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'step1':
                $errors = validateStep1($_POST, $user_type);
                if (empty($errors)) {
                    $_SESSION['registration_data']['step1'] = $_POST;
                    $_SESSION['registration_step'] = 2;
                    $current_step = 2;
                }
                break;
                
            case 'step2':
                $errors = validateStep2($_POST, $pdo);
                if (empty($errors)) {
                    $_SESSION['registration_data']['step2'] = $_POST;
                    $_SESSION['registration_step'] = 3;
                    $current_step = 3;
                }
                break;
                
            case 'confirm':
                $errors = processRegistration($pdo, $_SESSION['registration_data'], $user_type);
                if (empty($errors)) {
                    $success_message = "Registration completed successfully!";
                    // Clear registration session data
                    unset($_SESSION['registration_step']);
                    unset($_SESSION['registration_data']);
                    unset($_SESSION['user_type']);
                }
                break;
                
            case 'back':
                if ($current_step > 1) {
                    $_SESSION['registration_step']--;
                    $current_step = $_SESSION['registration_step'];
                }
                break;
                
            case 'restart':
                unset($_SESSION['registration_step']);
                unset($_SESSION['registration_data']);
                unset($_SESSION['user_type']);
                header('Location: main.php?page=register');
                exit;
                break;
        }
    }
}

// Validation functions
function validateStep1($data, $user_type) {
    $errors = [];
    
    // Required fields validation
    $required_fields = ['national_id', 'name', 'flat_house_no', 'street_name', 'city', 
                       'postal_code', 'date_of_birth', 'email', 'mobile_number', 'telephone_number'];
    
    if ($user_type === 'owner') {
        $required_fields = array_merge($required_fields, ['bank_name', 'bank_branch', 'account_number']);
    }
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Specific validations
    if (!empty($data['national_id']) && !validateNationalId($data['national_id'])) {
        $errors['national_id'] = 'National ID must be 9 digits';
    }
    
    if (!empty($data['name']) && !preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
        $errors['name'] = 'Name must contain only letters and spaces';
    }
    
    if (!empty($data['email']) && !validateEmail($data['email'])) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (!empty($data['mobile_number']) && !validateMobile($data['mobile_number'])) {
        $errors['mobile_number'] = 'Mobile number must be in format 05XXXXXXXX';
    }
    
    if (!empty($data['date_of_birth'])) {
        $birth_date = new DateTime($data['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        if ($age < 18) {
            $errors['date_of_birth'] = 'You must be at least 18 years old';
        }
    }
    
    return $errors;
}

function validateStep2($data, $pdo) {
    $errors = [];
    
    if (empty($data['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (!validateEmail($data['username'])) {
        $errors['username'] = 'Username must be a valid email address';
    } else {
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = :email";
        $stmt = executeQuery($pdo, $sql, [':email' => $data['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'This email address is already registered';
        }
    }
    
    if (empty($data['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (!validatePassword($data['password'])) {
        $errors['password'] = 'Password must be 6-15 characters, start with a digit, and end with a lowercase letter';
    }
    
    if (empty($data['confirm_password'])) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    return $errors;
}

function processRegistration($pdo, $registration_data, $user_type) {
    $errors = [];
    
    try {
        beginTransaction($pdo);
        
        $step1 = $registration_data['step1'];
        $step2 = $registration_data['step2'];
        
        // Debug log
        error_log("Starting registration process for user type: " . $user_type);
        error_log("Step 1 data: " . print_r($step1, true));
        error_log("Step 2 data: " . print_r($step2, true));
        
        // Insert into users table
        $sql = "INSERT INTO users (national_id, name, flat_house_no, street_name, city, postal_code, 
                                  date_of_birth, email, mobile_number, telephone_number, user_type, password_hash) 
                VALUES (:national_id, :name, :flat_house_no, :street_name, :city, :postal_code, 
                        :date_of_birth, :email, :mobile_number, :telephone_number, :user_type, :password_hash)";
        
        $params = [
            ':national_id' => $step1['national_id'],
            ':name' => $step1['name'],
            ':flat_house_no' => $step1['flat_house_no'],
            ':street_name' => $step1['street_name'],
            ':city' => $step1['city'],
            ':postal_code' => $step1['postal_code'],
            ':date_of_birth' => $step1['date_of_birth'],
            ':email' => $step2['username'],
            ':mobile_number' => $step1['mobile_number'],
            ':telephone_number' => $step1['telephone_number'],
            ':user_type' => $user_type,
            ':password_hash' => password_hash($step2['password'], PASSWORD_DEFAULT)
        ];
        
        error_log("Executing users table insert with params: " . print_r($params, true));
        
        executeQuery($pdo, $sql, $params);
        $user_id = getLastInsertId($pdo);
        
        error_log("User inserted with ID: " . $user_id);
        
        // Insert into specific user type table
        if ($user_type === 'customer') {
            $customer_id = generateCustomerId();
            $sql = "INSERT INTO customers (customer_id, user_id) VALUES (:customer_id, :user_id)";
            error_log("Inserting customer with ID: " . $customer_id);
            executeQuery($pdo, $sql, [':customer_id' => $customer_id, ':user_id' => $user_id]);
            $_SESSION['generated_id'] = $customer_id;
        } else {
            $owner_id = generateOwnerId();
            $sql = "INSERT INTO owners (owner_id, user_id, bank_name, bank_branch, account_number) 
                    VALUES (:owner_id, :user_id, :bank_name, :bank_branch, :account_number)";
            error_log("Inserting owner with ID: " . $owner_id);
            executeQuery($pdo, $sql, [
                ':owner_id' => $owner_id,
                ':user_id' => $user_id,
                ':bank_name' => $step1['bank_name'],
                ':bank_branch' => $step1['bank_branch'],
                ':account_number' => $step1['account_number']
            ]);
            $_SESSION['generated_id'] = $owner_id;
        }
        
        $_SESSION['registered_name'] = $step1['name'];
        commitTransaction($pdo);
        error_log("Registration completed successfully");
        
    } catch (Exception $e) {
        rollbackTransaction($pdo);
        error_log("Registration failed with error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $errors['general'] = 'Registration failed. Please try again. Error: ' . $e->getMessage();
    }
    
    return $errors;
}
?>

<div class="registration-page">
    <h1>📝 <?php echo ucfirst($user_type); ?> Registration</h1>
    
    <!-- Registration Steps Indicator -->
    <div class="registration-steps">
        <div class="step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
            Step 1: Personal Info
        </div>
        <div class="step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
            Step 2: E-Account
        </div>
        <div class="step <?php echo $current_step >= 3 ? 'active' : ''; ?>">
            Step 3: Confirmation
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

    <?php if ($success_message): ?>
        <div class="success-message">
            <h2>🎉 Registration Successful!</h2>
            <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['registered_name']); ?></strong>!</p>
            <p>Your <?php echo $user_type; ?> ID is: <strong><?php echo $_SESSION['generated_id']; ?></strong></p>
            <p>You can now log in to your account using your email and password.</p>
            <div class="success-actions">
                <a href="main.php?page=login" class="btn btn-primary">Login Now</a>
                <a href="main.php" class="btn btn-secondary">Go to Home</a>
            </div>
            <?php 
            unset($_SESSION['registered_name']);
            unset($_SESSION['generated_id']);
            ?>
        </div>
    <?php else: ?>

        <!-- User Type Selection -->
        <?php if ($current_step === 1 && !isset($_SESSION['registration_data']['step1'])): ?>
            <div class="user-type-selection">
                <h2>Select Registration Type</h2>
                <div class="type-cards">
                    <a href="?type=customer" class="type-card <?php echo $user_type === 'customer' ? 'selected' : ''; ?>">
                        <h3>👤 Customer</h3>
                        <p>Register as a customer to search and rent flats</p>
                        <ul>
                            <li>Search available flats</li>
                            <li>Book viewing appointments</li>
                            <li>Rent flats online</li>
                            <li>Manage your rentals</li>
                        </ul>
                    </a>
                    <a href="?type=owner" class="type-card <?php echo $user_type === 'owner' ? 'selected' : ''; ?>">
                        <h3>🏠 Property Owner</h3>
                        <p>Register as an owner to list your properties for rent</p>
                        <ul>
                            <li>List your flats for rent</li>
                            <li>Manage property details</li>
                            <li>Review rental applications</li>
                            <li>Track rental income</li>
                        </ul>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 1: Personal Information -->
        <?php if ($current_step === 1): ?>
            <div class="registration-form">
                <h2>Step 1: Personal Information</h2>
                <form method="POST" class="step-form">
                    <input type="hidden" name="action" value="step1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="national_id" class="required">National ID Number (رقم الهوية)</label>
                            <input type="text" id="national_id" name="national_id" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['national_id'] ?? ''); ?>" 
                                   maxlength="9" pattern="[0-9]{9}" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="name" class="required">Full Name</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['name'] ?? ''); ?>" 
                                   pattern="[a-zA-Z\s]+" required>
                        </div>
                    </div>
                    
                    <fieldset>
                        <legend>Address Information</legend>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="flat_house_no" class="required">Flat/House Number</label>
                                <input type="text" id="flat_house_no" name="flat_house_no" 
                                       value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['flat_house_no'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="street_name" class="required">Street Name</label>
                                <input type="text" id="street_name" name="street_name" 
                                       value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['street_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city" class="required">City</label>
                                <input type="text" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['city'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="postal_code" class="required">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['postal_code'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </fieldset>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth" class="required">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['date_of_birth'] ?? ''); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mobile_number" class="required">Mobile Number</label>
                            <input type="tel" id="mobile_number" name="mobile_number" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['mobile_number'] ?? ''); ?>" 
                                   pattern="05[0-9]{8}" placeholder="05XXXXXXXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone_number" class="required">Telephone Number</label>
                            <input type="tel" id="telephone_number" name="telephone_number" 
                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['telephone_number'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <?php if ($user_type === 'owner'): ?>
                        <fieldset>
                            <legend>Bank Details</legend>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="bank_name" class="required">Bank Name</label>
                                    <input type="text" id="bank_name" name="bank_name" 
                                           value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['bank_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bank_branch" class="required">Bank Branch</label>
                                    <input type="text" id="bank_branch" name="bank_branch" 
                                           value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['bank_branch'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="account_number" class="required">Account Number</label>
                                <input type="text" id="account_number" name="account_number" 
                                       value="<?php echo htmlspecialchars($_SESSION['registration_data']['step1']['account_number'] ?? ''); ?>" required>
                            </div>
                        </fieldset>
                    <?php endif; ?>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                        <a href="main.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 2: E-Account Creation -->
        <?php if ($current_step === 2): ?>
            <div class="registration-form">
                <h2>Step 2: E-Account Creation</h2>
                <form method="POST" class="step-form">
                    <input type="hidden" name="action" value="step2">
                    
                    <div class="form-group">
                        <label for="username" class="required">Username (Email Address)</label>
                        <input type="email" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_SESSION['registration_data']['step2']['username'] ?? ''); ?>" 
                               required>
                        <small>This will be your login email address</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" required>
                            <small>6-15 characters, must start with a digit and end with a lowercase letter</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="action" value="back" class="btn btn-secondary">← Back</button>
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 3: Confirmation -->
        <?php if ($current_step === 3): ?>
            <div class="registration-form">
                <h2>Step 3: Review & Confirmation</h2>
                <p>Please review your information before confirming your registration:</p>
                
                <div class="confirmation-details">
                    <div class="detail-section">
                        <h3>Personal Information</h3>
                        <div class="detail-grid">
                            <div><strong>National ID:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['national_id']); ?></div>
                            <div><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['name']); ?></div>
                            <div><strong>Address:</strong> 
                                <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['flat_house_no'] . ', ' . 
                                     $_SESSION['registration_data']['step1']['street_name'] . ', ' . 
                                     $_SESSION['registration_data']['step1']['city'] . ', ' . 
                                     $_SESSION['registration_data']['step1']['postal_code']); ?>
                            </div>
                            <div><strong>Date of Birth:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['date_of_birth']); ?></div>
                            <div><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['email']); ?></div>
                            <div><strong>Mobile:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['mobile_number']); ?></div>
                            <div><strong>Telephone:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['telephone_number']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($user_type === 'owner'): ?>
                        <div class="detail-section">
                            <h3>Bank Information</h3>
                            <div class="detail-grid">
                                <div><strong>Bank:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['bank_name']); ?></div>
                                <div><strong>Branch:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['bank_branch']); ?></div>
                                <div><strong>Account:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step1']['account_number']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detail-section">
                        <h3>Account Information</h3>
                        <div class="detail-grid">
                            <div><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['step2']['username']); ?></div>
                            <div><strong>Password:</strong> ••••••••</div>
                            <div><strong>Account Type:</strong> <?php echo ucfirst($user_type); ?></div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="step-form">
                    <div class="form-actions">
                        <button type="submit" name="action" value="back" class="btn btn-secondary">← Back</button>
                        <button type="submit" name="action" value="confirm" class="btn btn-success">✓ Confirm Registration</button>
                        <button type="submit" name="action" value="restart" class="btn btn-danger">🔄 Start Over</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

