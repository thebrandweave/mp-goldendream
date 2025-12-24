<?php
require_once __DIR__ . '/jwt_config.php';

function checkSession()
{
    session_start();

    // Check if user is logged in
    if (!isset($_SESSION['jwt_token'])) {
        header('Location: ../login');
        exit;
    }

    // Validate JWT token
    $token = $_SESSION['jwt_token'];
    $decoded = validateJWTToken($token);

    if (!$decoded) {
        // Token is invalid or expired
        session_destroy();
        header('Location: ../login');
        exit;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    // Return user data
    return [
        'customer_id' => $_SESSION['customer_id'],
        'customer_name' => $_SESSION['customer_name'],
        'customer_unique_id' => $_SESSION['customer_unique_id'],
        'token' => $token,
        'decoded' => $decoded
    ];
}

// Function to check if user is logged in without redirecting
function isLoggedIn()
{
    session_start();

    if (!isset($_SESSION['jwt_token'])) {
        return false;
    }

    $token = $_SESSION['jwt_token'];
    $decoded = validateJWTToken($token);

    return $decoded !== false;
}

// Function to get user data without validation
function getUserData()
{
    if (!isset($_SESSION['customer_id'])) {
        return null;
    }

    return [
        'customer_id' => $_SESSION['customer_id'],
        'customer_name' => $_SESSION['customer_name'],
        'customer_unique_id' => $_SESSION['customer_unique_id']
    ];
}
