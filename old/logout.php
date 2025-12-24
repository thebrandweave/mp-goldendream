<?php
session_start();

// Function to clear all cookies
function clearAllCookies()
{
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach ($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time() - 3600, '/', '', true, true);
        }
    }
}

// Function to clear session data
function clearSessionData()
{
    // Clear all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/', '', true, true);
    }

    // Destroy the session
    session_destroy();
}

// Function to clear client-side storage
function clearClientStorage()
{
    echo '<script>
        // Clear localStorage
        localStorage.clear();
        
        // Clear sessionStorage
        sessionStorage.clear();
        
        // Clear specific items if needed
        localStorage.removeItem("rememberedEmail");
        
        // Redirect to login page
        window.location.href = "login/login.php";
    </script>';
}

try {
    // Clear all cookies
    clearAllCookies();

    // Clear session data
    clearSessionData();

    // Clear client-side storage and redirect
    clearClientStorage();

    // Fallback redirect (if JavaScript is disabled)
    header('Location: login/login.php');
    exit;
} catch (Exception $e) {
    // Log the error
    error_log("Logout Error: " . $e->getMessage());

    // Still try to redirect even if there's an error
    header('Location: login/login.php');
    exit;
}
