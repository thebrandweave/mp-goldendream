<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "schemes";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$promoterUniqueID = $_SESSION['promoter_id'];

// Get promoter's unique ID from Promoters table
try {
    $stmt = $conn->prepare("SELECT PromoterUniqueID FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$promoterUniqueID]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($promoter) {
        $promoterUniqueID = $promoter['PromoterUniqueID'];
    } else {
        throw new Exception("Promoter not found.");
    }
} catch (Exception $e) {
    header("Location: ../login.php");
    exit();
}

// Check if scheme ID and customer ID are provided
if (!isset($_GET['scheme_id']) || !isset($_GET['customer_id'])) {
    header("Location: index.php");
    exit();
}

$schemeId = $_GET['scheme_id'];
$customerId = $_GET['customer_id'];

try {
    // Get scheme details
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        throw new Exception("Scheme not found or inactive.");
    }

    // Get customer details
    $stmt = $conn->prepare("
        SELECT c.*, s.SubscriptionID, s.StartDate as SubscriptionStartDate, s.EndDate as SubscriptionEndDate
        FROM Customers c
        JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        LEFT JOIN Subscriptions s ON c.CustomerID = s.CustomerID AND s.SchemeID = ?
        WHERE c.CustomerID = ? AND p.PromoterUniqueID = ? AND c.Status = 'Active'
    ");
    $stmt->execute([$schemeId, $customerId, $promoterUniqueID]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception("Customer not found or not associated with this scheme.");
    }

    // Get all installments for this scheme
    $stmt = $conn->prepare("
        SELECT i.*, 
            (SELECT COUNT(*) FROM Payments p 
             WHERE p.InstallmentID = i.InstallmentID 
             AND p.CustomerID = ? 
             AND p.Status = 'Verified') as IsPaid
        FROM Installments i 
        WHERE i.SchemeID = ? 
        ORDER BY i.InstallmentNumber ASC
    ");
    $stmt->execute([$customerId, $schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Installments | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            padding: 24px;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding-top: calc(var(--topbar-height) + 24px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: rgb(11, 90, 68);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.1);
        }

        .customer-info {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .customer-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .customer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
        }

        .customer-details {
            flex: 1;
        }

        .customer-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .customer-meta {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .installments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .installment-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
        }

        .installment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .installment-number {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .installment-amount {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .installment-details {
            margin-bottom: 16px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: var(--text-secondary);
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .installment-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-paid {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(127, 140, 141, 0.1);
            color: var(--text-secondary);
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 16px;
            }

            .installments-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="page-header">
                <div class="header-actions">
                    <a href="subscriptions.php?scheme_id=<?php echo $schemeId; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Subscriptions
                    </a>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="customer-info">
                    <div class="customer-header">
                        <div class="customer-avatar">
                            <?php echo strtoupper(substr($customer['Name'], 0, 1)); ?>
                        </div>
                        <div class="customer-details">
                            <div class="customer-name"><?php echo htmlspecialchars($customer['Name']); ?></div>
                            <div class="customer-meta">
                                <div>ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></div>
                                <div>Contact: <?php echo htmlspecialchars($customer['Contact']); ?></div>
                                <?php if (!empty($customer['Email'])): ?>
                                    <div>Email: <?php echo htmlspecialchars($customer['Email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="scheme-details">
                        <div style="font-weight: 600; margin-bottom: 8px;"><?php echo htmlspecialchars($scheme['SchemeName']); ?></div>
                        <div>Monthly Payment: ₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></div>
                        <div>Total Payments: <?php echo $scheme['TotalPayments']; ?> months</div>
                        <div>Subscription Period: <?php echo date('d M Y', strtotime($customer['SubscriptionStartDate'])); ?> to <?php echo date('d M Y', strtotime($customer['SubscriptionEndDate'])); ?></div>
                    </div>
                </div>

                <div class="installments-grid">
                    <?php foreach ($installments as $installment): ?>
                        <div class="installment-card">
                            <div class="installment-header">
                                <div class="installment-number">Installment #<?php echo $installment['InstallmentNumber']; ?></div>
                                <div class="installment-amount">₹<?php echo number_format($installment['Amount'], 2); ?></div>
                            </div>
                            <div class="installment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Draw Date</span>
                                    <span class="detail-value"><?php echo date('d M Y', strtotime($installment['DrawDate'])); ?></span>
                                </div>
                                <?php if (!empty($installment['Benefits'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Benefits</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($installment['Benefits']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="installment-status <?php echo $installment['IsPaid'] ? 'status-paid' : 'status-pending'; ?>">
                                <i class="fas fa-<?php echo $installment['IsPaid'] ? 'check-circle' : 'clock'; ?>"></i>
                                <?php echo $installment['IsPaid'] ? 'Paid' : 'Pending'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            adjustContent();
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 