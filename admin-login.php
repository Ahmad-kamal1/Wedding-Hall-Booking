<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $query = "SELECT * FROM admin_users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        // For demo, using simple password check
        if ($password == 'admin123') {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            header('Location: admin-dashboard.php');
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Admin user not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ElegantVenues</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #1a1a1a;
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
            color: #fff;
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
            background: #2d2d2d;
            padding: 30px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            border: 1px solid #787F56;
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: bold;
            color: #fff;
        }

        .admin-badge {
            background: #787F56;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
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
            border: 1px solid #444;
            border-radius: 5px;
            outline: none;
            font-size: 16px;
            background: #3a3a3a;
            color: white;
        }

        .input-group input:focus {
            border-color: #787F56;
            box-shadow: 0 0 5px rgba(120, 127, 86, 0.5);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #787F56;
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
            background: #676e4c;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #787F56;
            text-decoration: none;
        }

        .back-link a:hover {
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
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="logo">
            <i class="fas fa-heart logo-icon"></i>
            Elegant<span>Venues</span> Admin
        </a>
    </div>

    <div class="login-container">
        <h2 class="title">Admin Portal</h2>
        <div style="text-align: center;">
            <span class="admin-badge">Secure Access</span>
        </div>

        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form class="login-form" id="adminLoginForm" method="POST" action="admin-login.php">
            <div class="input-group">
                <label>Admin Email</label>
                <input type="email" id="adminEmail" name="email" placeholder="admin@elegantvenues.com" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" id="adminPassword" name="password" placeholder="Enter admin password" required>
            </div>

            <button type="submit" class="login-btn">Access Dashboard</button>

            <div class="back-link">
                <a href="index.php">← Back to Main Site</a>
            </div>
        </form>
    </div>

    <script>
        // Show demo credentials
        window.onload = function() {
            alert('Demo Admin Credentials:\n\nEmail: admin@elegantvenues.com\nPassword: admin123');
        };
    </script>
</body>
</html>