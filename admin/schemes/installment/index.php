<?php
session_start();

$menuPath = "../../";
$currentPage = "schemes";

require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Check if installment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No installment specified.";
    header("Location: ../index.php");
    exit();
}

$installmentId = intval($_GET['id']);

// Get installment details
try {
    $stmt = $conn->prepare("
        SELECT i.*, s.SchemeName
        FROM Installments i
        JOIN Schemes s ON i.SchemeID = s.SchemeID
        WHERE i.InstallmentID = ?
    ");
    $stmt->execute([$installmentId]);
    $installment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$installment) {
        $_SESSION['error_message'] = "Installment not found.";
        header("Location: ../index.php");
        exit();
    }

    // Get scheme details
    $schemeId = $installment['SchemeID'];
    $schemeStmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ?");
    $schemeStmt->execute([$schemeId]);
    $scheme = $schemeStmt->fetch(PDO::FETCH_ASSOC);

    // Get payment count for this installment
    $paymentStmt = $conn->prepare("
        SELECT COUNT(*) as PaymentCount
        FROM Payments
        WHERE InstallmentID = ?
    ");
    $paymentStmt->execute([$installmentId]);
    $paymentData = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    $paymentCount = $paymentData['PaymentCount'];

    // Get verified payment count
    $verifiedStmt = $conn->prepare("
        SELECT COUNT(*) as VerifiedCount
        FROM Payments
        WHERE InstallmentID = ? AND Status = 'Verified'
    ");
    $verifiedStmt->execute([$installmentId]);
    $verifiedData = $verifiedStmt->fetch(PDO::FETCH_ASSOC);
    $verifiedCount = $verifiedData['VerifiedCount'];

    // Get total collection amount for this installment
    $collectionStmt = $conn->prepare("
        SELECT COALESCE(SUM(Amount), 0) as TotalCollection
        FROM Payments
        WHERE InstallmentID = ? AND Status = 'Verified'
    ");

    
    $collectionStmt->execute([$installmentId]);
    $collectionData = $collectionStmt->fetch(PDO::FETCH_ASSOC);
    $totalCollection = $collectionData['TotalCollection'];

    // Get recent payments for this installment (limited to 10)
    $recentPaymentsStmt = $conn->prepare("
        SELECT p.*, c.Name as CustomerName, c.CustomerUniqueID
        FROM Payments p
        JOIN Customers c ON p.CustomerID = c.CustomerID
        WHERE p.InstallmentID = ?
        ORDER BY p.SubmittedAt DESC
        LIMIT 10
    ");
    $recentPaymentsStmt->execute([$installmentId]);
    $recentPayments = $recentPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity logs related to this installment (limited to 10)
    $logStmt = $conn->prepare("
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
    $logStmt->execute(['%' . $installment['InstallmentName'] . '%']);
    $activityLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: ../index.php");
    exit();
}

include("../../components/sidebar.php");
include("../../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Installment - <?php echo htmlspecialchars($installment['InstallmentName'] ?? 'Installment ' . $installment['InstallmentNumber']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .installment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
            flex-wrap: wrap;
            gap: 20px;
        }

        .installment-info {
            flex: 1;
            min-width: 300px;
        }

        .installment-image {
            flex: 1;
            min-width: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .installment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .installment-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .installment-subtitle {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .installment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .meta-value {
            font-size: 1rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #3498db;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 20px;
            overflow-x: auto;
            white-space: nowrap;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #7f8c8d;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab:hover {
            color: #3498db;
        }

        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
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
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #eaeaea;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eaeaea;
            color: #2c3e50;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-completed {
            background: rgba(52, 152, 219, 0.1);
            color: #2980b9;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: rgba(52, 152, 219, 0.1);
            color: #2980b9;
        }

        .edit-btn:hover {
            background: rgba(52, 152, 219, 0.2);
        }

        .delete-btn {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .delete-btn:hover {
            background: rgba(231, 76, 60, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .benefits-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .benefits-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .benefits-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .benefits-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .benefits-list li:last-child {
            border-bottom: none;
        }

        .benefits-list li i {
            color: #27ae60;
        }

        .activity-log {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .activity-user {
            font-weight: 500;
            color: #2c3e50;
        }

        .activity-time {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        .activity-action {
            color: #34495e;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #3498db;
        }

        .installment-img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
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
        }

        .no-image i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .no-image span {
            font-size: 1.1rem;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .installment-header {
                flex-direction: column;
            }

            .installment-image {
                width: 100%;
                margin-bottom: 20px;
            }

            .installment-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .data-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="../view.php?id=<?php echo $schemeId; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Scheme
        </a>

        <div class="installment-header">
            <div class="installment-info">
                <h1 class="installment-title">
                    <?php echo htmlspecialchars($installment['InstallmentName'] ?? 'Installment ' . $installment['InstallmentNumber']); ?>
                </h1>
                <div class="installment-subtitle">
                    <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                </div>
                <div class="installment-meta">
                    <div class="meta-item">
                        <span class="meta-label">Installment Number</span>
                        <span class="meta-value">#<?php echo $installment['InstallmentNumber']; ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Amount</span>
                        <span class="meta-value">₹<?php echo number_format($installment['Amount'], 2); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Draw Date</span>
                        <span class="meta-value"><?php echo date('M d, Y', strtotime($installment['DrawDate'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Status</span>
                        <span class="meta-value">
                            <span class="status-badge status-<?php echo strtolower($installment['Status']); ?>">
                                <?php echo $installment['Status']; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="installment-image">
                <?php if (!empty($installment['ImageURL'])): ?>
                    <img src="../../../<?php echo htmlspecialchars($installment['ImageURL']); ?>" alt="<?php echo htmlspecialchars($installment['InstallmentName'] ?? 'Installment ' . $installment['InstallmentNumber']); ?>" class="installment-img">
                <?php else: ?>
                    <div class="no-image">
                        <i class="fas fa-image"></i>
                        <span>No image available</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="installment-actions">
                <a href="edit.php?id=<?php echo $installmentId; ?>" class="action-btn edit-btn">
                    <i class="fas fa-edit"></i> Edit Installment
                </a>
                <a href="delete.php?id=<?php echo $installmentId; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this installment? This action cannot be undone.');">
                    <i class="fas fa-trash"></i> Delete Installment
                </a>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">₹<?php echo number_format($totalCollection, 2); ?></div>
                <div class="stat-label">Total Collection</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value"><?php echo $paymentCount; ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $verifiedCount; ?></div>
                <div class="stat-label">Verified Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $paymentCount - $verifiedCount; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <?php if ($scheme['is_subscribed']): ?>
                <a href="view_benefits.php?scheme_id=<?php echo $scheme['SchemeID']; ?>" class="btn-subscribe">
                    <i class="fas fa-eye"></i> View Benefits
                </a>
            <?php else: ?>
                <a href="subscribe.php?scheme_id=<?php echo $scheme['SchemeID']; ?>" class="btn-subscribe">
                    <i class="fas fa-plus-circle"></i> Subscribe Now
                </a>
            <?php endif; ?>
        </div>

        <div class="tabs">
            <div class="tab active" data-tab="details">
                <i class="fas fa-info-circle"></i> Details
            </div>
            <div class="tab" data-tab="payments">
                <i class="fas fa-money-bill-wave"></i> Payments
            </div>
            <div class="tab" data-tab="activity">
                <i class="fas fa-history"></i> Activity Log
            </div>
        </div>

        <div class="tab-content active" id="details-tab">
            <div class="benefits-section">
                <h3 class="benefits-title">
                    <i class="fas fa-gift"></i> Benefits
                </h3>
                <?php if (!empty($installment['Benefits'])): ?>
                    <ul class="benefits-list">
                        <?php
                        $benefits = explode("\n", $installment['Benefits']);
                        foreach ($benefits as $benefit):
                            if (!empty(trim($benefit))):
                        ?>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo htmlspecialchars(trim($benefit)); ?>
                                </li>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </ul>
                <?php else: ?>
                    <p>No benefits defined for this installment.</p>
                <?php endif; ?>
            </div>

            <div class="benefits-section">
                <h3 class="benefits-title">
                    <i class="fas fa-info-circle"></i> Additional Information
                </h3>
                <div class="installment-meta" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                    <div class="meta-item">
                        <span class="meta-label">Created At</span>
                        <span class="meta-value"><?php echo date('M d, Y H:i', strtotime($installment['CreatedAt'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Updated At</span>
                        <span class="meta-value"><?php echo isset($installment['UpdatedAt']) ? date('M d, Y H:i', strtotime($installment['UpdatedAt'])) : 'Not available'; ?></span>
                    </div>
                    <?php if (!empty($installment['Description'])): ?>
                        <div class="meta-item" style="grid-column: span 2;">
                            <span class="meta-label">Description</span>
                            <span class="meta-value"><?php echo nl2br(htmlspecialchars($installment['Description'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
                                <td>
                                    <div><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo $payment['CustomerUniqueID']; ?></div>
                                </td>
                                <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                        <?php echo $payment['Status']; ?>
                                    </span>
                                </td>
                                <td class="no-print">
                                    <a href="../../payments/view.php?id=<?php echo $payment['PaymentID']; ?>" class="action-btn edit-btn" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-money-bill-wave"></i>
                    <p>No payments found for this installment.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="activity-tab">
            <div class="activity-log">
                <h3 class="activity-title">
                    <i class="fas fa-history"></i> Recent Activity
                </h3>
                <?php if (count($activityLogs) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($activityLogs as $log): ?>
                            <li class="activity-item">
                                <div class="activity-header">
                                    <span class="activity-user"><?php echo htmlspecialchars($log['UserName'] ?? 'Unknown User'); ?></span>
                                    <span class="activity-time"><?php echo date('M d, Y H:i', strtotime($log['CreatedAt'])); ?></span>
                                </div>
                                <div class="activity-action">
                                    <?php echo htmlspecialchars($log['Action']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity logs found for this installment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
        });
    </script>
</body>
</html>

