<?php
// Utility functions for Birzeit Flat Rent System

// User authentication and management functions
function authenticateUser($pdo, $email, $password) {
    $sql = "SELECT u.*, c.customer_id, o.owner_id, m.manager_id 
            FROM users u 
            LEFT JOIN customers c ON u.user_id = c.user_id 
            LEFT JOIN owners o ON u.user_id = o.user_id 
            LEFT JOIN managers m ON u.user_id = m.user_id 
            WHERE u.email = :email";
    
    $stmt = executeQuery($pdo, $sql, [':email' => $email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function getUserInfo($pdo, $user_id) {
    $sql = "SELECT * FROM users WHERE user_id = :user_id";
    $stmt = executeQuery($pdo, $sql, [':user_id' => $user_id]);
    return $stmt->fetch();
}

function generateCustomerId() {
    return str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
}

function generateOwnerId() {
    return str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
}

function generateFlatReference() {
    global $pdo;
    do {
        $reference = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT flat_id FROM flats WHERE flat_reference = ?");
        $stmt->execute([$reference]);
    } while ($stmt->fetch());
    
    return $reference;
}

// Password validation
function validatePassword($password) {
    // Between 6-15 characters, starts with digit, ends with lowercase letter
    if (strlen($password) < 6 || strlen($password) > 15) {
        return false;
    }
    if (!ctype_digit($password[0])) {
        return false;
    }
    if (!ctype_lower($password[strlen($password) - 1])) {
        return false;
    }
    return true;
}

// Email validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Flat search functions
function searchFlats($pdo, $filters = []) {
    $sql = "SELECT f.*, u.name as owner_name, u.mobile_number as owner_mobile,
                   fp.photo_path as main_photo
            FROM flats f 
            JOIN owners o ON f.owner_id = o.owner_id 
            JOIN users u ON o.user_id = u.user_id 
            LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id AND fp.is_main_photo = 1
            WHERE f.status = 'approved' 
            AND f.flat_id NOT IN (
                SELECT flat_id FROM rentals 
                WHERE status IN ('active', 'confirmed') 
                AND CURDATE() BETWEEN rental_start_date AND rental_end_date
            )";
    
    $params = [];
    
    if (!empty($filters['min_price'])) {
        $sql .= " AND f.monthly_cost >= :min_price";
        $params[':min_price'] = $filters['min_price'];
    }
    
    if (!empty($filters['max_price'])) {
        $sql .= " AND f.monthly_cost <= :max_price";
        $params[':max_price'] = $filters['max_price'];
    }
    
    if (!empty($filters['location'])) {
        $sql .= " AND f.location LIKE :location";
        $params[':location'] = '%' . $filters['location'] . '%';
    }
    
    if (!empty($filters['bedrooms'])) {
        $sql .= " AND f.bedrooms >= :bedrooms";
        $params[':bedrooms'] = $filters['bedrooms'];
    }
    
    if (!empty($filters['bathrooms'])) {
        $sql .= " AND f.bathrooms >= :bathrooms";
        $params[':bathrooms'] = $filters['bathrooms'];
    }
    
    if (isset($filters['furnished']) && $filters['furnished'] !== '') {
        $sql .= " AND f.furnished = :furnished";
        $params[':furnished'] = $filters['furnished'];
    }
    
    // Default sorting by monthly rental rate (ascending)
    $sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'monthly_cost';
    $sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'ASC';
    
    $sql .= " ORDER BY f.$sort_by $sort_order";
    
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetchAll();
}

function getFlatDetails($pdo, $flat_id) {
    $sql = "SELECT f.*, u.name as owner_name, u.mobile_number as owner_mobile,
                   u.email as owner_email, u.city as owner_city
            FROM flats f 
            JOIN owners o ON f.owner_id = o.owner_id 
            JOIN users u ON o.user_id = u.user_id 
            WHERE f.flat_id = :flat_id";
    
    // If user is an owner, only allow access to their own flats
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'owner') {
        $sql .= " AND o.owner_id = :owner_id";
        $params = [
            ':flat_id' => $flat_id,
            ':owner_id' => $_SESSION['owner_id']
        ];
    } else {
        $params = [':flat_id' => $flat_id];
    }
    
    $stmt = executeQuery($pdo, $sql, $params);
    return $stmt->fetch();
}

function getFlatPhotos($pdo, $flat_id) {
    echo "<!-- Debug: Getting photos for flat ID: " . $flat_id . " -->\n";
    $sql = "SELECT * FROM flat_photos WHERE flat_id = :flat_id ORDER BY is_main_photo DESC, photo_order ASC";
    echo "<!-- Debug: SQL Query: " . htmlspecialchars($sql) . " -->\n";
    
    try {
        $stmt = executeQuery($pdo, $sql, [':flat_id' => $flat_id]);
        $photos = $stmt->fetchAll();
        echo "<!-- Debug: Found " . count($photos) . " photos -->\n";
        echo "<!-- Debug: Photo data: " . htmlspecialchars(json_encode($photos)) . " -->\n";
        return $photos;
    } catch (PDOException $e) {
        echo "<!-- Debug: Database error: " . htmlspecialchars($e->getMessage()) . " -->\n";
        return [];
    }
}

function getFlatMarketing($pdo, $flat_id) {
    $sql = "SELECT * FROM flat_marketing WHERE flat_id = :flat_id";
    $stmt = executeQuery($pdo, $sql, [':flat_id' => $flat_id]);
    return $stmt->fetchAll();
}

// Rental functions
function getUserRentals($pdo, $customer_id) {
    $sql = "SELECT r.*, f.flat_reference, f.location, f.monthly_cost,
                   u.name as owner_name, u.mobile_number as owner_mobile, u.email as owner_email,
                   u.city as owner_city,
                   CASE 
                       WHEN CURDATE() < r.rental_start_date THEN 'upcoming'
                       WHEN CURDATE() BETWEEN r.rental_start_date AND r.rental_end_date THEN 'current'
                       ELSE 'past'
                   END as rental_status
            FROM rentals r 
            JOIN flats f ON r.flat_id = f.flat_id 
            JOIN owners o ON f.owner_id = o.owner_id 
            JOIN users u ON o.user_id = u.user_id 
            WHERE r.customer_id = :customer_id 
            AND r.status IN ('confirmed', 'active', 'completed')
            ORDER BY r.rental_start_date DESC";
    
    $stmt = executeQuery($pdo, $sql, [':customer_id' => $customer_id]);
    return $stmt->fetchAll();
}

function calculateRentalCost($monthly_cost, $start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $months = $start->diff($end)->m + ($start->diff($end)->y * 12) + 1;
    return $monthly_cost * $months;
}

// Preview appointment functions
function getAvailableAppointments($pdo, $flat_id) {
    $sql = "SELECT * FROM preview_timetables 
            WHERE flat_id = :flat_id 
            AND available_date >= CURDATE() 
            ORDER BY available_date ASC, available_time ASC";
    
    $stmt = executeQuery($pdo, $sql, [':flat_id' => $flat_id]);
    return $stmt->fetchAll();
}

function bookPreviewAppointment($pdo, $timetable_id, $customer_id) {
    $sql = "UPDATE preview_timetables 
            SET is_booked = 1, booked_by = :customer_id 
            WHERE timetable_id = :timetable_id AND is_booked = 0";
    
    $stmt = executeQuery($pdo, $sql, [
        ':timetable_id' => $timetable_id,
        ':customer_id' => $customer_id
    ]);
    
    return $stmt->rowCount() > 0;
}

// Message functions
function sendMessage($pdo, $recipient_id, $title, $message_body, $sender_type = 'system', $sender_id = null) {
    $sql = "INSERT INTO messages (user_id, title, message, sender_type, created_date) 
            VALUES (:user_id, :title, :message, :sender_type, NOW())";
    
    $params = [
        ':user_id' => $recipient_id,
        ':title' => $title,
        ':message' => $message_body,
        ':sender_type' => $sender_type
    ];
    
    executeQuery($pdo, $sql, $params);
}

function getUserMessages($pdo, $user_id) {
    $sql = "SELECT m.*, u.name as sender_name 
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.user_id 
            WHERE m.user_id = :user_id 
            ORDER BY m.created_date DESC";
    
    $stmt = executeQuery($pdo, $sql, [':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function markMessageAsRead($pdo, $message_id, $user_id) {
    $sql = "UPDATE messages SET is_read = 1 
            WHERE message_id = :message_id AND user_id = :user_id";
    
    executeQuery($pdo, $sql, [
        ':message_id' => $message_id,
        ':user_id' => $user_id
    ]);
}

// Shopping basket functions
function addToBasket($pdo, $customer_id, $flat_id, $start_date = null, $end_date = null) {
    // Check if item already in basket
    $sql = "SELECT * FROM shopping_basket WHERE customer_id = :customer_id AND flat_id = :flat_id";
    $stmt = executeQuery($pdo, $sql, [':customer_id' => $customer_id, ':flat_id' => $flat_id]);
    
    if ($stmt->fetch()) {
        return false; // Already in basket
    }
    
    $sql = "INSERT INTO shopping_basket (customer_id, flat_id, rental_start_date, rental_end_date) 
            VALUES (:customer_id, :flat_id, :start_date, :end_date)";
    
    executeQuery($pdo, $sql, [
        ':customer_id' => $customer_id,
        ':flat_id' => $flat_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    return true;
}

function getBasketItems($pdo, $customer_id) {
    $sql = "SELECT sb.*, f.flat_reference, f.location, f.monthly_cost,
                   fp.photo_path as main_photo
            FROM shopping_basket sb 
            JOIN flats f ON sb.flat_id = f.flat_id 
            LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id AND fp.is_main_photo = 1
            WHERE sb.customer_id = :customer_id 
            ORDER BY sb.created_at DESC";
    
    $stmt = executeQuery($pdo, $sql, [':customer_id' => $customer_id]);
    return $stmt->fetchAll();
}

function removeFromBasket($pdo, $basket_id, $customer_id) {
    $sql = "DELETE FROM shopping_basket 
            WHERE basket_id = :basket_id AND customer_id = :customer_id";
    
    executeQuery($pdo, $sql, [
        ':basket_id' => $basket_id,
        ':customer_id' => $customer_id
    ]);
}

// Manager functions
function getPendingFlats($pdo) {
    $sql = "SELECT f.*, u.name as owner_name, u.email as owner_email,
                   COUNT(fp.photo_id) as photo_count
            FROM flats f 
            JOIN owners o ON f.owner_id = o.owner_id 
            JOIN users u ON o.user_id = u.user_id 
            LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id
            WHERE f.status = 'pending_approval' 
            GROUP BY f.flat_id
            ORDER BY f.created_at ASC";
    
    $stmt = executeQuery($pdo, $sql);
    return $stmt->fetchAll();
}

function approveFlat($pdo, $flat_id) {
    // Generate flat reference number
    $flat_reference = generateFlatReference();
    
    $sql = "UPDATE flats 
            SET status = 'approved', flat_reference = :flat_reference 
            WHERE flat_id = :flat_id";
    
    executeQuery($pdo, $sql, [
        ':flat_reference' => $flat_reference,
        ':flat_id' => $flat_id
    ]);
    
    return $flat_reference;
}

function rejectFlat($pdo, $flat_id, $reason) {
    $sql = "UPDATE flats SET status = 'rejected' WHERE flat_id = :flat_id";
    executeQuery($pdo, $sql, [':flat_id' => $flat_id]);
    
    // Send message to owner
    $flat_sql = "SELECT o.user_id FROM flats f JOIN owners o ON f.owner_id = o.owner_id WHERE f.flat_id = :flat_id";
    $stmt = executeQuery($pdo, $flat_sql, [':flat_id' => $flat_id]);
    $owner = $stmt->fetch();
    
    if ($owner) {
        sendMessage($pdo, $owner['user_id'], 'Flat Listing Rejected', 
                   "Your flat listing has been rejected. Reason: $reason");
    }
}

// Utility functions
function formatCurrency($amount) {
    return '₪' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirectToLogin($message = '') {
    if ($message) {
        $_SESSION['login_message'] = $message;
    }
    header('Location: main.php?page=login');
    exit;
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirectToLogin('Please log in to access this page.');
    }
}

function requireUserType($required_type) {
    requireLogin();
    if ($_SESSION['user_type'] !== $required_type) {
        header('Location: main.php');
        exit;
    }
}

// Cookie functions for sorting preferences
function setSortingPreference($column, $order) {
    // Clear any old cookies
    setcookie('sort_column', '', time() - 3600, '/');
    setcookie('sort_order', '', time() - 3600, '/');
    
    // Set new cookies
    setcookie('sort_column', $column, time() + (86400 * 30), '/'); // 30 days
    setcookie('sort_order', $order, time() + (86400 * 30), '/');
}

function getSortingPreference() {
    // If old column name exists in cookie, update it
    if (isset($_COOKIE['sort_column']) && $_COOKIE['sort_column'] === 'rent_cost_per_month') {
        setSortingPreference('monthly_cost', $_COOKIE['sort_order'] ?? 'ASC');
    }
    
    return [
        'column' => isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'monthly_cost',
        'order' => isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'ASC'
    ];
}

// File upload functions
function uploadFile($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code ' . $file['error']);
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('File type not allowed');
    }
    
    $new_filename = uniqid() . '.' . $extension;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return $new_filename;
}

// Session cleanup for multi-step registration
function cleanExpiredSessions($pdo) {
    $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
    executeQuery($pdo, $sql);
}

// Validation functions
function validateNationalId($id) {
    return preg_match('/^\d{9}$/', $id);
}

function validateMobile($mobile) {
    return preg_match('/^05\d{8}$/', $mobile);
}

function validateCreditCard($card_number) {
    return preg_match('/^\d{9}$/', $card_number);
}

// Function to get absolute URL for uploaded files
function getUploadedFileUrl($relative_path) {
    // Add debug logging
    error_log("getUploadedFileUrl called with path: " . $relative_path);
    
    // If it's a default image and it doesn't exist, return a placeholder
    if ($relative_path === 'images/default-flat.jpg' && !file_exists(__DIR__ . '/' . $relative_path)) {
        error_log("Default image not found, returning placeholder");
        return 'https://via.placeholder.com/400x300?text=No+Image+Available';
    }
    
    // For flat photos, check if the file exists in the uploads/flats directory
    if (strpos($relative_path, 'uploads/flats/') === 0) {
        $file_name = basename($relative_path);
        $file_path = __DIR__ . '/uploads/flats/' . $file_name;
        error_log("Checking file existence at: " . $file_path);
        
        if (file_exists($file_path)) {
            error_log("File exists, returning URL");
            return 'uploads/flats/' . $file_name;
        }
        // If the file doesn't exist, return the placeholder
        error_log("File not found, returning placeholder");
        return 'https://via.placeholder.com/400x300?text=No+Image+Available';
    }
    
    error_log("Returning default path: " . $relative_path);
    return $relative_path;
}
?>