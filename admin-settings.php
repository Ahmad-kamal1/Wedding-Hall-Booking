<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Get current settings
$settings_query = "SELECT * FROM settings LIMIT 1";
try {
    $settings_result = $db->query($settings_query);
    $settings = $settings_result->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $settings = array();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        $site_name = trim($_POST['site_name']);
        $site_email = trim($_POST['site_email']);
        $site_phone = trim($_POST['site_phone']);
        $site_address = trim($_POST['site_address']);
        $booking_confirmation = isset($_POST['booking_confirmation']) ? 1 : 0;
        $admin_notifications = isset($_POST['admin_notifications']) ? 1 : 0;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        try {
            // Check if settings exist
            $check = $db->query("SELECT COUNT(*) FROM settings");
            $settingsExist = $check->fetchColumn() > 0;
            
            if ($settingsExist) {
                $query = "UPDATE settings SET 
                          site_name = :site_name,
                          site_email = :site_email,
                          site_phone = :site_phone,
                          site_address = :site_address,
                          booking_confirmation = :booking_confirmation,
                          admin_notifications = :admin_notifications,
                          email_notifications = :email_notifications,
                          updated_at = NOW()";
            } else {
                $query = "INSERT INTO settings 
                          (site_name, site_email, site_phone, site_address, booking_confirmation, admin_notifications, email_notifications, created_at)
                          VALUES (:site_name, :site_email, :site_phone, :site_address, :booking_confirmation, :admin_notifications, :email_notifications, NOW())";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':site_name', $site_name);
            $stmt->bindParam(':site_email', $site_email);
            $stmt->bindParam(':site_phone', $site_phone);
            $stmt->bindParam(':site_address', $site_address);
            $stmt->bindParam(':booking_confirmation', $booking_confirmation);
            $stmt->bindParam(':admin_notifications', $admin_notifications);
            $stmt->bindParam(':email_notifications', $email_notifications);
            
            if ($stmt->execute()) {
                $success_message = "Settings updated successfully!";
                // Refresh settings
                $settings_result = $db->query($settings_query);
                $settings = $settings_result->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to update settings.";
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

if (empty($settings)) {
    $settings = array(
        'site_name' => 'Elegant Venues',
        'site_email' => '',
        'site_phone' => '',
        'site_address' => '',
        'booking_confirmation' => 1,
        'admin_notifications' => 1,
        'email_notifications' => 1
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Elegant Venues Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            background-color: #787f56;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #30360E;
            text-decoration: none;
        }
        
        .logo span {
            color: #fff;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #30360E;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .dashboard {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background-color: #30360E;
            color: white;
            padding: 20px 0;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #787f56;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .page-title {
            margin-bottom: 25px;
            color: #30360E;
            font-size: 28px;
        }
        
        /* Messages */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        /* Settings Sections */
        .settings-section {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #30360E;
            border-bottom: 2px solid #787f56;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #30360E;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            font-family: inherit;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group textarea:focus {
            border-color: #787f56;
            outline: none;
            box-shadow: 0 0 5px rgba(120, 127, 86, 0.3);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #787f56;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #676e4c;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Elegant<span>Venues</span> Admin</a>
            <div class="admin-info">
                <div class="admin-avatar">A</div>
                <div>Admin User <a href="logout.php" style="color: white; margin-left: 15px;">Logout</a></div>
            </div>
        </div>
    </header>

    <div class="dashboard">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin-dashboard.php?tab=bookings"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="admin-dashboard.php?tab=featured"><i class="fas fa-star"></i> Featured Events</a></li>
                <li><a href="admin-dashboard.php?tab=manage-venues"><i class="fas fa-building"></i> Manage Venues</a></li>
                <li><a href="admin-dashboard.php?tab=hot"><i class="fas fa-fire"></i> Hot Menus</a></li>
                <li><a href="admin-customers.php"><i class="fas fa-users"></i> Customers</a></li>
                <li><a href="admin-reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin-settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <h1 class="page-title"><i class="fas fa-cog"></i> Settings</h1>

            <!-- Display Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- General Settings -->
            <div class="settings-section">
                <h2 class="section-title"><i class="fas fa-cog"></i> General Settings</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="site_email">Admin Email</label>
                            <input type="email" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_phone">Admin Phone</label>
                            <input type="text" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="site_address">Admin Address</label>
                            <input type="text" id="site_address" name="site_address" value="<?php echo htmlspecialchars($settings['site_address'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr style="margin: 25px 0; border: none; border-top: 1px solid #dee2e6;">

                    <h3 style="color: #30360E; margin: 25px 0 15px 0;">Notification Settings</h3>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="booking_confirmation" name="booking_confirmation" 
                                   <?php echo ($settings['booking_confirmation'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="booking_confirmation">Send booking confirmation emails to customers</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="admin_notifications" name="admin_notifications"
                                   <?php echo ($settings['admin_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="admin_notifications">Send admin notifications for new bookings</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="email_notifications" name="email_notifications"
                                   <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <label for="email_notifications">Enable all email notifications</label>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- System Information -->
            <div class="settings-section">
                <h2 class="section-title"><i class="fas fa-info-circle"></i> System Information</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label>PHP Version</label>
                        <input type="text" value="<?php echo phpversion(); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Server</label>
                        <input type="text" value="<?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Current Time</label>
                        <input type="text" value="<?php echo date('F d, Y H:i:s'); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Database</label>
                        <input type="text" value="elegant_venues" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
