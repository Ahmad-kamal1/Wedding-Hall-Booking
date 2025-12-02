<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    // Here you would typically:
    // 1. Save to database (create a contacts table)
    // 2. Send email notification
    
    // For now, we'll just simulate success
    $_SESSION['contact_success'] = true;
    header('Location: index.php?section=contact');
    exit;
} else {
    header('Location: index.php?section=contact');
    exit;
}
?>