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

// Ensure event_categories table exists so admin can add categories
try {
    $db->exec("CREATE TABLE IF NOT EXISTS event_categories (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        icon VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // If creation fails, set error message for admin
    $error_message = 'Could not ensure event_categories table exists: ' . $e->getMessage();
}

// Ensure `venue_tier` column exists in `venues` table (safe, idempotent)
try {
    $colCheck = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues' AND COLUMN_NAME = 'venue_tier'");
    $colCheck->execute();
    $colExists = (int) $colCheck->fetchColumn();
    if ($colExists === 0) {
        // Add the column with a sensible default
        $db->exec("ALTER TABLE venues ADD COLUMN venue_tier VARCHAR(20) NOT NULL DEFAULT 'basic'");
    }
} catch (PDOException $e) {
    // If adding fails, set an admin-visible error (non-fatal)
    $error_message = 'Could not ensure venues.venue_tier column exists: ' . $e->getMessage();
}

// Initialize messages
$success_message = '';
$error_message = '';
$upload_error = '';

// Handle booking status updates
if (isset($_POST['update_booking_status'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    try {
        $query = "UPDATE bookings SET status = :status, admin_notes = :admin_notes, response_date = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':admin_notes', $admin_notes);
        $stmt->bindParam(':id', $booking_id);
        
        if ($stmt->execute()) {
            $success_message = "Booking status updated successfully!";
        } else {
            $error_message = "Failed to update booking status.";
        }
    } catch(PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle event category management
if (isset($_POST['add_event_category'])) {
    $cat_name = trim($_POST['category_name'] ?? '');
    $cat_description = trim($_POST['category_description'] ?? '');
    $cat_icon = trim($_POST['category_icon'] ?? 'fa-calendar');
    
    // Validation
    if (empty($cat_name)) {
        $error_message = "Category name is required.";
    } else {
        try {
            // Check if category already exists
            $check_query = "SELECT COUNT(*) FROM event_categories WHERE name = :name";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':name', $cat_name);
            $check_stmt->execute();
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                $error_message = "Category '$cat_name' already exists.";
            } else {
                $query = "INSERT INTO event_categories (name, description, icon) VALUES (:name, :description, :icon)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $cat_name);
                $stmt->bindParam(':description', $cat_description);
                $stmt->bindParam(':icon', $cat_icon);
                
                if ($stmt->execute()) {
                    $success_message = "Event category '$cat_name' added successfully!";
                    $_POST = array(); // Clear form
                } else {
                    $error_message = "Failed to add event category.";
                }
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

if (isset($_POST['delete_event_category'])) {
    $category_id = $_POST['category_id'];
    
    try {
        // Get category name before deleting
        $get_query = "SELECT name FROM event_categories WHERE id = :id";
        $get_stmt = $db->prepare($get_query);
        $get_stmt->bindParam(':id', $category_id);
        $get_stmt->execute();
        $category_name = $get_stmt->fetchColumn();
        
        // Delete the category
        $query = "DELETE FROM event_categories WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $category_id);
        
        if ($stmt->execute()) {
            $success_message = "Event category '$category_name' deleted successfully!";
        } else {
            $error_message = "Failed to delete event category.";
        }
    } catch(PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle form submissions for all categories
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ADD NEW VENUE
    if (isset($_POST['add_venue'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price_range = trim($_POST['price_range']);
        $timing = trim($_POST['timing']);
        $address = trim($_POST['address']);
        $category = trim($_POST['category'] ?? '');
        $rating = $_POST['rating'] ?? '';
        $venue_tier = trim($_POST['venue_tier'] ?? '');
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_hot = isset($_POST['is_hot']) ? 1 : 0;
        
        // Validate category is not empty
        if (empty($category)) {
            $upload_error = "Please select a valid event category.";
        }
        
        // Validate venue tier is not empty
        if (empty($venue_tier) || !in_array($venue_tier, ['basic', 'advanced', 'luxury'])) {
            $upload_error = "Please select a valid venue tier (Basic, Advanced, or Luxury).";
        }
        
        // Handle image upload
        $image_filename = '';
        if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['venue_image']['type'];
            $file_size = $_FILES['venue_image']['size'];
            
            // Check file type
            if (in_array($file_type, $allowed_types)) {
                // Check file size (5MB max)
                if ($file_size <= 5000000) {
                    $file_extension = strtolower(pathinfo($_FILES['venue_image']['name'], PATHINFO_EXTENSION));
                    $image_filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $upload_path = 'uploads/venues/' . $image_filename;
                    
                    // Create uploads directory if it doesn't exist
                    if (!is_dir('uploads/venues')) {
                        mkdir('uploads/venues', 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['venue_image']['tmp_name'], $upload_path)) {
                        // Image uploaded successfully
                    } else {
                        $upload_error = "Failed to upload image. Please try again.";
                    }
                } else {
                    $upload_error = "File size too large. Maximum size is 5MB.";
                }
            } else {
                $upload_error = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP allowed.";
            }
        } else {
            $upload_error = "Please select a venue image.";
        }
        
        if (empty($upload_error)) {
            try {
                $query = "INSERT INTO venues (name, description, price_range, timing, address, category, rating, venue_tier, image_filename, is_featured, is_hot) 
                          VALUES (:name, :description, :price_range, :timing, :address, :category, :rating, :venue_tier, :image_filename, :is_featured, :is_hot)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price_range', $price_range);
                $stmt->bindParam(':timing', $timing);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':rating', $rating);
                $stmt->bindParam(':venue_tier', $venue_tier);
                $stmt->bindParam(':image_filename', $image_filename);
                $stmt->bindParam(':is_featured', $is_featured);
                $stmt->bindParam(':is_hot', $is_hot);
                
                if ($stmt->execute()) {
                    $success_message = "Venue added successfully!";
                    // Clear form fields
                    $_POST = array();
                } else {
                    $error_message = "Failed to add venue.";
                }
            } catch(PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // EDIT VENUE
    if (isset($_POST['edit_venue'])) {
        $venue_id = $_POST['venue_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price_range = trim($_POST['price_range']);
        $timing = trim($_POST['timing']);
        $address = trim($_POST['address']);
        $category = $_POST['category'];
        $rating = $_POST['rating'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_hot = isset($_POST['is_hot']) ? 1 : 0;
        
        // Handle image upload if new image is provided
        $image_update = '';
        $image_filename = '';
        
        if (isset($_FILES['venue_image']) && $_FILES['venue_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['venue_image']['type'];
            $file_size = $_FILES['venue_image']['size'];
            
            if (in_array($file_type, $allowed_types)) {
                if ($file_size <= 5000000) {
                    // Get old image to delete it later
                    $old_image_query = "SELECT image_filename FROM venues WHERE id = :id";
                    $old_image_stmt = $db->prepare($old_image_query);
                    $old_image_stmt->bindParam(':id', $venue_id);
                    $old_image_stmt->execute();
                    $old_venue = $old_image_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $file_extension = strtolower(pathinfo($_FILES['venue_image']['name'], PATHINFO_EXTENSION));
                    $image_filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $upload_path = 'uploads/venues/' . $image_filename;
                    
                    if (move_uploaded_file($_FILES['venue_image']['tmp_name'], $upload_path)) {
                        // Delete old image file
                        if ($old_venue['image_filename'] && file_exists('uploads/venues/' . $old_venue['image_filename'])) {
                            unlink('uploads/venues/' . $old_venue['image_filename']);
                        }
                        $image_update = ", image_filename = :image_filename";
                    } else {
                        $upload_error = "Failed to upload new image.";
                    }
                } else {
                    $upload_error = "File size too large. Maximum size is 5MB.";
                }
            } else {
                $upload_error = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP allowed.";
            }
        }
        
        if (empty($upload_error)) {
            try {
                if (!empty($image_update)) {
                    $query = "UPDATE venues SET 
                              name = :name, 
                              description = :description, 
                              price_range = :price_range, 
                              timing = :timing, 
                              address = :address, 
                              category = :category, 
                              rating = :rating,
                              is_featured = :is_featured,
                              is_hot = :is_hot,
                              image_filename = :image_filename 
                              WHERE id = :id";
                } else {
                    $query = "UPDATE venues SET 
                              name = :name, 
                              description = :description, 
                              price_range = :price_range, 
                              timing = :timing, 
                              address = :address, 
                              category = :category, 
                              rating = :rating,
                              is_featured = :is_featured,
                              is_hot = :is_hot
                              WHERE id = :id";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price_range', $price_range);
                $stmt->bindParam(':timing', $timing);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':rating', $rating);
                $stmt->bindParam(':is_featured', $is_featured);
                $stmt->bindParam(':is_hot', $is_hot);
                $stmt->bindParam(':id', $venue_id);
                
                if (!empty($image_update)) {
                    $stmt->bindParam(':image_filename', $image_filename);
                }
                
                if ($stmt->execute()) {
                    $success_message = "Venue updated successfully!";
                    // Redirect to clear POST data
                    header("Location: admin-dashboard.php?success=updated&tab=venues");
                    exit();
                } else {
                    $error_message = "Failed to update venue.";
                }
            } catch(PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // DELETE VENUE
    if (isset($_POST['delete_venue'])) {
        $venue_id = $_POST['venue_id'];
        
        try {
            // First, get the image filename to delete the file
            $query = "SELECT image_filename FROM venues WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $venue_id);
            $stmt->execute();
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete the image file if it exists
            if ($venue && $venue['image_filename'] && file_exists('uploads/venues/' . $venue['image_filename'])) {
                unlink('uploads/venues/' . $venue['image_filename']);
            }
            
            // Delete the venue from database
            $query = "DELETE FROM venues WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $venue_id);
            
            if ($stmt->execute()) {
                $success_message = "Venue deleted successfully!";
                header("Location: admin-dashboard.php?success=deleted&tab=venues");
                exit();
            } else {
                $error_message = "Failed to delete venue. There might be existing bookings for this venue.";
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    // QUICK ACTIONS - Feature/Unfeature, Hot/Not Hot
    if (isset($_POST['quick_action'])) {
        $venue_id = $_POST['venue_id'];
        $action = $_POST['action'];
        
        try {
            if ($action == 'feature') {
                $query = "UPDATE venues SET is_featured = 1 WHERE id = :id";
            } elseif ($action == 'unfeature') {
                $query = "UPDATE venues SET is_featured = 0 WHERE id = :id";
            } elseif ($action == 'make_hot') {
                $query = "UPDATE venues SET is_hot = 1 WHERE id = :id";
            } elseif ($action == 'remove_hot') {
                $query = "UPDATE venues SET is_hot = 0 WHERE id = :id";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $venue_id);
            
            if ($stmt->execute()) {
                $success_message = "Venue updated successfully!";
                header("Location: admin-dashboard.php?success=updated&tab=" . $_POST['current_tab']);
                exit();
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'updated') {
        $success_message = "Venue updated successfully!";
    } elseif ($_GET['success'] == 'deleted') {
        $success_message = "Venue deleted successfully!";
    } elseif ($_GET['success'] == 'booking_updated') {
        $success_message = "Booking status updated successfully!";
    }
}

// Get active tab from URL or default to featured
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'bookings';

// Get venue data for editing if ID is provided
$edit_venue = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $venue_id = $_GET['edit'];
    $query = "SELECT * FROM venues WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $venue_id);
    $stmt->execute();
    $edit_venue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_venue) {
        $error_message = "Venue not found!";
    }
}

// Get statistics
$bookings_count = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$approved_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$rejected_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'rejected'")->fetchColumn();
$venues_count = $db->query("SELECT COUNT(*) FROM venues")->fetchColumn();
$users_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$featured_count = $db->query("SELECT COUNT(*) FROM venues WHERE is_featured = 1")->fetchColumn();
$hot_count = $db->query("SELECT COUNT(*) FROM venues WHERE is_hot = 1")->fetchColumn();

// Get event categories (ensure some defaults exist)
try {
    $event_categories = $db->query("SELECT * FROM event_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($event_categories)) {
        // Insert default categories if table is empty
        $defaults = [
            ['Wedding', 'Wedding events and celebrations', 'fa-ring'],
            ['Engagement', 'Engagement ceremonies', 'fa-heart'],
            ['Mehndi', 'Mehndi events', 'fa-palette'],
        ];
        $insertStmt = $db->prepare("INSERT INTO event_categories (name, description, icon) VALUES (:name, :description, :icon)");
        foreach ($defaults as $d) {
            try {
                $insertStmt->execute([':name' => $d[0], ':description' => $d[1], ':icon' => $d[2]]);
            } catch (Exception $ie) {
                // ignore duplicates or errors here
            }
        }
        // reload
        $event_categories = $db->query("SELECT * FROM event_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $event_categories = [];
}

// Get venues based on category
$featured_venues = $db->query("SELECT * FROM venues WHERE is_featured = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$hot_venues = $db->query("SELECT * FROM venues WHERE is_hot = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_venues = $db->query("SELECT * FROM venues ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get all bookings with venue and user info
$bookings_query = "SELECT b.*, v.name as venue_name, u.first_name, u.last_name, u.email as user_email 
                   FROM bookings b 
                   JOIN venues v ON b.venue_id = v.id 
                   LEFT JOIN users u ON b.user_id = u.id 
                   ORDER BY b.created_at DESC";
$all_bookings = $db->query($bookings_query)->fetchAll(PDO::FETCH_ASSOC);

// Get recent bookings for dashboard
$recent_bookings_query = "SELECT b.*, v.name as venue_name, u.first_name, u.last_name 
                         FROM bookings b 
                         JOIN venues v ON b.venue_id = v.id 
                         LEFT JOIN users u ON b.user_id = u.id 
                         ORDER BY b.created_at DESC 
                         LIMIT 10";
$recent_bookings = $db->query($recent_bookings_query)->fetchAll(PDO::FETCH_ASSOC);

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
        case 'pending': return 'Pending';
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
    <title>Admin Dashboard - Elegant Venues</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ADD THESE NEW STYLES FOR BOOKINGS TAB */
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .bookings-table th,
        .bookings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .bookings-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #30360E;
        }
        
        .bookings-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .status-form select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .status-form textarea {
            flex: 1;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-height: 40px;
        }
        
        .status-form button {
            padding: 5px 15px;
            background: #787f56;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .booking-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 5px;
            display: none;
        }
        
        .booking-details.active {
            display: block;
        }
        
        .toggle-details {
            background: none;
            border: none;
            color: #787f56;
            cursor: pointer;
            font-size: 14px;
        }
        
        /* REST OF YOUR EXISTING ADMIN DASHBOARD STYLES REMAIN THE SAME */
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
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .page-title {
            margin-bottom: 20px;
            color: #30360E;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            display: flex;
            flex-direction: column;
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
            align-self: center;
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
        
        /* Main Tabs */
        .main-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            background: white;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }
        
        .main-tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .main-tab:hover {
            background: #f8f9fa;
        }
        
        .main-tab.active {
            color: #30360E;
            border-bottom-color: #787f56;
            background: #f8f9fa;
        }
        
        .main-tab-content {
            display: none;
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-tab-content.active {
            display: block;
        }
        
        .tab-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .tab-header h3 {
            color: #30360E;
            margin: 0;
        }
        
        .tab-content-inner {
            padding: 20px;
        }
        
        /* Form Styles */
        .form-container {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        
        .form-container h4 {
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
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            background-color: white;
            font-family: inherit;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 30px;
            cursor: pointer;
        }

        .form-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: #787f56;
            outline: none;
            box-shadow: 0 0 5px rgba(120, 127, 86, 0.3);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin: 15px 0;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: block;
            padding: 12px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            border-color: #787f56;
            background: #e9ecef;
        }
        
        .file-input-label i {
            margin-right: 8px;
            color: #787f56;
        }
        
        .image-preview {
            margin-top: 10px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .current-image {
            margin-top: 10px;
            text-align: center;
        }
        
        .current-image img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .current-image p {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn-full {
            width: 100%;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Venue Cards */
        .venues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .venue-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .venue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        
        .venue-badges {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        
        .badge-featured {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-hot {
            background: #dc3545;
            color: white;
        }
        
        .venue-img-container {
            position: relative;
            height: 200px;
            background-color: #e2d4b9;
            overflow: hidden;
        }
        
        .venue-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .venue-content {
            padding: 15px;
        }
        
        .venue-name {
            font-size: 18px;
            margin-bottom: 10px;
            color: #30360E;
        }
        
        .venue-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .venue-price {
            color: #30360E;
            font-weight: 600;
        }
        
        .venue-timing {
            color: #666;
        }
        
        .venue-rating {
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .venue-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .venue-description {
            font-size: 14px;
            color: #777;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .venue-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .venue-actions .btn {
            flex: 1;
            min-width: 80px;
        }
        
        .quick-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        
        .quick-actions .btn {
            flex: 1;
            padding: 6px 10px;
            font-size: 12px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        /* Responsive Styles */
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
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .venue-actions {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .main-tabs {
                flex-direction: column;
            }
            
            .main-tab {
                text-align: left;
                border-bottom: 1px solid #dee2e6;
            }
            
            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .bookings-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Venues Grid Styles */
        .venues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .venue-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .venue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .venue-image {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .venue-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .venue-badges {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }

        .badge-featured {
            background: #FFD700;
            color: #333;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-hot {
            background: #FF4500;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .venue-details {
            padding: 20px;
        }

        .venue-details h5 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #30360E;
        }

        .venue-details p {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .venue-details p i {
            margin-right: 8px;
            color: #787f56;
            min-width: 20px;
        }

        .venue-actions {
            display: flex;
            gap: 5px;
            margin-top: 15px;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            cursor: pointer;
        }

        /* Responsive Form Styles */
        .responsive-form {
            max-width: 1000px;

                    /* Event Categories Grid */
                    .categories-grid {

                                /* Icon Suggestions */
                                .icon-suggestions {
                                    display: grid;
                                    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                                    gap: 10px;
                                    margin-top: 15px;
                                }

                                .icon-btn {
                                    padding: 10px;
                                    border: 2px solid #ddd;
                                    border-radius: 6px;
                                    background: white;
                                    color: #30360E;
                                    cursor: pointer;
                                    font-size: 12px;
                                    transition: all 0.3s ease;
                                    display: flex;
                                    flex-direction: column;
                                    align-items: center;
                                    gap: 5px;
                                }

                                .icon-btn:hover {
                                    border-color: #787f56;
                                    background: #f0f2e6;
                                    box-shadow: 0 2px 8px rgba(120, 127, 86, 0.2);
                                }

                                .icon-btn i {
                                    font-size: 20px;
                                }

                                @media (max-width: 600px) {
                                    .icon-suggestions {
                                        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                                    }

                                    .icon-btn {
                                        padding: 8px;
                                        font-size: 11px;
                                    }

                                    .icon-btn i {
                                        font-size: 16px;
                                    }
                                }
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                        gap: 20px;
                        margin-top: 25px;
                    }

                    .category-card-item {
                        background: white;
                        border: 1px solid #dee2e6;
                        border-radius: 8px;
                        padding: 20px;
                        display: flex;
                        flex-direction: column;
                        gap: 15px;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
                    }

                    .category-card-item:hover {
                        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                        transform: translateY(-2px);
                        border-color: #787f56;
                    }

                    .category-icon {
                        text-align: center;
                        font-size: 40px;
                        color: #787f56;
                    }

                    .category-info {
                        flex: 1;
                    }

                    .category-info h5 {
                        margin: 0 0 8px 0;
                        color: #30360E;
                        font-size: 18px;
                    }

                    .category-info p {
                        margin: 0 0 10px 0;
                        color: #666;
                        font-size: 14px;
                        line-height: 1.4;
                    }

                    .category-info small {
                        color: #999;
                        font-size: 12px;
                    }

                    .category-actions {
                        display: flex;
                        gap: 10px;
                        padding-top: 10px;
                        border-top: 1px solid #eee;
                    }

                    .category-actions .btn {
                        flex: 1;
                        margin: 0;
                    }

                    /* Empty State */
                    .empty-state {
                        text-align: center;
                        padding: 60px 20px;
                        background: #f9faf7;
                        border-radius: 8px;
                        border: 2px dashed #ddd;
                    }

                    .empty-state i {
                        font-size: 48px;
                        color: #ccc;
                        display: block;
                        margin-bottom: 15px;
                    }

                    .empty-state h3 {
                        color: #666;
                        margin-bottom: 8px;
                    }

                    .empty-state p {
                        color: #999;
                        font-size: 14px;
                    }

                    @media (max-width: 768px) {
                        .categories-grid {
                            grid-template-columns: 1fr;
                        }
                    }
            margin: 0 auto;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        /* Venue Tier Selection */
        .tier-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .tier-option {
            position: relative;
            cursor: pointer;
        }

        .tier-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .tier-card {
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
        }

        .tier-option input[type="radio"]:checked + .tier-card {
            border-color: #787f56;
            background: #f0f2e6;
            box-shadow: 0 0 10px rgba(120, 127, 86, 0.3);
        }

        .tier-card i {
            font-size: 32px;
            color: #787f56;
            margin-bottom: 10px;
        }

        .tier-card h5 {
            margin: 10px 0;
            color: #30360E;
            font-size: 18px;
        }

        .tier-card p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }

        /* Promotion Options */
        .promotion-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .promotion-card {
            position: relative;
            cursor: pointer;
        }

        .promotion-card input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .promotion-badge {
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
        }

        .promotion-card input[type="checkbox"]:checked + .promotion-badge {
            border-color: #787f56;
            background: #f0f2e6;
            box-shadow: 0 0 10px rgba(120, 127, 86, 0.3);
        }

        .promotion-badge i {
            font-size: 28px;
            color: #787f56;
            margin-bottom: 10px;
        }

        .promotion-badge h6 {
            margin: 8px 0;
            color: #30360E;
            font-size: 16px;
        }

        .promotion-badge p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        /* File Input Styling */
        .file-input-container {
            position: relative;
            overflow: hidden;
        }

        .file-input-container input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            border: 2px dashed #787f56;
            border-radius: 8px;
            background: #f9faf7;
            color: #787f56;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .file-input-container input[type="file"]:hover + .file-input-label,
        .file-input-container:hover .file-input-label {
            background: #f0f2e6;
            border-color: #676e4c;
        }

        .file-input-label i {
            margin-right: 10px;
            font-size: 24px;
        }

        /* Button Sizes */
        .btn-large {
            padding: 15px 40px;
            font-size: 16px;
            width: 100%;
            max-width: none;
        }

        /* Responsive Grid for Form Rows */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .tier-selection {
                grid-template-columns: 1fr;
            }

            .promotion-options {
                grid-template-columns: 1fr;
            }

            .tier-card,
            .promotion-badge {
                padding: 15px;
            }

            .tier-card i,
            .promotion-badge i {
                font-size: 24px;
            }

            .btn-large {
                padding: 12px 20px;
                font-size: 14px;
            }

            .file-input-label {
                padding: 20px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .tier-card h5,
            .promotion-badge h6 {
                font-size: 14px;
            }

            .file-input-label {
                flex-direction: column;
                padding: 15px;
            }

            .file-input-label i {
                margin-right: 0;
                margin-bottom: 8px;
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
                <li><a href="admin-dashboard.php?tab=bookings" class="<?php echo $active_tab == 'bookings' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                <li><a href="admin-dashboard.php?tab=featured" class="<?php echo $active_tab == 'featured' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Featured Events</a></li>
                <li><a href="admin-dashboard.php?tab=venues" class="<?php echo $active_tab == 'venues' ? 'active' : ''; ?>"><i class="fas fa-building"></i> All Venues</a></li>
                <li><a href="admin-dashboard.php?tab=hot" class="<?php echo $active_tab == 'hot' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Hot Menus</a></li>
                <li><a href="admin-customers.php"><i class="fas fa-users"></i> Customers</a></li>
                <li><a href="admin-reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin-settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-title">
                <h1>Admin Dashboard</h1>
            </div>

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

            <?php if (!empty($upload_error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $upload_error; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-value"><?php echo $venues_count; ?></div>
                    <div class="stat-label">Total Venues</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-value"><?php echo $featured_count; ?></div>
                    <div class="stat-label">Featured Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-fire"></i></div>
                    <div class="stat-value"><?php echo $hot_count; ?></div>
                    <div class="stat-label">Hot Menus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-value"><?php echo $bookings_count; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo $pending_bookings; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check"></i></div>
                    <div class="stat-value"><?php echo $approved_bookings; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times"></i></div>
                    <div class="stat-value"><?php echo $rejected_bookings; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $users_count; ?></div>
                    <div class="stat-label">Users</div>
                </div>
            </div>

            <!-- Main Tabs -->
            <div class="main-tabs">
                <button class="main-tab <?php echo $active_tab == 'bookings' ? 'active' : ''; ?>" onclick="switchMainTab('bookings')">
                    <i class="fas fa-calendar-check"></i> Bookings Management
                </button>
                <button class="main-tab <?php echo $active_tab == 'featured' ? 'active' : ''; ?>" onclick="switchMainTab('featured')">
                    <i class="fas fa-star"></i> Featured Wedding Events
                </button>
                <button class="main-tab <?php echo $active_tab == 'manage-venues' ? 'active' : ''; ?>" onclick="switchMainTab('manage-venues')">
                    <i class="fas fa-plus-circle"></i> Add/Manage Venues
                </button>
                <button class="main-tab <?php echo $active_tab == 'venues' ? 'active' : ''; ?>" onclick="switchMainTab('venues')">
                    <i class="fas fa-building"></i> All Venues
                </button>
                <button class="main-tab <?php echo $active_tab == 'hot' ? 'active' : ''; ?>" onclick="switchMainTab('hot')">
                    <i class="fas fa-fire"></i> Hot Menus
                </button>
                <button class="main-tab <?php echo $active_tab == 'events' ? 'active' : ''; ?>" onclick="switchMainTab('events')">
                    <i class="fas fa-calendar-alt"></i> Event Categories
                </button>
            </div>

            <!-- ===== BOOKINGS MANAGEMENT TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'bookings' ? 'active' : ''; ?>" id="bookingsTab">
                <div class="tab-header">
                    <h3><i class="fas fa-calendar-check"></i> Manage Bookings</h3>
                    <span class="badge badge-warning"><?php echo count($all_bookings); ?> Total Bookings</span>
                </div>
                <div class="tab-content-inner">
                    <!-- Bookings Table -->
                    <h4 style="margin-bottom: 20px;">All Bookings (<?php echo count($all_bookings); ?>)</h4>
                    
                    <?php if (empty($all_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <h3>No Bookings Yet</h3>
                            <p>Bookings will appear here when customers make reservations.</p>
                        </div>
                    <?php else: ?>
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Venue</th>
                                    <th>Event Date</th>
                                    <th>Package</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td>
                                        <?php 
                                        $customer_name = $booking['full_name'];
                                        if ($booking['first_name']) {
                                            $customer_name = $booking['first_name'] . ' ' . $booking['last_name'];
                                        }
                                        echo htmlspecialchars($customer_name);
                                        ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['venue_name']); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($booking['event_date'])); ?><br>
                                        <small><?php echo date('h:i A', strtotime($booking['event_time'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo ucfirst($booking['package_category']); ?><br>
                                        <small><?php echo $booking['guest_count']; ?> guests</small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadgeClass($booking['status']); ?>">
                                            <?php echo getStatusText($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="toggle-details" onclick="toggleBookingDetails(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-chevron-down"></i> Details
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7">
                                        <div class="booking-details" id="details-<?php echo $booking['id']; ?>">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                                <div>
                                                    <h5>Booking Information</h5>
                                                    <p><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                                                    <p><strong>Booked On:</strong> <?php echo date('M d, Y h:i A', strtotime($booking['created_at'])); ?></p>
                                                    <p><strong>CNIC:</strong> <?php echo htmlspecialchars($booking['cnic']); ?></p>
                                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($booking['contact_number']); ?></p>
                                                </div>
                                                <div>
                                                    <h5>Event Details</h5>
                                                    <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['event_date'])); ?></p>
                                                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['event_time'])); ?></p>
                                                    <p><strong>Package:</strong> <?php echo ucfirst($booking['package_category']); ?></p>
                                                    <p><strong>Guests:</strong> <?php echo $booking['guest_count']; ?></p>
                                                    <p><strong>Payment:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></p>
                                                </div>
                                                <div>
                                                    <h5>Additional Info</h5>
                                                    <p><strong>Requirements:</strong><br><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                                                    <p><strong>Referral:</strong> <?php echo ucfirst($booking['referral_source']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($booking['admin_notes'] ?? '')): ?>
                                            <div style="background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                                                <h5>Admin Notes</h5>
                                                <p><?php echo nl2br(htmlspecialchars($booking['admin_notes'] ?? '')); ?></p>
                                                <?php if (!empty($booking['response_date'] ?? '')): ?>
                                                <small>Updated: <?php echo date('M d, Y h:i A', strtotime($booking['response_date'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <select name="status" required>
                                                    <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="approved" <?php echo $booking['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="rejected" <?php echo $booking['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                                <textarea name="admin_notes" placeholder="Add notes for customer..."><?php echo htmlspecialchars($booking['admin_notes'] ?? ''); ?></textarea>
                                                <button type="submit" name="update_booking_status" class="btn btn-sm btn-primary">
                                                    Update Status
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== FEATURED WEDDING EVENTS TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'featured' ? 'active' : ''; ?>" id="featuredTab">
                <!-- Featured Events content remains the same as before -->
                <!-- ... [KEEP ALL YOUR EXISTING FEATURED EVENTS CONTENT] ... -->
            </div>

            <!-- ===== MANAGE VENUES TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'manage-venues' ? 'active' : ''; ?>" id="manage-venuesTab">
                <div class="tab-header">
                    <h3><i class="fas fa-plus-circle"></i> Add & Manage Venues</h3>
                    <span class="badge badge-info"><?php echo count($all_venues); ?> Total Venues</span>
                </div>
                <div class="tab-content-inner">
                    <!-- Add New Venue Form -->
                    <div class="form-container">
                        <h4><i class="fas fa-plus"></i> Add New Venue</h4>
                        <form method="POST" action="" enctype="multipart/form-data" class="responsive-form">
                            <!-- Row 1: Venue Name & Category -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Venue Name <span style="color: red;">*</span></label>
                                    <input type="text" id="name" name="name" placeholder="Enter venue name" required>
                                </div>
                                <div class="form-group">
                                    <label for="category">Event Category <span style="color: red;">*</span></label>
                                    <select id="category" name="category" required onchange="validateCategory()">
                                        <option value="">-- Select Category --</option>
                                        <?php if (!empty($event_categories)): ?>
                                            <?php foreach($event_categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option disabled>No categories available</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 2: Venue Tier Selection -->
                            <div class="form-group full-width">
                                <label>Venue Tier <span style="color: red;">*</span></label>
                                <div class="tier-selection">
                                    <label class="tier-option">
                                        <input type="radio" name="venue_tier" value="basic" required>
                                        <div class="tier-card">
                                            <i class="fas fa-home"></i>
                                            <h5>Basic</h5>
                                            <p>Small venues, intimate gatherings</p>
                                        </div>
                                    </label>
                                    <label class="tier-option">
                                        <input type="radio" name="venue_tier" value="advanced">
                                        <div class="tier-card">
                                            <i class="fas fa-building"></i>
                                            <h5>Advanced</h5>
                                            <p>Medium venues, modern amenities</p>
                                        </div>
                                    </label>
                                    <label class="tier-option">
                                        <input type="radio" name="venue_tier" value="luxury">
                                        <div class="tier-card">
                                            <i class="fas fa-crown"></i>
                                            <h5>Luxury</h5>
                                            <p>Premium venues, premium experience</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Row 3: Price Range & Rating -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="price_range">Price Range <span style="color: red;">*</span></label>
                                    <input type="text" id="price_range" name="price_range" placeholder="e.g., Rs. 50,000 - 100,000" required>
                                </div>
                                <div class="form-group">
                                    <label for="rating">Rating (1-5) <span style="color: red;">*</span></label>
                                    <select id="rating" name="rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                                        <option value="4.5">⭐⭐⭐⭐☆ Very Good</option>
                                        <option value="4">⭐⭐⭐⭐ Good</option>
                                        <option value="3.5">⭐⭐⭐☆ Fair</option>
                                        <option value="3">⭐⭐⭐ Average</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 4: Timing & Address -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="timing">Timing/Hours <span style="color: red;">*</span></label>
                                    <input type="text" id="timing" name="timing" placeholder="e.g., 2:00 PM - 12:00 AM" required>
                                </div>
                                <div class="form-group">
                                    <label for="address">Address <span style="color: red;">*</span></label>
                                    <input type="text" id="address" name="address" placeholder="Enter venue address" required>
                                </div>
                            </div>

                            <!-- Row 5: Description -->
                            <div class="form-group full-width">
                                <label for="description">Description <span style="color: red;">*</span></label>
                                <textarea id="description" name="description" placeholder="Describe the venue features, amenities, capacity, etc." rows="5" required></textarea>
                            </div>

                            <!-- Row 6: Image Upload -->
                            <div class="form-group full-width">
                                <label for="venue_image">Venue Image <span style="color: red;">*</span></label>
                                <div class="file-input-container">
                                    <input type="file" id="venue_image" name="venue_image" accept="image/*" onchange="previewImage(this, 'imagePreview')" required>
                                    <span class="file-input-label"><i class="fas fa-cloud-upload-alt"></i> Click to upload image</span>
                                </div>
                            </div>

                            <div id="imagePreview" style="margin: 15px 0; text-align: center;"></div>

                            <!-- Row 7: Featured & Hot Selection -->
                            <div class="form-group full-width">
                                <label>Venue Promotion <span style="color: red;">*</span></label>
                                <div class="promotion-options">
                                    <label class="promotion-card">
                                        <input type="checkbox" name="is_featured" value="1">
                                        <div class="promotion-badge">
                                            <i class="fas fa-star"></i>
                                            <h6>Featured</h6>
                                            <p>Display on featured section</p>
                                        </div>
                                    </label>
                                    <label class="promotion-card">
                                        <input type="checkbox" name="is_hot" value="1">
                                        <div class="promotion-badge">
                                            <i class="fas fa-fire"></i>
                                            <h6>Hot Deal</h6>
                                            <p>Display as hot/trending</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group full-width">
                                <button type="submit" name="add_venue" class="btn btn-primary btn-large">
                                    <i class="fas fa-plus"></i> Add Venue
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Existing Venues List -->
                    <h4 style="margin-bottom: 20px; margin-top: 30px;">Existing Venues</h4>
                    <?php if (!empty($all_venues)): ?>
                    <div class="venues-grid">
                        <?php foreach($all_venues as $venue): ?>
                        <div class="venue-card">
                            <div class="venue-image">
                                <?php if (!empty($venue['image_filename'])): ?>
                                <img src="uploads/venues/<?php echo htmlspecialchars($venue['image_filename']); ?>" alt="<?php echo htmlspecialchars($venue['name']); ?>">
                                <?php else: ?>
                                <div style="background: #ddd; height: 200px; display: flex; align-items: center; justify-content: center; color: #666;">No Image</div>
                                <?php endif; ?>
                                <div class="venue-badges">
                                    <?php if ($venue['is_featured']): ?>
                                    <span class="badge badge-featured"><i class="fas fa-star"></i> Featured</span>
                                    <?php endif; ?>
                                    <?php if ($venue['is_hot']): ?>
                                    <span class="badge badge-hot"><i class="fas fa-fire"></i> Hot</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="venue-details">
                                <h5><?php echo htmlspecialchars($venue['name']); ?></h5>
                                <p><i class="fas fa-tag"></i> <strong><?php echo htmlspecialchars($venue['category']); ?></strong></p>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($venue['address']); ?></p>
                                <p><i class="fas fa-clock"></i> <?php echo htmlspecialchars($venue['timing']); ?></p>
                                <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($venue['price_range']); ?></p>
                                <p><i class="fas fa-star"></i> Rating: <strong><?php echo $venue['rating']; ?>/5</strong></p>
                                <div class="venue-actions">
                                    <a href="?tab=manage-venues&edit_id=<?php echo $venue['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="venue_id" value="<?php echo $venue['id']; ?>">
                                        <button type="submit" name="delete_venue" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center; color: #666;">No venues added yet. Add your first venue above!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== ALL VENUES TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'venues' ? 'active' : ''; ?>" id="venuesTab">
                <!-- All Venues content remains the same as before -->
                <!-- ... [KEEP ALL YOUR EXISTING ALL VENUES CONTENT] ... -->
            </div>

            <!-- ===== HOT MENUS TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'hot' ? 'active' : ''; ?>" id="hotTab">
                <!-- Hot Menus content remains the same as before -->
                <!-- ... [KEEP ALL YOUR EXISTING HOT MENUS CONTENT] ... -->
            </div>

            <!-- ===== EVENT CATEGORIES TAB ===== -->
            <div class="main-tab-content <?php echo $active_tab == 'events' ? 'active' : ''; ?>" id="eventsTab">
                <div class="tab-header">
                    <h3><i class="fas fa-calendar-alt"></i> Event Categories</h3>
                </div>
                <div class="tab-content-inner">
                    <!-- Add New Event Category Form -->
                    <div class="form-container">
                        <h4><i class="fas fa-plus"></i> Add New Event Category</h4>
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_name">Category Name</label>
                                    <input type="text" id="category_name" name="category_name" placeholder="e.g., Wedding, Engagement, Mehndi" required>
                                </div>
                                <div class="form-group">
                                    <label for="category_icon">Icon Class (Font Awesome)</label>
                                    <input type="text" id="category_icon" name="category_icon" placeholder="e.g., fa-ring, fa-heart" value="fa-calendar">
                                </div>
                            </div>
                            <!-- Icon Suggestions -->
                            <div class="form-group">
                                <label>Quick Icon Selection</label>
                                <div class="icon-suggestions">
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-ring')" title="Wedding">
                                        <i class="fas fa-ring"></i> Wedding
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-heart')" title="Engagement">
                                        <i class="fas fa-heart"></i> Engagement
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-palette')" title="Mehndi">
                                        <i class="fas fa-palette"></i> Mehndi
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-horse')" title="Barat">
                                        <i class="fas fa-horse"></i> Barat
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-utensils')" title="Valima">
                                        <i class="fas fa-utensils"></i> Valima
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-briefcase')" title="Corporate">
                                        <i class="fas fa-briefcase"></i> Corporate
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-glass-cheers')" title="Celebration">
                                        <i class="fas fa-glass-cheers"></i> Celebration
                                    </button>
                                    <button type="button" class="icon-btn" onclick="setIcon('fa-music')" title="Party">
                                        <i class="fas fa-music"></i> Party
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="category_description">Description</label>
                                <textarea id="category_description" name="category_description" placeholder="Brief description of this event category"></textarea>
                            </div>
                            <button type="submit" name="add_event_category" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </form>
                    </div>

                    <!-- Event Categories List -->
                    <div style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 30px;">
                        <h4 style="margin-bottom: 25px;"><i class="fas fa-list"></i> Existing Event Categories (<?php echo count($event_categories); ?>)</h4>
                    </div>
                    <?php if (!empty($event_categories)): ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Icon</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($event_categories as $category): ?>
                            <tr>
                                <td>#<?php echo $category['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                <td><i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i> <?php echo htmlspecialchars($category['icon']); ?></td>
                                <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" name="delete_event_category" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="categories-grid">
                        <?php foreach($event_categories as $category): ?>
                        <div class="category-card-item">
                            <div class="category-icon">
                                <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                            </div>
                            <div class="category-info">
                                <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></p>
                                <small>Added: <?php echo date('M d, Y', strtotime($category['created_at'])); ?></small>
                            </div>
                            <div class="category-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" name="delete_event_category" class="btn btn-sm btn-danger" onclick="return confirm('Delete &quot;<?php echo htmlspecialchars($category['name']); ?>&quot;? This cannot be undone.');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Categories Yet</h3>
                        <p>Add your first event category using the form above.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Venue Modal -->
            <?php if ($edit_venue): ?>
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                <div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
                    <h3>Edit Venue: <?php echo htmlspecialchars($edit_venue['name']); ?></h3>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- ... [KEEP YOUR EXISTING EDIT VENUE FORM] ... -->
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Validate category selection
        function validateCategory() {
            const categorySelect = document.getElementById('category');
            if (categorySelect && categorySelect.value === '') {
                categorySelect.style.borderColor = '#dc3545';
            } else if (categorySelect) {
                categorySelect.style.borderColor = '#ddd';

                    // Set icon in the icon input field
                    function setIcon(iconClass) {
                        event.preventDefault();
                        const iconInput = document.getElementById('category_icon');
                        if (iconInput) {
                            iconInput.value = iconClass;
                            iconInput.focus();
                        }
                    }
            }
        }

        // Main tab switching functionality
        function switchMainTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.main-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.main-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // Activate selected tab
            event.target.classList.add('active');
            
            // Update URL without reloading page
            history.pushState(null, null, '?tab=' + tabName);
        }

        // Toggle booking details
        function toggleBookingDetails(bookingId) {
            const detailsDiv = document.getElementById('details-' + bookingId);
            const button = event.target;
            
            if (detailsDiv.classList.contains('active')) {
                detailsDiv.classList.remove('active');
                button.innerHTML = '<i class="fas fa-chevron-down"></i> Details';
            } else {
                detailsDiv.classList.add('active');
                button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide';
            }
        }

        // Image preview functionality
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '150px';
                    img.style.borderRadius = '5px';
                    img.style.border = '1px solid #ddd';
                    preview.appendChild(img);
                }

                reader.readAsDataURL(input.files[0]);
                
                // Update file input label
                const label = input.nextElementSibling;
                label.innerHTML = '<i class="fas fa-check"></i> ' + input.files[0].name;
                label.style.borderColor = '#28a745';
                label.style.background = '#d4edda';
            }
        }

        // Set active tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'bookings';
            
            // Activate the correct tab
            const tabButton = document.querySelector(`.main-tab[onclick*="${tab}"]`);
            if (tabButton) {
                switchMainTab(tab);
            }
            
            // File input label update for existing files after form submission
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                if (input.files.length > 0) {
                    const label = input.nextElementSibling;
                    label.innerHTML = '<i class="fas fa-check"></i> ' + input.files[0].name;
                    label.style.borderColor = '#28a745';
                    label.style.background = '#d4edda';
                }
            } );
        });
    </script>
</body>
</html>