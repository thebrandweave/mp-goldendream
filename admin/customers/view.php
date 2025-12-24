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

// Get customer details
try {
    $query = "SELECT c.*, 
              p.Name as PromoterName, 
              p.PromoterUniqueID,
              p.Contact as PromoterContact
              FROM Customers c 
              LEFT JOIN Promoters p ON c.PromoterID = p.PromoterID 
              WHERE c.CustomerID = ?";

    $stmt = $conn->prepare($query);
    $stmt->execute([$customerId]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: index.php");
        exit();
    }

    // Get subscriptions count
    $stmt = $conn->prepare("SELECT COUNT(*) as subscription_count FROM Subscriptions WHERE CustomerID = ?");
    $stmt->execute([$customerId]);
    $subscriptionCount = $stmt->fetch(PDO::FETCH_ASSOC)['subscription_count'];

    // Get recent subscriptions (5)
    $stmt = $conn->prepare("SELECT s.SubscriptionID, s.RenewalStatus as Status, s.StartDate, s.EndDate, 
                           sch.SchemeName, sch.MonthlyPayment as Amount
                           FROM Subscriptions s 
                           JOIN Schemes sch ON s.SchemeID = sch.SchemeID 
                           WHERE s.CustomerID = ? 
                           ORDER BY s.CreatedAt DESC LIMIT 5");
    $stmt->execute([$customerId]);
    $recentSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments (5)
    $stmt = $conn->prepare("SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, 
                            s.SchemeName 
                            FROM Payments p 
                            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID 
                            WHERE p.CustomerID = ? 
                            ORDER BY p.SubmittedAt DESC LIMIT 5");
    $stmt->execute([$customerId]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity logs (10)
    $stmt = $conn->prepare("SELECT LogID, Action, IPAddress, CreatedAt 
                           FROM ActivityLogs 
                           WHERE UserID = ? AND UserType = 'Customer' 
                           ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$customerId]);
    $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get withdrawal information (5)
    $stmt = $conn->prepare("SELECT WithdrawalID, Amount, Status, RequestedAt, ProcessedAt, Remarks
                           FROM Withdrawals
                           WHERE UserID = ? AND UserType = 'Customer' 
                           ORDER BY RequestedAt DESC LIMIT 5");
    $stmt->execute([$customerId]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get prizes/winnings (5)
    $stmt = $conn->prepare("SELECT WinnerID, PrizeType, WinningDate, Status, Remarks
                           FROM Winners
                           WHERE UserID = ? AND UserType = 'Customer' 
                           ORDER BY WinningDate DESC LIMIT 5");
    $stmt->execute([$customerId]);
    $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Customer - <?php echo htmlspecialchars($customer['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Customer View Page Styles */
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

        .customer-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .customer-avatar {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--cs_primary), var(--cs_secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: 600;
            box-shadow: var(--cs_shadow-md);
            border: 4px solid white;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--cs_text-dark);
            margin-bottom: 5px;
        }

        .customer-id {
            font-size: 14px;
            color: var(--cs_text-light);
            margin-bottom: 10px;
        }

        .customer-contact {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--cs_text-medium);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-item i {
            color: var(--cs_primary);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--cs_success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--cs_danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .status-suspended {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--cs_warning);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
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

        .btn-warning {
            background: var(--cs_warning);
            color: white;
        }

        .btn-warning:hover {
            background: var(--cs_warning-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.2);
        }

        .btn-danger {
            background: var(--cs_danger);
            color: white;
        }

        .btn-danger:hover {
            background: var(--cs_danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--cs_text-medium);
            border: 1px solid var(--cs_border-color);
        }

        .btn-outline:hover {
            border-color: var(--cs_primary);
            color: var(--cs_primary);
            background: rgba(58, 123, 213, 0.05);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .grid-item-8 {
            grid-column: span 8;
        }

        .grid-item-4 {
            grid-column: span 4;
        }

        .grid-item-6 {
            grid-column: span 6;
        }

        .grid-item-12 {
            grid-column: span 12;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--cs_shadow-sm);
            overflow: hidden;
            transition: transform var(--cs_transition), box-shadow var(--cs_transition);
            display: flex;
            flex-direction: column;
            height: auto;
            min-height: 0;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--cs_shadow-md);
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--cs_border-color);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--cs_text-dark);
        }

        .card-header-action {
            color: var(--cs_primary);
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-header-action:hover {
            text-decoration: underline;
        }

        .card-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 300px;
            min-height: 0;
        }

        /* Custom scrollbar for card bodies */
        .card-body::-webkit-scrollbar {
            width: 4px;
        }

        .card-body::-webkit-scrollbar-track {
            background: var(--cs_bg-light);
        }

        .card-body::-webkit-scrollbar-thumb {
            background: var(--cs_border-color);
            border-radius: 2px;
        }

        .card-body::-webkit-scrollbar-thumb:hover {
            background: var(--cs_text-light);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: var(--cs_text-light);
        }

        .info-value {
            font-size: 14px;
            color: var(--cs_text-dark);
            font-weight: 500;
        }

        .table-clean {
            width: 100%;
            border-collapse: collapse;
        }

        .table-clean th,
        .table-clean td {
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid var(--cs_border-color);
        }

        .table-clean th {
            font-weight: 600;
            color: var(--cs_text-medium);
            background-color: var(--cs_bg-light);
        }

        .table-clean tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--cs_success);
        }

        .badge-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--cs_warning);
        }

        .badge-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--cs_danger);
        }

        .badge-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--cs_info);
        }

        .subscription-count-badge {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--cs_shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .count-value {
            font-size: 36px;
            font-weight: 700;
        }

        .count-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--cs_border-color);
            font-size: 13px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            font-size: 11px;
            color: var(--cs_text-light);
            margin-top: 4px;
        }

        .address-info {
            background-color: var(--cs_bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            color: var(--cs_text-medium);
            line-height: 1.5;
        }

        .bank-info {
            background-color: var(--cs_bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .bank-info-item {
            margin-bottom: 10px;
        }

        .bank-info-label {
            font-size: 12px;
            color: var(--cs_text-light);
            margin-bottom: 3px;
        }

        .bank-info-value {
            font-size: 14px;
            color: var(--cs_text-dark);
            font-weight: 500;
        }

        .promoter-info-card {
            background-color: var(--cs_bg-light);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--cs_primary);
        }

        .promoter-title {
            font-size: 13px;
            color: var(--cs_text-light);
            margin-bottom: 5px;
        }

        .promoter-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--cs_text-dark);
            margin-bottom: 5px;
        }

        .promoter-id {
            font-size: 13px;
            color: var(--cs_text-medium);
            margin-bottom: 8px;
        }

        .promoter-contact {
            font-size: 13px;
            color: var(--cs_text-medium);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tabs-container {
            margin-top: 20px;
        }

        .tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--cs_border-color);
            margin-bottom: 15px;
        }

        .tab-item {
            padding: 10px 15px;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            color: var(--cs_text-medium);
        }

        .tab-item.active {
            border-bottom-color: var(--cs_primary);
            color: var(--cs_primary);
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--cs_text-light);
            font-size: 14px;
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .grid-container {
                grid-template-columns: 1fr;
            }

            .grid-item-8,
            .grid-item-4,
            .grid-item-6,
            .grid-item-12 {
                grid-column: span 1;
            }

            .customer-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .customer-contact {
                flex-direction: column;
                gap: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Customers List
        </a>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="background-color: rgba(46, 204, 113, 0.1); color: var(--cs_success); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(46, 204, 113, 0.2);">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                    unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="background-color: rgba(231, 76, 60, 0.1); color: var(--cs_danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(231, 76, 60, 0.2);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message'];
                                                            unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="customer-header">
            <div class="customer-avatar">
                <?php
                $initials = '';
                $nameParts = explode(' ', $customer['Name']);
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($customer['Name'], 0, 2));
                }
                echo $initials;
                ?>
            </div>
            <div class="customer-info">
                <div class="customer-name">
                    <?php echo htmlspecialchars($customer['Name']); ?>
                    <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                        <?php echo $customer['Status']; ?>
                    </span>
                </div>
                <div class="customer-id">ID: <?php echo $customer['CustomerUniqueID']; ?></div>
                <div class="customer-contact">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($customer['Contact']); ?>
                    </div>
                    <?php if (!empty($customer['Email'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($customer['Email']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="contact-item">
                        <i class="fas fa-calendar"></i>
                        Joined: <?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit.php?id=<?php echo $customer['CustomerID']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Customer
                    </a>

                    <?php if ($customer['Status'] == 'Active'): ?>
                        <a href="index.php?status=deactivate&id=<?php echo $customer['CustomerID']; ?>"
                            class="btn btn-warning"
                            onclick="return confirm('Are you sure you want to deactivate this customer?');">
                            <i class="fas fa-ban"></i> Deactivate
                        </a>
                    <?php elseif ($customer['Status'] == 'Inactive'): ?>
                        <a href="index.php?status=activate&id=<?php echo $customer['CustomerID']; ?>"
                            class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this customer?');">
                            <i class="fas fa-check"></i> Activate
                        </a>
                    <?php elseif ($customer['Status'] == 'Suspended'): ?>
                        <a href="index.php?status=activate&id=<?php echo $customer['CustomerID']; ?>"
                            class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this suspended customer?');">
                            <i class="fas fa-check"></i> Unsuspend
                        </a>
                    <?php endif; ?>

                    <a href="../subscriptions/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-outline">
                        <i class="fas fa-plus-circle"></i> Add Subscription
                    </a>

                    <a href="../payments/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-outline">
                        <i class="fas fa-money-bill-wave"></i> Record Payment
                    </a>

                    <a href="index.php?delete=<?php echo $customer['CustomerID']; ?>"
                        class="btn btn-danger"
                        onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone and will remove all subscriptions and payment records.');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>

        <div class="grid-container">
            <!-- Left Column -->
            <div class="grid-item-8">
                <!-- Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($customer['Name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Unique ID</span>
                                <span class="info-value"><?php echo $customer['CustomerUniqueID']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($customer['Contact']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo !empty($customer['Email']) ? htmlspecialchars($customer['Email']) : 'Not provided'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="badge badge-<?php echo
                                                                $customer['Status'] === 'Active' ? 'success' : ($customer['Status'] === 'Suspended' ? 'warning' : 'danger'); ?>">
                                        <?php echo $customer['Status']; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Registration Date</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($customer['CreatedAt'])); ?></span>
                            </div>
                            <?php if (!empty($customer['ReferredBy'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Referral Code</span>
                                    <span class="info-value"><?php echo htmlspecialchars($customer['ReferredBy']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($customer['Address'])): ?>
                            <div class="address-info">
                                <strong>Address:</strong> <?php echo htmlspecialchars($customer['Address']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($customer['PromoterName'])): ?>
                            <div class="promoter-info-card">
                                <div class="promoter-title">Referred By</div>
                                <div class="promoter-name"><?php echo htmlspecialchars($customer['PromoterName']); ?></div>
                                <div class="promoter-id">ID: <?php echo $customer['PromoterUniqueID']; ?></div>
                                <div class="promoter-contact">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($customer['PromoterContact']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($customer['BankAccountNumber'])): ?>
                            <div class="bank-info">
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Bank Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($customer['BankName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Holder Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($customer['BankAccountName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Number</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($customer['BankAccountNumber']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">IFSC Code</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($customer['IFSCCode']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabs Container -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Customer Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="tabs-container">
                            <div class="tabs-nav">
                                <div class="tab-item active" data-tab="subscriptions">Subscriptions</div>
                                <div class="tab-item" data-tab="payments">Payments</div>
                                <div class="tab-item" data-tab="withdrawals">Withdrawals</div>
                                <div class="tab-item" data-tab="prizes">Prizes & Winnings</div>
                            </div>

                            <!-- Subscriptions Tab -->
                            <div class="tab-content active" id="subscriptions-tab">
                                <?php if (count($recentSubscriptions) > 0): ?>
                                    <table class="table-clean">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Scheme</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentSubscriptions as $subscription): ?>
                                                <tr>
                                                    <td>#<?php echo $subscription['SubscriptionID']; ?></td>
                                                    <td><?php echo htmlspecialchars($subscription['SchemeName']); ?></td>
                                                    <td>₹<?php echo number_format($subscription['Amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo
                                                                                    $subscription['Status'] === 'Active' ? 'success' : ($subscription['Status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo $subscription['Status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?></td>
                                                    <td><?php echo !empty($subscription['EndDate']) ? date('M d, Y', strtotime($subscription['EndDate'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div style="text-align: right; margin-top: 15px;">
                                        <a href="../subscriptions/index.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="card-header-action">
                                            View All Subscriptions <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-file-invoice"></i>
                                        <p>No subscriptions found for this customer.</p>
                                        <a href="../subscriptions/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus-circle"></i> Add New Subscription
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Payments Tab -->
                            <div class="tab-content" id="payments-tab">
                                <?php if (count($recentPayments) > 0): ?>
                                    <table class="table-clean">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Scheme</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentPayments as $payment): ?>
                                                <tr>
                                                    <td>#<?php echo $payment['PaymentID']; ?></td>
                                                    <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                                    <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo
                                                                                    $payment['Status'] === 'Verified' ? 'success' : ($payment['Status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo $payment['Status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div style="text-align: right; margin-top: 15px;">
                                        <a href="../payments/index.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="card-header-action">
                                            View All Payments <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <p>No payments found for this customer.</p>
                                        <a href="../payments/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus-circle"></i> Record New Payment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Withdrawals Tab -->
                            <div class="tab-content" id="withdrawals-tab">
                                <?php if (count($withdrawals) > 0): ?>
                                    <table class="table-clean">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Requested</th>
                                                <th>Processed</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawals as $withdrawal): ?>
                                                <tr>
                                                    <td>#<?php echo $withdrawal['WithdrawalID']; ?></td>
                                                    <td>₹<?php echo number_format($withdrawal['Amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo
                                                                                    $withdrawal['Status'] === 'Approved' ? 'success' : ($withdrawal['Status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo $withdrawal['Status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($withdrawal['RequestedAt'])); ?></td>
                                                    <td><?php echo !empty($withdrawal['ProcessedAt']) ? date('M d, Y', strtotime($withdrawal['ProcessedAt'])) : 'Pending'; ?></td>
                                                    <td><?php echo !empty($withdrawal['Remarks']) ? htmlspecialchars($withdrawal['Remarks']) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div style="text-align: right; margin-top: 15px;">
                                        <a href="../withdrawals/index.php?user_id=<?php echo $customer['CustomerID']; ?>&user_type=Customer" class="card-header-action">
                                            View All Withdrawals <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-wallet"></i>
                                        <p>No withdrawal requests found for this customer.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Prizes Tab -->
                            <div class="tab-content" id="prizes-tab">
                                <?php if (count($prizes) > 0): ?>
                                    <table class="table-clean">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Prize Type</th>
                                                <th>Status</th>
                                                <th>Winning Date</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($prizes as $prize): ?>
                                                <tr>
                                                    <td>#<?php echo $prize['WinnerID']; ?></td>
                                                    <td><?php echo htmlspecialchars($prize['PrizeType']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo
                                                                                    $prize['Status'] === 'Claimed' ? 'success' : ($prize['Status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo $prize['Status']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($prize['WinningDate'])); ?></td>
                                                    <td><?php echo !empty($prize['Remarks']) ? htmlspecialchars($prize['Remarks']) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div style="text-align: right; margin-top: 15px;">
                                        <a href="../winners/index.php?user_id=<?php echo $customer['CustomerID']; ?>&user_type=Customer" class="card-header-action">
                                            View All Prizes <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-trophy"></i>
                                        <p>No prizes or winnings found for this customer.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="grid-item-4">
                <!-- Stats -->
                <div class="subscription-count-badge">
                    <span class="count-value"><?php echo $subscriptionCount; ?></span>
                    <span class="count-label">Total Subscriptions</span>
                </div>

                <!-- Activity Log -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Activity Log</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($activityLogs) > 0): ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="activity-item">
                                    <div><?php echo htmlspecialchars($log['Action']); ?></div>
                                    <div class="activity-time">
                                        <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($log['CreatedAt'])); ?>
                                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($log['IPAddress']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No activity logs found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="../subscriptions/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-primary" style="justify-content: center;">
                                <i class="fas fa-plus-circle"></i> Add Subscription
                            </a>
                            <a href="../payments/add.php?customer_id=<?php echo $customer['CustomerID']; ?>" class="btn btn-outline" style="justify-content: center;">
                                <i class="fas fa-money-bill-wave"></i> Record Payment
                            </a>
                            <a href="reset-password.php?id=<?php echo $customer['CustomerID']; ?>" class="btn btn-outline" style="justify-content: center;">
                                <i class="fas fa-key"></i> Reset Password
                            </a>
                            <a href="../notifications/send.php?user_id=<?php echo $customer['CustomerID']; ?>&user_type=Customer" class="btn btn-outline" style="justify-content: center;">
                                <i class="fas fa-bell"></i> Send Notification
                            </a>
                            <?php if ($_SESSION['admin_role'] === 'SuperAdmin'): ?>
                                <button onclick="viewPassword(<?php echo $customer['CustomerID']; ?>)" class="btn btn-outline" style="justify-content: center;">
                                    <i class="fas fa-eye"></i> View Password
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabItems = document.querySelectorAll('.tab-item');
            const tabContents = document.querySelectorAll('.tab-content');

            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabItems.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to current tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
        });

        // View Password Function
        function viewPassword(customerId) {
            fetch('get_password.php?id=' + customerId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create a modal to show the password
                        const modal = document.createElement('div');
                        modal.style.position = 'fixed';
                        modal.style.top = '0';
                        modal.style.left = '0';
                        modal.style.width = '100%';
                        modal.style.height = '100%';
                        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
                        modal.style.display = 'flex';
                        modal.style.justifyContent = 'center';
                        modal.style.alignItems = 'center';
                        modal.style.zIndex = '1000';

                        const modalContent = document.createElement('div');
                        modalContent.style.backgroundColor = 'white';
                        modalContent.style.padding = '20px';
                        modalContent.style.borderRadius = '8px';
                        modalContent.style.maxWidth = '400px';
                        modalContent.style.width = '90%';
                        modalContent.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';

                        modalContent.innerHTML = `
                            <h3 style="margin-top: 0; color: #2c3e50;">Password Information</h3>
                            <p style="margin: 15px 0; color: #34495e;">${data.message}</p>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;">
                                <strong style="color: #2c3e50;">Password:</strong>
                                <span style="color: #e74c3c; font-family: monospace; margin-left: 10px;">${data.password}</span>
                            </div>
                            <button onclick="this.parentElement.parentElement.remove()" 
                                style="background: #3498db; color: white; border: none; padding: 8px 16px; 
                                border-radius: 4px; cursor: pointer; float: right;">
                                Close
                            </button>
                            <div style="clear: both;"></div>
                        `;

                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);

                        // Close modal when clicking outside
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.remove();
                            }
                        });
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching password');
                });
        }

        // Print function customization
        window.onbeforeprint = function() {
            document.querySelector('.action-buttons').style.display = 'none';
            document.querySelectorAll('.card-header-action').forEach(function(el) {
                el.style.display = 'none';
            });
        };

        window.onafterprint = function() {
            document.querySelector('.action-buttons').style.display = 'flex';
            document.querySelectorAll('.card-header-action').forEach(function(el) {
                el.style.display = 'flex';
            });
        };
    </script>
</body>

</html>