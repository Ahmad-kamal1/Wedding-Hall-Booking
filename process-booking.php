<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form data
    $full_name = $_POST['full_name'];
    $contact_number = $_POST['contact_number'];
    $cnic = $_POST['cnic'];
    $email = $_POST['email'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $package_category = $_POST['package_category'];
    $guest_count = $_POST['guest_count'];
    $payment_method = $_POST['payment_method'];
    $venue_id = $_POST['venue_id'];
    $special_requirements = $_POST['special_requirements'];
    $referral_source = $_POST['referral_source'];
    
    // Get user_id from session if logged in
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Default status is 'pending'
    $status = 'pending';
    
    try {
        $query = "INSERT INTO bookings (user_id, venue_id, full_name, contact_number, cnic, email, event_date, event_time, package_category, guest_count, payment_method, special_requirements, referral_source, status) 
                  VALUES (:user_id, :venue_id, :full_name, :contact_number, :cnic, :email, :event_date, :event_time, :package_category, :guest_count, :payment_method, :special_requirements, :referral_source, :status)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':venue_id', $venue_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':contact_number', $contact_number);
        $stmt->bindParam(':cnic', $cnic);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':event_date', $event_date);
        $stmt->bindParam(':event_time', $event_time);
        $stmt->bindParam(':package_category', $package_category);
        $stmt->bindParam(':guest_count', $guest_count);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':special_requirements', $special_requirements);
        $stmt->bindParam(':referral_source', $referral_source);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            $_SESSION['booking_success'] = true;
        } else {
            $_SESSION['booking_error'] = true;
        }
    } catch(PDOException $exception) {
        $_SESSION['booking_error'] = true;
        error_log("Booking error: " . $exception->getMessage());
    }
    
    header('Location: index.php');
    exit();
} else {
    header('Location: index.php');
}
?>