<?php
require_once 'database.inc.php';
require_once 'functions.php';

// Update existing flats with status 'pending' to 'pending_approval'
$update_sql = "UPDATE flats SET status = 'pending_approval' WHERE status = 'pending'";
$stmt = executeQuery($pdo, $update_sql);

$affected_rows = $stmt->rowCount();
echo "Updated $affected_rows flats from 'pending' to 'pending_approval'";
?> 