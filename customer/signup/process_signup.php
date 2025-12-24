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
    $fullName = trim($_POST['fullName']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $password = $_POST['password'];

    // Validate input
    if (empty($fullName) || empty($phoneNumber) || empty($password)) {
        throw new Exception('All fields are required');
    }

    // Validate phone number format
    if (!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
        throw new Exception('Invalid phone number format');
    }

    // Check if phone number already exists
    $stmt = $db->prepare("SELECT CustomerID FROM Customers WHERE Contact = ?");
    $stmt->execute([$phoneNumber]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Phone number already registered');
    }

    // Generate unique customer ID
    $customerUniqueID = 'CUST' . date('Ymd') . rand(1000, 9999);

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new customer
    $stmt = $db->prepare("
        INSERT INTO Customers (CustomerUniqueID, Name, Contact, PasswordHash, Status)
        VALUES (?, ?, ?, ?, 'Active')
    ");

    $stmt->execute([$customerUniqueID, $fullName, $phoneNumber, $passwordHash]);
    $customerId = $db->lastInsertId();

    // Generate JWT Token
    $payload = array(
        "user_id" => $customerId,
        "unique_id" => $customerUniqueID,
        "name" => $fullName,
        "user_type" => "Customer"
    );

    $jwt = generateJWTToken($payload);
    $expire = time() + JWT_EXPIRE_TIME;

    // Configure session to last 30 days
    ini_set('session.gc_maxlifetime', JWT_EXPIRE_TIME);
    session_set_cookie_params(JWT_EXPIRE_TIME);

    // Start session and set customer data
    session_start();
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_unique_id'] = $customerUniqueID;
    $_SESSION['customer_name'] = $fullName;
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['jwt_token'] = $jwt;
    $_SESSION['last_activity'] = time();

    // Set JWT token in HTTP-only cookie
    setJWTCookie($jwt, $expire);

    // Return success response with token
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'token' => $jwt,
        'user' => [
            'id' => $customerId,
            'unique_id' => $customerUniqueID,
            'name' => $fullName
        ]
    ]);

    // Redirect to dashboard
    header('Location: ../dashboard');
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
