<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

function createJWT($payload)
{
    $key = "your_secret_key"; // Change this to a secure secret key
    $payload['iat'] = time();
    $payload['exp'] = time() + (86400 * 30); // 30 days expiration

    return JWT::encode($payload, $key, 'HS256');
}

function verifyJWT($token)
{
    try {
        $key = "your_secret_key"; // Same secret key as above
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}
