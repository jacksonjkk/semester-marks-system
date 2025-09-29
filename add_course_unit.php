<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_unit = trim($_POST['course_unit'] ?? '');
    if ($course_unit !== '') {
        $stmt = $conn->prepare('INSERT INTO course_units (name) VALUES (?)');
        if ($stmt) {
            $stmt->bind_param('s', $course_unit);
            if ($stmt->execute()) {
                header('Location: dashboard.php?added=1');
                exit;
            }
            $stmt->close();
        }
    }
    header('Location: dashboard.php?added=0');
    exit;
}
?>