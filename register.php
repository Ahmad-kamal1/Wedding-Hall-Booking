<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $wedding_date = $_POST['wedding_date'];
    $guest_count = $_POST['guest_count'];
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        try {
            // Check if email already exists
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Email already exists!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $query = "INSERT INTO users (first_name, last_name, email, phone, password, wedding_date, guest_count, newsletter) 
                          VALUES (:first_name, :last_name, :email, :phone, :password, :wedding_date, :guest_count, :newsletter)";
                
                $stmt = $db->prepare($query);
                
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':wedding_date', $wedding_date);
                $stmt->bindParam(':guest_count', $guest_count);
                $stmt->bindParam(':newsletter', $newsletter);
                
                if ($stmt->execute()) {
                    $success = "Registration successful! You can now login.";
                }
            }
        } catch(PDOException $exception) {
            $error = "Registration failed: " . $exception->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ElegantVenues</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include all CSS from your original register page */
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
            min-height: 100vh;
            padding: 20px;
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

        .register-container {
            background: #787F56;
            padding: 30px;
            width: 450px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: bold;
            color: #fff;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 18px;
        }

        .input-group {
            flex: 1;
            margin-bottom: 0;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #fff;
        }

        .input-group input, 
        .input-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
            font-size: 16px;
        }

        .input-group input:focus, 
        .input-group select:focus {
            border-color: #30360E;
            box-shadow: 0 0 5px rgba(48, 54, 14, 0.3);
        }

        .input-group.full-width {
            width: 100%;
        }

        .password-strength {
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .strength-weak {
            background: #ff4d4d;
            width: 33%;
        }

        .strength-medium {
            background: #ffa500;
            width: 66%;
        }

        .strength-strong {
            background: #28a745;
            width: 100%;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: #fff;
        }

        .terms {
            display: flex;
            align-items: flex-start;
            margin: 15px 0;
        }

        .terms input {
            margin-right: 10px;
            margin-top: 3px;
        }

        .terms label {
            color: #fff;
            font-size: 14px;
        }

        .terms a {
            color: #30360E;
            text-decoration: none;
            font-weight: bold;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .register-btn {
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

        .register-btn:hover {
            background: #3d4512;
        }

        .register-btn:disabled {
            background: #666;
            cursor: not-allowed;
        }

        .login-text {
            text-align: center;
            margin-top: 20px;
            color: #fff;
        }

        .login-text a {
            color: #30360E;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }

        .login-text a:hover {
            color: #e2d4b9;
            text-decoration: underline;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
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
            .register-container {
                width: 100%;
                padding: 20px;
            }
            
            .header {
                left: 20px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .input-group {
                margin-bottom: 18px;
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

    <!-- Registration Form -->
    <div class="register-container">
        <h2 class="title">Create Your Account</h2>

        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <form class="register-form" id="registerForm" method="POST" action="register.php">
            <div class="form-row">
                <div class="input-group">
                    <label class="required">First Name</label>
                    <input type="text" id="firstName" name="first_name" placeholder="Enter your first name" required>
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" id="lastName" name="last_name" placeholder="Enter your last name">
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label class="required">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="input-group">
                    <label class="required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                </div>
            </div>

            <div class="input-group full-width">
                <label class="required">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText">Password strength</div>
            </div>

            <div class="input-group full-width">
                <label class="required">Confirm Password</label>
                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter your password" required>
                <div id="passwordMatch" style="font-size: 12px; margin-top: 5px; color: #fff;"></div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label>Wedding Date</label>
                    <input type="date" id="weddingDate" name="wedding_date">
                </div>
                <div class="input-group">
                    <label>Guest Count</label>
                    <select id="guestCount" name="guest_count">
                        <option value="">Select guest count</option>
                        <option value="1-50">1-50 Guests</option>
                        <option value="51-100">51-100 Guests</option>
                        <option value="101-200">101-200 Guests</option>
                        <option value="201-300">201-300 Guests</option>
                        <option value="300+">300+ Guests</option>
                    </select>
                </div>
            </div>

            <div class="terms">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a></label>
            </div>

            <div class="terms">
                <input type="checkbox" id="newsletter" name="newsletter">
                <label for="newsletter">Send me updates about new venues and special offers</label>
            </div>

            <button type="submit" class="register-btn" id="registerBtn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <p class="login-text">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMatch = document.getElementById('passwordMatch');
        const registerBtn = document.getElementById('registerBtn');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = 'Password strength';

            // Check password strength
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            // Update strength bar and text
            strengthBar.className = 'strength-bar';
            if (strength === 0) {
                text = 'Very Weak';
            } else if (strength === 1) {
                strengthBar.classList.add('strength-weak');
                text = 'Weak';
            } else if (strength === 2 || strength === 3) {
                strengthBar.classList.add('strength-medium');
                text = 'Medium';
            } else if (strength === 4) {
                strengthBar.classList.add('strength-strong');
                text = 'Strong';
            }

            strengthText.textContent = text;
            checkFormValidity();
        });

        // Password confirmation check
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;

            if (confirmPassword === '') {
                passwordMatch.textContent = '';
            } else if (password === confirmPassword) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.style.color = '#28a745';
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.style.color = '#ff4d4d';
            }
            checkFormValidity();
        });

        // Form validation
        function checkFormValidity() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const termsChecked = document.getElementById('terms').checked;
            const email = document.getElementById('email').value;
            const firstName = document.getElementById('firstName').value;
            const phone = document.getElementById('phone').value;

            const isPasswordStrong = password.length >= 8;
            const passwordsMatch = password === confirmPassword && confirmPassword !== '';
            const requiredFieldsFilled = email && firstName && phone;

            registerBtn.disabled = !(isPasswordStrong && passwordsMatch && termsChecked && requiredFieldsFilled);
        }

        // Add event listeners for required fields
        document.getElementById('email').addEventListener('input', checkFormValidity);
        document.getElementById('firstName').addEventListener('input', checkFormValidity);
        document.getElementById('phone').addEventListener('input', checkFormValidity);
        document.getElementById('terms').addEventListener('change', checkFormValidity);

        // Set minimum date for wedding date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('weddingDate').min = today;
    </script>
</body>
</html>