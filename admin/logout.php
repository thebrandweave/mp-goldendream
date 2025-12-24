<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear the JWT token cookie
if (isset($_COOKIE['admin_token'])) {
    setcookie('admin_token', '', time() - 3600, '/', '', true, true);
}

// Clear remember me cookie if it exists
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
}

// Redirect to login page
header("Location: login.php");
exit();
