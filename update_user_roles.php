<?php
require_once 'database.inc.php';
require_once 'functions.php';

$user_id = 21; // Moath's user ID

try {
    // Start transaction
    $pdo->beginTransaction();

    // 1. Check if already in owners table
    $check_owner = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = ?");
    $check_owner->execute([$user_id]);
    $existing_owner = $check_owner->fetch();

    if (!$existing_owner) {
        // Generate owner ID
        $owner_id = generateOwnerId();
        
        // Add to owners table
        $add_owner = $pdo->prepare("INSERT INTO owners (owner_id, user_id) VALUES (?, ?)");
        $add_owner->execute([$owner_id, $user_id]);
        
        echo "Added user to owners table with owner_id: $owner_id\n";
    } else {
        echo "User already in owners table with owner_id: {$existing_owner['owner_id']}\n";
    }

    // 2. Update user type to indicate both roles
    $update_type = $pdo->prepare("UPDATE users SET user_type = 'manager,owner' WHERE user_id = ?");
    $update_type->execute([$user_id]);
    
    echo "Updated user_type to 'manager,owner'\n";

    // Commit transaction
    $pdo->commit();
    echo "Changes committed successfully\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?> 