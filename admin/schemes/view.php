<?php
session_start();


$menuPath = "../";
$currentPage = "schemes";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Check if scheme ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No scheme specified.";
    header("Location: index.php");
    exit();
}

$schemeId = intval($_GET['id']);

// Get scheme details
try {
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        $_SESSION['error_message'] = "Scheme not found.";
        header("Location: index.php");
        exit();
    }

    // Get subscription count for this scheme
    $stmt = $conn->prepare("
        SELECT COUNT(*) as SubscriptionCount
        FROM Subscriptions
        WHERE SchemeID = ?
    ");
    $stmt->execute([$schemeId]);
    $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $subscriptionCount = $subscriptionData['SubscriptionCount'];

    // Get active subscription count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as ActiveCount
        FROM Subscriptions
        WHERE SchemeID = ? AND RenewalStatus = 'Active'
    ");
    $stmt->execute([$schemeId]);
    $activeData = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeCount = $activeData['ActiveCount'];

    // Get total collection amount for this scheme
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(Amount), 0) as TotalCollection
        FROM Payments
        WHERE SchemeID = ? AND Status = 'Verified'
    ");
    $stmt->execute([$schemeId]);
    $collectionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCollection = $collectionData['TotalCollection'];

    // Get recent subscriptions (limited to 5)
    $stmt = $conn->prepare("
        SELECT s.*, c.Name as CustomerName, c.CustomerUniqueID
        FROM Subscriptions s
        JOIN Customers c ON s.CustomerID = c.CustomerID
        WHERE s.SchemeID = ?
        ORDER BY s.StartDate DESC
        LIMIT 5
    ");
    $stmt->execute([$schemeId]);
    $recentSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments (limited to 5)
    $stmt = $conn->prepare("
        SELECT p.*, c.Name as CustomerName, c.CustomerUniqueID
        FROM Payments p
        JOIN Customers c ON p.CustomerID = c.CustomerID
        WHERE p.SchemeID = ?
        ORDER BY p.SubmittedAt DESC
        LIMIT 5
    ");
    $stmt->execute([$schemeId]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity logs related to this scheme (limited to 10)
    $stmt = $conn->prepare("
        SELECT al.*, 
               CASE 
                   WHEN al.UserType = 'Admin' THEN a.Name
                   WHEN al.UserType = 'Customer' THEN c.Name
                   WHEN al.UserType = 'Promoter' THEN p.Name
               END as UserName
        FROM ActivityLogs al
        LEFT JOIN Admins a ON al.UserType = 'Admin' AND al.UserID = a.AdminID
        LEFT JOIN Customers c ON al.UserType = 'Customer' AND al.UserID = c.CustomerID
        LEFT JOIN Promoters p ON al.UserType = 'Promoter' AND al.UserID = p.PromoterID
        WHERE al.Action LIKE ?
        ORDER BY al.CreatedAt DESC
        LIMIT 10
    ");
    $stmt->execute(['%' . $scheme['SchemeName'] . '%']);
    $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Scheme - <?php echo htmlspecialchars($scheme['SchemeName']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .scheme-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
            flex-wrap: wrap;
            gap: 20px;
        }

        .scheme-info {
            flex: 1;
            min-width: 300px;
        }

        .scheme-image {
            flex: 1;
            min-width: 300px;
            display: flex;
            justify-content: left;
            align-items: center;
        }
        .scheme-image img{
          
            height: 300px;            object-fit: cover;
        }

        .scheme-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .scheme-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .scheme-subtitle {
            color: #7f8c8d;
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .scheme-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .scheme-meta-item {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scheme-meta-item i {
            color: #3498db;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-verified {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-expired {
            background-color: rgba(149, 165, 166, 0.1);
            color: #7f8c8d;
        }

        .status-cancelled {
            background-color: rgba(52, 73, 94, 0.1);
            color: #34495e;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #2980b9, #2573a7);
        }

        .delete-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }

        .activate-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .activate-btn:hover {
            background: linear-gradient(135deg, #27ae60, #219d54);
        }

        .deactivate-btn {
            background: linear-gradient(135deg, #f39c12, #d35400);
        }

        .deactivate-btn:hover {
            background: linear-gradient(135deg, #d35400, #ba4a00);
        }

        .print-btn {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }

        .print-btn:hover {
            background: linear-gradient(135deg, #8e44ad, #7d3c98);
        }

        .scheme-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .detail-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }

        .detail-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-card-title i {
            color: #3498db;
        }

        .detail-item {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #2c3e50;
            font-weight: 500;
        }

        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
            color: #3498db;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eaeaea;
            overflow-x: auto;
        }

        .tab {
            padding: 12px 20px;
            font-size: 0.9rem;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
        }

        .tab.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
            color: #2c3e50;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #bdc3c7;
        }

        .empty-state p {
            font-size: 1rem;
        }

        .description-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            color: #2c3e50;
            font-size: 0.95rem;
            white-space: pre-line;
        }

        .log-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .log-time {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .log-action {
            font-size: 0.95rem;
            color: #2c3e50;
        }

        .log-user {
            font-size: 0.85rem;
            color: #3498db;
            margin-bottom: 3px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid #2ecc71;
            color: #27ae60;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
            color: #c0392b;
        }

        .see-all-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-section,
            .print-section * {
                visibility: visible;
            }

            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .tab-content {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .scheme-header {
                flex-direction: column;
                gap: 15px;
            }

            .scheme-image {
                width: 100%;
                margin-bottom: 20px;
            }

            .scheme-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .stat-cards {
                grid-template-columns: 1fr;
            }

            .scheme-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .scheme-meta-item {
                width: 100%;
            }

            .scheme-actions {
                justify-content: center;
            }
        }

        .no-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 300px;
        }

        .no-image i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 10px;
        }

        .no-image span {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .no-image small {
            font-size: 0.8rem;
            color: #95a5a6;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="print-section">
            <div class="scheme-header">
                <div class="scheme-info">
                    <h1 class="scheme-title"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h1>
                    <div class="scheme-subtitle">
                        <?php
                        // Based on schema, SchemeType may not exist
                        if (isset($scheme['SchemeType'])) {
                            echo htmlspecialchars($scheme['SchemeType']);
                        } else {
                            echo "Payment";
                        }
                        ?> Scheme
                        <span class="status-badge status-<?php echo strtolower($scheme['Status']); ?>">
                            <?php echo $scheme['Status']; ?>
                        </span>
                    </div>
                    <div class="scheme-meta">
                        <div class="scheme-meta-item">
                            <i class="fas fa-rupee-sign"></i>
                            <?php if (isset($scheme['SchemeAmount'])): ?>
                                <span>Value: ₹<?php echo number_format($scheme['SchemeAmount']); ?></span>
                            <?php else: ?>
                                <span>Monthly: ₹<?php echo number_format($scheme['MonthlyPayment']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="scheme-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <?php if (isset($scheme['SchemeDuration'])): ?>
                                <span>Duration: <?php echo $scheme['SchemeDuration']; ?> Months</span>
                            <?php else: ?>
                                <span>Payments: <?php echo $scheme['TotalPayments']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="scheme-meta-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>Created: <?php echo date('M d, Y', strtotime($scheme['CreatedAt'])); ?></span>
                        </div>
                    </div>
                </div>
          
                <div class="scheme-actions no-print">
                    <a href="index.php" class="action-btn edit-btn">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>

                    <a href="edit.php?id=<?php echo $schemeId; ?>" class="action-btn edit-btn">
                        <i class="fas fa-edit"></i> Edit
                    </a>

                    <?php if ($scheme['Status'] === 'Active'): ?>
                        <button onclick="confirmStatusChange('deactivate', <?php echo $schemeId; ?>)" class="action-btn deactivate-btn">
                            <i class="fas fa-ban"></i> Deactivate
                        </button>
                    <?php else: ?>
                        <button onclick="confirmStatusChange('activate', <?php echo $schemeId; ?>)" class="action-btn activate-btn">
                            <i class="fas fa-check"></i> Activate
                        </button>
                    <?php endif; ?>

                    <button onclick="window.print()" class="action-btn print-btn">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $subscriptionCount; ?></div>
                    <div class="stat-label">Total Subscribers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $activeCount; ?></div>
                    <div class="stat-label">Active Subscribers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-value">₹<?php echo number_format($totalCollection); ?></div>
                    <div class="stat-label">Total Collection</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <?php if (isset($scheme['InstallmentFrequency'])): ?>
                        <div class="stat-value"><?php echo $scheme['InstallmentFrequency']; ?></div>
                        <div class="stat-label">Installment Frequency</div>
                    <?php else: ?>
                        <div class="stat-value"><?php echo $scheme['TotalPayments']; ?></div>
                        <div class="stat-label">Total Payments</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="scheme-image">
                    <?php if (!empty($scheme['SchemeImageURL'])): ?>
                        <img src="../../<?php echo htmlspecialchars($scheme['SchemeImageURL']); ?>" alt="<?php echo htmlspecialchars($scheme['SchemeName']); ?>" class="scheme-img">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                            <span>Scheme image not available</span>
                            <small>Note: Scheme images are not currently supported in the database schema.</small>
                        </div>
                    <?php endif; ?>
                </div>
            <div class="scheme-details">
                <div class="detail-card">
                    <h2 class="detail-card-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h2>

                    <div class="detail-item">
                        <span class="detail-label">Scheme ID</span>
                        <span class="detail-value"><?php echo $scheme['SchemeID']; ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Scheme Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($scheme['SchemeName']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Monthly Payment</span>
                        <span class="detail-value">₹<?php echo number_format($scheme['MonthlyPayment']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Total Payments</span>
                        <span class="detail-value"><?php echo $scheme['TotalPayments']; ?></span>
                    </div>
                </div>

                <div class="detail-card">
                    <h2 class="detail-card-title">
                        <i class="fas fa-file-contract"></i> Scheme Details
                    </h2>

                    <?php
                    // Let's check if we can get installment information for this scheme
                    try {
                        $installmentStmt = $conn->prepare("
                            SELECT COUNT(*) as count FROM Installments WHERE SchemeID = ?
                        ");
                        $installmentStmt->execute([$schemeId]);
                        $installmentCount = $installmentStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    } catch (PDOException $e) {
                        $installmentCount = 0;
                    }
                    ?>

                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?php echo strtolower($scheme['Status']); ?>">
                                <?php echo $scheme['Status']; ?>
                            </span>
                        </span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Creation Date</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($scheme['CreatedAt'])); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Installments Defined</span>
                        <span class="detail-value"><?php echo $installmentCount; ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Created By</span>
                        <span class="detail-value">System</span>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <h2 class="detail-card-title">
                    <i class="fas fa-align-left"></i> Scheme Description
                </h2>
                <div class="description-box">
                    <?php echo !empty($scheme['Description']) ? nl2br(htmlspecialchars($scheme['Description'])) : 'No description available.'; ?>
                </div>
            </div>

            <div class="tabs no-print">
                <div class="tab active" data-tab="subscriptions">
                    <i class="fas fa-users"></i> Recent Subscriptions
                </div>
                <div class="tab" data-tab="payments">
                    <i class="fas fa-credit-card"></i> Recent Payments
                </div>
                <div class="tab" data-tab="activity">
                    <i class="fas fa-history"></i> Activity Log
                </div>
                <?php if ($installmentCount > 0): ?>
                    <div class="tab" data-tab="installments">
                        <i class="fas fa-calendar-alt"></i> Installments
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content active" id="subscriptions-tab">
                <?php if (count($recentSubscriptions) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>ID</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSubscriptions as $subscription): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscription['CustomerName']); ?></td>
                                    <td><?php echo htmlspecialchars($subscription['CustomerUniqueID']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($subscription['RenewalStatus']); ?>">
                                            <?php echo $subscription['RenewalStatus']; ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <a href="../subscriptions/view.php?id=<?php echo $subscription['SubscriptionID']; ?>" class="action-btn edit-btn" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($subscriptionCount > 5): ?>
                        <div class="see-all-link">
                            <a href="../subscriptions/index.php?scheme_id=<?php echo $schemeId; ?>" class="action-btn edit-btn">
                                <i class="fas fa-list"></i> See All Subscriptions
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No subscriptions found for this scheme.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="payments-tab">
                <?php if (count($recentPayments) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
                                    <td>₹<?php echo number_format($payment['Amount']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                            <?php echo $payment['Status']; ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <a href="../payments/view.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn edit-btn" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    // Get total payments count for comparison
                    try {
                        $paymentCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM Payments WHERE SchemeID = ?");
                        $paymentCountStmt->execute([$schemeId]);
                        $paymentCount = $paymentCountStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

                        if ($paymentCount > 5):
                    ?>
                            <div class="see-all-link">
                                <a href="../payments/index.php?scheme_id=<?php echo $schemeId; ?>" class="action-btn edit-btn">
                                    <i class="fas fa-list"></i> See All Payments
                                </a>
                            </div>
                    <?php
                        endif;
                    } catch (Exception $e) {
                        // Silently handle error
                    }
                    ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <p>No payments found for this scheme.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="activity-tab">
                <?php if (count($activityLogs) > 0): ?>
                    <div class="detail-card">
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="log-item">
                                <div class="log-user">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['UserName'] ?? 'Unknown User'); ?> (<?php echo $log['UserType']; ?>)
                                </div>
                                <div class="log-action"><?php echo htmlspecialchars($log['Action']); ?></div>
                                <div class="log-time">
                                    <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($log['CreatedAt'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity logs found for this scheme.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($installmentCount > 0): ?>
                <div class="tab-content" id="installments-tab">
                    <?php
                    try {
                        $installmentsStmt = $conn->prepare("
                        SELECT * FROM Installments 
                        WHERE SchemeID = ? 
                        ORDER BY InstallmentNumber ASC
                    ");
                        $installmentsStmt->execute([$schemeId]);
                        $installments = $installmentsStmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($installments) > 0):
                    ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Installment</th>
                                        <th>Amount</th>
                                        <th>Draw Date</th>
                                        <th>Status</th>
                                        <th class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($installments as $installment): ?>
                                        <tr>
                                            <td><?php echo $installment['InstallmentNumber']; ?></td>
                                            <td><?php echo htmlspecialchars($installment['InstallmentName'] ?? 'Installment ' . $installment['InstallmentNumber']); ?></td>
                                            <td>₹<?php echo number_format($installment['Amount']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($installment['DrawDate'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($installment['Status']); ?>">
                                                    <?php echo $installment['Status']; ?>
                                                </span>
                                            </td>
                                            <td class="no-print">
                                                <a href="./installment?id=<?php echo $installment['InstallmentID']; ?>" class="action-btn edit-btn" style="padding: 5px 10px; font-size: 12px;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>No installments defined for this scheme yet.</p>
                            </div>
                    <?php
                        endif;
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Failed to load installments: ' . $e->getMessage() . '</div>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
        });

        // Status change confirmation
        function confirmStatusChange(action, schemeId) {
            const actionText = action === 'activate' ? 'activate' : 'deactivate';
            const confirmed = confirm(`Are you sure you want to ${actionText} this scheme?`);

            if (confirmed) {
                window.location.href = `update_status.php?id=${schemeId}&action=${action}`;
            }
        }

        // Print customization
        function beforePrint() {
            // Add any pre-print modifications here
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'block';
            });
        }

        function afterPrint() {
            // Reset after printing
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = '';
                content.classList.remove('active');
            });

            document.querySelector('.tab[data-tab="subscriptions"]').classList.add('active');
            document.getElementById('subscriptions-tab').classList.add('active');
        }

        if (window.matchMedia) {
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addListener(function(mql) {
                if (mql.matches) {
                    beforePrint();
                } else {
                    afterPrint();
                }
            });
        }

        window.onbeforeprint = beforePrint;
        window.onafterprint = afterPrint;

        // Add a confirmation for printing
        document.querySelector('.print-btn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Do you want to print the scheme details?')) {
                window.print();
            }
        });
    </script>
</body>

</html>