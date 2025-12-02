<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    if ($user_type == 'admin') {
        // Admin login
        $query = "SELECT * FROM admin_users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            // For demo, using simple password check. In production, use password_verify()
            if ($password == 'admin123') {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                header('Location: admin-dashboard.php');
                exit();
            } else {
                $error = "Invalid admin credentials!";
            }
        } else {
            $error = "Admin user not found!";
        }
    } else {
        // User login
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                header('Location: user-dashboard.php');
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ElegantVenues</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include all CSS from your original login page */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #e2d4b9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        .header {
            position: absolute;
            top: 20px;
            left: 30px;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #30360E;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo span {
            color: #787F56;
        }

        .logo-icon {
            margin-right: 10px;
            font-size: 24px;
        }

        .login-container {
            background: #787F56;
            padding: 30px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: bold;
            color: #fff;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #fff;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
            font-size: 16px;
        }

        .input-group input:focus {
            border-color: #30360E;
            box-shadow: 0 0 5px rgba(48, 54, 14, 0.3);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #30360E;
            border: none;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #3d4512;
        }

        .user-type {
            display: flex;
            margin-bottom: 20px;
            background: #6a7450;
            border-radius: 5px;
            overflow: hidden;
        }

        .user-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            transition: background 0.3s;
            color: white;
        }

        .user-option.active {
            background: #30360E;
            color: white;
        }

        .user-option input {
            display: none;
        }

        .register-text {
            text-align: center;
            margin-top: 20px;
            color: #fff;
        }

        .register-text a {
            color: #30360E;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }

        .register-text a:hover {
            color: #e2d4b9;
            text-decoration: underline;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 25px;
            }

            .header {
                left: 20px;
            }

            .logo {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Header with Logo -->
    <div class="header">
        <a href="index.php" class="logo">
            <i class="fas fa-heart logo-icon"></i>
            Elegant<span>Venues</span>
        </a>
    </div>

    <!-- Login Form -->
    <div class="login-container">
        <h2 class="title">Login to Your Account</h2>

        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form class="login-form" id="loginForm" method="POST" action="login.php">
            <!-- User Type Selection -->
            <div class="user-type">
                <label class="user-option active">
                    <input type="radio" name="user_type" value="user" checked> User
                </label>
                <label class="user-option">
                    <input type="radio" name="user_type" value="admin"> Admin
                </label>
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Login</button>
            <p class="register-text">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </form>
    </div>

    <script>
        // User type selection
        const userOptions = document.querySelectorAll('.user-option');
        userOptions.forEach(option => {
            option.addEventListener('click', function () {
                userOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });

        // Show demo credentials alert
        window.onload = function () {
            alert('Demo Credentials:\n\nFor Admin: admin@elegantvenues.com / admin123\nFor User: Use any registered email/password');
        };
    </script>
</body>
</html>