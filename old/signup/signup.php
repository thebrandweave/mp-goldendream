<?php
session_start();
require_once '../login/helpers/JWT.php';

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
      echo "Connection Error: " . $e->getMessage();
    }
    return $this->conn;
  }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
  header('Content-Type: application/json');

  $data = json_decode(file_get_contents('php://input'), true);

  if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
  }

  // Validate required fields
  $required_fields = ['fullName', 'phone', 'password', 'confirmPassword'];
  foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
      echo json_encode(['success' => false, 'message' => 'All fields are required']);
      exit;
    }
  }

  // Validate phone format
  if (!preg_match('/^\d{10}$/', $data['phone'])) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number']);
    exit;
  }

  // Validate password match
  if ($data['password'] !== $data['confirmPassword']) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
  }

  // Validate password strength
  $password = $data['password'];
  if (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
  ) {
    echo json_encode(['success' => false, 'message' => 'Password does not meet requirements']);
    exit;
  }

  try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if phone already exists
    $check_query = "SELECT CustomerID FROM Customers WHERE Contact = :phone";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':phone', $data['phone']);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
      echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
      exit;
    }

    // Generate a unique customer ID
    $customer_unique_id = 'CUST' . date('YmdHis') . rand(1000, 9999);

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new customer
    $query = "INSERT INTO Customers (CustomerUniqueID, Name, Contact, PasswordHash, Status) 
              VALUES (:unique_id, :name, :contact, :password, 'Active')";

    $stmt = $db->prepare($query);

    // Bind parameters
    $stmt->bindParam(':unique_id', $customer_unique_id);
    $stmt->bindParam(':name', $data['fullName']);
    $stmt->bindParam(':contact', $data['phone']);
    $stmt->bindParam(':password', $hashed_password);

    // Execute query
    if ($stmt->execute()) {
      // Get the new customer ID
      $customer_id = $db->lastInsertId();

      // Set session variables
      $_SESSION['customer_id'] = $customer_id;
      $_SESSION['customer_unique_id'] = $customer_unique_id;
      $_SESSION['customer_name'] = $data['fullName'];
      $_SESSION['customer_phone'] = $data['phone'];

      // Generate JWT token for auto-login
      $tokenData = [
        'customer_id' => $customer_id,
        'customer_unique_id' => $customer_unique_id,
        'name' => $data['fullName'],
        'phone' => $data['phone']
      ];
      $token = JWT::generateToken($tokenData);

      // Set cookie with token (30 days)
      setcookie('auth_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);

      echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'redirect' => '../dashboard.php'
      ]);
    } else {
      echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
  } catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during registration']);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Golden Dream - Sign Up</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="./signup.css">
</head>

<body>
  <div class="container">
    <div class="login-box signup-box">
      <div class="logo">
        <i class="fas fa-crown"></i>
        <h2>Golden Dream</h2>
      </div>
      <form id="signupForm" class="login-form">
        <div class="input-group">
          <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" id="fullName" placeholder="Full Name" required />
          </div>
          <div class="input-field">
            <i class="fas fa-phone"></i>
            <input type="tel" id="phone" placeholder="Phone Number (10 digits)" maxlength="10" pattern="\d{10}" required />
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" placeholder="Password" required />
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirmPassword" placeholder="Confirm Password" required />
          </div>
        </div>
        <div class="options">
          <label class="remember-me">
            <input type="checkbox" id="terms" required />
            <span>I agree to the <a href="#" class="terms-link">Terms & Conditions</a></span>
          </label>
        </div>
        <button type="submit" class="login-btn">Create Account</button>
        <div class="register-link">
          <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
      </form>
    </div>
  </div>
  <script src="./signup.js"></script>
</body>

</html>