<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get user information
$user_query = "SELECT * FROM users WHERE id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user bookings
$bookings_query = "SELECT b.*, v.name as venue_name, v.image_filename as venue_image 
                   FROM bookings b 
                   JOIN venues v ON b.venue_id = v.id 
                   WHERE b.user_id = :user_id 
                   ORDER BY b.created_at DESC";
$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->bindParam(':user_id', $user_id);
$bookings_stmt->execute();
$bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'badge-warning';
        case 'approved': return 'badge-success';
        case 'rejected': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

// Function to get status text
function getStatusText($status) {
    switch ($status) {
        case 'pending': return 'Pending Review';
        case 'approved': return 'Approved';
        case 'rejected': return 'Rejected';
        default: return $status;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Elegant Venues</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #E2D4B9;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 90%;
            max-width: 1200px;
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

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
        }

        nav ul li a {
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: color 0.3s;
        }

        nav ul li a:hover {
            color: #30360E;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-actions a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .user-actions a.login-btn {
            background-color: #30360E;
        }

        .user-actions a.register-btn {
            background-color: transparent;
            border: 1px solid white;
        }

        .user-actions a:hover {
            background-color: #57640a;
        }

        /* Dashboard Styles */
        .dashboard {
            padding: 40px 0;
            min-height: calc(100vh - 200px);
        }

        .welcome-section {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .welcome-header h1 {
            color: #30360E;
            font-size: 28px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #787f56;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }

        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-card {
            background-color: #f9f7f2;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #787f56;
        }

        .info-card h3 {
            color: #30360E;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .info-card p {
            color: #666;
            font-size: 16px;
        }

        .bookings-section {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            margin-bottom: 30px;
            color: #30360E;
            font-size: 24px;
            border-bottom: 2px solid #787f56;
            padding-bottom: 10px;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .booking-card {
            background-color: #f9f7f2;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }

        .booking-card:hover {
            transform: translateY(-5px);
        }

        .booking-header {
            background-color: #787f56;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-id {
            font-weight: bold;
            font-size: 14px;
        }

        .booking-date {
            font-size: 12px;
            opacity: 0.9;
        }

        .booking-content {
            padding: 20px;
        }

        .booking-venue {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .venue-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .venue-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .venue-info h4 {
            color: #30360E;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .venue-info p {
            color: #666;
            font-size: 14px;
        }

        .booking-details {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #30360E;
            font-weight: 600;
        }

        .booking-status {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-approved {
            background-color: #28a745;
            color: white;
        }

        .badge-rejected {
            background-color: #dc3545;
            color: white;
        }

        .admin-notes {
            margin-top: 10px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            font-size: 13px;
            color: #666;
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-bookings i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }

        .no-bookings a {
            display: inline-block;
            margin-top: 20px;
            background-color: #30360E;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .no-bookings a:hover {
            background-color: #57640a;
        }

        /* Footer Styles */
        footer {
            background-color: #787f56;
            color: white;
            padding: 50px 0 20px;
            margin-top: 50px;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-section h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #30360E;
            position: relative;
        }

        .footer-section h3:after {
            content: '';
            display: block;
            width: 40px;
            height: 2px;
            background-color: #30360E;
            margin-top: 5px;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            font-size: 14px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                padding: 15px 0;
            }

            nav ul {
                margin: 15px 0;
                flex-wrap: wrap;
                justify-content: center;
            }

            nav ul li {
                margin: 5px 10px;
            }

            .user-actions {
                margin-top: 10px;
            }

            .bookings-grid {
                grid-template-columns: 1fr;
            }

            .welcome-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .user-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Elegant<span>Venues</span></a>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#bookUsNav">Book Us</a></li>
                    <li><a href="index.php?section=contact">Contact</a></li>
                    <li><a href="index.php?section=about">About</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <a href="user-dashboard.php" class="login-btn">Dashboard</a>
                <a href="logout.php" class="register-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Dashboard Content -->
    <div class="dashboard">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-header">
                    <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                </div>
                <p>Manage your wedding venue bookings and track their status from your dashboard.</p>
                
                <div class="user-info">
                    <div class="info-card">
                        <h3><i class="fas fa-envelope"></i> Email</h3>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-phone"></i> Phone</h3>
                        <p><?php echo htmlspecialchars($user['phone']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-calendar"></i> Wedding Date</h3>
                        <p><?php echo $user['wedding_date'] ? date('F d, Y', strtotime($user['wedding_date'])) : 'Not set'; ?></p>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-users"></i> Guest Count</h3>
                        <p><?php echo $user['guest_count'] ?: 'Not specified'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="bookings-section">
                <h2 class="section-title">My Bookings</h2>
                
                <?php if (empty($bookings)): ?>
                    <div class="no-bookings">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Bookings Yet</h3>
                        <p>You haven't made any venue bookings yet.</p>
                        <a href="index.php">Browse Venues & Book Now</a>
                    </div>
                <?php else: ?>
                    <div class="bookings-grid">
                        <?php foreach($bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="booking-id">Booking #<?php echo $booking['id']; ?></div>
                                <div class="booking-date"><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></div>
                            </div>
                            <div class="booking-content">
                                <div class="booking-venue">
                                    <div class="venue-image">
                                        <?php if ($booking['venue_image'] && file_exists('uploads/venues/' . $booking['venue_image'])): ?>
                                            <img src="uploads/venues/<?php echo $booking['venue_image']; ?>" alt="<?php echo htmlspecialchars($booking['venue_name']); ?>">
                                        <?php else: ?>
                                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background-color: #e2d4b9; color: #787f56;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="venue-info">
                                        <h4><?php echo htmlspecialchars($booking['venue_name']); ?></h4>
                                        <p>Venue Booking</p>
                                    </div>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Event Date:</span>
                                        <span class="detail-value"><?php echo date('F d, Y', strtotime($booking['event_date'])); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Event Time:</span>
                                        <span class="detail-value"><?php echo date('h:i A', strtotime($booking['event_time'])); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Package:</span>
                                        <span class="detail-value"><?php echo ucfirst($booking['package_category']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Guests:</span>
                                        <span class="detail-value"><?php echo $booking['guest_count']; ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Payment:</span>
                                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="booking-status">
                                    <span class="status-badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                        <?php echo getStatusText($booking['status']); ?>
                                    </span>
                                    
                                    <?php if (!empty($booking['admin_notes'] ?? '')): ?>
                                    <div class="admin-notes">
                                        <strong><i class="fas fa-info-circle"></i> Admin Response:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($booking['admin_notes'] ?? '')); ?>
                                        <?php if (!empty($booking['response_date'] ?? '')): ?>
                                        <br><small>Updated: <?php echo date('M d, Y h:i A', strtotime($booking['response_date'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-section footer-about">
                    <h3>About Elegant Venues</h3>
                    <p>Elegant Venues is your premier destination for finding the perfect wedding venue in Peshawar.</p>
                </div>

                <div class="footer-section footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i>Zaman Khan Plaza 2nd Floor University Town Peshawar</p>
                    <p><i class="fas fa-phone"></i>+923341513407</p>
                    <p><i class="fas fa-envelope"></i>Elwgantvenues@gmail.com</p>
                </div>

                <div class="footer-section footer-social">
                    <h3>Follow Us</h3>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2023 Elegant Venues. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize any JavaScript functionality if needed
        document.addEventListener('DOMContentLoaded', function() {
            console.log('User dashboard loaded');
        });
    </script>
</body>
</html>