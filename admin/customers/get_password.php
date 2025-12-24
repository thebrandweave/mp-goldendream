<?php
session_start();
require_once("../../config/config.php");

// Check if user is logged in and is SuperAdmin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'SuperAdmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No customer ID provided']);
    exit();
}

$customerId = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get customer password
    $stmt = $conn->prepare("SELECT PasswordHash, Name FROM Customers WHERE CustomerID = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Log this action
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Viewed password for customer: " . $customer['Name'],
            $_SERVER['REMOTE_ADDR']
        ]);

        // Check if it's the default password
        $defaultPassword = "123456"; // Default password from schema
        $isDefaultPassword = password_verify($defaultPassword, $customer['PasswordHash']);

        if ($isDefaultPassword) {
            echo json_encode([
                'success' => true,
                'password' => $defaultPassword,
                'message' => 'Default password is being used'
            ]);
        } else {
            // If not default password, show a message that password has been changed
            echo json_encode([
                'success' => true,
                'password' => 'Password has been changed by user',
                'message' => 'This customer has changed their default password'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
