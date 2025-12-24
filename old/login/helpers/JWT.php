<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static $secret_key = 'goldendream_secret_key_2024';
    private static $algorithm = 'HS256';

    public static function generateToken($data)
    {
        $issued_at = time();
        $expiration = $issued_at + (30 * 24 * 60 * 60); // 30 days

        $payload = [
            'iat' => $issued_at,
            'exp' => $expiration,
            'iss' => 'goldendream',
            'aud' => 'goldendream_users',
            'data' => $data
        ];

        return FirebaseJWT::encode($payload, self::$secret_key, self::$algorithm);
    }

    public static function verifyToken($token)
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::$secret_key, self::$algorithm));
            // Convert stdClass object to array
            return json_decode(json_encode($decoded), true);
        } catch (Exception $e) {
            return false;
        }
    }
}
