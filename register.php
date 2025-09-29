<?php
session_start(); // Add this at the very top
include 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $year = sanitize_input($_POST['year'] ?? '');
    $semester = sanitize_input($_POST['semester'] ?? '');
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (empty($year) || empty($semester)) {
        $error = 'Please select your year and semester.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Username already taken. Please choose a different username.';
            } else {
                // Insert new user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $conn->prepare('INSERT INTO users (username, password, year, semester) VALUES (?, ?, ?, ?)');
                
                if ($insert_stmt) {
                    $insert_stmt->bind_param('ssss', $username, $hash, $year, $semester);
                    
                    if ($insert_stmt->execute()) {
                        $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                    } else {
                        $error = 'Registration failed. Please try again. Error: ' . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                } else {
                    $error = 'Database error. Please try again.';
                }
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
    <title>Register - Semester Marks System</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            max-width: 450px;
            padding: 40px;
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
        .form-group input,
        .form-group select { 
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus { 
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
            margin-top: 10px;
        }
        .btn:hover { 
            transform: translateY(-2px);
        }
        .error { 
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }
        .success { 
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #363;
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
        .password-requirements {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-user-plus"></i>
            <h1>Create Account</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       pattern="[a-zA-Z0-9_]{3,20}" 
                       title="Username must be 3-20 characters (letters, numbers, underscore)">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required 
                       minlength="6"
                       title="Password must be at least 6 characters">
                <div class="password-requirements">Minimum 6 characters</div>
            </div>
            
            <div class="form-group">
                <label for="confirm"><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm" name="confirm" required>
            </div>
            
            <div class="form-group">
                <label for="year"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                <select id="year" name="year" required>
                    <option value="">-- Select Year --</option>
                    <option value="1" <?php echo ($_POST['year'] ?? '') == '1' ? 'selected' : ''; ?>>Year 1</option>
                    <option value="2" <?php echo ($_POST['year'] ?? '') == '2' ? 'selected' : ''; ?>>Year 2</option>
                    <option value="3" <?php echo ($_POST['year'] ?? '') == '3' ? 'selected' : ''; ?>>Year 3</option>
                    <option value="4" <?php echo ($_POST['year'] ?? '') == '4' ? 'selected' : ''; ?>>Year 4</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="semester"><i class="fas fa-book"></i> Semester</label>
                <select id="semester" name="semester" required>
                    <option value="">-- Select Semester --</option>
                    <option value="1" <?php echo ($_POST['semester'] ?? '') == '1' ? 'selected' : ''; ?>>Semester 1</option>
                    <option value="2" <?php echo ($_POST['semester'] ?? '') == '2' ? 'selected' : ''; ?>>Semester 2</option>
                </select>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        
        <div class="links">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Back to Login</a>
        </div>
    </div>

    <script>
        // Client-side password validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                document.getElementById('confirm').focus();
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                document.getElementById('password').focus();
            }
        });

        // Real-time password match indicator
        document.getElementById('confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            } else {
                this.style.borderColor = '#e1e5ee';
            }
        });
    </script>
</body>
</html>