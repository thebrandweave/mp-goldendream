<?php
require_once '../config/session_check.php';

// Check if user is logged in before logging out
if (isLoggedIn()) {
    // Clear the JWT cookie
    setcookie('jwt_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Strict'
    ]);

    // Destroy the session
    session_destroy();
}

// Redirect to login page
header('Location: ../login');
exit;
