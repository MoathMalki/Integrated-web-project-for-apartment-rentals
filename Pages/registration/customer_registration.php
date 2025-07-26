<?php
session_start();

// Initialize step if not set
if (!isset($_SESSION['registration_step'])) {
    $_SESSION['registration_step'] = 1;
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate password
function isValidPassword($password) {
    return preg_match('/^[0-9].*[a-z]$/', $password) && 
           strlen($password) >= 6 && 
           strlen($password) <= 15;
}

// Function to generate customer ID
function generateCustomerID() {
    return mt_rand(100000000, 999999999);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1'])) {
        // Validate and store step 1 data
        $_SESSION['customer_data'] = [
            'national_id' => $_POST['national_id'],
            'name' => $_POST['name'],
            'flat_no' => $_POST['flat_no'],
            'street' => $_POST['street'],
            'city' => $_POST['city'],
            'postal_code' => $_POST['postal_code'],
            'dob' => $_POST['dob'],
            'email' => $_POST['email'],
            'mobile' => $_POST['mobile'],
            'telephone' => $_POST['telephone']
        ];
        $_SESSION['registration_step'] = 2;
    } 
    elseif (isset($_POST['step2'])) {
        // Validate and store step 2 data
        $email = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (!isValidEmail($email)) {
            $error = "Invalid email format";
        } 
        elseif (!isValidPassword($password)) {
            $error = "Password must be 6-15 characters, start with a digit and end with a lowercase letter";
        }
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        }
        else {
            $_SESSION['account_data'] = [
                'username' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ];
            $_SESSION['registration_step'] = 3;
        }
    }
    elseif (isset($_POST['confirm'])) {
        // Generate customer ID and save to database
        $customer_id = generateCustomerID();
        $_SESSION['customer_id'] = $customer_id;
        
        // Here you would typically save all the data to your database
        // For now, we'll just show the confirmation message
        
        $_SESSION['registration_complete'] = true;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // ... other validation ...
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }
    // If no error, proceed with registration logic
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="registration-form">
        <h1>Customer Registration</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message error-inline">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['registration_complete'])): ?>
            <div class="confirmation-message">
                <h2>Registration Complete!</h2>
                <p>Thank you, <?php echo htmlspecialchars($_SESSION['customer_data']['name']); ?>!</p>
                <p>Your Customer ID is: <?php echo htmlspecialchars($_SESSION['customer_id']); ?></p>
                <p>Please keep this ID for future reference.</p>
                <a href="../login.php" class="btn btn-primary">Proceed to Login</a>
            </div>
        <?php else: ?>
            <div class="step-indicator">
                <div class="step-line"></div>
                <div class="step <?php echo $_SESSION['registration_step'] >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $_SESSION['registration_step'] >= 2 ? 'active' : ''; ?>">2</div>
                <div class="step <?php echo $_SESSION['registration_step'] >= 3 ? 'active' : ''; ?>">3</div>
            </div>

            <?php if ($_SESSION['registration_step'] == 1): ?>
                <form method="POST" class="form-step active">
                    <h2>Step 1: Personal Information</h2>
                    
                    <div class="form-group">
                        <label for="national_id">National ID Number (رقم الهوية)</label>
                        <input type="text" id="national_id" name="national_id" required>
                    </div>

                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" pattern="[A-Za-z\s]+" title="Only characters allowed" required>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="flat_no" placeholder="Flat/House No." required>
                        <input type="text" name="street" placeholder="Street Name" required>
                        <input type="text" name="city" placeholder="City" required>
                        <input type="text" name="postal_code" placeholder="Postal Code" required>
                    </div>

                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile" required>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Telephone Number</label>
                        <input type="tel" id="telephone" name="telephone">
                    </div>

                    <button type="submit" name="step1" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($_SESSION['registration_step'] == 2): ?>
                <form method="POST" class="form-step active">
                    <h2>Step 2: Account Information</h2>
                    
                    <div class="form-group">
                        <label for="username">Username (Email Address)</label>
                        <input type="email" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" 
                               pattern="^[0-9].*[a-z]$"
                               title="Must be 6-15 characters, start with a digit and end with a lowercase letter"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" name="step2" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($_SESSION['registration_step'] == 3): ?>
                <form method="POST" class="form-step active">
                    <h2>Step 3: Review Information</h2>
                    
                    <div class="review-info">
                        <h3>Personal Information</h3>
                        <p><strong>National ID:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['national_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['name']); ?></p>
                        <p><strong>Address:</strong><br>
                           <?php echo htmlspecialchars($_SESSION['customer_data']['flat_no']); ?><br>
                           <?php echo htmlspecialchars($_SESSION['customer_data']['street']); ?><br>
                           <?php echo htmlspecialchars($_SESSION['customer_data']['city']); ?><br>
                           <?php echo htmlspecialchars($_SESSION['customer_data']['postal_code']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['dob']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['email']); ?></p>
                        <p><strong>Mobile:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['mobile']); ?></p>
                        <p><strong>Telephone:</strong> <?php echo htmlspecialchars($_SESSION['customer_data']['telephone']); ?></p>
                        
                        <h3>Account Information</h3>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['account_data']['username']); ?></p>
                    </div>

                    <button type="submit" name="confirm" class="btn btn-primary">Confirm Registration</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html> 