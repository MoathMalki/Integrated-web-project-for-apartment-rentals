<?php
require_once 'includes/db.php';

try {
    // Check flats
    $flats_query = "SELECT flat_id, owner_id, status FROM flats WHERE status = 'pending_approval'";
    $flats_stmt = $pdo->query($flats_query);
    $flats = $flats_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Pending Flats:\n";
    print_r($flats);
    
    if (!empty($flats)) {
        // Get the owner_ids from flats
        $owner_ids = array_column($flats, 'owner_id');
        $owner_ids_str = implode("','", $owner_ids);
        
        // Check owners
        $owners_query = "SELECT * FROM owners WHERE owner_id IN ('$owner_ids_str')";
        $owners_stmt = $pdo->query($owners_query);
        $owners = $owners_stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nMatching Owners:\n";
        print_r($owners);
        
        if (!empty($owners)) {
            // Get the user_ids from owners
            $user_ids = array_column($owners, 'user_id');
            $user_ids_str = implode("','", $user_ids);
            
            // Check users
            $users_query = "SELECT user_id, name, user_type FROM users WHERE user_id IN ('$user_ids_str')";
            $users_stmt = $pdo->query($users_query);
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "\nMatching Users:\n";
            print_r($users);
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 