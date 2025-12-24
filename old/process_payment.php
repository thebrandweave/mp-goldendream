<?php
session_start();
require_once 'login/helpers/JWT.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Database connection
class Database
{
    private $host = "localhost";
    private $db_name = "goldendream";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return false;
        }
        return $this->conn;
    }
}

try {
    // Validate required fields
    if (!isset($_POST['amount']) || !isset($_POST['payment_code']) || !isset($_FILES['screenshot'])) {
        throw new Exception('All fields are required');
    }

    $amount = floatval($_POST['amount']);
    $payment_code = intval($_POST['payment_code']);
    $screenshot = $_FILES['screenshot'];

    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }

    // Validate payment code
    if ($payment_code <= 0) {
        throw new Exception('Invalid payment code');
    }

    // Validate screenshot
    if ($screenshot['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error uploading screenshot');
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($screenshot['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed');
    }

    // Validate file size (max 5MB)
    if ($screenshot['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 5MB');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/payments/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($screenshot['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . $_SESSION['customer_id'] . '.' . $file_extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($screenshot['tmp_name'], $filepath)) {
        throw new Exception('Error saving screenshot');
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Start transaction
    $db->beginTransaction();

    // Insert payment record
    $query = "INSERT INTO Payments (CustomerID, Amount, PaymentCodeValue, ScreenshotURL, Status) 
              VALUES (:customer_id, :amount, :payment_code, :screenshot_url, 'Pending')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':payment_code', $payment_code);
    $stmt->bindParam(':screenshot_url', $filepath);

    if (!$stmt->execute()) {
        throw new Exception('Error saving payment record');
    }

    // Update customer's payment codes
    $update_query = "UPDATE Customers 
                    SET PaymentCodes = PaymentCodes - :payment_code 
                    WHERE CustomerID = :customer_id";

    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':payment_code', $payment_code);
    $update_stmt->bindParam(':customer_id', $_SESSION['customer_id']);

    if (!$update_stmt->execute()) {
        throw new Exception('Error updating payment codes');
    }

    // Commit transaction
    $db->commit();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully',
        'screenshot_url' => $filepath
    ]);
} catch (Exception $e) {
    // Rollback transaction if started
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Delete uploaded file if exists
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
