<?php
session_start();
// Check if user is logged in, redirect if not


$menuPath = "../";
$currentPage = "mp_promoters";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No promoter ID provided.";
    header("Location: index.php");
    exit();
}

$promoterId = $_GET['id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter details
try {
    $query = "SELECT p.*, 
              parent.Name as ParentName, 
              parent.PromoterUniqueID as ParentUniqueID 
              FROM mp_promoters p 
              LEFT JOIN mp_promoters parent ON p.ParentPromoterID = parent.PromoterID 
              WHERE p.PromoterID = ?";

    $stmt = $conn->prepare($query);
    $stmt->execute([$promoterId]);

    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promoter) {
        $_SESSION['error_message'] = "Promoter not found.";
        header("Location: index.php");
        exit();
    }

    // Get customer count
    $stmt = $conn->prepare("SELECT COUNT(*) as customer_count FROM mp_customers WHERE PromoterID = ? AND Status = 'Active'");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['customer_count'];

    // Get wallet balance
    $stmt = $conn->prepare("SELECT BalanceAmount FROM PromoterWallet WHERE PromoterUniqueID = ?");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $walletBalance = $stmt->fetch(PDO::FETCH_ASSOC)['BalanceAmount'] ?? 0;

    // Get wallet logs
    $stmt = $conn->prepare("SELECT * FROM WalletLogs WHERE PromoterUniqueID = ? ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $walletLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent customers (5)
    $stmt = $conn->prepare("SELECT CustomerID, CustomerUniqueID, Name, Contact, Email, Status, CreatedAt 
                           FROM mp_customers 
                           WHERE PromoterID = ? 
                           ORDER BY CreatedAt DESC LIMIT 5");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $recentCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments (5)
    $stmt = $conn->prepare("SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, 
                            c.Name as CustomerName, c.CustomerUniqueID, 
                            s.SchemeName 
                            FROM Payments p 
                            LEFT JOIN mp_customers c ON p.CustomerID = c.CustomerID 
                            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID 
                            WHERE c.PromoterID = ? 
                            ORDER BY p.SubmittedAt DESC LIMIT 5");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity logs (10)
    $stmt = $conn->prepare("SELECT LogID, Action, IPAddress, CreatedAt 
                           FROM ActivityLogs 
                           WHERE UserID = ? AND UserType = 'Promoter' 
                           ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$promoterId]);
    $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving promoter details: " . $e->getMessage();
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
    <title>View Promoter - <?php echo htmlspecialchars($promoter['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Promoter View Page Styles */
        :root {
            --pr_primary: #3a7bd5;
            --pr_primary-hover: #2c60a9;
            --pr_secondary: #00d2ff;
            --pr_success: #2ecc71;
            --pr_success-hover: #27ae60;
            --pr_warning: #f39c12;
            --pr_warning-hover: #d35400;
            --pr_danger: #e74c3c;
            --pr_danger-hover: #c0392b;
            --pr_info: #3498db;
            --pr_info-hover: #2980b9;
            --pr_text-dark: #2c3e50;
            --pr_text-medium: #34495e;
            --pr_text-light: #7f8c8d;
            --pr_bg-light: #f8f9fa;
            --pr_border-color: #e0e0e0;
            --pr_shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --pr_shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --pr_transition: 0.25s;
        }

        .content-wrapper {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--pr_text-medium);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color var(--pr_transition);
            padding: 8px 16px;
            border-radius: 8px;
            background: var(--pr_bg-light);
        }

        .back-link:hover {
            color: var(--pr_primary);
            background: rgba(58, 123, 213, 0.1);
        }

        .promoter-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--pr_shadow-sm);
        }

        .promoter-avatar {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--pr_primary), var(--pr_secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: 600;
            box-shadow: var(--pr_shadow-md);
            border: 4px solid white;
            flex-shrink: 0;
        }

        .promoter-info {
            flex: 1;
        }

        .promoter-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--pr_text-dark);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .promoter-id {
            font-size: 14px;
            color: var(--pr_text-light);
            margin-bottom: 10px;
            background: var(--pr_bg-light);
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .promoter-contact {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--pr_text-medium);
            flex-wrap: wrap;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--pr_bg-light);
            padding: 6px 12px;
            border-radius: 6px;
        }

        .contact-item i {
            color: var(--pr_primary);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--pr_success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--pr_danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
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
            transition: all var(--pr_transition);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--pr_primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(58, 123, 213, 0.2);
        }

        .btn-warning {
            background: var(--pr_warning);
            color: white;
        }

        .btn-warning:hover {
            background: var(--pr_warning-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.2);
        }

        .btn-danger {
            background: var(--pr_danger);
            color: white;
        }

        .btn-danger:hover {
            background: var(--pr_danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--pr_text-medium);
            border: 1px solid var(--pr_border-color);
        }

        .btn-outline:hover {
            border-color: var(--pr_primary);
            color: var(--pr_primary);
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
            box-shadow: var(--pr_shadow-sm);
            overflow: hidden;
            transition: transform var(--pr_transition), box-shadow var(--pr_transition);
            display: flex;
            flex-direction: column;
            height: auto;
            min-height: 0;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--pr_shadow-md);
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--pr_border-color);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--pr_text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: var(--pr_primary);
        }

        .card-header-action {
            color: var(--pr_primary);
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            background: rgba(58, 123, 213, 0.1);
            transition: all var(--pr_transition);
        }

        .card-header-action:hover {
            background: rgba(58, 123, 213, 0.2);
            transform: translateX(2px);
        }

        .card-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            max-height: 300px;
            min-height: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 12px;
            color: var(--pr_text-light);
            line-height: 1.2;
        }

        .info-value {
            font-size: 14px;
            color: var(--pr_text-dark);
            font-weight: 500;
            word-break: break-word;
            line-height: 1.4;
        }

        .table-clean {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 0;
        }

        .table-clean th,
        .table-clean td {
            padding: 8px 12px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid var(--pr_border-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4;
        }

        .activity-item {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .credit-transaction {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid #2ecc71;
        }

        .debit-transaction {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .transaction-amount {
            font-weight: 600;
            font-size: 1.1em;
        }

        .transaction-amount.credit {
            color: #2ecc71;
        }

        .transaction-amount.debit {
            color: #e74c3c;
        }

        .transaction-message {
            color: #2c3e50;
        }

        .activity-time {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .address-info {
            background-color: var(--pr_bg-light);
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
            color: var(--pr_text-medium);
            line-height: 1.4;
            word-break: break-word;
        }

        .bank-info {
            background-color: var(--pr_bg-light);
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .bank-info-item {
            margin-bottom: 8px;
        }

        .bank-info-item:last-child {
            margin-bottom: 0;
        }

        .bank-info-label {
            font-size: 12px;
            color: var(--pr_text-light);
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .bank-info-value {
            font-size: 14px;
            color: var(--pr_text-dark);
            font-weight: 500;
            word-break: break-word;
            line-height: 1.4;
        }

        /* Custom scrollbar for card bodies */
        .card-body::-webkit-scrollbar {
            width: 4px;
        }

        .card-body::-webkit-scrollbar-track {
            background: var(--pr_bg-light);
        }

        .card-body::-webkit-scrollbar-thumb {
            background: var(--pr_border-color);
            border-radius: 2px;
        }

        .card-body::-webkit-scrollbar-thumb:hover {
            background: var(--pr_text-light);
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

            .promoter-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .promoter-contact {
                flex-direction: column;
                gap: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Responsive table */
        @media (max-width: 768px) {
            .table-clean {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .table-clean th,
            .table-clean td {
                min-width: 120px;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--pr_shadow-md);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: var(--pr_text-light);
            transition: color var(--pr_transition);
        }

        .close-modal:hover {
            color: var(--pr_danger);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--pr_text-dark);
        }

        .commission-input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--pr_border-color);
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
            transition: all var(--pr_transition);
        }

        .commission-input:focus {
            outline: none;
            border-color: var(--pr_primary);
            box-shadow: 0 0 0 4px var(--pr_primary-light);
        }

        .referral-link {
            background: var(--pr_bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            word-break: break-all;
            display: none;
            font-size: 14px;
            color: var(--pr_text-dark);
        }

        .copy-btn {
            background: var(--pr_primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: none;
            transition: all var(--pr_transition);
        }

        .copy-btn:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
        }

        .error-message {
            color: var(--pr_danger);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        /* Add these styles to your existing CSS */
        .parent-promoter-link {
            color: var(--pr_primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            background: var(--pr_primary-light);
            transition: all 0.3s ease;
        }

        .parent-promoter-link:hover {
            background: var(--pr_primary);
            color: white;
            transform: translateY(-1px);
        }

        .parent-promoter-link i {
            font-size: 14px;
        }

        .commission-badge {
            background: var(--pr_primary-light);
            color: var(--pr_primary);
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .commission-badge::before {
            content: '₹';
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Promoters List
        </a>

        <div class="promoter-header">
            <div class="promoter-avatar">
                <?php
                $initials = '';
                $nameParts = explode(' ', $promoter['Name']);
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($promoter['Name'], 0, 2));
                }
                echo $initials;
                ?>
            </div>
            <div class="promoter-info">
                <div class="promoter-name">
                    <?php echo htmlspecialchars($promoter['Name']); ?>
                    <span class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                        <?php echo $promoter['Status']; ?>
                    </span>
                </div>
                <div class="promoter-id">ID: <?php echo $promoter['PromoterUniqueID']; ?></div>
                <div class="promoter-contact">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($promoter['Contact']); ?>
                    </div>
                    <?php if (!empty($promoter['Email'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($promoter['Email']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="contact-item">
                        <i class="fas fa-calendar"></i>
                        Joined: <?php echo date('M d, Y', strtotime($promoter['CreatedAt'])); ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit.php?id=<?php echo $promoter['PromoterID']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Promoter
                    </a>

                    <?php if ($promoter['Status'] == 'Active'): ?>
                        <a href="index.php?status=deactivate&id=<?php echo $promoter['PromoterID']; ?>"
                            class="btn btn-warning"
                            onclick="return confirm('Are you sure you want to deactivate this promoter?');">
                            <i class="fas fa-ban"></i> Deactivate
                        </a>
                    <?php else: ?>
                        <a href="index.php?status=activate&id=<?php echo $promoter['PromoterID']; ?>"
                            class="btn btn-primary"
                            onclick="return confirm('Are you sure you want to activate this promoter?');">
                            <i class="fas fa-check"></i> Activate
                        </a>
                    <?php endif; ?>

                    <a href="index.php?delete=<?php echo $promoter['PromoterID']; ?>"
                        class="btn btn-danger"
                        onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone.');">
                        <i class="fas fa-trash"></i> Delete
                    </a>

                    <a href="#" class="btn btn-outline" onclick="window.print();">
                        <i class="fas fa-print"></i> Print
                    </a>

                    <a href="#" class="btn btn-outline" id="generateReferral17Btn">
                        <i class="fas fa-link"></i> Generate Referral 17 Link
                    </a>

                    <a href="#" class="btn btn-outline" id="generateReferralBtn">
                        <i class="fas fa-link"></i> Generate Referral Link
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
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Unique ID</span>
                                <span class="info-value"><?php echo $promoter['PromoterUniqueID']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($promoter['Contact']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo !empty($promoter['Email']) ? htmlspecialchars($promoter['Email']) : 'Not provided'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="badge badge-<?php echo $promoter['Status'] === 'Active' ? 'success' : 'danger'; ?>">
                                        <?php echo $promoter['Status']; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Registration Date</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($promoter['CreatedAt'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Commission</span>
                                <span class="info-value">
                                    <span class="commission-badge">
                                        <?php echo $promoter['Commission']; ?>
                                    </span>
                                </span>
                            </div>
                            <?php if (!empty($promoter['ParentPromoterID'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Parent Promoter</span>
                                    <span class="info-value">
                                        <?php
                                        // Get parent promoter details including PromoterID
                                        $stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE PromoterUniqueID = ?");
                                        $stmt->execute([$promoter['ParentPromoterID']]);
                                        $parentPromoter = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($parentPromoter) {
                                            echo '<a href="view.php?id=' . $parentPromoter['PromoterID'] . '" class="parent-promoter-link">';
                                            echo '<i class="fas fa-user-tie"></i>';
                                            echo htmlspecialchars($parentPromoter['Name'] . ' (' . $parentPromoter['PromoterUniqueID'] . ')');
                                            echo '</a>';
                                        } else {
                                            echo 'Unknown Parent';
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($promoter['Address'])): ?>
                            <div class="address-info">
                                <strong>Address:</strong> <?php echo htmlspecialchars($promoter['Address']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Bank Details -->
                        <?php if (!empty($promoter['BankAccountNumber'])): ?>
                            <div class="bank-info">
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Bank Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Holder Name</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankAccountName']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">Account Number</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['BankAccountNumber']); ?></div>
                                </div>
                                <div class="bank-info-item">
                                    <div class="bank-info-label">IFSC Code</div>
                                    <div class="bank-info-value"><?php echo htmlspecialchars($promoter['IFSCCode']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Customers -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Recent Customers</h3>
                        <a href="../customers/index.php?promoter_id=<?php echo $promoter['PromoterID']; ?>" class="card-header-action">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentCustomers) > 0): ?>
                            <table class="table-clean">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCustomers as $customer): ?>
                                        <tr>
                                            <td><?php echo $customer['CustomerUniqueID']; ?></td>
                                            <td><?php echo htmlspecialchars($customer['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $customer['Status'] === 'Active' ? 'success' : 'danger'; ?>">
                                                    <?php echo $customer['Status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No customers found for this promoter.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-money-bill-wave"></i> Recent Payments</h3>
                        <a href="../payments/index.php?promoter_id=<?php echo $promoter['PromoterID']; ?>" class="card-header-action">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentPayments) > 0): ?>
                            <table class="table-clean">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
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
                                            <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
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
                        <?php else: ?>
                            <p>No payments found for this promoter.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="grid-item-4">
                <!-- Stats -->
                <div class="customer-count-badge">
                    <span class="count-value"><?php echo $customerCount; ?></span>
                    <span class="count-label">Active Customers</span>
                </div>

                <!-- Wallet Balance -->
                <div class="wallet-balance-badge">
                    <span class="count-value">₹<?php echo number_format($walletBalance, 2); ?></span>
                    <span class="count-label">Wallet Balance</span>
                </div>

                <!-- Wallet Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Wallet Transactions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($walletLogs) > 0): ?>
                            <?php foreach ($walletLogs as $log): ?>
                                <div class="activity-item <?php echo $log['TransactionType'] === 'Debit' ? 'debit-transaction' : 'credit-transaction'; ?>">
                                    <div class="transaction-info">
                                        <span class="transaction-amount <?php echo $log['TransactionType'] === 'Debit' ? 'debit' : 'credit'; ?>">
                                            <?php echo $log['TransactionType'] === 'Debit' ? '-' : '+'; ?>₹<?php echo number_format(abs($log['Amount']), 2); ?>
                                        </span>
                                        <span class="transaction-message"><?php echo htmlspecialchars($log['Message']); ?></span>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M d, Y h:i A', strtotime($log['CreatedAt'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No wallet transactions found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list"></i> Activity Log</h3>
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
            </div>
        </div>
    </div>

    <!-- Referral Link Modal -->
    <div id="referralModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">Generate Referral Link</h2>
            <div class="form-group">
                <label>Enter Commission</label>
                <input type="number" id="commissionInput" class="commission-input" min="0" max="<?php echo $promoter['Commission']; ?>" step="0.01">
                <div class="error-message" id="commissionError"></div>
            </div>
            <div class="referral-link" id="referralLink"></div>
            <button class="copy-btn" id="copyBtn">Copy Link</button>
        </div>
    </div>

    <!-- Referral17 Link Modal -->
    <div id="referral17Modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">Generate Referral 17 Link</h2>
            <div class="form-group">
                <label>Enter Commission</label>
                <input type="number" id="commission17Input" class="commission-input" min="0" max="<?php echo $promoter['Commission']; ?>" step="0.01">
                <div class="error-message" id="commission17Error"></div>
            </div>
            <div class="referral-link" id="referral17Link"></div>
            <button class="copy-btn" id="copy17Btn">Copy Link</button>
        </div>
    </div>

    <script>
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

        // Referral Link Generation
        const modal = document.getElementById('referralModal');
        const generateBtn = document.getElementById('generateReferralBtn');
        const closeBtn = document.querySelector('.close-modal');
        const commissionInput = document.getElementById('commissionInput');
        const referralLink = document.getElementById('referralLink');
        const copyBtn = document.getElementById('copyBtn');
        const commissionError = document.getElementById('commissionError');
        const maxCommission = <?php echo $promoter['Commission']; ?>;

        generateBtn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
            resetModal();
        }

        window.onclick = function(e) {
            if (e.target == modal) {
                modal.style.display = 'none';
                resetModal();
            }
        }

        commissionInput.oninput = function() {
            const value = parseFloat(this.value);
            if (value > maxCommission) {
                commissionError.textContent = `Commission cannot be greater than ${maxCommission}`;
                commissionError.style.display = 'block';
                referralLink.style.display = 'none';
                copyBtn.style.display = 'none';
            } else {
                commissionError.style.display = 'none';
                generateReferralLink(value);
            }
        }

        function generateReferralLink(commission) {
            const promoterId = '<?php echo $promoter['PromoterUniqueID']; ?>';
            const encodedRef = btoa(commission.toString());
            const baseUrl = '<?php echo Database::$baseUrl; ?>';
            const link = `${baseUrl}/refer?id=${promoterId}&ref=${encodedRef}`;
            referralLink.textContent = link;
            referralLink.style.display = 'block';
            copyBtn.style.display = 'inline-block';
        }

        copyBtn.onclick = function() {
            const link = referralLink.textContent;
            navigator.clipboard.writeText(link).then(() => {
                copyBtn.textContent = 'Copied!';
                setTimeout(() => {
                    copyBtn.textContent = 'Copy Link';
                }, 2000);
            });
        }

        function resetModal() {
            commissionInput.value = '';
            referralLink.style.display = 'none';
            copyBtn.style.display = 'none';
            commissionError.style.display = 'none';
        }

        // Referral17 Link Generation
        const modal17 = document.getElementById('referral17Modal');
        const generateBtn17 = document.getElementById('generateReferral17Btn');
        const closeBtn17 = document.querySelector('#referral17Modal .close-modal');
        const commissionInput17 = document.getElementById('commission17Input');
        const referralLink17 = document.getElementById('referral17Link');
        const copyBtn17 = document.getElementById('copy17Btn');
        const commissionError17 = document.getElementById('commission17Error');

        generateBtn17.onclick = function(e) {
            e.preventDefault();
            modal17.style.display = 'flex';
        }

        closeBtn17.onclick = function() {
            modal17.style.display = 'none';
            resetModal17();
        }

        window.onclick = function(e) {
            if (e.target == modal17) {
                modal17.style.display = 'none';
                resetModal17();
            }
        }

        commissionInput17.oninput = function() {
            const value = parseFloat(this.value);
            if (value > maxCommission) {
                commissionError17.textContent = `Commission cannot be greater than ${maxCommission}`;
                commissionError17.style.display = 'block';
                referralLink17.style.display = 'none';
                copyBtn17.style.display = 'none';
            } else {
                commissionError17.style.display = 'none';
                generateReferral17Link(value);
            }
        }

        function generateReferral17Link(commission) {
            const promoterId = '<?php echo $promoter['PromoterUniqueID']; ?>';
            const encodedRef = btoa(commission.toString());
            const baseUrl = '<?php echo Database::$baseUrl; ?>';
            const link = `${baseUrl}/refer17?id=${promoterId}&ref=${encodedRef}`;
            referralLink17.textContent = link;
            referralLink17.style.display = 'block';
            copyBtn17.style.display = 'inline-block';
        }

        copyBtn17.onclick = function() {
            const link = referralLink17.textContent;
            navigator.clipboard.writeText(link).then(() => {
                copyBtn17.textContent = 'Copied!';
                setTimeout(() => {
                    copyBtn17.textContent = 'Copy Link';
                }, 2000);
            });
        }

        function resetModal17() {
            commissionInput17.value = '';
            referralLink17.style.display = 'none';
            copyBtn17.style.display = 'none';
            commissionError17.style.display = 'none';
        }
    </script>
</body>

</html>