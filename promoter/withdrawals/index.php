<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "withdrawals";
$promoterUniqueID = $_SESSION['promoter_id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';

try {
    // Get promoter's wallet balance
    $stmt = $conn->prepare("
        SELECT 
            pw.BalanceAmount,
            pw.Message,
            pw.LastUpdated,
            p.PromoterUniqueID,
            (
                SELECT COUNT(*) 
                FROM WalletLogs wl 
                WHERE wl.PromoterUniqueID = p.PromoterUniqueID
            ) as TotalTransactions
        FROM PromoterWallet pw
        JOIN Promoters p ON p.PromoterID = pw.UserID
        WHERE p.PromoterID = ?
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set default values if no wallet found
    $balance = $wallet ? $wallet['BalanceAmount'] : 0;
    $walletMessage = $wallet ? $wallet['Message'] : '';
    $lastUpdated = $wallet ? $wallet['LastUpdated'] : null;
    $totalTransactions = $wallet ? $wallet['TotalTransactions'] : 0;
    $promoterUniqueID = $wallet ? $wallet['PromoterUniqueID'] : null;
    // Debug log
    error_log("Promoter ID from session: " . $_SESSION['promoter_id']);
    error_log("Promoter Unique ID from wallet: " . $promoterUniqueID);

    // Get recent wallet transactions
    $stmt = $conn->prepare("
        SELECT Amount, Message, CreatedAt, TransactionType
        FROM WalletLogs 
        WHERE PromoterUniqueID = ?
        ORDER BY CreatedAt DESC
        LIMIT 5
    ");
    $stmt->execute([$promoterUniqueID]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get promoter's bank details
    $stmt = $conn->prepare("
        SELECT BankAccountName, BankAccountNumber, IFSCCode, BankName 
        FROM Promoters 
        WHERE PromoterID = ?
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $bankDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get withdrawal history
    $stmt = $conn->prepare("
        SELECT w.*, a.Name as AdminName
        FROM Withdrawals w
        LEFT JOIN Admins a ON w.AdminID = a.AdminID
        WHERE w.UserID = ? AND w.UserType = 'Promoter'
        ORDER BY w.RequestedAt DESC
    ");
    $stmt->execute([$promoterUniqueID]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle withdrawal request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_amount'])) {
        $amount = floatval($_POST['withdraw_amount']);

        // Validate amount
        if ($amount <= 0) {
            throw new Exception("Invalid withdrawal amount.");
        }

        if ($amount > $balance) {
            throw new Exception("Insufficient balance.");
        }

        // Start transaction
        $conn->beginTransaction();

        try {
            // Debug log before withdrawal
            error_log("Creating withdrawal with PromoterUniqueID: " . $promoterUniqueID);

            // Create withdrawal request
            $stmt = $conn->prepare("
                INSERT INTO Withdrawals (UserID, UserType, Amount, Status, Remarks)
                VALUES (?, 'Promoter', ?, 'Pending', ?)
            ");
            $remarks = "Withdrawal request for ₹" . number_format($amount, 2);
            $stmt->execute([$_SESSION['promoter_id'], $amount, $remarks]);

            // Update wallet balance
            $stmt = $conn->prepare("
                UPDATE PromoterWallet 
                SET BalanceAmount = BalanceAmount - ?,
                    LastUpdated = CURRENT_TIMESTAMP
                WHERE UserID = ?
            ");
            $stmt->execute([$amount, $_SESSION['promoter_id']]);

            // Add wallet log entry
            $stmt = $conn->prepare("
                INSERT INTO WalletLogs (PromoterUniqueID, Amount, Message, TransactionType)
                VALUES (?, ?, ?, 'Debit')
            ");
            $logMessage = "Withdrawal request of ₹" . number_format($amount, 2);
            $stmt->execute([$promoterUniqueID, -$amount, $logMessage]);

            // Add activity log
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress)
                VALUES (?, 'Promoter', ?, ?)
            ");
            $action = "Requested withdrawal of ₹" . number_format($amount, 2);
            $stmt->execute([$_SESSION['promoter_id'], $action, $_SERVER['REMOTE_ADDR']]);

            // Commit transaction
            $conn->commit();

            $message = "Withdrawal request submitted successfully.";
            $messageType = "success";

            // Refresh the page to show updated balance
            header("Location: index.php?success=1");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw new Exception("Failed to process withdrawal: " . $e->getMessage());
        }
    }
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
    <title>Withdrawals | Golden Dreams</title>
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
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            padding: 24px;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            padding-top: calc(var(--topbar-height) + 24px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .balance-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform var(--transition-speed) ease;
            animation: slideIn 0.5s ease;
        }

        .balance-card:hover {
            transform: translateY(-5px);
        }

        .balance-title {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .balance-title i {
            color: var(--primary-color);
        }

        .balance-amount {
            font-size: 42px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 24px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .withdraw-form {
            background: white;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease 0.1s backwards;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            transition: all var(--transition-speed) ease;
            background: var(--bg-light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: white;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--primary-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 106, 80, 0.2);
        }

        .btn:disabled {
            background: var(--border-color);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .withdrawal-history {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease 0.2s backwards;
        }

        .history-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-title i {
            color: var(--primary-color);
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .history-table th,
        .history-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .history-table th {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .history-table tr:hover {
            background: var(--bg-light);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .status-approved {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
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

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 16px;
            }

            .balance-card,
            .withdraw-form,
            .withdrawal-history {
                padding: 24px;
                border-radius: 16px;
            }

            .balance-amount {
                font-size: 32px;
            }

            .history-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Testing notice styles */
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
    </style>
</head>

<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <!-- <div class="content-wrapper">
        <div class="testing-notice">
            <i class="fas fa-flask"></i>
            <div class="notice-content">
                <span class="notice-text">This feature is currently under testing. The amounts and logs shown are not final and may change upon verification.</span>
                <span class="notice-text kannada">ಈ ವೈಶಿಷ್ಟ್ಯವು ಪ್ರಸ್ತುತ ಪರೀಕ್ಷೆಯಲ್ಲಿದೆ. ತೋರಿಸಲಾದ ಮೊತ್ತಗಳು ಮತ್ತು ಲಾಗ್‌ಗಳು ಅಂತಿಮವಾಗಿಲ್ಲ ಮತ್ತು ಪರಿಶೀಲನೆಯ ಮೇಲೆ ಬದಲಾಗಬಹುದು</span>
            </div>
        </div> -->
        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="balance-card">
                <h2 class="balance-title">
                    <i class="fas fa-wallet"></i>
                    Available Balance
                </h2>
                <div class="balance-amount">₹<?php echo number_format($balance, 2); ?></div>
                <?php if ($walletMessage): ?>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                        <?php echo htmlspecialchars($walletMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if ($lastUpdated): ?>
                    <div style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                        Last updated: <?php echo date('d M Y, h:i A', strtotime($lastUpdated)); ?>
                    </div>
                <?php endif; ?>
                <div style="color: var(--text-secondary); font-size: 12px; margin-top: 4px;">
                    Total Transactions: <?php echo number_format($totalTransactions); ?>
                </div>

                <?php if (!empty($recentTransactions)): ?>
                    <div style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <h3 style="font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-history"></i>
                            Recent Transactions
                        </h3>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 13px; padding: 8px; border-radius: 8px; background: var(--bg-light);">
                                <span style="color: var(--text-secondary);">
                                    <?php echo date('d M Y', strtotime($transaction['CreatedAt'])); ?>
                                </span>
                                <span style="color: <?php echo $transaction['TransactionType'] === 'Debit' ? 'var(--error-color)' : 'var(--success-color)'; ?>; font-weight: 500;">
                                    <?php echo ($transaction['TransactionType'] === 'Credit' ? '+' : '') . '₹' . number_format($transaction['Amount'], 2); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="withdraw-form">
                <h2 class="history-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Request Withdrawal
                </h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="withdraw_amount">Withdrawal Amount</label>
                        <input type="number" id="withdraw_amount" name="withdraw_amount" class="form-control"
                            min="1" max="<?php echo $balance; ?>" step="0.01" required>
                    </div>
                    <button type="submit" class="btn" <?php echo $balance <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-money-bill-wave"></i>
                        Request Withdrawal
                    </button>
                </form>
            </div>

            <div class="withdrawal-history">
                <h2 class="history-title">
                    <i class="fas fa-history"></i>
                    Withdrawal History
                </h2>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Processed By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($withdrawals)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 8px;"></i>
                                    <p>No withdrawal history found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td><?php echo date('d M Y, h:i A', strtotime($withdrawal['RequestedAt'])); ?></td>
                                    <td style="color: <?php echo $withdrawal['Amount'] < 0 ? 'var(--error-color)' : 'var(--success-color)'; ?>">
                                        <?php echo ($withdrawal['Amount'] >= 0 ? '+' : '') . '₹' . number_format($withdrawal['Amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($withdrawal['Status']); ?>">
                                            <i class="fas fa-<?php
                                                                echo $withdrawal['Status'] === 'Pending' ? 'clock' : ($withdrawal['Status'] === 'Approved' ? 'check-circle' : 'times-circle');
                                                                ?>"></i>
                                            <?php echo $withdrawal['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $withdrawal['AdminName'] ?: 'N/A'; ?></td>
                                    <td><?php echo $withdrawal['Remarks'] ?: 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            observer.observe(sidebar, {
                attributes: true
            });
        });
    </script>
</body>

</html>