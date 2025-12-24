<?php
session_start();
require_once("../../../config/config.php");

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();

    $loginType = $_POST['loginType'] ?? 'email';
    $password = $_POST['password'] ?? '';

    // Build the query based on login type
    $query = "SELECT CustomerID, Name, PasswordHash, Status FROM Customers WHERE ";

    switch ($loginType) {
        case 'email':
            $email = $_POST['email'] ?? '';
            $query .= "Email = :identifier";
            $identifier = $email;
            break;

        case 'phone':
            $phone = $_POST['phone'] ?? '';
            $query .= "Contact = :identifier";
            $identifier = $phone;
            break;

        case 'customerid':
            $customerId = $_POST['customerId'] ?? '';
            $query .= "CustomerUniqueID = :identifier";
            $identifier = $customerId;
            break;

        default:
            throw new Exception("Invalid login type");
    }

    $stmt = $conn->prepare($query);
    $stmt->bindParam(":identifier", $identifier);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid credentials"
        ]);
        exit();
    }

    if ($customer['Status'] !== 'Active') {
        echo json_encode([
            "status" => "error",
            "message" => "Your account is not active. Please contact support."
        ]);
        exit();
    }

    if (password_verify($password, $customer['PasswordHash'])) {
        $_SESSION['customer_id'] = $customer['CustomerID'];
        $_SESSION['customer_name'] = $customer['Name'];

        echo json_encode([
            "status" => "success",
            "message" => "Login successful"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid credentials"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred. Please try again later."
    ]);
}
