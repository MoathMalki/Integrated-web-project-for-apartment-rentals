<?php
// Database connection configuration
// Birzeit Flat Rent System

// Database configuration
$db_host = 'localhost';
$db_name = 'birzeit_rent'; // Replace with {prefix}_stdID format
$db_username = 'root'; // Replace {stID} with your student ID
$db_password = ''; // Replace {stID} with your student ID
$db_charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// PDO options
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $db_username, $db_password, $pdo_options);
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Function to execute prepared statements with named parameters
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw $e;
    }
}

// Function to get last insert ID
function getLastInsertId($pdo) {
    return $pdo->lastInsertId();
}

// Function to begin transaction
function beginTransaction($pdo) {
    return $pdo->beginTransaction();
}

// Function to commit transaction
function commitTransaction($pdo) {
    return $pdo->commit();
}

// Function to rollback transaction
function rollbackTransaction($pdo) {
    return $pdo->rollBack();
}
?>