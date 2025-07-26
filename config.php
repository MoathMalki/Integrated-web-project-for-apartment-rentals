// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'rental_system',
    'username' => 'root',
    'password' => ''
]; 