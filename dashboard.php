<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle course unit selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Select course unit
    if (isset($_POST['select_course_unit'])) {
        $course_unit = trim($_POST['course_unit'] ?? '');
        if (!empty($course_unit)) {
            $_SESSION['selected_course_unit'] = $course_unit;
            $_SESSION['set1_done'] = false;
        }
    }
    // Save Test 1 and Coursework 1
    elseif (isset($_POST['save_set1'])) {
        $course_unit = $_SESSION['selected_course_unit'] ?? '';
        $test1 = floatval($_POST['test1'] ?? 0);
        $coursework1 = floatval($_POST['coursework1'] ?? 0);
        
        // Validate marks
        $valid = true;
        if ($test1 < 0 || $test1 > 20) {
            $error = 'Test 1 marks must be between 0 and 20.';
            $valid = false;
        } elseif ($coursework1 < 0 || $coursework1 > 10) {
            $error = 'Coursework 1 marks must be between 0 and 10.';
            $valid = false;
        }
        
        if ($valid) {
            $stmt = $conn->prepare('INSERT INTO marks (user_id, course_unit, test1, coursework1) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isdd', $user_id, $course_unit, $test1, $coursework1);
            if ($stmt->execute()) {
                $success = 'Test 1 and Coursework 1 saved! Now enter Test 2 and Coursework 2.';
                $_SESSION['set1_done'] = true;
            } else {
                $error = 'Failed to save marks.';
            }
            $stmt->close();
        }
    }
    // Save Test 2 and Coursework 2
    elseif (isset($_POST['save_set2'])) {
        $course_unit = $_SESSION['selected_course_unit'] ?? '';
        $test2 = floatval($_POST['test2'] ?? 0);
        $coursework2 = floatval($_POST['coursework2'] ?? 0);
        
        // Validate marks
        $valid = true;
        if ($test2 < 0 || $test2 > 20) {
            $error = 'Test 2 marks must be between 0 and 20.';
            $valid = false;
        } elseif ($coursework2 < 0 || $coursework2 > 10) {
            $error = 'Coursework 2 marks must be between 0 and 10.';
            $valid = false;
        }
        
        if ($valid) {
            $stmt = $conn->prepare('UPDATE marks SET test2 = ?, coursework2 = ? WHERE user_id = ? AND course_unit = ? ORDER BY id DESC LIMIT 1');
            $stmt->bind_param('ddis', $test2, $coursework2, $user_id, $course_unit);
            if ($stmt->execute()) {
                $success = 'All marks saved successfully!';
                unset($_SESSION['selected_course_unit'], $_SESSION['set1_done']);
            } else {
                $error = 'Failed to save marks.';
            }
            $stmt->close();
        }
    }
    // Update marks (Edit functionality)
    elseif (isset($_POST['update_marks'])) {
        $mark_id = intval($_POST['edit_mark_id']);
        $course_unit = trim($_POST['edit_course_unit'] ?? '');
        $test1 = floatval($_POST['edit_test1'] ?? 0);
        $coursework1 = floatval($_POST['edit_coursework1'] ?? 0);
        $test2 = ($_POST['edit_test2'] !== '') ? floatval($_POST['edit_test2']) : null;
        $coursework2 = ($_POST['edit_coursework2'] !== '') ? floatval($_POST['edit_coursework2']) : null;
        
        // Validate marks
        $valid = true;
        if ($test1 < 0 || $test1 > 20 || $coursework1 < 0 || $coursework1 > 10) {
            $error = 'Set 1: Test (0-20), Coursework (0-10)';
            $valid = false;
        } elseif (($test2 !== null && ($test2 < 0 || $test2 > 20)) ||
                 ($coursework2 !== null && ($coursework2 < 0 || $coursework2 > 10))) {
            $error = 'Set 2: Test (0-20), Coursework (0-10)';
            $valid = false;
        }
        
        if ($valid) {
            $stmt = $conn->prepare('UPDATE marks SET test1 = ?, coursework1 = ?, test2 = ?, coursework2 = ? WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ddddii', $test1, $coursework1, $test2, $coursework2, $mark_id, $user_id);
            if ($stmt->execute()) {
                $success = 'Marks updated successfully!';
            } else {
                $error = 'Failed to update marks.';
            }
            $stmt->close();
        }
    }
}

// Get current state
$selected_course = $_SESSION['selected_course_unit'] ?? '';
$set1_done = $_SESSION['set1_done'] ?? false;

// Get course units for dropdown
$course_units = [
    'advanced programming',
    'data structures and algorithms',
    'linear programming',
    'data encoding and encryption',
    'scripting languages',
    'system analysis and design'
];

// Get user's marks for results table
$results = $conn->query("SELECT * FROM marks WHERE user_id = $user_id ORDER BY id DESC");

// Calculate course statistics for the progress section
$submitted_courses = $results ? $results->num_rows : 0;
$completed_courses = 0;
$incomplete_courses = 0;

if ($results && $results->num_rows > 0) {
    $results->data_seek(0); // Reset pointer
    while ($row = $results->fetch_assoc()) {
        if ($row['test2'] !== null && $row['coursework2'] !== null) {
            $completed_courses++;
        } else {
            $incomplete_courses++;
        }
    }
    $results->data_seek(0); // Reset pointer again for later use
}

// Get course unit statistics
$course_stats = [];
$stats_query = $conn->query("
    SELECT course_unit, 
           test1, coursework1, test2, coursework2,
           GREATEST(test1, COALESCE(test2, 0)) as best_test,
           coursework1 + COALESCE(coursework2, 0) as total_coursework,
           GREATEST(test1, COALESCE(test2, 0)) + coursework1 + COALESCE(coursework2, 0) as final_score
    FROM marks 
    WHERE user_id = $user_id 
    ORDER BY course_unit
");

if ($stats_query && $stats_query->num_rows > 0) {
    while ($row = $stats_query->fetch_assoc()) {
        $course_stats[$row['course_unit']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="application-name" content="Semester Marks">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Semester Marks">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#2575fc">
<meta name="msapplication-tap-highlight" content="no">
<meta name="theme-color" content="#2575fc">
    <title>Semester Marks System</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ENjdO4Dr2bkBIFxQpeoA6VZgQAnbL9A7+U6U8g5QkP4ylFf4l+4t5F5d5z5F5d5F" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="icons/icon-72x72.png">
<link rel="apple-touch-icon" href="icons/icon-152x152.png">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-startup-image" href="icons/splash-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            margin: 0;
        }
        .header {
            background: #fff;
            padding: 30px 0 10px 0;
            text-align: center;
            color: #222;
            box-shadow: 0 2px 12px rgba(40,40,80,0.07);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header h1 {
            font-size: 2.5em;
            margin: 0;
            font-weight: 700;
            color: #2575fc;
        }
        .header p {
            font-size: 1.1em;
            margin-top: 8px;
            color: #444;
        }
        .logout-header-btn {
            background: linear-gradient(90deg, #06a328ff 0%, #1cb324ff 100%);
            color: #fff !important;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 6px;
            transition: background 0.2s;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(40,40,80,0.07);
        }
        .logout-header-btn:hover {
            background: linear-gradient(90deg, #2575fc 0%, #6a11cb 100%);
        }
        @media (max-width: 767.98px) {
            .header {
                padding: 18px 0 6px 0;
            }
            .header-content {
                flex-direction: column;
                align-items: stretch;
                padding: 0 6px;
                text-align: center;
            }
            .header h1 {
                font-size: 1.3em;
                margin-bottom: 4px;
                color: #2575fc;
            }
            .header p {
                font-size: 0.98em;
                margin-top: 2px;
                color: #444;
            }
            .logout-header-btn {
                width: 100%;
                margin-top: 10px;
                padding: 10px 0;
                font-size: 1em;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-bottom: 18px !important;
            }
            .dashboard-card > div[style*='background: linear-gradient']:last-child {
                margin-bottom: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                width: 100% !important;
                box-sizing: border-box;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                display: block !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                min-width: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                max-width: 100% !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                float: none !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                clear: both !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                border-radius: 12px !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                text-align: center !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                padding: 25px !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                font-size: 1em !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                background-clip: padding-box !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-top: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-bottom: 18px !important;
            }
            .dashboard-card > div[style*='background: linear-gradient']:last-child {
                margin-bottom: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                grid-column: 1 / -1 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-right: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                margin-left: 0 !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                width: 100% !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                display: block !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                float: none !important;
            }
            .dashboard-card > div[style*='background: linear-gradient'] {
                clear: both !important;
            }
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .dashboard-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(40,40,80,0.18);
            padding: 30px;
        }
        .dashboard-card h2 {
            text-align: center;
            color: #2575fc;
            font-size: 1.6em;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d1d1;
            border-radius: 6px;
            font-size: 1em;
            background: #f7f7f7;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #2575fc;
            outline: none;
        }
        .marks-info {
            background: #f0f8ff;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #2575fc;
            text-align: center;
        }
        .grading-explanation {
            background: #fff8e1;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ff9800;
        }
        .grading-explanation h4 {
            color: #ff9800;
            margin-bottom: 8px;
        }
        .submit-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: linear-gradient(90deg, #2575fc 0%, #6a11cb 100%);
        }
        .edit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .edit-btn:hover {
            background: #218838;
        }
        .success, .error {
            text-align: center;
            font-weight: 600;
            margin-bottom: 18px;
            padding: 12px;
            border-radius: 6px;
        }
        .success { background: #e6ffe6; color: #28a745; }
        .error { background: #ffe6e6; color: #d32f2f; }
        .results-container {
            grid-column: 1 / -1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f4f4f4;
            font-weight: 600;
        }
        .total-score {
            font-weight: bold;
            background: #f8f9fa;
        }
        .best-test {
            background: #e8f5e8;
            font-weight: bold;
        }
        .final-score {
            background: #2575fc;
            color: white;
            font-weight: bold;
            font-size: 1.1em;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: #fff;
            margin: 40px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-sizing: border-box;
            max-height: 90vh;
            overflow-y: auto;
        }
        @media (max-width: 991.98px) {
            .modal-content {
                width: 98% !important;
                max-width: 98vw !important;
                padding: 10px !important;
                border-radius: 10px !important;
                max-height: 90vh !important;
            }
            .modal h4, .modal h3 {
                font-size: 1.1em;
            }
            .set-container {
                padding: 8px !important;
            }
        }
        @media (max-width: 575.98px) {
            .modal-content {
                padding: 5px !important;
                border-radius: 7px !important;
                max-height: 90vh !important;
            }
            .modal h4, .modal h3 {
                font-size: 1em;
            }
            .set-container {
                padding: 5px !important;
            }
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #333;
        }
        .modal h4 {
            color: #2575fc;
            margin: 20px 0 10px 0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }
        .marks-limit {
            font-size: 0.8em;
            color: #666;
            margin-top: 2px;
        }
        .set-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .set-container h4 {
            color: #2575fc;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        /* Course Unit Stats Styles */
        .course-stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .course-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .course-stat-card:hover {
            transform: translateY(-5px);
        }
        .course-stat-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .course-name {
            font-size: 1.2em;
            font-weight: bold;
            flex: 1;
        }
        .course-icon {
            font-size: 1.5em;
            margin-right: 10px;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .stat-item {
            text-align: center;
            padding: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
        }
        .stat-value {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .stat-label {
            font-size: 0.8em;
            opacity: 0.9;
        }
        .final-score-display {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            margin-top: 10px;
        }
        .final-score-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #4CAF50;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .no-stats {
            text-align: center;
            padding: 40px;
            color: #666;
            grid-column: 1 / -1;
        }
        .stats-section {
            grid-column: 1 / -1;
        }
        .resizable {
            resize: both;
            overflow: auto;
            min-height: 200px;
            min-width: 300px;
        }
        /* Responsive styles for mobile devices */
        @media (max-width: 991.98px) {
            .main-container {
                display: block !important;
                padding: 10px;
                max-width: 100%;
            }
            .dashboard-card,
            .dashboard-card.stats-section,
            .dashboard-card.results-container {
                margin-bottom: 20px;
                padding: 15px;
            }
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                padding: 0 10px;
            }
            .header h1 {
                font-size: 1.5em;
            }
            .header p {
                font-size: 1em;
            }
            .course-stats-container {
                grid-template-columns: 1fr !important;
                gap: 10px;
            }
            .course-stat-card {
                min-width: unset !important;
                min-height: unset !important;
                padding: 10px;
            }
            .stat-grid {
                grid-template-columns: 1fr 1fr;
                gap: 5px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
                width: 100%;
            }
            thead {
                display: none;
            }
            tr {
                margin-bottom: 15px;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.03);
                padding: 8px 0;
            }
            td {
                text-align: left;
                padding: 8px 12px;
                border: none;
                position: relative;
            }
            td:before {
                content: attr(data-label);
                font-weight: bold;
                color: #2575fc;
                display: block;
                margin-bottom: 2px;
                font-size: 0.95em;
            }
            .edit-btn, .submit-btn {
                width: 100%;
                margin-top: 8px;
            }
            .modal-content {
                width: 98%;
                padding: 10px;
            }
            .set-container {
                padding: 8px;
            }
        }
        @media (max-width: 575.98px) {
            .header-content {
                padding: 0 2px;
            }
            .dashboard-card {
                padding: 8px;
            }
            .set-container {
                padding: 5px;
            }
            .modal-content {
                padding: 5px;
            }
        }
        .header-welcome-logo {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 28px;
            justify-content: flex-start;
        }
        .header-welcome-logo p {
            margin: 0;
            font-size: 1.2em;
            color: #444;
            white-space: nowrap;
        }
        .header-welcome-logo img {
            max-width: 120px;
            max-height: 120px;
            margin-bottom: 0;
            display: block;
            margin-left: auto;
        }
        @media (max-width: 767.98px) {
            .header-welcome-logo {
                flex-direction: column !important;
                align-items: center !important;
                gap: 0 !important;
            }
            .header-welcome-logo p {
                margin-bottom: 8px !important;
                font-size: 0.98em !important;
            }
                    .header-welcome-logo img {
                        margin-bottom: 12px !important;
                        max-width: 160px !important;
                        max-height: 160px !important;
                        margin-left: auto !important;
                        margin-right: auto !important;
                        display: block !important;
                    }
        }
                  @media (max-width: 767.98px) {
                                .header {
                                    padding: 10px 0 6px 0;
                                }
                                .pro-header-flex {
                                    flex-direction: column;
                                    align-items: center;
                                    gap: 8px;
                                    padding: 0 6px;
                                }
                                .header-logo img {
                                    max-width: 110px;
                                    max-height: 110px;
                                    margin-bottom: 4px;
                                }
                                .header-title-user {
                                    text-align: center;
                                }
                                .app-title {
                                    font-size: 1.1em;
                                    margin-bottom: 2px;
                                }
                                .user-info {
                                    font-size: 0.98em;
                                }
                                .header-logout {
                                    width: 100%;
                                    display: flex;
                                    justify-content: center;
                                    margin-top: 8px;
                                }
                                .logout-header-btn {
                                    width: 100%;
                                    padding: 10px 0;
                                    font-size: 1em;
                                }
                            }
                                .header {
                                    background: #fff;
                                    padding: 22px 0 10px 0;
                                    color: #222;
                                    box-shadow: 0 2px 12px rgba(40,40,80,0.07);
                                }
                                .pro-header-flex {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    max-width: 1400px;
                                    margin: 0 auto;
                                    padding: 0 20px;
                                    gap: 32px;
                                }
                                .header-logo img {
                                    max-width: 80px;
                                    max-height: 80px;
                                    display: block;
                                    border-radius: 12px;
                                    box-shadow: 0 2px 8px rgba(40,40,80,0.08);
                                }
                                .header-title-user {
                                    flex: 1;
                                    text-align: center;
                                }
                                .app-title {
                                    font-size: 2.1em;
                                    margin: 0 0 4px 0;
                                    font-weight: 700;
                                    color: #2575fc;
                                    letter-spacing: 0.5px;
                                }
                                .user-info {
                                    font-size: 1.1em;
                                    color: #444;
                                    margin-top: 2px;
                                }
                                .header-logout {
                                    display: flex;
                                    align-items: center;
                                }
                                /* PWA Specific Styles */
@media (display-mode: standalone) {
    body {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
    }
    
    .header {
        padding-top: calc(22px + env(safe-area-inset-top));
    }
}

/* Enhanced touch targets for mobile */
@media (max-width: 768px) {
    .btn, .submit-btn, .edit-btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    .form-group input, .form-group select {
        min-height: 44px;
    }
}

/* Loading animation for app */
.app-loading {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    color: white;
}

.app-loading.show {
    display: flex;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
.pwa-install-btn {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    margin-left: 10px;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
}

.pwa-install-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

/* Style for standalone mode */
.pwa-standalone .pwa-install-btn {
    display: none !important;
}

/* Offline indicator */
.offline-indicator {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #ff6b6b;
    color: white;
    text-align: center;
    padding: 10px;
    z-index: 10000;
    display: none;
}

.offline .offline-indicator {
    display: block;
}
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content pro-header-flex">
            <div class="header-logo">
                <img src="logo.png" alt="Logo">
            </div>
            <div class="header-title-user">
                <h1 class="app-title">Semester Marks System</h1>
                <div class="user-info">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                    <?php if (isset($_SESSION['year'])): ?> | Year <?php echo $_SESSION['year']; ?><?php endif; ?>
                    <?php if (isset($_SESSION['semester'])): ?>, Semester <?php echo $_SESSION['semester']; ?><?php endif; ?>
                </div>
            </div>
           <div class="header-logout">
    <!-- Install button will be inserted here by pwa.js -->
    <a href="logout.php" class="logout-header-btn">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</div>
        </div>
    </div>

    <div class="main-container">
        <!-- Marks Entry Section -->
        <div class="dashboard-card">
            <h2><i class="fa-solid fa-pen-to-square"></i> Enter Marks</h2>
            
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Course Unit Selection -->
            <?php if (empty($selected_course)): ?>
                <form method="post">
                    <div class="form-group">
                        <label for="course_unit"><i class="fa-solid fa-list"></i> Select Course Unit</label>
                        <select id="course_unit" name="course_unit" required>
                            <option value="">-- Select Course Unit --</option>
                            <?php foreach ($course_units as $unit): ?>
                                <option value="<?php echo htmlspecialchars($unit); ?>">
                                    <?php echo htmlspecialchars(ucwords($unit)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="select_course_unit" class="submit-btn">
                        <i class="fa-solid fa-check"></i> Select Course Unit
                    </button>
                </form>
            <?php else: ?>
                <div class="marks-info">
                    <strong>Selected Course:</strong> <?php echo htmlspecialchars(ucwords($selected_course)); ?>
                </div>

                <!-- Set 1 Marks Form -->
                <?php if (!$set1_done): ?>
                    <form method="post">
                        <div class="set-container">
                            <h4>Set 1 Marks</h4>
                            <div class="form-group">
                                <label for="test1">Test 1 <span class="marks-limit">(0-20 marks)</span></label>
                                <input type="number" id="test1" name="test1" min="0" max="20" step="0.5" required>
                            </div>
                            <div class="form-group">
                                <label for="coursework1">Coursework 1 <span class="marks-limit">(0-10 marks)</span></label>
                                <input type="number" id="coursework1" name="coursework1" min="0" max="10" step="0.5" required>
                            </div>
                        </div>
                        <button type="submit" name="save_set1" class="submit-btn">
                            <i class="fa-solid fa-save"></i> Save Set 1 Marks
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Set 2 Marks Form -->
                    <form method="post">
                        <div class="set-container">
                            <h4>Set 2 Marks</h4>
                            <div class="form-group">
                                <label for="test2">Test 2 <span class="marks-limit">(0-20 marks)</span></label>
                                <input type="number" id="test2" name="test2" min="0" max="20" step="0.5">
                            </div>
                            <div class="form-group">
                                <label for="coursework2">Coursework 2 <span class="marks-limit">(0-10 marks)</span></label>
                                <input type="number" id="coursework2" name="coursework2" min="0" max="10" step="0.5">
                            </div>
                        </div>
                        <button type="submit" name="save_set2" class="submit-btn">
                            <i class="fa-solid fa-save"></i> Save Set 2 Marks
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="grading-explanation">
                <h4><i class="fa-solid fa-info-circle"></i> Grading Information</h4>
                <p><strong>Best Test:</strong> Higher score between Test 1 and Test 2</p>
                <p><strong>Total Coursework:</strong> Sum of Coursework 1 and Coursework 2</p>
                <p><strong>Final Score:</strong> Best Test + Total Coursework (out of 40)</p>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="dashboard-card">
            <h2><i class="fa-solid fa-chart-line"></i> Progress Overview</h2>
            <div style="display: flex; justify-content: space-around; text-align: center; margin-bottom: 20px;">
                
                
                <div style="text-align: center;">
                    <div style="font-size: 2em; color: #28a745; font-weight: bold;">
                        <?php echo $completed_courses; ?>
                    </div>
                    <div style="color: #666; font-size: 0.9em;">Fully Completed</div>
                    <div style="font-size: 0.8em; color: #28a745; margin-top: 2px;">
                        <i class="fa-solid fa-check-circle"></i> All marks entered
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 2em; color: #ffc107; font-weight: bold;">
                        <?php echo $incomplete_courses; ?>
                    </div>
                    <div style="color: #666; font-size: 0.9em;">In Progress</div>
                    <div style="font-size: 0.8em; color: #ffc107; margin-top: 2px;">
                        <i class="fa-solid fa-clock"></i> Missing some marks
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <?php if ($submitted_courses > 0): ?>
            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 0.9em; color: #666;">Progress</span>
                    <span style="font-size: 0.9em; color: #2575fc; font-weight: bold;">
                        <?php echo round(($completed_courses / $submitted_courses) * 100); ?>%
                    </span>
                </div>
                <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                    <div style="width: <?php echo ($completed_courses / $submitted_courses) * 100; ?>%; height: 100%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 4px;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                    <span style="font-size: 0.8em; color: #666;"><?php echo $completed_courses; ?> completed</span>
                    <span style="font-size: 0.8em; color: #666;"><?php echo $submitted_courses; ?> total</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Course Unit Specific Stats -->
        <div class="dashboard-card stats-section">
            <h2><i class="fa-solid fa-chart-bar"></i> Course Unit Statistics</h2>
            <?php if (!empty($course_stats)): ?>
                <div class="course-stats-container">
                    <?php foreach ($course_stats as $course_name => $stats): 
                        $final_score = $stats['final_score'];
                        $percentage = ($final_score / 40) * 100;
                        $best_test = $stats['best_test'];
                        $total_coursework = $stats['total_coursework'];
                    ?>
                    <div class="course-stat-card resizable">
                        <div class="course-stat-header">
                            <i class="fa-solid fa-book course-icon"></i>
                            <div class="course-name"><?php echo htmlspecialchars(ucwords($course_name)); ?></div>
                        </div>
                        
                        <div class="stat-grid">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['test1']; ?>/20</div>
                                <div class="stat-label">Test 1</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['coursework1']; ?>/10</div>
                                <div class="stat-label">Coursework 1</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['test2'] ?? '-'; ?><?php echo $stats['test2'] ? '/20' : ''; ?></div>
                                <div class="stat-label">Test 2</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['coursework2'] ?? '-'; ?><?php echo $stats['coursework2'] ? '/10' : ''; ?></div>
                                <div class="stat-label">Coursework 2</div>
                            </div>
                        </div>
                        
                        <div class="final-score-display">
                            <div class="final-score-value"><?php echo $final_score; ?>/40</div>
                            <div class="stat-label">Final Score</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                            <div style="text-align: center; padding: 5px; background: rgba(255,255,255,0.1); border-radius: 4px;">
                                <div style="font-size: 0.9em; font-weight: bold;">Best Test</div>
                                <div><?php echo $best_test; ?>/20</div>
                            </div>
                            <div style="text-align: center; padding: 5px; background: rgba(255,255,255,0.1); border-radius: 4px;">
                                <div style="font-size: 0.9em; font-weight: bold;">Coursework</div>
                                <div><?php echo $total_coursework; ?>/20</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-stats">
                    <i class="fa-solid fa-chart-bar" style="font-size: 3em; margin-bottom: 10px; color: #ccc;"></i>
                    <p>No course statistics available yet. Start adding marks to see your progress!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Results Table -->
        <div class="dashboard-card results-container">
            <h2><i class="fa-solid fa-list-check"></i> Your Results</h2>
            <?php if ($results && $results->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Course Unit</th>
                            <th>Test 1</th>
                            <th>Work 1</th>
                            <th>Test 2</th>
                            <th>Work 2</th>
                            <th>Best Test</th>
                            <th>Final Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $results->fetch_assoc()): 
                            $best_test = max($row['test1'], $row['test2'] ?? 0);
                            $coursework1 = $row['coursework1'];
                            $coursework2 = $row['coursework2'] ?? 0;
                            $final_score = $best_test + $coursework1 + $coursework2;
                        ?>
                        <tr>
                            <td data-label="Course Unit"><?php echo htmlspecialchars(ucwords($row['course_unit'])); ?></td>
                            <td data-label="Test 1"><?php echo $row['test1']; ?>/20</td>
                            <td data-label="Work 1"><?php echo $row['coursework1']; ?>/10</td>
                            <td data-label="Test 2"><?php echo $row['test2'] ?? '-'; ?><?php echo $row['test2'] ? '/20' : ''; ?></td>
                            <td data-label="Work 2"><?php echo $row['coursework2'] ?? '-'; ?><?php echo $row['coursework2'] ? '/10' : ''; ?></td>
                            <td data-label="Best Test" class="best-test"><?php echo $best_test; ?>/20</td>
                            <td data-label="Final Score" class="final-score"><?php echo $final_score; ?>/40</td>
                            <td data-label="Actions">
                                <button class="edit-btn" onclick="openEditModal(<?php echo $row['id']; ?>, 
                                    '<?php echo addslashes($row['course_unit']); ?>',
                                    <?php echo $row['test1']; ?>,
                                    <?php echo $row['coursework1']; ?>,
                                    <?php echo $row['test2'] ?? 'null'; ?>,
                                    <?php echo $row['coursework2'] ?? 'null'; ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #888;">
                    <i class="fa-solid fa-clipboard-list" style="font-size: 3em; margin-bottom: 10px;"></i>
                    <p>No marks entered yet. Select a course unit above to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3><i class="fa-solid fa-pen-to-square"></i> Edit Marks</h3>
            <form method="post" id="editForm">
                <input type="hidden" id="edit_mark_id" name="edit_mark_id">
                <input type="hidden" id="edit_course_unit" name="edit_course_unit">
                
                <div class="set-container">
                    <h4>Set 1 Marks</h4>
                    <div class="form-group">
                        <label for="edit_test1">Test 1 <span class="marks-limit">(0-20 marks)</span></label>
                        <input type="number" id="edit_test1" name="edit_test1" min="0" max="20" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_coursework1">Coursework 1 <span class="marks-limit">(0-10 marks)</span></label>
                        <input type="number" id="edit_coursework1" name="edit_coursework1" min="0" max="10" step="0.5" required>
                    </div>
                </div>
                
                <div class="set-container">
                    <h4>Set 2 Marks</h4>
                    <div class="form-group">
                        <label for="edit_test2">Test 2 <span class="marks-limit">(0-20 marks)</span></label>
                        <input type="number" id="edit_test2" name="edit_test2" min="0" max="20" step="0.5">
                    </div>
                    <div class="form-group">
                        <label for="edit_coursework2">Coursework 2 <span class="marks-limit">(0-10 marks)</span></label>
                        <input type="number" id="edit_coursework2" name="edit_coursework2" min="0" max="10" step="0.5">
                    </div>
                </div>
                
                <button type="submit" name="update_marks" class="submit-btn">
                    <i class="fa-solid fa-save"></i> Update Marks
                </button>
                <button type="button" class="submit-btn" onclick="closeEditModal()" style="margin-top: 10px; background: #6c757d;">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
            </form>
        </div>
    </div>

    <script>
        // Edit Modal Functions
        function openEditModal(markId, courseUnit, test1, coursework1, test2, coursework2) {
            document.getElementById('edit_mark_id').value = markId;
            document.getElementById('edit_course_unit').value = courseUnit;
            document.getElementById('edit_test1').value = test1;
            document.getElementById('edit_coursework1').value = coursework1;
            document.getElementById('edit_test2').value = test2 !== null ? test2 : '';
            document.getElementById('edit_coursework2').value = coursework2 !== null ? coursework2 : '';
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const test1 = parseFloat(document.getElementById('edit_test1').value);
            const coursework1 = parseFloat(document.getElementById('edit_coursework1').value);
            
            if (test1 < 0 || test1 > 20 || coursework1 < 0 || coursework1 > 10) {
                e.preventDefault();
                alert('Set 1 marks: Test (0-20), Coursework (0-10)');
                return false;
            }
            
            return true;
        });

        // Make course stat cards resizable
        document.addEventListener('DOMContentLoaded', function() {
            const resizableElements = document.querySelectorAll('.resizable');
            resizableElements.forEach(element => {
                element.style.minHeight = '200px';
                element.style.minWidth = '300px';
            });
        });
    </script>
    <!-- Offline Indicator -->
<div class="offline-indicator" id="offlineIndicator">
    <i class="fas fa-wifi"></i> You are currently offline
</div>

<!-- PWA Script -->
<script src="js/pwa.js"></script>
</body>
</html>