<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "Customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Get promoter's unique ID
try {
    $stmt = $conn->prepare("SELECT PromoterUniqueID FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
    $promoterUniqueID = $promoter['PromoterUniqueID'];
} catch (PDOException $e) {
    $message = "Error fetching promoter data";
    $messageType = "error";
    $showNotification = true;
}

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$customerId = $_GET['id'];

// Get customer details
try {
    $stmt = $conn->prepare("
        SELECT * FROM Customers 
        WHERE CustomerID = ? AND PromoterID = ?
    ");
    $stmt->execute([$customerId, $promoterUniqueID]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $message = "Error fetching customer data";
    $messageType = "error";
    $showNotification = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $email = $_POST['email'];
        $gender = $_POST['gender'];
        $dateOfBirth = $_POST['date_of_birth'];
        $status = $_POST['status'];
        
        // Validate required fields
        if (empty($name) || empty($contact)) {
            throw new Exception("Name and Contact are required fields.");
        }
        
        // Check if contact number is already registered (excluding current customer)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM Customers 
            WHERE Contact = ? AND CustomerID != ? AND PromoterID = ?
        ");
        $stmt->execute([$contact, $customerId, $promoterUniqueID]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Contact number is already registered.");
        }
        
        // Check if email is already registered (excluding current customer)
        if (!empty($email)) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM Customers 
                WHERE Email = ? AND CustomerID != ? AND PromoterID = ?
            ");
            $stmt->execute([$email, $customerId, $promoterUniqueID]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Email address is already registered.");
            }
        }
        
        // Update customer details
        $stmt = $conn->prepare("
            UPDATE Customers 
            SET Name = ?, Contact = ?, Email = ?, Gender = ?, DateOfBirth = ?, Status = ?
            WHERE CustomerID = ? AND PromoterID = ?
        ");
        $stmt->execute([
            $name, $contact, $email, $gender, $dateOfBirth, $status,
            $customerId, $promoterUniqueID
        ]);
        
        $message = "Customer details updated successfully.";
        $messageType = "success";
        $showNotification = true;
        
        // Refresh customer data
        $stmt = $conn->prepare("
            SELECT * FROM Customers 
            WHERE CustomerID = ? AND PromoterID = ?
        ");
        $stmt->execute([$customerId, $promoterUniqueID]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        $showNotification = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .main-content {
                padding: 20px;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .section-header > div {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .form-actions button,
            .form-actions a {
                width: 100%;
                justify-content: center;
                padding: 10px 15px;
                font-size: 13px;
            }

            .form-section {
                padding: 15px;
            }

            .form-section-title {
                font-size: 16px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-control {
                padding: 10px 12px;
                font-size: 13px;
            }

            .form-label {
                font-size: 13px;
                margin-bottom: 5px;
            }

            .form-text {
                font-size: 11px;
            }

            .section-icon {
                width: 40px;
                height: 40px;
            }

            .section-icon i {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .form-section {
                padding: 12px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-control {
                padding: 8px 10px;
                font-size: 12px;
            }

            .form-label {
                font-size: 12px;
            }

            .form-text {
                font-size: 10px;
            }

            .btn-primary, .btn-secondary {
                padding: 8px 12px;
                font-size: 12px;
            }

            .section-icon {
                width: 35px;
                height: 35px;
            }

            .section-icon i {
                font-size: 18px;
            }

            .section-info h2 {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="section-info">
                        <h2>Edit Customer</h2>
                        <p>Update customer information</p>
                    </div>
                </div>
                <a href="view.php?id=<?php echo $customerId; ?>" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to View
                </a>
            </div>

            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($customer['Name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact Number *</label>
                        <input type="tel" id="contact" name="contact" class="form-control" 
                               value="<?php echo htmlspecialchars($customer['Contact']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($customer['Email']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $customer['Gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $customer['Gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $customer['Gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                               value="<?php echo $customer['DateOfBirth'] ? date('Y-m-d', strtotime($customer['DateOfBirth'])) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Active" <?php echo $customer['Status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $customer['Status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Suspended" <?php echo $customer['Status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <a href="view.php?id=<?php echo $customerId; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 
