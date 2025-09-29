<?php
// Database connection settings
$host = 'localhost';
$db = 'sem_app';
$user = 'root';
$pass = '';

// Create MySQLi connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die('Database connection failed. Please try again later.');
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Security functions
function sanitize_input($data) {
    if (empty($data)) return $data;
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function get_course_units($connection) {
    $units = [];
    $result = $connection->query("SELECT name FROM course_units ORDER BY name");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $units[] = $row['name'];
        }
    }
    return $units;
}
?>