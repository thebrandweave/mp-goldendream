<?php
session_start();

// Log the logout activity if promoter is logged in
if (isset($_SESSION['promoter_id'])) {
    // Database connection
    require_once("../config/config.php");
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        // Log the activity
        $action = "Logged out";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Promoter', ?, ?)");
        $stmt->execute([$_SESSION['promoter_id'], $action, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silently fail if logging fails
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?> 