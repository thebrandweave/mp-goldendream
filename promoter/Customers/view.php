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
        SELECT c.*, p.Name as PromoterName, p.PromoterUniqueID 
        FROM Customers c
        JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        WHERE c.CustomerID = ? AND c.PromoterID = ?
    ");
    $stmt->execute([$customerId, $promoterUniqueID]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header("Location: index.php");
        exit();
    }
    
    // Get customer's subscriptions
    $stmt = $conn->prepare("
        SELECT s.*, sc.SchemeName, sc.Description as SchemeDescription
        FROM Subscriptions s
        JOIN Schemes sc ON s.SchemeID = sc.SchemeID
        WHERE s.CustomerID = ? AND s.RenewalStatus = 'Active'
    ");
    $stmt->execute([$customerId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customer's payments
    $stmt = $conn->prepare("
        SELECT p.*, s.SchemeName, i.InstallmentName
        FROM Payments p
        LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
        LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID
        WHERE p.CustomerID = ?
        ORDER BY p.SubmittedAt DESC
    ");
    $stmt->execute([$customerId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customer's KYC details
    $stmt = $conn->prepare("
        SELECT * FROM KYC 
        WHERE UserID = ? AND UserType = 'Customer'
    ");
    $stmt->execute([$customerId]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Error fetching customer data: " . $e->getMessage();
    $messageType = "error";
    $showNotification = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer | Golden Dreams</title>
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
            max-width: 1200px;
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

        .btn-danger {
            background: var(--error-color);
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

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .customer-profile {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
        }

        .profile-image-container {
            width: 200px;
            height: 200px;
            border-radius: 15px;
            overflow: hidden;
            border: 3px solid var(--primary-light);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .customer-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .customer-id {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-suspended {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
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
            color: var(--text-secondary);
        }

        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .tab.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .badge-warning {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .kyc-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kyc-status i {
            font-size: 20px;
        }

        .kyc-status.pending i {
            color: var(--warning-color);
        }

        .kyc-status.verified i {
            color: var(--success-color);
        }

        .kyc-status.rejected i {
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .customer-profile {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 20px;
            }

            .profile-image-container {
                width: 150px;
                height: 150px;
                margin: 0 auto;
            }

            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .tabs {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 5px;
                gap: 5px;
                -webkit-overflow-scrolling: touch;
            }

            .tab {
                padding: 8px 15px;
                font-size: 13px;
            }

            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
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

            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
                padding: 10px 15px;
                font-size: 13px;
            }

            .card {
                padding: 15px;
            }

            .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .table th, .table td {
                padding: 10px;
                font-size: 13px;
            }

            .badge {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .customer-profile {
                padding: 15px;
            }

            .profile-image-container {
                width: 120px;
                height: 120px;
            }

            .customer-name {
                font-size: 18px;
            }

            .card {
                padding: 12px;
            }

            .table th, .table td {
                padding: 8px;
                font-size: 12px;
            }

            .badge {
                padding: 3px 6px;
                font-size: 10px;
            }

            .info-label {
                font-size: 11px;
            }

            .info-value {
                font-size: 14px;
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
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="section-info">
                        <h2>Customer Details</h2>
                        <p>View and manage customer information</p>
                    </div>
                </div>
                <div>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                    <a href="edit.php?id=<?php echo $customerId; ?>" class="btn-primary">
                        <i class="fas fa-edit"></i>
                        Edit Customer
                    </a>
                </div>
            </div>

            <div class="customer-profile">
                <div class="profile-image-container">
                    <img src="<?php 
                        if (!empty($customer['ProfileImageURL']) && $customer['ProfileImageURL'] !== '-'): 
                            echo '../../' . htmlspecialchars($customer['ProfileImageURL']);
                        else:
                            echo '../../uploads/profile/image.png';
                        endif;
                    ?>" alt="Profile Image" class="profile-image">
                </div>
                <div class="profile-info">
                    <div class="profile-header">
                        <div>
                            <h1 class="customer-name"><?php echo htmlspecialchars($customer['Name']); ?></h1>
                            <div class="customer-id">ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></div>
                        </div>
                        <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                            <?php echo htmlspecialchars($customer['Status']); ?>
                        </span>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Contact Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($customer['Contact']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($customer['Email'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?php echo $customer['DateOfBirth'] ? date('d M Y', strtotime($customer['DateOfBirth'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value"><?php echo htmlspecialchars($customer['Gender'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Joined Date</span>
                            <span class="info-value"><?php echo date('d M Y', strtotime($customer['CreatedAt'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Promoter</span>
                            <span class="info-value"><?php echo htmlspecialchars($customer['PromoterName']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="subscriptions">Subscriptions</div>
                <div class="tab" data-tab="payments">Payments</div>
                <div class="tab" data-tab="kyc">KYC Details</div>
            </div>

            <div class="tab-content active" id="subscriptions">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Subscriptions</h3>
                    </div>
                    <?php if (empty($subscriptions)): ?>
                        <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                            No active subscriptions found.
                        </p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Scheme Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscription['SchemeName']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($subscription['StartDate'])); ?></td>
                                        <td><?php echo date('d M Y', strtotime($subscription['EndDate'])); ?></td>
                                        <td>
                                            <span class="badge badge-success">Active</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-content" id="payments">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Payment History</h3>
                    </div>
                    <?php if (empty($payments)): ?>
                        <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                            No payment history found.
                        </p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Scheme</th>
                                    <th>Installment</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['InstallmentName']); ?></td>
                                        <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($payment['SubmittedAt'])); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($payment['Status']) {
                                                case 'Verified':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'Pending':
                                                    $statusClass = 'badge-warning';
                                                    break;
                                                case 'Rejected':
                                                    $statusClass = 'badge-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($payment['Status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-content" id="kyc">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">KYC Information</h3>
                    </div>
                    <?php if (!$kyc): ?>
                        <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                            No KYC information available.
                        </p>
                    <?php else: ?>
                        <div class="info-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="info-item">
                                <span class="info-label">Aadhar Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($kyc['AadharNumber']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">PAN Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($kyc['PANNumber']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ID Proof Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($kyc['IDProofType']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Address Proof Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($kyc['AddressProofType']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <div class="kyc-status <?php echo strtolower($kyc['Status']); ?>">
                                    <i class="fas fa-<?php 
                                        echo $kyc['Status'] === 'Verified' ? 'check-circle' : 
                                            ($kyc['Status'] === 'Pending' ? 'clock' : 'times-circle'); 
                                    ?>"></i>
                                    <span><?php echo htmlspecialchars($kyc['Status']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Submitted On</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($kyc['SubmittedAt'])); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(tab.dataset.tab).classList.add('active');
                });
            });

            // Ensure proper topbar integration
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
