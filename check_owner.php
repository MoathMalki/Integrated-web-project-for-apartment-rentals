<?php
require_once 'database.inc.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

try {
    // Check user details
    $user_sql = "SELECT * FROM users WHERE user_id = :user_id";
    $user_stmt = executeQuery($pdo, $user_sql, [':user_id' => $_SESSION['user_id']]);
    $user = $user_stmt->fetch();
    
    echo "User Information:\n";
    echo "----------------\n";
    echo "User ID: " . $user['user_id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Type: " . $user['user_type'] . "\n";
    
    // Check if user is an owner
    $owner_sql = "SELECT * FROM owners WHERE user_id = :user_id";
    $owner_stmt = executeQuery($pdo, $owner_sql, [':user_id' => $_SESSION['user_id']]);
    $owner = $owner_stmt->fetch();
    
    if ($owner) {
        echo "\nOwner Information:\n";
        echo "-----------------\n";
        echo "Owner ID: " . $owner['owner_id'] . "\n";
        echo "Bank Name: " . $owner['bank_name'] . "\n";
        echo "Bank Branch: " . $owner['bank_branch'] . "\n";
        echo "Account Number: " . $owner['account_number'] . "\n";
    } else {
        echo "\nThis user is not registered as an owner!\n";
        echo "You need to add an owner record for this user.\n";
        
        // Generate a new owner ID
        $new_owner_id = str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        echo "\nSuggested owner_id for registration: " . $new_owner_id . "\n";
        
        echo "\nUse this SQL to add the owner record:\n";
        echo "----------------------------------------\n";
        echo "INSERT INTO owners (owner_id, user_id, bank_name, bank_branch, account_number)\n";
        echo "VALUES ('" . $new_owner_id . "', " . $_SESSION['user_id'] . ", 'YOUR_BANK_NAME', 'YOUR_BRANCH', 'YOUR_ACCOUNT');\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 