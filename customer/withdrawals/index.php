<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "withdrawals";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total balance from Balances table
$stmt = $db->prepare("
    SELECT SUM(BalanceAmount) as total_balance 
    FROM Balances 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$balance_result = $stmt->fetch(PDO::FETCH_ASSOC);
$available_balance = $balance_result['total_balance'] ?? 0;

// Get withdrawal statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_withdrawals,
        SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as approved_withdrawals,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_withdrawals,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_withdrawals,
        SUM(CASE WHEN Status = 'Approved' THEN Amount ELSE 0 END) as total_withdrawn
    FROM Withdrawals 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get withdrawal history
$stmt = $db->prepare("
    SELECT w.*, a.Name as AdminName 
    FROM Withdrawals w 
    LEFT JOIN Admins a ON w.AdminID = a.AdminID 
    WHERE w.UserID = ? AND w.UserType = 'Customer' 
    ORDER BY w.RequestedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.05);
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .withdrawals-container {
            padding: 24px;
            margin-top: 70px;
        }

        .withdrawals-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .withdrawals-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .withdrawals-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .withdrawal-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .withdrawal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .withdrawal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-green), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .withdrawal-card:hover::before {
            opacity: 1;
        }

        .withdrawal-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }

        .detail-item {
            background: rgba(47, 155, 127, 0.1);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-approved {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .btn-request {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-request:hover {
            background: #248c6f;
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .withdrawals-container {
                margin-left: 70px;
                padding: 16px;
            }

            .withdrawals-header {
                padding: 30px 20px;
            }

            .withdrawal-card {
                padding: 20px;
            }

            .withdrawal-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="withdrawals-container">
            <div class="container">
                <div class="withdrawals-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Withdrawals</h2>
                    <p class="mb-0">Manage your withdrawal requests and track their status</p>
                    <?php if ($available_balance > 0): ?>
                        <a href="request_withdrawal.php" class="btn btn-light btn-lg mt-3">
                            <i class="fas fa-plus"></i> Request Withdrawal
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Balance Card -->
                <div class="balance-card">
                    <h4>Available Balance</h4>
                    <div class="balance-amount">₹<?php echo number_format($available_balance, 2); ?></div>
                    <?php if ($available_balance > 0): ?>
                        <a href="request_withdrawal.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i> Request Withdrawal
                        </a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No available balance for withdrawal
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-wallet stats-icon text-primary"></i>
                            <h5>Total Withdrawals</h5>
                            <h3><?php echo $stats['total_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-check-circle stats-icon text-success"></i>
                            <h5>Approved</h5>
                            <h3><?php echo $stats['approved_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-clock stats-icon text-warning"></i>
                            <h5>Pending</h5>
                            <h3><?php echo $stats['pending_withdrawals']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <i class="fas fa-times-circle stats-icon text-danger"></i>
                            <h5>Rejected</h5>
                            <h3><?php echo $stats['rejected_withdrawals']; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal History -->
                <h4 class="mb-4">Withdrawal History</h4>
                <?php if (!empty($withdrawals)): ?>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <div class="withdrawal-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h5>₹<?php echo number_format($withdrawal['Amount'], 2); ?></h5>
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($withdrawal['RequestedAt'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge 
                                        <?php echo $withdrawal['Status'] === 'Pending' ? 'status-pending' : ($withdrawal['Status'] === 'Approved' ? 'status-approved' : 'status-rejected'); ?>">
                                        <?php echo $withdrawal['Status']; ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($withdrawal['AdminName']): ?>
                                        <small class="text-muted">Processed by: <?php echo htmlspecialchars($withdrawal['AdminName']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($withdrawal['Remarks']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($withdrawal['Remarks']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Withdrawal History</h3>
                        <p>You haven't made any withdrawal requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>