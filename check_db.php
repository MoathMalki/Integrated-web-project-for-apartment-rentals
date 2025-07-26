<?php
require_once 'database.inc.php';

try {
    // Check flat statuses
    $sql = "SELECT status, COUNT(*) as count FROM flats GROUP BY status";
    $stmt = executeQuery($pdo, $sql);
    $results = $stmt->fetchAll();
    
    echo "Flat Status Counts:\n";
    foreach ($results as $row) {
        echo "{$row['status']}: {$row['count']}\n";
    }
    
    // Check specific flat details
    $sql = "SELECT f.*, u.name as owner_name 
            FROM flats f 
            JOIN users u ON f.owner_id = u.user_id 
            ORDER BY f.created_date DESC 
            LIMIT 5";
    $stmt = executeQuery($pdo, $sql);
    $flats = $stmt->fetchAll();
    
    echo "\nLatest Flats:\n";
    foreach ($flats as $flat) {
        echo "ID: {$flat['flat_id']}, ";
        echo "Location: {$flat['location']}, ";
        echo "Status: {$flat['status']}, ";
        echo "Owner: {$flat['owner_name']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 