<?php
session_start();
require_once 'config/database.php';

// Check for booking success/error messages
$booking_success = isset($_SESSION['booking_success']) ? $_SESSION['booking_success'] : false;
$booking_error = isset($_SESSION['booking_error']) ? $_SESSION['booking_error'] : false;

// Clear the messages after displaying
unset($_SESSION['booking_success']);
unset($_SESSION['booking_error']);

// Database connection for fetching venues
$database = new Database();
$db = $database->getConnection();

// Handle search functionality
$search_results = [];
$search_query = '';
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    if (!empty($search_query)) {
        $search_query_like = "%" . $search_query . "%";
        $stmt = $db->prepare("SELECT * FROM venues WHERE name LIKE :query OR description LIKE :query OR address LIKE :query OR price_range LIKE :query OR category LIKE :query");
        $stmt->bindParam(':query', $search_query_like);
        $stmt->execute();
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Determine which section to show
$section = isset($_GET['section']) ? $_GET['section'] : 'home';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegant Venues - Wedding Hall Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* All CSS styles from your original home page */
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

        .nav-active {
            color: #30360E !important;
            font-weight: bold !important;
            border-bottom: 2px solid #30360E;
            padding-bottom: 5px;
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

        .search-bar {
            display: flex;
            align-items: center;
        }

        .search-bar input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            outline: none;
            width: 200px;
        }

        .search-bar button {
            background-color: #30360E;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .search-bar button:hover {
            background-color: #65750b;
        }

        /* Horizontal Scrolling Section */
        .featured-events {
            padding: 40px 0;
            background-color: #f9f7f2;
        }

        .section-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 6px;
            background-color: #30360E;
            margin: 10px auto;
        }

        .events-scroll-container {
            position: relative;
            overflow: hidden;
        }

        .events-scroll {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 20px 10px;
            gap: 25px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .events-scroll::-webkit-scrollbar {
            display: none;
        }

        .event-card {
            flex: 0 0 auto;
            width: 300px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .event-card:hover {
            transform: translateY(-10px);
        }

        .event-img {
            height: 200px;
            background-color: #e2d4b9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #787f56;
            font-size: 14px;
            overflow: hidden;
        }

        .event-content {
            padding: 20px;
        }

        .event-name {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        .event-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(48, 54, 14, 0.8);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .scroll-btn:hover {
            background-color: rgba(48, 54, 14, 1);
        }

        .scroll-left {
            left: 10px;
        }

        .scroll-right {
            right: 10px;
        }

        /* Categories Section */
        .categories {
            margin-bottom: 60px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .category-card {
            background-color: #fefffc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .category-card:hover {
            transform: translateY(-10px);
        }

        .category-content {
            padding: 20px;
        }

        .category-name {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        .category-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .category-price {
            color: #30360E;
            font-weight: 600;
        }

        .category-timing {
            color: #30360E;
        }

        .category-address {
            color: #30360E;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .rating {
            color: #ffc107;
            margin-bottom: 15px;
        }

        .btn {
            display: inline-block;
            background-color: #30360E;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            cursor: pointer;
            border: none;
        }

        .btn:hover {
            background-color: #57640a;
        }

        /* Blog Section */
        .blog {
            margin-bottom: 60px;
            position: relative;
        }

        .blog-bg {
            background-color: #f5f5f5;
            padding: 60px 0;
            background-color: #787f56;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            opacity: 0.8;
        }

        .blog-bg:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .blog-content {
            position: relative;
            z-index: 1;
            color: white;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .blog-title {
            font-size: 32px;
            margin-bottom: 20px;
        }

        .blog-text {
            font-size: 18px;
            margin-bottom: 25px;
        }

        /* Hot Items Section */
        .hot-items {
            margin-bottom: 60px;
        }

        .hot-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .hot-item-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            position: relative;
        }

        .hot-item-card:hover {
            transform: translateY(-10px);
        }

        .hot-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #ff4d4d;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .hot-item-content {
            padding: 20px;
        }

        .hot-item-name {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        .hot-item-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .hot-item-price {
            color: #30360E;
            font-weight: 600;
        }

        .hot-item-timing {
            color: #30360E;
        }

        .hot-item-address {
            color: #30360E;
            font-size: 14px;
            margin-bottom: 15px;
        }

        /* Footer Styles */
        footer {
            background-color: #787f56;
            color: white;
            padding: 50px 0 20px;
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

        .footer-about p {
            margin-bottom: 15px;
        }

        .footer-contact p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .footer-contact i {
            margin-right: 10px;
            color: #30360E;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background-color: #30360E;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
            cursor: pointer;
        }

        .social-links a:hover {
            background-color: #515d0e;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            font-size: 14px;
        }

        /* Booking Form Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .booking-container {
            background: #787F56;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: white;
            cursor: pointer;
            background: none;
            border: none;
        }

        .form-title {
            text-align: center;
            margin-bottom: 25px;
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
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
            font-size: 16px;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: #30360E;
            box-shadow: 0 0 5px rgba(48, 54, 14, 0.3);
        }

        .input-group.full-width {
            width: 100%;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
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

        .submit-btn:hover {
            background: #3d4512;
        }

        .required::after {
            content: " *";
            color: #ff4d4d;
        }

        /* Success Message */
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

        .category-img,
        .event-img,
        .hot-item-img {
            height: 200px;
            background-color: #e2d4b9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #787f56;
            font-size: 14px;
            overflow: hidden;
        }

        .category-img img,
        .event-img img,
        .hot-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* About Section Styles */
        .about-section {
            padding: 60px 0;
            background-color: #f9f7f2;
        }

        .about-title {
            text-align: center;
            margin-bottom: 40px;
            color: #30360E;
            font-size: 32px;
            position: relative;
        }

        .about-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 6px;
            background-color: #30360E;
            margin: 10px auto;
        }

        .about-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            align-items: center;
        }

        .about-text {
            font-size: 16px;
            line-height: 1.8;
            color: #333;
        }

        .about-text p {
            margin-bottom: 20px;
        }

        .about-image {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Contact Section Styles */
        .contact-section {
            padding: 60px 0;
            background-color: #f9f7f2;
        }

        .contact-title {
            text-align: center;
            margin-bottom: 40px;
            color: #30360E;
            font-size: 32px;
            position: relative;
        }

        .contact-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 6px;
            background-color: #30360E;
            margin: 10px auto;
        }

        .contact-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .contact-info {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .contact-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .contact-icon {
            background: #787f56;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .contact-details h4 {
            margin-bottom: 5px;
            color: #30360E;
        }

        .contact-details p {
            color: #666;
        }

        /* Search Results Styles */
        .search-results-section {
            padding: 40px 0;
            background-color: #f9f7f2;
            min-height: 500px;
        }

        .search-results-title {
            margin-bottom: 30px;
            color: #30360E;
            text-align: center;
        }

        .search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Social Media Popup Styles */
        .social-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .social-popup-content {
            background: #787F56;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            position: relative;
            text-align: center;
            color: white;
        }

        .social-popup h3 {
            margin-bottom: 20px;
            color: #30360E;
            font-size: 24px;
        }

        .social-popup p {
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .social-close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: white;
            cursor: pointer;
            background: none;
            border: none;
        }

        .social-link-btn {
            display: inline-block;
            background-color: #30360E;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            margin: 10px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .social-link-btn:hover {
            background-color: #3d4512;
        }

        /* Content Section Hiding */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
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

            .search-bar {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }

            .search-bar input {
                width: 70%;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .input-group {
                margin-bottom: 18px;
            }

            .scroll-btn {
                display: none;
            }

            .user-actions {
                margin-top: 10px;
            }

            .about-content,
            .contact-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php?section=home" class="logo">Elegant<span>Venues</span></a>
            <nav>
                <ul>
                    <li><a href="index.php?section=home" class="<?php echo $section == 'home' ? 'nav-active' : ''; ?>">Home</a></li>
                    <li><a href="#" id="bookUsNav">Book Us</a></li>
                    <li><a href="index.php?section=contact" class="<?php echo $section == 'contact' ? 'nav-active' : ''; ?>">Contact</a></li>
                    <li><a href="index.php?section=about" class="<?php echo $section == 'about' ? 'nav-active' : ''; ?>">About</a></li>
                    <li><a href="admin-login.php" style="color: #ffffff; font-weight: bold;">Admin</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user-dashboard.php" class="login-btn">Dashboard</a>
                    <a href="logout.php" class="register-btn">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Login</a>
                    <a href="register.php" class="register-btn">Register</a>
                <?php endif; ?>
            </div>
            <form method="GET" action="index.php" class="search-bar">
                <input type="hidden" name="section" value="search">
                <input type="text" name="search" placeholder="Search venues..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </header>

    <!-- Display booking messages -->
    <?php if ($booking_success): ?>
        <div class="container">
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Booking submitted successfully! We'll contact you shortly.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($booking_error): ?>
        <div class="container">
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> There was an error submitting your booking. Please try again.
            </div>
        </div>
    <?php endif; ?>

    <!-- Home Section -->
    <div id="home-section" class="content-section <?php echo $section == 'home' ? 'active' : ''; ?>">
        <!-- Featured Events Section -->
        <section class="featured-events">
            <div class="container">
                <h2 class="section-title">Featured Wedding Events</h2>
                <div class="events-scroll-container">
                    <button class="scroll-btn scroll-left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="events-scroll">
                        <!-- Featured events will be populated by PHP -->
                        <?php
                        $query = "SELECT * FROM venues WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 6";
                        $stmt = $db->prepare($query);
                        $stmt->execute();

                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '
                                <div class="event-card">
                                    <div class="event-img">';
                                if ($row['image_filename'] && file_exists('uploads/venues/' . $row['image_filename'])) {
                                    echo '<img src="uploads/venues/' . $row['image_filename'] . '" alt="' . $row['name'] . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                } else {
                                    echo '<i class="fas fa-image" style="font-size: 48px;"></i>';
                                }
                                echo '</div>
                                    <div class="event-content">
                                        <h3 class="event-name">' . htmlspecialchars($row['name']) . '</h3>
                                        <p class="event-description">' . htmlspecialchars($row['description']) . '</p>
                                        <button class="btn book-venue-btn" data-venue="' . htmlspecialchars($row['name']) . '" data-venue-id="' . $row['id'] . '">Book Similar</button>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '<p style="text-align: center; width: 100%; padding: 20px;">No featured events available at the moment.</p>';
                        }
                        ?>
                    </div>
                    <button class="scroll-btn scroll-right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <main>
            <div class="container">
                <!-- Categories Section -->
                <section class="categories">
                    <h2 class="section-title">Our Wedding Venues</h2>
                    <div class="category-grid">
                        <!-- All venues will be populated by PHP -->
                        <?php
                        $query = "SELECT * FROM venues ORDER BY created_at DESC LIMIT 12";
                        $stmt = $db->prepare($query);
                        $stmt->execute();

                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $rating_stars = '';
                                $full_stars = floor($row['rating']);
                                $has_half_star = ($row['rating'] - $full_stars) >= 0.5;

                                for ($i = 0; $i < $full_stars; $i++) {
                                    $rating_stars .= '<i class="fas fa-star"></i>';
                                }

                                if ($has_half_star) {
                                    $rating_stars .= '<i class="fas fa-star-half-alt"></i>';
                                }

                                $empty_stars = 5 - ceil($row['rating']);
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    $rating_stars .= '<i class="far fa-star"></i>';
                                }

                                echo '
                                <div class="category-card">
                                    <div class="category-img">';
                                if ($row['image_filename'] && file_exists('uploads/venues/' . $row['image_filename'])) {
                                    echo '<img src="uploads/venues/' . $row['image_filename'] . '" alt="' . $row['name'] . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                } else {
                                    echo '<i class="fas fa-image" style="font-size: 48px;"></i>';
                                }
                                echo '</div>
                                    <div class="category-content">
                                        <h3 class="category-name">' . htmlspecialchars($row['name']) . '</h3>
                                        <div class="category-details">
                                            <span class="category-price">' . htmlspecialchars($row['price_range']) . '</span>
                                            <span class="category-timing">' . htmlspecialchars($row['timing']) . '</span>
                                        </div>
                                        <p class="category-address">' . htmlspecialchars($row['address']) . '</p>
                                        <div class="rating">
                                            ' . $rating_stars . '
                                            <span>(' . $row['rating'] . ')</span>
                                        </div>
                                        <button class="btn book-venue-btn" data-venue="' . htmlspecialchars($row['name']) . '" data-venue-id="' . $row['id'] . '">Book Me</button>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '<p style="text-align: center; width: 100%; padding: 20px;">No venues available at the moment.</p>';
                        }
                        ?>
                    </div>
                </section>

                <!-- Blog Section -->
                <section class="blog">
                    <div class="blog-bg">
                        <div class="container">
                            <div class="blog-content">
                                <h2 class="blog-title">Creating Unforgettable Wedding Experiences</h2>
                                <p class="blog-text">At Elegant Venues, we understand that your wedding day is one of the
                                    most important days of your life. That's why we've curated the finest selection of
                                    wedding venues to make your special day truly magical.</p>
                                <a href="index.php?section=about" class="btn">Learn More About Us</a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Hot Items Section -->
                <section class="hot-items">
                    <h2 class="section-title">Popular Wedding Venues</h2>
                    <div class="hot-items-grid">
                        <!-- Hot venues will be populated by PHP -->
                        <?php
                        $query = "SELECT * FROM venues WHERE is_hot = 1 ORDER BY rating DESC LIMIT 3";
                        $stmt = $db->prepare($query);
                        $stmt->execute();

                        if ($stmt->rowCount() > 0) {
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $rating_stars = '';
                                $full_stars = floor($row['rating']);
                                $has_half_star = ($row['rating'] - $full_stars) >= 0.5;

                                for ($i = 0; $i < $full_stars; $i++) {
                                    $rating_stars .= '<i class="fas fa-star"></i>';
                                }

                                if ($has_half_star) {
                                    $rating_stars .= '<i class="fas fa-star-half-alt"></i>';
                                }

                                $empty_stars = 5 - ceil($row['rating']);
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    $rating_stars .= '<i class="far fa-star"></i>';
                                }

                                echo '
                                <div class="hot-item-card">
                                    <div class="hot-badge">HOT</div>
                                    <div class="hot-item-img">';
                                if ($row['image_filename'] && file_exists('uploads/venues/' . $row['image_filename'])) {
                                    echo '<img src="uploads/venues/' . $row['image_filename'] . '" alt="' . $row['name'] . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                } else {
                                    echo '<i class="fas fa-image" style="font-size: 48px;"></i>';
                                }
                                echo '</div>
                                    <div class="hot-item-content">
                                        <h3 class="hot-item-name">' . htmlspecialchars($row['name']) . '</h3>
                                        <div class="hot-item-details">
                                            <span class="hot-item-price">' . htmlspecialchars($row['price_range']) . '</span>
                                            <span class="hot-item-timing">' . htmlspecialchars($row['timing']) . '</span>
                                        </div>
                                        <p class="hot-item-address">' . htmlspecialchars($row['address']) . '</p>
                                        <div class="rating">
                                            ' . $rating_stars . '
                                            <span>(' . $row['rating'] . ')</span>
                                        </div>
                                        <button class="btn book-venue-btn" data-venue="' . htmlspecialchars($row['name']) . '" data-venue-id="' . $row['id'] . '">Book Now</button>
                                    </div>
                                </div>';
                            }
                        } else {
                            // Fallback to high-rated venues if no hot venues
                            $query = "SELECT * FROM venues WHERE rating >= 4.0 ORDER BY rating DESC LIMIT 3";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $rating_stars = '';
                                $full_stars = floor($row['rating']);
                                $has_half_star = ($row['rating'] - $full_stars) >= 0.5;

                                for ($i = 0; $i < $full_stars; $i++) {
                                    $rating_stars .= '<i class="fas fa-star"></i>';
                                }

                                if ($has_half_star) {
                                    $rating_stars .= '<i class="fas fa-star-half-alt"></i>';
                                }

                                $empty_stars = 5 - ceil($row['rating']);
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    $rating_stars .= '<i class="far fa-star"></i>';
                                }

                                echo '
                                <div class="hot-item-card">
                                    <div class="hot-badge">POPULAR</div>
                                    <div class="hot-item-img">';
                                if ($row['image_filename'] && file_exists('uploads/venues/' . $row['image_filename'])) {
                                    echo '<img src="uploads/venues/' . $row['image_filename'] . '" alt="' . $row['name'] . '" style="width: 100%; height: 100%; object-fit: cover;">';
                                } else {
                                    echo '<i class="fas fa-image" style="font-size: 48px;"></i>';
                                }
                                echo '</div>
                                    <div class="hot-item-content">
                                        <h3 class="hot-item-name">' . htmlspecialchars($row['name']) . '</h3>
                                        <div class="hot-item-details">
                                            <span class="hot-item-price">' . htmlspecialchars($row['price_range']) . '</span>
                                            <span class="hot-item-timing">' . htmlspecialchars($row['timing']) . '</span>
                                        </div>
                                        <p class="hot-item-address">' . htmlspecialchars($row['address']) . '</p>
                                        <div class="rating">
                                            ' . $rating_stars . '
                                            <span>(' . $row['rating'] . ')</span>
                                        </div>
                                        <button class="btn book-venue-btn" data-venue="' . htmlspecialchars($row['name']) . '" data-venue-id="' . $row['id'] . '">Book Now</button>
                                    </div>
                                </div>';
                            }
                        }
                        ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- About Section -->
    <div id="about-section" class="content-section <?php echo $section == 'about' ? 'active' : ''; ?>">
        <section class="about-section">
            <div class="container">
                <h2 class="about-title">About Elegant Venues</h2>
                <div class="about-content">
                    <div class="about-text">
                        <p>Welcome to Elegant Venues, your premier destination for finding the perfect wedding venue in Peshawar. With over a decade of experience in the wedding industry, we have helped thousands of couples create their dream weddings.</p>
                        
                        <p>Our mission is to simplify the wedding planning process and make it as enjoyable as possible. We understand that finding the right venue is one of the most important decisions you'll make, and we're here to guide you every step of the way.</p>
                        
                        <p><strong>What sets us apart:</strong></p>
                        <ul style="margin-left: 20px; margin-bottom: 20px;">
                            <li>Curated selection of premium wedding venues in Peshawar</li>
                            <li>Personalized venue recommendations based on your preferences</li>
                            <li>Transparent pricing with no hidden fees</li>
                            <li>Expert wedding planning advice from experienced coordinators</li>
                            <li>Dedicated customer support throughout your planning journey</li>
                        </ul>
                        
                        <p>Whether you're planning an intimate gathering or a grand celebration, we have the perfect venue for your special day. Our team of wedding experts is dedicated to helping you find a venue that matches your vision, budget, and style.</p>
                        
                        <p>Located in the heart of University Town, Peshawar, we pride ourselves on providing exceptional service and creating unforgettable wedding experiences.</p>
                    </div>
                    <div class="about-image">
                        <div style="background-color: #e2d4b9; height: 400px; display: flex; align-items: center; justify-content: center; color: #787f56;">
                            <i class="fas fa-heart" style="font-size: 100px;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Contact Section -->
    <div id="contact-section" class="content-section <?php echo $section == 'contact' ? 'active' : ''; ?>">
        <section class="contact-section">
            <div class="container">
                <h2 class="contact-title">Contact Us</h2>
                <div class="contact-content">
                    <div class="contact-info">
                        <h3>Get in Touch</h3>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Our Location</h4>
                                <p>Zaman Khan Plaza, 2nd Floor<br>University Town, Peshawar</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Phone Number</h4>
                                <p>+92 334 1513407</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Email Address</h4>
                                <p>Elwgantvenues@gmail.com</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Working Hours</h4>
                                <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 4:00 PM<br>Sunday: Closed</p>
                            </div>
                        </div>
                    </div>
                    <div class="contact-form">
                        <h3>Send us a Message</h3>
                        <form action="process-contact.php" method="POST">
                            <div class="input-group">
                                <label>Your Name</label>
                                <input type="text" name="name" placeholder="Enter your name" required>
                            </div>
                            <div class="input-group">
                                <label>Your Email</label>
                                <input type="email" name="email" placeholder="Enter your email" required>
                            </div>
                            <div class="input-group">
                                <label>Subject</label>
                                <input type="text" name="subject" placeholder="Enter subject" required>
                            </div>
                            <div class="input-group">
                                <label>Message</label>
                                <textarea name="message" placeholder="Your message here..." rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn" style="width: 100%;">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Search Results Section -->
    <div id="search-section" class="content-section <?php echo $section == 'search' ? 'active' : ''; ?>">
        <section class="search-results-section">
            <div class="container">
                <h2 class="search-results-title">
                    <?php 
                    if (!empty($search_query)) {
                        echo 'Search Results for: "' . htmlspecialchars($search_query) . '"';
                    } else {
                        echo 'Search Venues';
                    }
                    ?>
                </h2>
                
                <?php if (!empty($search_query)): ?>
                    <?php if (!empty($search_results)): ?>
                        <div class="search-results-grid">
                            <?php foreach ($search_results as $row): ?>
                                <?php
                                $rating_stars = '';
                                $full_stars = floor($row['rating']);
                                $has_half_star = ($row['rating'] - $full_stars) >= 0.5;

                                for ($i = 0; $i < $full_stars; $i++) {
                                    $rating_stars .= '<i class="fas fa-star"></i>';
                                }

                                if ($has_half_star) {
                                    $rating_stars .= '<i class="fas fa-star-half-alt"></i>';
                                }

                                $empty_stars = 5 - ceil($row['rating']);
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    $rating_stars .= '<i class="far fa-star"></i>';
                                }
                                ?>
                                
                                <div class="category-card">
                                    <div class="category-img">
                                        <?php if ($row['image_filename'] && file_exists('uploads/venues/' . $row['image_filename'])): ?>
                                            <img src="uploads/venues/<?php echo $row['image_filename']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-image" style="font-size: 48px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="category-content">
                                        <h3 class="category-name"><?php echo htmlspecialchars($row['name']); ?></h3>
                                        <div class="category-details">
                                            <span class="category-price"><?php echo htmlspecialchars($row['price_range']); ?></span>
                                            <span class="category-timing"><?php echo htmlspecialchars($row['timing']); ?></span>
                                        </div>
                                        <p class="category-address"><?php echo htmlspecialchars($row['address']); ?></p>
                                        <div class="rating">
                                            <?php echo $rating_stars; ?>
                                            <span>(<?php echo $row['rating']; ?>)</span>
                                        </div>
                                        <button class="btn book-venue-btn" data-venue="<?php echo htmlspecialchars($row['name']); ?>" data-venue-id="<?php echo $row['id']; ?>">Book Me</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No venues found matching your search criteria.</h3>
                            <p>Try searching with different keywords or browse our <a href="index.php?section=home">home page</a> to see all available venues.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <h3>Search for venues</h3>
                        <p>Use the search bar above to find venues by name, description, address, price range, or category.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Footer Section -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-section footer-about">
                    <h3>About Elegant Venues</h3>
                    <p>Elegant Venues is your premier destination for finding the perfect wedding venue in Peshawar. We connect you with the finest wedding venues for your special day.</p>
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
                        <a href="#" id="facebook-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" id="instagram-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2023 Elegant Venues. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Social Media Popups -->
    <!-- Facebook Popup -->
    <div class="social-popup" id="facebook-popup">
        <div class="social-popup-content">
            <button class="social-close-btn" data-popup="facebook">&times;</button>
            <h3>Follow us on Facebook</h3>
            <p>Stay updated with our latest wedding venues, special offers, and wedding planning tips by following us on Facebook.</p>
            <p><strong>Facebook Page:</strong> facebook.com/ElegantVenuesPK</p>
            <p><em>Connect with us for the latest updates and offers!</em></p>
            <a href="https://facebook.com/ElegantVenuesPK" target="_blank" class="social-link-btn">
                <i class="fab fa-facebook-f"></i> Visit our Facebook Page
            </a>
        </div>
    </div>

    <!-- Instagram Popup -->
    <div class="social-popup" id="instagram-popup">
        <div class="social-popup-content">
            <button class="social-close-btn" data-popup="instagram">&times;</button>
            <h3>Follow us on Instagram</h3>
            <p>See beautiful wedding venue photos, real wedding stories, and behind-the-scenes content on our Instagram.</p>
            <p><strong>Instagram Handle:</strong> @ElegantVenuesPK</p>
            <p><em>Follow us for daily inspiration and venue highlights!</em></p>
            <a href="https://instagram.com/ElegantVenuesPK" target="_blank" class="social-link-btn">
                <i class="fab fa-instagram"></i> Visit our Instagram
            </a>
        </div>
    </div>

    <!-- Booking Form Modal -->
    <div class="modal" id="bookingModal">
        <div class="booking-container">
            <button class="close-btn" id="closeBtn">&times;</button>
            <h2 class="form-title">Complete Your Booking</h2>

            <div class="success-message" id="successMessage" style="display: none;">
                <i class="fas fa-check-circle"></i> Booking submitted successfully! We'll contact you shortly.
            </div>

            <div class="error-message" id="errorMessage" style="display: none;">
                <i class="fas fa-exclamation-circle"></i> There was an error submitting your booking. Please try again.
            </div>

            <form class="booking-form" id="bookingForm" action="process-booking.php" method="POST">
                <input type="hidden" id="venueId" name="venue_id">

                <div class="form-row">
                    <div class="input-group">
                        <label class="required">Full Name</label>
                        <input type="text" id="fullName" name="full_name" placeholder="Enter your full name" required>
                    </div>
                    <div class="input-group">
                        <label class="required">Contact Number</label>
                        <input type="tel" id="contactNumber" name="contact_number" placeholder="Enter your phone number" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="required">CNIC</label>
                        <input type="text" id="cnic" name="cnic" placeholder="Enter your CNIC number" required>
                    </div>
                    <div class="input-group">
                        <label class="required">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="required">Event Date</label>
                        <input type="date" id="eventDate" name="event_date" required>
                    </div>
                    <div class="input-group">
                        <label class="required">Event Time</label>
                        <input type="time" id="eventTime" name="event_time" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="required">Package Category</label>
                        <select id="packageCategory" name="package_category" required>
                            <option value="" disabled selected>Select a package</option>
                            <option value="simple">Simple - Basic amenities</option>
                            <option value="advanced">Advanced - Enhanced services</option>
                            <option value="luxury">Luxury - Premium experience</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label class="required">Number of Guests</label>
                        <input type="number" id="guestCount" name="guest_count" placeholder="Approximate guest count" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label class="required">Payment Method</label>
                        <select id="paymentMethod" name="payment_method" required>
                            <option value="" disabled selected>Select payment option</option>
                            <option value="advance">Advance Payment (25%)</option>
                            <option value="half">Half Payment (50%)</option>
                            <option value="after">After Service</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label class="required">Venue</label>
                        <select id="venueSelect" name="venue" required>
                            <option value="" disabled selected>Select preferred venue</option>
                            <?php
                            $query = "SELECT * FROM venues ORDER BY name ASC";
                            $stmt = $db->prepare($query);
                            $stmt->execute();

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="input-group full-width">
                    <label class="required">Special Requirements</label>
                    <textarea id="specialRequirements" name="special_requirements" placeholder="Any special requests, dietary requirements, or additional services needed..." required></textarea>
                </div>

                <div class="input-group full-width">
                    <label>How did you hear about us?</label>
                    <select id="referralSource" name="referral_source">
                        <option value="" disabled selected>Select an option</option>
                        <option value="friend">Friend/Family</option>
                        <option value="social">Social Media</option>
                        <option value="search">Online Search</option>
                        <option value="ad">Advertisement</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-check-circle"></i> Confirm Booking
                </button>
            </form>
        </div>
    </div>

    <script>
        // Get modal and button elements
        const bookUsNav = document.getElementById('bookUsNav');
        const bookingModal = document.getElementById('bookingModal');
        const closeBtn = document.getElementById('closeBtn');
        const bookingForm = document.getElementById('bookingForm');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const venueSelect = document.getElementById('venueSelect');
        const venueIdInput = document.getElementById('venueId');
        const bookVenueBtns = document.querySelectorAll('.book-venue-btn');

        // Social Media Elements
        const facebookLink = document.getElementById('facebook-link');
        const instagramLink = document.getElementById('instagram-link');
        const facebookPopup = document.getElementById('facebook-popup');
        const instagramPopup = document.getElementById('instagram-popup');
        const socialCloseBtns = document.querySelectorAll('.social-close-btn');

        // Open modal when Book Us in navigation is clicked
        bookUsNav.addEventListener('click', function(e) {
            e.preventDefault();
            bookingModal.style.display = 'flex';
        });

        // Open modal when any Book Me button is clicked and set the venue
        bookVenueBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const venueName = this.getAttribute('data-venue');
                const venueId = this.getAttribute('data-venue-id');
                venueSelect.value = venueId;
                venueIdInput.value = venueId;
                bookingModal.style.display = 'flex';
            });
        });

        // Social Media Popup Handlers
        facebookLink.addEventListener('click', function(e) {
            e.preventDefault();
            facebookPopup.style.display = 'flex';
        });

        instagramLink.addEventListener('click', function(e) {
            e.preventDefault();
            instagramPopup.style.display = 'flex';
        });

        // Close social media popups
        socialCloseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const popupName = this.getAttribute('data-popup');
                if (popupName === 'facebook') {
                    facebookPopup.style.display = 'none';
                } else if (popupName === 'instagram') {
                    instagramPopup.style.display = 'none';
                }
            });
        });

        // Close popups when clicking outside
        window.addEventListener('click', function(event) {
            // Close booking modal
            if (event.target === bookingModal) {
                bookingModal.style.display = 'none';
                successMessage.style.display = 'none';
                errorMessage.style.display = 'none';
                bookingForm.style.display = 'block';
            }
            
            // Close social media popups
            if (event.target === facebookPopup) {
                facebookPopup.style.display = 'none';
            }
            if (event.target === instagramPopup) {
                instagramPopup.style.display = 'none';
            }
        });

        // Close booking modal when X button is clicked
        closeBtn.addEventListener('click', function() {
            bookingModal.style.display = 'none';
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            bookingForm.style.display = 'block';
        });

        // Horizontal scroll functionality
        const scrollLeftBtn = document.querySelector('.scroll-left');
        const scrollRightBtn = document.querySelector('.scroll-right');
        const eventsScroll = document.querySelector('.events-scroll');

        if (scrollLeftBtn && scrollRightBtn && eventsScroll) {
            scrollLeftBtn.addEventListener('click', function() {
                eventsScroll.scrollBy({
                    left: -300,
                    behavior: 'smooth'
                });
            });

            scrollRightBtn.addEventListener('click', function() {
                eventsScroll.scrollBy({
                    left: 300,
                    behavior: 'smooth'
                });
            });
        }

        // Set minimum date for event date to today
        const today = new Date().toISOString().split('T')[0];
        const eventDateInput = document.getElementById('eventDate');
        if (eventDateInput) {
            eventDateInput.min = today;
        }

        // Navigation highlighting based on current section
        document.addEventListener('DOMContentLoaded', function() {
            // Get current section from URL
            const urlParams = new URLSearchParams(window.location.search);
            const currentSection = urlParams.get('section') || 'home';
            
            // Update navigation highlighting
            const navLinks = document.querySelectorAll('nav a');
            navLinks.forEach(link => {
                link.classList.remove('nav-active');
                const href = link.getAttribute('href');
                if (href && href.includes('section=' + currentSection)) {
                    link.classList.add('nav-active');
                }
            });
        });

        // Form submission handling
        if (bookingForm) {
            bookingForm.addEventListener('submit', function(e) {
                // Form will be submitted to process-booking.php
                // Success/error messages will be shown after redirect
            });
        }
    </script>
</body>
</html>