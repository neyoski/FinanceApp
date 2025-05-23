<?php
session_start();

// Log the logout activity if user was logged in
if (isset($_SESSION['user_id'])) {
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Log the logout activity
    logActivity($conn, $_SESSION['user_id'], 'logout', 'User logged out successfully');
}

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with a success message
header("Location: login.php?message=logged_out");
exit;