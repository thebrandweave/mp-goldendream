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

// Check if scheme ID is provided
if (!isset($_GET['scheme_id'])) {
    header("Location: index.php");
    exit();
}
echo $promoterUniqueID;

$schemeId = $_GET['scheme_id'];

try {
    // Get scheme details
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        throw new Exception("Scheme not found or inactive.");
    }

    // Handle subscription actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && isset($_POST['customer_id'])) {
            $customerId = $_POST['customer_id'];
            $action = $_POST['action'];

            // Verify if customer belongs to this promoter
            $stmt = $conn->prepare("SELECT CustomerID FROM Customers WHERE CustomerID = ? AND PromoterID = ?");
            $stmt->execute([$customerId, $promoterUniqueID]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid customer.");
            }

            if ($action === 'add') {
                // Check if customer already has an active subscription
                $stmt = $conn->prepare("
                    SELECT SubscriptionID FROM Subscriptions 
                    WHERE CustomerID = ? AND SchemeID = ? AND RenewalStatus = 'Active'
                ");
                $stmt->execute([$customerId, $schemeId]);
                if ($stmt->fetch()) {
                    throw new Exception("Customer already has an active subscription to this scheme.");
                }

                // Add new subscription
                $stmt = $conn->prepare("
                    INSERT INTO Subscriptions (CustomerID, SchemeID, StartDate, EndDate, RenewalStatus)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 'Active')
                ");
                $stmt->execute([$customerId, $schemeId, $scheme['TotalPayments']]);
                $message = "Customer successfully added to the scheme.";
                $messageType = "success";

            } elseif ($action === 'remove') {
                // Update subscription status to cancelled
                $stmt = $conn->prepare("
                    UPDATE Subscriptions 
                    SET RenewalStatus = 'Cancelled' 
                    WHERE CustomerID = ? AND SchemeID = ? AND RenewalStatus = 'Active'
                ");
                $stmt->execute([$customerId, $schemeId]);
                $message = "Customer successfully removed from the scheme.";
                $messageType = "success";
            }
        }
    }

    // Get all customers for this promoter with their subscription status
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.SubscriptionID,
            s.StartDate as SubscriptionStartDate,
            s.EndDate as SubscriptionEndDate,
            s.RenewalStatus,
            (
                SELECT COUNT(*) 
                FROM Payments p 
                WHERE p.CustomerID = c.CustomerID 
                AND p.Status = 'Verified'
            ) as TotalVerifiedPayments
        FROM Customers c
        JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        LEFT JOIN Subscriptions s ON c.CustomerID = s.CustomerID AND s.SchemeID = ?
        WHERE p.PromoterUniqueID = ? AND c.Status = 'Active'
        ORDER BY c.Name ASC
    ");
    $stmt->execute([$schemeId, $promoterUniqueID]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Subscriptions | Golden Dreams</title>
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

        .btn-danger {
            background: var(--error-color);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #c0392b;
            box-shadow: 0 4px 6px rgba(231, 76, 60, 0.2);
        }

        .scheme-info {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .scheme-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .scheme-details {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .search-filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            padding-left: 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .customers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .customer-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
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

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .customer-contact {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .customer-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .meta-item {
            background: var(--bg-light);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .meta-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .subscription-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-expired {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .status-none {
            background: rgba(127, 140, 141, 0.1);
            color: var(--text-secondary);
        }

        .customer-actions {
            display: flex;
            gap: 12px;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 16px;
            }

            .search-filters {
                flex-direction: column;
            }

            .customers-grid {
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
                    <a href="view.php?id=<?php echo $schemeId; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Scheme
                    </a>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="scheme-info">
                    <h1 class="scheme-title"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h1>
                    <div class="scheme-details">
                        <div>Monthly Payment: ₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></div>
                        <div>Total Payments: <?php echo $scheme['TotalPayments']; ?> months</div>
                    </div>
                </div>

                <div class="search-filters">
                    <div class="search-box">
                        <input type="text" id="customerSearch" placeholder="Search customers..." class="search-input">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>

                <div class="customers-grid">
                    <?php if (empty($customers)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 48px; background: white; border-radius: 16px; box-shadow: var(--card-shadow);">
                            <i class="fas fa-users" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 16px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Customers Found</h3>
                            <p style="color: var(--text-secondary);">You don't have any active customers to add to this scheme.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px;">
                            <?php foreach ($customers as $customer): ?>
                                <div style="background: white; border-radius: 16px; box-shadow: var(--card-shadow); padding: 20px; transition: transform 0.2s;">
                                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                                        <div style="width: 60px; height: 60px; background: var(--primary-light); color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 600;">
                                            <?php echo strtoupper(substr($customer['Name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h3 style="font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 5px 0;">
                                                <?php echo htmlspecialchars($customer['Name']); ?>
                                            </h3>
                                            <div style="font-size: 13px; color: var(--text-secondary);">
                                                ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 20px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                            <i class="fas fa-phone" style="color: var(--primary-color);"></i>
                                            <span style="color: var(--text-primary);"><?php echo htmlspecialchars($customer['Contact']); ?></span>
                                        </div>
                                        <?php if (!empty($customer['Email'])): ?>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <i class="fas fa-envelope" style="color: var(--primary-color);"></i>
                                                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($customer['Email']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="margin-bottom: 20px;">
                                        <?php if ($customer['RenewalStatus'] === 'Active'): ?>
                                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; background: rgba(46, 204, 113, 0.1); color: var(--success-color);">
                                                <i class="fas fa-check-circle"></i>
                                                Active until <?php echo date('d M Y', strtotime($customer['SubscriptionEndDate'])); ?>
                                            </div>
                                        <?php elseif ($customer['RenewalStatus'] === 'Expired'): ?>
                                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; background: rgba(231, 76, 60, 0.1); color: var(--error-color);">
                                                <i class="fas fa-times-circle"></i>
                                                Expired
                                            </div>
                                        <?php else: ?>
                                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; background: rgba(127, 140, 141, 0.1); color: var(--text-secondary);">
                                                <i class="fas fa-info-circle"></i>
                                                Not subscribed
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <div>
                                            <div style="font-size: 24px; font-weight: 600; color: var(--primary-color);">
                                                <?php echo $customer['TotalVerifiedPayments']; ?>
                                            </div>
                                            <div style="font-size: 13px; color: var(--text-secondary);">
                                                Verified payments
                                            </div>
                                        </div>
                                    </div>

                                    <div style="text-align: center;">
                                        <?php if ($customer['RenewalStatus'] === 'Active'): ?>
                                            <a href="installments.php?scheme_id=<?php echo $schemeId; ?>&customer_id=<?php echo $customer['CustomerID']; ?>" 
                                               style="display: inline-block; width: 100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: background 0.2s;">
                                                <i class="fas fa-list"></i>
                                                View Installments
                                            </a>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline-block; width: 100%;">
                                                <input type="hidden" name="customer_id" value="<?php echo $customer['CustomerID']; ?>">
                                                <input type="hidden" name="action" value="add">
                                                <button type="submit" 
                                                        style="width: 100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s;">
                                                    <i class="fas fa-user-plus"></i>
                                                    Add to Scheme
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('customerSearch');
            const customerCards = document.querySelectorAll('.customer-card');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                customerCards.forEach(card => {
                    const name = card.querySelector('.customer-name').textContent.toLowerCase();
                    const contact = card.querySelector('.customer-contact').textContent.toLowerCase();

                    if (name.includes(searchTerm) || contact.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
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

            adjustContent();
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 