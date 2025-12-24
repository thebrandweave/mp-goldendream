<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTManager
{
    private static $secretKey = 'your-secret-key-here'; // Change this to a secure key
    private static $algorithm = 'HS256';
    private static $tokenExpiry = 3600; // 1 hour

    public static function generateToken($adminId, $email, $role)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + self::$tokenExpiry;

        $payload = array(
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'admin_id' => $adminId,
            'email' => $email,
            'role' => $role
        );

        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }

    public static function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function refreshToken($token)
    {
        $decoded = self::verifyToken($token);
        if ($decoded) {
            return self::generateToken($decoded->admin_id, $decoded->email, $decoded->role);
        }
        return false;
    }
}
