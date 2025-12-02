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

// Get report data
$bookings_by_status = $db->query("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

$revenue_data = $db->query("
    SELECT DATE(b.created_at) as date, COUNT(*) as bookings, COUNT(DISTINCT b.user_id) as customers
    FROM bookings b
    WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(b.created_at)
    ORDER BY date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$venue_popularity = $db->query("
    SELECT v.name, COUNT(b.id) as total_bookings, v.id
    FROM venues v
    LEFT JOIN bookings b ON v.id = b.venue_id
    GROUP BY v.id
    ORDER BY total_bookings DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$top_customers = $db->query("
    SELECT u.id, u.first_name, u.last_name, u.email, COUNT(b.id) as total_bookings, 
           COUNT(CASE WHEN b.status = 'approved' THEN 1 END) as approved_bookings
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY total_bookings DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_revenue = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$pending_value = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$this_month_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Elegant Venues Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
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
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #30360E;
        }
        
        .report-section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .report-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #30360E;
            border-bottom: 2px solid #787f56;
            padding-bottom: 10px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: #30360E;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .export-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #787f56;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 15px;
        }
        
        .export-btn:hover {
            background-color: #676e4c;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .stats-grid {
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
                <li><a href="admin-reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin-settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <h1 class="page-title"><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $total_revenue; ?></div>
                    <div class="stat-label">Approved Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo $pending_value; ?></div>
                    <div class="stat-label">Pending Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                    <div class="stat-value"><?php echo $this_month_bookings; ?></div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>

            <!-- Booking Status Chart -->
            <div class="chart-container">
                <h3 class="chart-title">Booking Status Distribution</h3>
                <canvas id="statusChart"></canvas>
            </div>

            <!-- Top Venues Report -->
            <div class="report-section">
                <div class="report-title"><i class="fas fa-building"></i> Top Venues by Bookings</div>
                <button class="export-btn" onclick="exportTableToCSV('topVenuesTable', 'Top_Venues.csv')">
                    <i class="fas fa-download"></i> Export to CSV
                </button>
                <table class="table" id="topVenuesTable">
                    <thead>
                        <tr>
                            <th>Venue Name</th>
                            <th>Total Bookings</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_venue_bookings = array_sum(array_column($venue_popularity, 'total_bookings'));
                        foreach($venue_popularity as $venue): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($venue['name']); ?></td>
                            <td><?php echo $venue['total_bookings']; ?></td>
                            <td><?php echo round(($venue['total_bookings'] / $total_venue_bookings) * 100, 2); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Customers Report -->
            <div class="report-section">
                <div class="report-title"><i class="fas fa-users"></i> Top Customers</div>
                <button class="export-btn" onclick="exportTableToCSV('topCustomersTable', 'Top_Customers.csv')">
                    <i class="fas fa-download"></i> Export to CSV
                </button>
                <table class="table" id="topCustomersTable">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Total Bookings</th>
                            <th>Approved Bookings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo $customer['total_bookings']; ?></td>
                            <td><?php echo $customer['approved_bookings']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Bookings Report -->
            <div class="report-section">
                <div class="report-title"><i class="fas fa-calendar-check"></i> Bookings by Date (Last 30 Days)</div>
                <button class="export-btn" onclick="exportTableToCSV('recentBookingsTable', 'Recent_Bookings.csv')">
                    <i class="fas fa-download"></i> Export to CSV
                </button>
                <table class="table" id="recentBookingsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Bookings</th>
                            <th>New Customers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($revenue_data as $record): ?>
                        <tr>
                            <td><?php echo date('F d, Y', strtotime($record['date'])); ?></td>
                            <td><?php echo $record['bookings']; ?></td>
                            <td><?php echo $record['customers']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Booking Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: <?php echo json_encode(array_column($bookings_by_status, 'status')); ?>,
            datasets: [{
                label: 'Number of Bookings',
                data: <?php echo json_encode(array_column($bookings_by_status, 'count')); ?>,
                backgroundColor: [
                    '#ffc107',
                    '#28a745',
                    '#dc3545',
                    '#6c757d'
                ],
                borderColor: [
                    '#ff9800',
                    '#228f3c',
                    '#c82333',
                    '#5a6268'
                ],
                borderWidth: 2
            }]
        };
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Export to CSV function
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            let csv = [];
            
            // Get headers
            const headers = table.querySelectorAll('th');
            let headerRow = [];
            headers.forEach(header => {
                headerRow.push('"' + header.textContent.trim() + '"');
            });
            csv.push(headerRow.join(','));
            
            // Get rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                let rowData = [];
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    rowData.push('"' + cell.textContent.trim() + '"');
                });
                csv.push(rowData.join(','));
            });
            
            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
