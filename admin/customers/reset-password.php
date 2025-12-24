<?php
session_start();
require_once("../../config/config.php");
$menuPath = "../";
$currentPage = "customers";
// Check if user is logged in and is SuperAdmin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'SuperAdmin') {
    $_SESSION['error_message'] = "Unauthorized access";
    header("Location: index.php");
    exit();
}

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No customer ID provided";
    header("Location: index.php");
    exit();
}

$customerId = $_GET['id'];

// Database connection
$database = new Database();
$conn = $database->getConnection();

// Get customer details
try {
    $stmt = $conn->prepare("SELECT CustomerID, Name, CustomerUniqueID FROM Customers WHERE CustomerID = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found";
        header("Location: index.php");
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error_message'] = "All fields are required";
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error_message'] = "Passwords do not match";
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['error_message'] = "Password must be at least 6 characters long";
        } else {
            try {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the password
                $stmt = $conn->prepare("UPDATE Customers SET PasswordHash = ? WHERE CustomerID = ?");
                $stmt->execute([$hashedPassword, $customerId]);

                // Log the action
                $stmt = $conn->prepare("
                    INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                    VALUES (?, 'Admin', ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['admin_id'],
                    "Reset password for customer: " . $customer['Name'],
                    $_SERVER['REMOTE_ADDR']
                ]);

                $_SESSION['success_message'] = "Password has been reset successfully";
                header("Location: view.php?id=" . $customerId);
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error resetting password: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving customer details: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($customer['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .reset-password-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
        }

        .reset-password-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }

        .reset-password-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .reset-password-header h2 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .customer-info p {
            margin: 5px 0;
            color: #34495e;
        }

        .customer-info strong {
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #ddd;
            color: #34495e;
        }

        .btn-outline:hover {
            border-color: #3498db;
            color: #3498db;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="reset-password-container">
            <a href="view.php?id=<?php echo $customerId; ?>" class="btn btn-outline" style="margin-bottom: 20px;">
                <i class="fas fa-arrow-left"></i> Back to Customer
            </a>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="reset-password-card">
                <div class="reset-password-header">
                    <h2><i class="fas fa-key"></i> Reset Password</h2>
                </div>

                <div class="customer-info">
                    <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($customer['Name']); ?></p>
                    <p><strong>Customer ID:</strong> <?php echo $customer['CustomerUniqueID']; ?></p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Reset Password
                        </button>
                        <a href="view.php?id=<?php echo $customerId; ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
        });
    </script>
</body>

</html>