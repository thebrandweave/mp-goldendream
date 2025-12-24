<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// JWT Configuration
define('JWT_SECRET_KEY', 'your_jwt_secret_key_here'); // Change this to a secure random string in production
define('JWT_EXPIRE_TIME', 60 * 60 * 24 * 30); // 30 days in seconds
define('JWT_ALGORITHM', 'HS256');

// JWT Token Validation Function
function validateJWTToken($token)
{
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, JWT_ALGORITHM));
        return $decoded;
    } catch (Exception $e) {
        return false;
    }
}

// JWT Token Generation Function
function generateJWTToken($payload)
{
    $issuedAt = time();
    $expire = $issuedAt + JWT_EXPIRE_TIME;

    $tokenPayload = array_merge($payload, [
        "iat" => $issuedAt,
        "exp" => $expire
    ]);

    return JWT::encode($tokenPayload, JWT_SECRET_KEY, JWT_ALGORITHM);
}

// Function to set JWT cookie
function setJWTCookie($token, $expiry)
{
    $options = [
        'expires' => $expiry,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ];

    setcookie('jwt_token', $token, $options);
}
