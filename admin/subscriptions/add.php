<?php
session_start();


$menuPath = "../";
$currentPage = "subscriptions";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';
$customerData = null;

// Fetch active schemes for dropdown
$schemesQuery = "SELECT SchemeID, SchemeName, MonthlyPayment, TotalPayments FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName";
$schemes = $conn->query($schemesQuery)->fetchAll(PDO::FETCH_ASSOC);

// Fetch active promoters for dropdown
$promotersQuery = "SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active' ORDER BY Name";
$promoters = $conn->query($promotersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Handle customer search
if (isset($_GET['search_customer'])) {
    $searchTerm = trim($_GET['search_customer']);
    if (!empty($searchTerm)) {
        $searchStmt = $conn->prepare("
            SELECT c.*, p.Name as PromoterName, p.PromoterUniqueID 
            FROM Customers c 
            LEFT JOIN Promoters p ON c.PromoterID = p.PromoterID 
            WHERE c.CustomerUniqueID = ? OR c.Contact = ? OR c.Email = ?
        ");
        $searchStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $customerData = $searchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$customerData) {
            $error = "No customer found with the provided details.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Validate and sanitize input
        $customerName = trim($_POST['customer_name']);
        $contact = trim($_POST['contact']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $schemeId = (int)$_POST['scheme_id'];
        $promoterId = (int)$_POST['promoter_id'];
        $startDate = $_POST['start_date'];
        $paymentMethod = trim($_POST['payment_method']);
        $paymentStatus = trim($_POST['payment_status']);
        $amountPaid = (float)$_POST['amount_paid'];
        $existingCustomerId = isset($_POST['existing_customer_id']) ? (int)$_POST['existing_customer_id'] : null;

        // Basic validation
        if (empty($customerName) || empty($contact) || empty($schemeId) || empty($promoterId) || empty($startDate)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Validate contact number
        if (!preg_match('/^[0-9]{10}$/', $contact)) {
            throw new Exception("Contact number must be 10 digits.");
        }

        // Get scheme details
        $schemeStmt = $conn->prepare("SELECT MonthlyPayment, TotalPayments FROM Schemes WHERE SchemeID = ?");
        $schemeStmt->execute([$schemeId]);
        $scheme = $schemeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$scheme) {
            throw new Exception("Invalid scheme selected.");
        }

        // Handle customer creation/update
        if ($existingCustomerId) {
            // Update existing customer
            $customerStmt = $conn->prepare("
                UPDATE Customers 
                SET Name = ?, Contact = ?, Email = ?, Address = ?, PromoterID = ?, UpdatedAt = NOW()
                WHERE CustomerID = ?
            ");
            $customerStmt->execute([$customerName, $contact, $email, $address, $promoterId, $existingCustomerId]);
            $customerId = $existingCustomerId;
        } else {
            // Generate unique customer ID
            $customerUniqueID = 'CUST' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // Insert new customer
            $customerStmt = $conn->prepare("
                INSERT INTO Customers (
                    CustomerUniqueID, Name, Contact, Email, Address, PromoterID, 
                    Status, CreatedAt, UpdatedAt
                ) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW(), NOW())
            ");
            $customerStmt->execute([$customerUniqueID, $customerName, $contact, $email, $address, $promoterId]);
            $customerId = $conn->lastInsertId();
        }

        // Insert subscription
        $subscriptionStmt = $conn->prepare("
            INSERT INTO Subscriptions (
                CustomerID, SchemeID, StartDate, EndDate, RenewalStatus, CreatedAt
            ) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL ? MONTH), 'Active', NOW())
        ");
        $subscriptionStmt->execute([
            $customerId,
            $schemeId,
            $startDate,
            $startDate,
            $scheme['TotalPayments']
        ]);
        $subscriptionId = $conn->lastInsertId();

        // Insert first installment
        $installmentStmt = $conn->prepare("
            INSERT INTO Installments (
                SubscriptionID, SchemeID, InstallmentNumber, Amount, DueDate, Status, CreatedAt
            ) VALUES (?, ?, 1, ?, ?, ?, NOW())
        ");
        $installmentStmt->execute([
            $subscriptionId,
            $schemeId,
            $scheme['MonthlyPayment'],
            $startDate,
            $paymentStatus
        ]);

        // Insert payment record
        $paymentStmt = $conn->prepare("
            INSERT INTO Payments (
                CustomerID, PromoterID, AdminID, SchemeID, InstallmentID,
                Amount, PaymentCodeValue, ScreenshotURL, Status, SubmittedAt, VerifiedAt
            ) VALUES (?, ?, ?, ?, ?, ?, 0, '', ?, NOW(), ?)
        ");
        $paymentStmt->execute([
            $customerId,
            $promoterId,
            $_SESSION['admin_id'],
            $schemeId,
            $subscriptionId,
            $amountPaid,
            $paymentStatus,
            $paymentStatus === 'Verified' ? NOW() : null
        ]);

        // Log activity
        $action = "Added new subscription for customer: $customerName";
        $logStmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $logStmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        $conn->commit();
        $success = "Subscription added successfully!";

        // Clear form data
        $_POST = array();
        $customerData = null;
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Subscription</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #3a7bd5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(58, 123, 213, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid #2ecc71;
            color: #2d6a4f;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
            color: #ae1e2f;
        }

        .required::after {
            content: " *";
            color: #e74c3c;
        }

        .customer-search {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .customer-search input {
            width: 70%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }

        .customer-search button {
            padding: 10px 15px;
            background: #3a7bd5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .customer-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .customer-details.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Subscription</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="customer-search">
                <form method="GET" action="">
                    <input type="text" name="search_customer" placeholder="Search by Customer ID, Contact, or Email"
                        value="<?php echo isset($_GET['search_customer']) ? htmlspecialchars($_GET['search_customer']) : ''; ?>">
                    <button type="submit">Search Customer</button>
                </form>
            </div>

            <?php if ($customerData): ?>
                <div class="customer-details active">
                    <h3>Customer Details</h3>
                    <p><strong>Customer ID:</strong> <?php echo htmlspecialchars($customerData['CustomerUniqueID']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($customerData['Name']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($customerData['Contact']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($customerData['Email']); ?></p>
                    <p><strong>Promoter:</strong> <?php echo htmlspecialchars($customerData['PromoterName'] . ' (' . $customerData['PromoterUniqueID'] . ')'); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php if ($customerData): ?>
                    <input type="hidden" name="existing_customer_id" value="<?php echo $customerData['CustomerID']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="required">Customer Name</label>
                    <input type="text" name="customer_name" class="form-control" required
                        value="<?php echo isset($customerData) ? htmlspecialchars($customerData['Name']) : (isset($_POST['customer_name']) ? htmlspecialchars($_POST['customer_name']) : ''); ?>">
                </div>

                <div class="form-group">
                    <label class="required">Contact Number</label>
                    <input type="text" name="contact" class="form-control" required
                        pattern="[0-9]{10}" title="10 digit mobile number"
                        value="<?php echo isset($customerData) ? htmlspecialchars($customerData['Contact']) : (isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''); ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control"
                        value="<?php echo isset($customerData) ? htmlspecialchars($customerData['Email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php
                                                                            echo isset($customerData) ? htmlspecialchars($customerData['Address']) : (isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '');
                                                                            ?></textarea>
                </div>

                <div class="form-group">
                    <label class="required">Scheme</label>
                    <select name="scheme_id" class="form-control" required>
                        <option value="">Select Scheme</option>
                        <?php foreach ($schemes as $scheme): ?>
                            <option value="<?php echo $scheme['SchemeID']; ?>"
                                <?php echo (isset($_POST['scheme_id']) && $_POST['scheme_id'] == $scheme['SchemeID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                (₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?>/month)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Promoter</label>
                    <select name="promoter_id" class="form-control" required>
                        <option value="">Select Promoter</option>
                        <?php foreach ($promoters as $promoter): ?>
                            <option value="<?php echo $promoter['PromoterID']; ?>"
                                <?php echo (isset($customerData) && $customerData['PromoterID'] == $promoter['PromoterID']) ? 'selected' : (isset($_POST['promoter_id']) && $_POST['promoter_id'] == $promoter['PromoterID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($promoter['Name']); ?>
                                (<?php echo $promoter['PromoterUniqueID']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Start Date</label>
                    <input type="date" name="start_date" class="form-control" required
                        value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label class="required">Payment Method</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">Select Payment Method</option>
                        <option value="Cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="UPI" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'UPI') ? 'selected' : ''; ?>>UPI</option>
                        <option value="Bank Transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Payment Status</label>
                    <select name="payment_status" class="form-control" required>
                        <option value="">Select Payment Status</option>
                        <option value="Verified" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'Verified') ? 'selected' : ''; ?>>Verified</option>
                        <option value="Pending" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Amount Paid</label>
                    <input type="number" name="amount_paid" class="form-control" required step="0.01" min="0"
                        value="<?php echo isset($_POST['amount_paid']) ? htmlspecialchars($_POST['amount_paid']) : ''; ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add any necessary JavaScript here
    </script>
</body>

</html>