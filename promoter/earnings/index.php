<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "earnings";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching profile data";
    $messageType = "error";
}

// Get total earnings from verified payments
try {
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN p.Status = 'Verified' THEN (p.Amount * pr.Commission / 100) ELSE 0 END) as TotalEarnings,
            COUNT(DISTINCT CASE WHEN p.Status = 'Verified' THEN p.PaymentID END) as TotalVerifiedPayments
        FROM Payments p
        JOIN Promoters pr ON p.PromoterID = pr.PromoterID
        WHERE p.PromoterID = ?
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $earnings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching earnings data";
    $messageType = "error";
}

// Get recent earnings transactions
try {
    $stmt = $conn->prepare("
        SELECT 
            p.PaymentID,
            p.Amount,
            p.VerifiedAt,
            c.Name as CustomerName,
            s.SchemeName,
            (p.Amount * pr.Commission / 100) as CommissionEarned
        FROM Payments p
        JOIN Promoters pr ON p.PromoterID = pr.PromoterID
        JOIN Customers c ON p.CustomerID = c.CustomerID
        JOIN Schemes s ON p.SchemeID = s.SchemeID
        WHERE p.PromoterID = ? AND p.Status = 'Verified'
        ORDER BY p.VerifiedAt DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching recent transactions";
    $messageType = "error";
}

// Get team earnings if promoter has team members
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT p.PromoterID) as TeamSize,
            SUM(CASE WHEN pay.Status = 'Verified' THEN (pay.Amount * p.Commission / 100) ELSE 0 END) as TeamEarnings
        FROM Promoters p
        LEFT JOIN Payments pay ON p.PromoterID = pay.PromoterID
        WHERE p.ParentPromoterID = ?
    ");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $teamStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching team statistics";
    $messageType = "error";
}

// Get promoter wallet data
try {
    $stmt = $conn->prepare("
        SELECT 
            pw.BalanceAmount,
            pw.Message,
            pw.LastUpdated,
            (
                SELECT COUNT(*) 
                FROM WalletLogs wl 
                WHERE wl.PromoterUniqueID = pw.PromoterUniqueID
            ) as TotalTransactions
        FROM PromoterWallet pw
        WHERE pw.PromoterUniqueID = ?
    ");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $walletData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent wallet transactions
    $stmt = $conn->prepare("
        SELECT Amount, Message, CreatedAt, TransactionType
        FROM WalletLogs 
        WHERE PromoterUniqueID = ?
        ORDER BY CreatedAt DESC
        LIMIT 5
    ");
    $stmt->execute([$promoter['PromoterUniqueID']]);
    $walletTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching wallet data";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Kannada:wght@400;500;600&display=swap" rel="stylesheet">
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
            --Topprimary-color: #3a7bd5;
            --Topsecondary-color: #00d2ff;
            --Toptext-dark: #2c3e50;
            --Toptext-light: #7f8c8d;
            --topborder-color: #e5e9f2;
            --Topbg-light: #f8f9fa;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        /* Add testing notice styles */
        .testing-notice {
            background: linear-gradient(135deg, #ffd700, #ffa500);
            color: #000;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            animation: pulse 2s infinite;
        }

        .testing-notice i {
            font-size: 20px;
            margin-top: 2px;
        }

        .notice-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .notice-text {
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
        }

        .notice-text.kannada {
            font-family: 'Noto Sans Kannada', sans-serif;
            font-size: 13px;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }

            100% {
                opacity: 1;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--Toptext-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 24px;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            padding-top: calc(var(--topbar-height) + 24px) !important;
        }

        .earnings-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-areas:
                "sidebar main"
                "quick-actions main";
            gap: 25px;
            padding: 0 15px;
        }

        .earnings-sidebar {
            grid-area: sidebar;
            background: white;
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            height: fit-content;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease;
        }

        .earnings-stats {
            margin-top: 20px;
        }

        .stat-card {
            background: var(--Topbg-light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            text-align: left;
            transition: transform var(--transition-speed) ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 14px;
            color: var(--Toptext-light);
            margin-bottom: 5px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: 600;
            color: var(--Topprimary-color);
        }

        .main-content {
            grid-area: main;
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease 0.1s backwards;
        }

        .section-title {
            color: var(--Toptext-dark);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
        }

        .section-title i {
            color: var(--primary-color);
        }

        .transactions-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--topborder-color);
        }

        .transactions-table th {
            background: var(--Topbg-light);
            font-weight: 500;
            color: var(--Toptext-light);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .transactions-table tr:hover {
            background: var(--Topbg-light);
        }

        .commission-badge {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1200px) {
            .earnings-container {
                padding: 0 10px;
            }

            .earnings-sidebar {
                padding: 25px;
            }

            .main-content {
                padding: 25px;
            }
        }

        @media (max-width: 992px) {
            .content-wrapper {
                margin-left: 0;
                padding: 16px;
            }

            .earnings-container {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "sidebar"
                    "quick-actions"
                    "main";
                gap: 20px;
            }

            .earnings-sidebar {
                padding: 24px;
            }

            .main-content {
                padding: 24px;
            }

            .section-title {
                font-size: 1.2rem;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .value {
                font-size: 20px;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 12px;
            }

            .earnings-container {
                margin: 10px auto;
            }

            .earnings-sidebar {
                padding: 20px;
                border-radius: 16px;
            }

            .main-content {
                padding: 20px;
                border-radius: 16px;
            }

            .section-title {
                font-size: 1.1rem;
                padding-bottom: 10px;
            }

            .transactions-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 12px;
                font-size: 13px;
            }

            .stat-card {
                padding: 12px;
                margin-bottom: 10px;
            }

            .stat-card h3 {
                font-size: 13px;
            }

            .stat-card .value {
                font-size: 18px;
            }

            .commission-badge {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .content-wrapper {
                padding: 8px;
            }

            .earnings-container {
                margin: 5px auto;
            }

            .earnings-sidebar {
                padding: 16px;
                border-radius: 12px;
            }

            .main-content {
                padding: 16px;
                border-radius: 12px;
            }

            .section-title {
                font-size: 1rem;
                gap: 8px;
                margin-bottom: 15px;
            }

            .section-title i {
                font-size: 16px;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 10px;
                font-size: 12px;
            }

            .stat-card {
                padding: 10px;
                margin-bottom: 8px;
            }

            .stat-card h3 {
                font-size: 12px;
            }

            .stat-card .value {
                font-size: 16px;
            }

            .commission-badge {
                padding: 3px 6px;
                font-size: 10px;
            }
        }
    </style>
</head>

<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="testing-notice">
            <i class="fas fa-flask"></i>
            <div class="notice-content">
                <span class="notice-text">This feature is currently under testing. The amounts and logs shown are not final and may change upon verification.</span>
                <span class="notice-text kannada">ಈ ವೈಶಿಷ್ಟ್ಯವು ಪ್ರಸ್ತುತ ಪರೀಕ್ಷೆಯಲ್ಲಿದೆ. ತೋರಿಸಲಾದ ಮೊತ್ತಗಳು ಮತ್ತು ಲಾಗ್‌ಗಳು ಅಂತಿಮವಾಗಿಲ್ಲ ಮತ್ತು ಪರಿಶೀಲನೆಯ ಮೇಲೆ ಬದಲಾಗಬಹುದು</span>
            </div>
        </div>
        <div class="earnings-container">
            <div class="earnings-sidebar">
                <h2>Earnings Overview</h2>
                <div class="earnings-stats">
                    <div class="stat-card">
                        <h3>Commission Rate</h3>
                        <div class="value"><?php echo htmlspecialchars($promoter['Commission'] ?? 0); ?></div>
                    </div>
                    <?php if ($teamStats && $teamStats['TeamSize'] > 0): ?>
                        <div class="stat-card">
                            <h3>Team Size</h3>
                            <div class="value"><?php echo number_format($teamStats['TeamSize']); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Team Earnings</h3>
                            <div class="value">₹<?php echo number_format($teamStats['TeamEarnings'] ?? 0, 2); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($walletData): ?>
                        <div class="stat-card">
                            <h3>Wallet Balance</h3>
                            <div class="value">₹<?php echo number_format($walletData['BalanceAmount'] ?? 0, 2); ?></div>
                            <?php if ($walletData['Message']): ?>
                                <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                                    <?php echo htmlspecialchars($walletData['Message']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($walletData['LastUpdated']): ?>
                                <div style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                    Last updated: <?php echo date('d M Y, h:i A', strtotime($walletData['LastUpdated'])); ?>
                                </div>
                            <?php endif; ?>
                            <div style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                                Total Transactions: <?php echo number_format($walletData['TotalTransactions'] ?? 0); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="main-content">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Wallet Transactions
                </h2>
                <?php if (!empty($walletTransactions)): ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($walletTransactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d M Y, h:i A', strtotime($transaction['CreatedAt'])); ?></td>
                                    <td style="color: <?php echo $transaction['TransactionType'] === 'Debit' ? 'var(--error-color)' : 'var(--success-color)'; ?>">
                                        <?php echo ($transaction['TransactionType'] === 'Credit' ? '+' : '') . '₹' . number_format($transaction['Amount'], 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['Message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <p>No wallet transactions found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>