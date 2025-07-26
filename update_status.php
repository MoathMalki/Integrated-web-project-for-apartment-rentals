<?php
require_once 'database.inc.php';

try {
    // Start transaction
    beginTransaction($pdo);
    
    // Update all 'pending' flats to 'pending_approval'
    $sql = "UPDATE flats SET status = 'pending_approval' WHERE status = 'pending'";
    executeQuery($pdo, $sql);
    
    // Commit transaction
    commitTransaction($pdo);
    
    echo "Successfully updated flat statuses.\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    rollbackTransaction($pdo);
    echo "Error updating flat statuses: " . $e->getMessage() . "\n";
}
?> 