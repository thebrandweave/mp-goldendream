<?php
require_once '../config/config.php';
require_once '../config/jwt_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get form data
    $customerId = trim($_POST['customerId']);
    $password = $_POST['password'];
    $rememberMe = isset($_POST['rememberMe']);

    // Validate input
    if (empty($customerId) || empty($password)) {
        throw new Exception('All fields are required');
    }

    // Validate customer ID format (assuming it's alphanumeric)
    if (!preg_match('/^[A-Za-z0-9]+$/', $customerId)) {
        throw new Exception('Invalid customer ID format');
    }

    // Get customer data
    $stmt = $db->prepare("SELECT CustomerID, CustomerUniqueID, Name, PasswordHash, Status FROM Customers WHERE CustomerUniqueID = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Invalid customer ID or password');
    }

    // Check if account is active
    if ($customer['Status'] !== 'Active') {
        throw new Exception('Your account is not active. Please contact support.');
    }

    // Verify password
    if (!password_verify($password, $customer['PasswordHash'])) {
        throw new Exception('Invalid customer ID or password');
    }

    // Generate JWT Token
    $payload = array(
        "user_id" => $customer['CustomerID'],
        "unique_id" => $customer['CustomerUniqueID'],
        "name" => $customer['Name'],
        "user_type" => "Customer"
    );

    $jwt = generateJWTToken($payload);

    // Set session expiration based on remember me
    $expire = $rememberMe ? time() + (60 * 60 * 24 * 30) : time() + (60 * 60 * 24); // 30 days or 1 day

    // Start session and set customer data
    session_start();
    $_SESSION['customer_id'] = $customer['CustomerID'];
    $_SESSION['customer_unique_id'] = $customer['CustomerUniqueID'];
    $_SESSION['customer_name'] = $customer['Name'];
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['jwt_token'] = $jwt;
    $_SESSION['last_activity'] = time();

    // Set session cookie parameters
    session_set_cookie_params($rememberMe ? 60 * 60 * 24 * 30 : 60 * 60 * 24);

    // Set JWT token in HTTP-only cookie
    setcookie('jwt_token', $jwt, [
        'expires' => $expire,
        'path' => '/',
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Strict'
    ]);

    // Redirect to dashboard
    header('Location: ../dashboard');
    exit;
} catch (Exception $e) {
    // Redirect back to login with error message
    header('Location: login.php?error=' . urlencode($e->getMessage()));
    exit;
}
