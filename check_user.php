<?php
require_once 'database.inc.php';
require_once 'functions.php';

$user_id = 21; // Moath's user ID

// Check user table
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $pdo->prepare($user_query);
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch();

echo "=== User Info ===\n";
echo "Name: " . $user['name'] . "\n";
echo "Type: " . $user['user_type'] . "\n";

// Check if in owners table
$owner_query = "SELECT * FROM owners WHERE user_id = :user_id";
$stmt = $pdo->prepare($owner_query);
$stmt->execute([':user_id' => $user_id]);
$owner = $stmt->fetch();

echo "\n=== Owner Info ===\n";
if ($owner) {
    echo "Owner ID: " . $owner['owner_id'] . "\n";
} else {
    echo "Not in owners table\n";
}

// Check if in managers table
$manager_query = "SELECT * FROM managers WHERE user_id = :user_id";
$stmt = $pdo->prepare($manager_query);
$stmt->execute([':user_id' => $user_id]);
$manager = $stmt->fetch();

echo "\n=== Manager Info ===\n";
if ($manager) {
    echo "Manager ID: " . $manager['manager_id'] . "\n";
} else {
    echo "Not in managers table\n";
}

// Check flats created by this user
$flats_query = "SELECT * FROM flats WHERE owner_id = :user_id";
$stmt = $pdo->prepare($flats_query);
$stmt->execute([':user_id' => $user_id]);
$flats = $stmt->fetchAll();

echo "\n=== Flats Created ===\n";
foreach ($flats as $flat) {
    echo "Flat ID: " . $flat['flat_id'] . "\n";
    echo "Location: " . $flat['location'] . "\n";
    echo "Status: " . $flat['status'] . "\n\n";
}
?> 