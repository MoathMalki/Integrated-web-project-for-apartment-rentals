<?php
require_once 'database.inc.php';

try {
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    echo "Successfully added photo column to users table.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 