<?php
session_start(); // Add this at the very top
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Prevent brute force attacks
        sleep(1);
        
        $stmt = $conn->prepare('SELECT id, username, password, year, semester FROM users WHERE username = ?');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $db_username, $hash, $year, $semester);
                $stmt->fetch();
                
                if (password_verify($password, $hash)) {
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $db_username;
                    $_SESSION['year'] = $year;
                    $_SESSION['semester'] = $semester;
                    
                    redirect('dashboard.php');
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
            $stmt->close();
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Semester Marks Portal">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SemesterMarks">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#ffffffff">
<meta name="msapplication-TileImage" content="icons/icon-144x144.png">
<meta name="msapplication-tap-highlight" content="no">
<meta name="theme-color" content="#ffffffff">
    <title>Login - Semester Marks System</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/icon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="icons/icon-16x16.png">
<link rel="apple-touch-icon" href="icons/icon-152x152.png">
   <link rel="manifest" href="manifest.json">
   <style>
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Montserrat', Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { 
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            backdrop-filter: blur(10px);
        }
        .logo { 
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }
        .logo i { 
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .logo h1 { 
            font-size: 1.8rem;
            font-weight: 700;
        }
        .form-group { 
            margin-bottom: 20px;
        }
        .form-group label { 
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input { 
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus { 
            border-color: #667eea;
            outline: none;
        }
        .btn { 
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover { 
            transform: translateY(-2px);
        }
        .error { 
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }
        .links { 
            text-align: center;
            margin-top: 20px;
        }
        .links a { 
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .links a:hover { 
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h1>Semester Marks</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="links">
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </div>
    <!-- Offline Indicator -->
<div class="offline-indicator" id="offlineIndicator">
    <i class="fas fa-wifi"></i> You are currently offline
</div>

<!-- PWA Script -->
<script src="js/pwa.js"></script>
</body>
</html>