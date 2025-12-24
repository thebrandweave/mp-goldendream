<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "dashboard";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total balance
$stmt = $db->prepare("
    SELECT SUM(BalanceAmount) as total_balance 
    FROM Balances 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$balance_result = $stmt->fetch(PDO::FETCH_ASSOC);
$available_balance = $balance_result['total_balance'] ?? 0;

// Get current month winners
$stmt = $db->prepare("
    SELECT w.*, s.SchemeName 
    FROM Winners w
    JOIN Subscriptions sub ON w.UserID = sub.CustomerID
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    WHERE w.UserID = ? 
    AND w.UserType = 'Customer'
    AND MONTH(w.WinningDate) = MONTH(CURRENT_DATE())
    AND YEAR(w.WinningDate) = YEAR(CURRENT_DATE())
    ORDER BY w.WinningDate DESC
");
$stmt->execute([$userData['customer_id']]);
$current_month_winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get next payment due
$stmt = $db->prepare("
    SELECT s.SchemeName, s.MonthlyPayment, sub.StartDate, sub.EndDate
    FROM Subscriptions sub
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    WHERE sub.CustomerID = ? 
    AND sub.RenewalStatus = 'Active'
    ORDER BY sub.EndDate ASC
    LIMIT 1
");
$stmt->execute([$userData['customer_id']]);
$next_payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total active subscriptions
$stmt = $db->prepare("
    SELECT COUNT(*) as total_subscriptions
    FROM Subscriptions
    WHERE CustomerID = ? AND RenewalStatus = 'Active'
");
$stmt->execute([$userData['customer_id']]);
$subscription_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_subscriptions'];

// Get total prizes won
$stmt = $db->prepare("
    SELECT COUNT(*) as total_prizes
    FROM Winners
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$total_prizes = $stmt->fetch(PDO::FETCH_ASSOC)['total_prizes'];

// Get pending withdrawals
$stmt = $db->prepare("
    SELECT COUNT(*) as pending_withdrawals
    FROM Withdrawals
    WHERE UserID = ? AND UserType = 'Customer' AND Status = 'Pending'
");
$stmt->execute([$userData['customer_id']]);
$pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['pending_withdrawals'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --card-hover: #2A2D31;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .dashboard-container {
            padding: 16px;
            margin-top: 70px;
            transition: margin-left 0.3s ease;
            max-width: 1600px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .dashboard-header h2 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .dashboard-header p {
            font-size: 14px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 0 10px #2F9B7F;
        }

        .stats-icon {
            font-size: 18px;
            margin-bottom: 12px;
        }

        .stats-card h5 {
            font-size: 12px;
            margin-bottom: 6px;
        }

        .stats-card h3 {
            font-size: 20px;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .info-card {
            padding: 16px;
        }

        .info-card h4 {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card h4 i {
            color: #2F9B7F;
        }

        .winner-item {
            padding: 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.02);
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .winner-item:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .winner-item h5 {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .badge {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%) !important;
            color: #fff;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
        }

        .payment-due-date {
            font-size: 24px;
            font-weight: 600;
            color: #2F9B7F;
            margin: 16px 0;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 32px;
            color: #2F9B7F;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 14px;
        }

        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin-left: 0;
                padding: 8px;
            }
            .stats-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .info-cards {
                grid-template-columns: 1fr;
            }
            .dashboard-header {
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-header {
                font-size: 1.1rem;
                padding: 8px;
            }
            .stats-card {
                padding: 8px;
                font-size: 0.95rem;
            }
            .info-card {
                padding: 8px;
            }
        }

        @media (min-width: 769px) {
            .sidebar {
                /* width: 220px; */
            }

            .dashboard-container {
                /* margin-left: 220px; */
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($userData['customer_name']); ?>!</p>
            </div>

            <div class="stats-row">
                <div class="stats-card">
                    <i class="fas fa-wallet stats-icon"></i>
                    <div class="stats-info">
                        <h5>AVAILABLE BALANCE</h5>
                        <h3>₹<?php echo number_format($available_balance, 2); ?></h3>
                    </div>
                </div>
                <div class="stats-card">
                    <i class="fas fa-calendar-check stats-icon"></i>
                    <div class="stats-info">
                        <h5>ACTIVE SUBSCRIPTIONS</h5>
                        <h3><?php echo $subscription_count; ?></h3>
                    </div>
                </div>
                <div class="stats-card">
                    <i class="fas fa-trophy stats-icon"></i>
                    <div class="stats-info">
                        <h5>TOTAL PRIZES WON</h5>
                        <h3><?php echo $total_prizes; ?></h3>
                    </div>
                </div>
                <div class="stats-card">
                    <i class="fas fa-clock stats-icon"></i>
                    <div class="stats-info">
                        <h5>PENDING WITHDRAWALS</h5>
                        <h3><?php echo $pending_withdrawals; ?></h3>
                    </div>
                </div>
            </div>

            <div class="info-cards">
                <div class="info-card">
                    <h4><i class="fas fa-calendar-alt"></i> Next Payment Due</h4>
                    <?php if ($next_payment): ?>
                        <div class="payment-info">
                            <h5><?php echo htmlspecialchars($next_payment['SchemeName']); ?></h5>
                            <div class="payment-due-date">
                                <?php echo date('d M Y', strtotime($next_payment['EndDate'])); ?>
                            </div>
                            <div class="payment-amount">
                                Amount: ₹<?php echo number_format($next_payment['MonthlyPayment'], 2); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Active Subscriptions</h3>
                            <p>You don't have any active subscriptions.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h4><i class="fas fa-trophy"></i> Recent Winners</h4>
                    <?php
                    // Update the SQL query to get recent winners instead of current month
                    $stmt = $db->prepare("
                        SELECT w.*, s.SchemeName, w.WinningDate 
                        FROM Winners w
                        JOIN Subscriptions sub ON w.UserID = sub.CustomerID
                        JOIN Schemes s ON sub.SchemeID = s.SchemeID
                        WHERE w.UserID = ? 
                        AND w.UserType = 'Customer'
                        ORDER BY w.WinningDate DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$userData['customer_id']]);
                    $recent_winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($recent_winners)): ?>
                        <?php foreach ($recent_winners as $winner): ?>
                            <div class="winner-item">
                                <h5><?php echo htmlspecialchars($winner['SchemeName']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge"><?php echo $winner['PrizeType']; ?></span>
                                    <small style="color: var(--text-secondary);">
                                        <?php echo date('d M Y', strtotime($winner['WinningDate'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-trophy"></i>
                            <h3>No Winners Yet</h3>
                            <p>Keep participating to win prizes!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function adjustLayout() {
            const windowWidth = window.innerWidth;
            const container = document.querySelector('.dashboard-container');

            // if (windowWidth <= 768) {
            //     container.style.marginLeft = '70px';
            // } else {
            //     container.style.marginLeft = '220px';
            // }
        }

        window.addEventListener('load', adjustLayout);
        window.addEventListener('resize', adjustLayout);
    </script>
</body>

</html>