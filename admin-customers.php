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

// Handle customer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Block/Unblock customer
    if (isset($_POST['toggle_status'])) {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $query = "UPDATE users SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $new_status);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $success_message = "User status updated successfully!";
            } else {
                $error_message = "Failed to update user status.";
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get all customers with safe query
try {
    $customers_query = "SELECT u.*, COUNT(b.id) as total_bookings, 
                        SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
                        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings
                        FROM users u 
                        LEFT JOIN bookings b ON u.id = b.user_id 
                        GROUP BY u.id 
                        ORDER BY u.created_at DESC";
    $all_customers = $db->query($customers_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_customers = [];
    $error_message = "Error loading customers: " . $e->getMessage();
}

// Get statistics with safe queries
try {
    $total_customers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $active_customers = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $new_customers = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Exception $e) {
    $total_customers = 0;
    $active_customers = 0;
    $new_customers = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Management - Elegant Venues</title>
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
            line-height: 1.6;
        }
        
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header Styles */
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
        
        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar Styles */
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
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .page-title {
            margin-bottom: 20px;
            color: #30360E;
            font-size: 28px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #30360E;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-icon {
            font-size: 24px;
            color: #787f56;
            margin-bottom: 10px;
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
        
        /* Table Styles */
        .customers-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .customers-table th {
            background-color: #787f56;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .customers-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .customers-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-active {
            background-color: #28a745;
            color: white;
        }
        
        .badge-inactive {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #787f56;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #676e4c;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .customers-table {
                font-size: 14px;
            }
            
            .customers-table th, .customers-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Elegant<span>Venues</span> Admin</a>
            <div class="admin-info">
                <div class="admin-avatar">A</div>
                <div>Admin User <a href="logout.php" style="color: white; margin-left: 15px;">Logout</a></div>
            </div>
        </div>
    </header>

    <!-- Dashboard Layout -->
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin-dashboard.php?tab=bookings"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="admin-dashboard.php?tab=featured"><i class="fas fa-star"></i> Featured Events</a></li>
                <li><a href="admin-dashboard.php?tab=manage-venues"><i class="fas fa-building"></i> Manage Venues</a></li>
                <li><a href="admin-dashboard.php?tab=hot"><i class="fas fa-fire"></i> Hot Menus</a></li>
                <li><a href="admin-customers.php" class="active"><i class="fas fa-users"></i> Customers</a></li>
                <li><a href="admin-reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin-settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1 class="page-title"><i class="fas fa-users"></i> Customers Management</h1>

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

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $total_customers; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-value"><?php echo $active_customers; ?></div>
                    <div class="stat-label">Active Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-value"><?php echo $new_customers; ?></div>
                    <div class="stat-label">New (30 days)</div>
                </div>
            </div>

            <!-- Customers Table -->
            <table class="customers-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Total Bookings</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_customers as $customer): ?>
                    <tr>
                        <td>#<?php echo $customer['id']; ?></td>
                        <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo $customer['total_bookings'] ?? 0; ?></td>
                        <td><span class="badge badge-active"><?php echo $customer['approved_bookings'] ?? 0; ?></span></td>
                        <td><span class="badge badge-pending"><?php echo $customer['pending_bookings'] ?? 0; ?></span></td>
                        <td>
                            <span class="badge <?php echo ($customer['status'] ?? 'active') == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($customer['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $customer['id']; ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="new_status" value="<?php echo ($customer['status'] ?? 'active') == 'active' ? 'blocked' : 'active'; ?>">
                                    <button type="submit" class="btn <?php echo ($customer['status'] ?? 'active') == 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo ($customer['status'] ?? 'active') == 'active' ? 'Block' : 'Unblock'; ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
