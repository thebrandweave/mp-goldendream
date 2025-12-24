<?php
session_start();
// Check if user is logged in, redirect if not


$menuPath = "../";
$currentPage = "customers";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No customer ID provided.";
    header("Location: index.php");
    exit();
}

$customerId = $_GET['id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoters for dropdown
try {
    $stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active'");
    $stmt->execute();
    $promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving promoters: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Get customer details
try {
    $query = "SELECT c.*, p.Name as PromoterName, p.PromoterUniqueID as PromoterUniqueID 
              FROM Customers c 
              LEFT JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID 
              WHERE c.CustomerID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$customerId]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving customer details: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Validate and sanitize input
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
        $promoterId = $customer['PromoterID'];
        $status = $_POST['status'];

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required";
        }

        if (empty($contact)) {
            $errors[] = "Contact number is required";
        } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
            $errors[] = "Contact number should be 10 digits";
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode("<br>", $errors);
            $conn->rollBack();
        } else {
            // Update customer
            $query = "UPDATE Customers SET 
                      Name = ?, 
                      Contact = ?, 
                      Email = ?, 
                      Address = ?, 
                      PromoterID = ?, 
                      Status = ?, 
                      UpdatedAt = NOW() 
                      WHERE CustomerID = ?";

            $stmt = $conn->prepare($query);
            $stmt->execute([
                $name,
                $contact,
                $email,
                $address,
                $promoterId,
                $status,
                $customerId
            ]);

            // Log the activity
            $adminId = $_SESSION['admin_id'];
            $action = "Updated customer details for " . $name . " (ID: " . $customer['CustomerUniqueID'] . ")";
            $ipAddress = $_SERVER['REMOTE_ADDR'];

            $logQuery = "INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, ?, ?, ?)";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->execute([$adminId, 'Admin', $action, $ipAddress]);

            $conn->commit();
            $_SESSION['success_message'] = "Customer updated successfully.";
            header("Location: view.php?id=" . $customerId);
            exit();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error updating customer: " . $e->getMessage();
    }
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
    <title>Edit Customer - <?php echo htmlspecialchars($customer['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Edit Customer Page Styles */
        :root {
            --cs_primary: #3a7bd5;
            --cs_primary-hover: #2c60a9;
            --cs_secondary: #00d2ff;
            --cs_success: #2ecc71;
            --cs_success-hover: #27ae60;
            --cs_warning: #f39c12;
            --cs_warning-hover: #d35400;
            --cs_danger: #e74c3c;
            --cs_danger-hover: #c0392b;
            --cs_info: #3498db;
            --cs_info-hover: #2980b9;
            --cs_text-dark: #2c3e50;
            --cs_text-medium: #34495e;
            --cs_text-light: #7f8c8d;
            --cs_bg-light: #f8f9fa;
            --cs_border-color: #e0e0e0;
            --cs_shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --cs_shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --cs_transition: 0.25s;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--cs_text-medium);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color var(--cs_transition);
        }

        .back-link:hover {
            color: var(--cs_primary);
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--cs_text-dark);
            margin-bottom: 20px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--cs_shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .form-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--cs_border-color);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--cs_text-dark);
        }

        .form-card-body {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--cs_text-medium);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--cs_text-dark);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--cs_border-color);
            border-radius: 8px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--cs_primary);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--cs_text-light);
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--cs_transition);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--cs_primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--cs_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(58, 123, 213, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .customer-unique-id {
            font-size: 14px;
            background-color: var(--cs_bg-light);
            padding: 10px;
            border-radius: 6px;
            color: var(--cs_text-medium);
            display: inline-block;
            margin-bottom: 20px;
        }

        .required-indicator {
            color: var(--cs_danger);
            margin-left: 3px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--cs_danger);
            border-color: rgba(231, 76, 60, 0.2);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="view.php?id=<?php echo $customerId; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Customer Details
        </a>

        <h1 class="page-title">Edit Customer</h1>

        <div class="customer-unique-id">
            <i class="fas fa-id-card"></i> Customer ID: <?php echo $customer['CustomerUniqueID']; ?>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message'];
                                                            unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form action="edit.php?id=<?php echo $customerId; ?>" method="post">
            <div class="form-card">
                <div class="form-card-header">
                    <h3>Customer Information</h3>
                </div>
                <div class="form-card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name<span class="required-indicator">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($customer['Name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact" class="form-label">Contact Number<span class="required-indicator">*</span></label>
                            <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($customer['Contact']); ?>" required>
                            <small class="form-text">10-digit mobile number without country code</small>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['Email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status<span class="required-indicator">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="Active" <?php echo $customer['Status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $customer['Status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="promoter_id" class="form-label">Referred By Promoter</label>
                            <input type="text" class="form-control" id="promoter_id" name="promoter_id"
                                value="<?php echo !empty($customer['PromoterID']) ? htmlspecialchars($customer['PromoterID']) . ' - ' . htmlspecialchars($customer['PromoterName']) : 'None (Direct Customer)'; ?>"
                                disabled>
                            <input type="hidden" name="promoter_id" value="<?php echo htmlspecialchars($customer['PromoterID']); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['Address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <a href="view.php?id=<?php echo $customerId; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Phone number validation
            const contactInput = document.getElementById('contact');
            contactInput.addEventListener('input', function() {
                const phoneNumber = this.value.replace(/\D/g, '');
                this.value = phoneNumber;

                if (phoneNumber.length > 10) {
                    this.value = phoneNumber.substring(0, 10);
                }
            });
        });
    </script>
</body>

</html>