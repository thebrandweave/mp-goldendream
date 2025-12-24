<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}
$menuPath = "../";
$currentPage = "dashboard";
// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Helper functions
function getStats($conn) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        // Get promoter details
        $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
        $stmt->execute([$promoterId]);
        $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get total customers
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM Customers WHERE PromoterID = ?");
        $stmt->execute([$promoter['PromoterUniqueID']]);
        $customers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get active customers
        $stmt = $conn->prepare("SELECT COUNT(*) as active FROM Customers WHERE PromoterID = ? AND Status = 'Active'");
        $stmt->execute([$promoter['PromoterUniqueID']]);
        $activeCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

        // Get total earnings from verified payments
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN p.Status = 'Verified' THEN (p.Amount * pr.Commission / 100) ELSE 0 END), 0) as total,
                COUNT(DISTINCT CASE WHEN p.Status = 'Verified' THEN p.PaymentID END) as verified_count,
                COUNT(DISTINCT CASE WHEN p.Status = 'Pending' THEN p.PaymentID END) as pending_count
            FROM Payments p
            JOIN Promoters pr ON p.PromoterID = pr.PromoterID
            WHERE p.PromoterID = ?
        ");
        $stmt->execute([$promoterId]);
        $payments = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get active schemes count
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT s.SchemeID) as total 
            FROM Schemes s 
            INNER JOIN Subscriptions sub ON s.SchemeID = sub.SchemeID 
            INNER JOIN Customers c ON sub.CustomerID = c.CustomerID 
            WHERE c.PromoterID = ? AND s.Status = 'Active'
        ");
        $stmt->execute([$promoter['PromoterUniqueID']]);
        $schemes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get wallet balance and total earnings
        $stmt = $conn->prepare("
            SELECT 
                pw.BalanceAmount,
                pw.Message,
                pw.LastUpdated,
                COALESCE(SUM(wl.Amount), 0) as total_earnings
            FROM PromoterWallet pw
            LEFT JOIN WalletLogs wl ON pw.PromoterUniqueID = wl.PromoterUniqueID
            WHERE pw.PromoterUniqueID = ?
            GROUP BY pw.PromoterUniqueID
        ");
        $stmt->execute([$promoter['PromoterUniqueID']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get team statistics if promoter has team members
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT p.PromoterID) as team_size,
                SUM(CASE WHEN pay.Status = 'Verified' THEN (pay.Amount * p.Commission / 100) ELSE 0 END) as team_earnings,
                COUNT(DISTINCT CASE WHEN pay.Status = 'Verified' THEN pay.PaymentID END) as team_verified_payments
            FROM Promoters p
            LEFT JOIN Payments pay ON p.PromoterID = pay.PromoterID
            WHERE p.ParentPromoterID = ?
        ");
        $stmt->execute([$promoter['PromoterUniqueID']]);
        $teamStats = $stmt->fetch(PDO::FETCH_ASSOC);

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

        return [
            'promoter' => $promoter,
            'customers' => [
                'total' => $customers,
                'active' => $activeCustomers
            ],
            'earnings' => [
                'total' => $payments['total'],
                'verified_payments' => $payments['verified_count'],
                'pending_payments' => $payments['pending_count']
            ],
            'schemes' => $schemes,
            'wallet' => [
                'balance' => $wallet['BalanceAmount'] ?? 0,
                'total_earnings' => $wallet['total_earnings'] ?? 0,
                'message' => $wallet['Message'] ?? '',
                'last_updated' => $wallet['LastUpdated'] ?? null,
                'recent_transactions' => $walletTransactions
            ],
            'team' => [
                'size' => $teamStats['team_size'] ?? 0,
                'earnings' => $teamStats['team_earnings'] ?? 0,
                'verified_payments' => $teamStats['team_verified_payments'] ?? 0
            ]
        ];
    } catch (PDOException $e) {
        return [
            'error' => $e->getMessage(),
            'customers' => ['total' => 0, 'active' => 0],
            'earnings' => ['total' => 0, 'verified_payments' => 0, 'pending_payments' => 0],
            'schemes' => 0,
            'wallet' => [
                'balance' => 0,
                'total_earnings' => 0,
                'message' => '',
                'last_updated' => null,
                'recent_transactions' => []
            ],
            'team' => ['size' => 0, 'earnings' => 0, 'verified_payments' => 0]
        ];
    }
}

function getRecentPayments($conn, $limit = 5) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        $stmt = $conn->prepare("
            SELECT p.*, c.Name as CustomerName, s.SchemeName 
            FROM Payments p 
            LEFT JOIN Customers c ON p.CustomerID = c.CustomerID 
            LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID 
            WHERE p.PromoterID = ? 
            ORDER BY p.SubmittedAt DESC 
            LIMIT ?
        ");
        $stmt->execute([$promoterId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getRecentActivity($conn, $limit = 7) {
    $promoterId = $_SESSION['promoter_id'];
    try {
        $stmt = $conn->prepare("
            SELECT * FROM ActivityLogs 
            WHERE UserID = ? AND UserType = 'Promoter'
            ORDER BY CreatedAt DESC 
            LIMIT ?
        ");
        $stmt->execute([$promoterId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Get dashboard data
$stats = getStats($conn);
$recentPayments = getRecentPayments($conn);
$recentActivity = getRecentActivity($conn);

$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promoter Dashboard | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            margin-left: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .activity-list {
            list-style: none;
            padding: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--bg-light);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            margin-left: 40px;
        }

        .quick-actions-card, .profile-card, .referral-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .action-btn {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            color: var(--primary-color);
        }

        .action-btn i {
            font-size: 20px;
            color: var(--primary-color);
            width: 24px;
            text-align: center;
        }

        .profile-info {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .profile-image-container {
            width: 120px;
            height: 120px;
            flex-shrink: 0;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-details {
            flex: 1;
        }

        .profile-details h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .profile-details p {
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-details i {
            color: var(--primary-color);
            width: 20px;
        }

        .view-profile-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .view-profile-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .kyc-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: auto;
        }

        .kyc-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .kyc-verified {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .kyc-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .profile-info {
                flex-direction: column;
                text-align: center;
            }

            .profile-details p {
                justify-content: center;
            }
        }

        .referral-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
        }

        .referral-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .referral-info {
            background: var(--primary-light);
            padding: 20px;
            border-radius: 12px;
            color: var(--primary-color);
        }

        .referral-info p {
            margin-bottom: 15px;
            font-size: 14px;
        }

        .commission-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .referral-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .commission-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .commission-input-group input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .commission-input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 106, 80, 0.1);
            outline: none;
        }

        .commission-input-group input::-webkit-inner-spin-button,
        .commission-input-group input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .commission-input-group input[type=number] {
            -moz-appearance: textfield;
        }

        .generate-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0 25px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .generate-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .generate-btn:active {
            transform: translateY(0);
        }

        .error-message {
            color: var(--danger-color);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .referral-result {
            display: none;
            flex-direction: column;
            gap: 20px;
        }

        .referral-link-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .referral-link-container input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .copy-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0 20px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 100px;
            justify-content: center;
        }

        .copy-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .copy-btn:active {
            transform: translateY(0);
        }

        .referral-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .stat {
            background: var(--bg-light);
            padding: 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat i {
            color: var(--primary-color);
            font-size: 18px;
        }

        .stat span {
            font-size: 14px;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .commission-input-group {
                flex-direction: column;
            }

            .referral-link-container {
                flex-direction: column;
            }

            .referral-stats {
                grid-template-columns: 1fr;
            }
        }

        .wallet-logs {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            margin-left: 40px;
            animation: slideIn 0.5s ease 0.3s backwards;
        }

        .wallet-logs-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .wallet-transaction {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--bg-light);
            border-radius: 12px;
            transition: transform 0.3s ease;
        }

        .wallet-transaction:hover {
            transform: translateX(5px);
        }

        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .transaction-details {
            flex: 1;
            min-width: 0; /* Prevents flex item from overflowing */
        }

        .transaction-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .transaction-message {
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
        }

        .transaction-amount {
            font-weight: 600;
            font-size: 16px;
        }

        .transaction-amount.credit {
            color: var(--success-color);
        }

        .transaction-amount.debit {
            color: var(--error-color);
        }

        .transaction-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 12px;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .transaction-type {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .transaction-type i {
            font-size: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 500;
        }

        /* Quick Access Section Styles */
        .quick-access-section {
            margin-bottom: 30px;
            margin-left: 40px;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .quick-access-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .quick-access-card:hover {
            transform: translateY(-5px);
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .quick-access-card i {
            font-size: 24px;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .quick-access-card:hover i {
            transform: scale(1.1);
        }

        .quick-access-card span {
            font-weight: 500;
            font-size: 14px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .section-title i {
            color: var(--primary-color);
        }

        @media (max-width: 1200px) {
            .quick-access-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .quick-access-grid {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 12px;
            }

            .quick-access-card {
                padding: 15px;
            }

            .quick-access-card i {
                font-size: 20px;
            }

            .quick-access-card span {
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .quick-access-card {
                padding: 12px;
            }

            .quick-access-card i {
                font-size: 18px;
            }

            .quick-access-card span {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .quick-access-grid {
                grid-template-columns: 1fr;
            }

            .quick-access-card {
                flex-direction: row;
                justify-content: flex-start;
                padding: 15px;
            }

            .quick-access-card i {
                font-size: 20px;
                margin-right: 10px;
            }

            .quick-access-card span {
                font-size: 14px;
            }
        }

        /* Inspiration Section Styles */
        .inspiration-section {
            margin-bottom: 30px;
            margin-top: 0;
        }

        .inspiration-card {
            background: linear-gradient(135deg, var(--primary-color), #1a5f4c);
            border-radius: 20px;
            padding: 30px;
            color: white;
            display: flex;
            gap: 20px;
            box-shadow: var(--card-shadow);
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
            margin-left: 40px;
        }

        .inspiration-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .inspiration-icon {
            font-size: 32px;
            opacity: 0.8;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            padding: 10px;
        }

        .inspiration-content {
            flex: 1;
            min-width: 0; /* Prevents flex item from overflowing */
        }

        .inspiration-message {
            font-size: 18px;
            font-weight: 500;
            line-height: 1.6;
            margin-bottom: 15px;
            font-style: italic;
            word-wrap: break-word;
            hyphens: auto;
        }

        .inspiration-author {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            opacity: 0.9;
        }

        .inspiration-author i {
            color: #ffd700;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive styles for inspiration section */
        @media (max-width: 1200px) {
            .inspiration-card {
                padding: 25px;
            }

            .inspiration-message {
                font-size: 17px;
            }
        }

        @media (max-width: 992px) {
            .inspiration-card {
                padding: 22px;
            }

            .inspiration-message {
                font-size: 16px;
            }

            .inspiration-icon {
                font-size: 28px;
                width: 45px;
                height: 45px;
            }
        }

        @media (max-width: 768px) {
            .inspiration-card {
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .inspiration-icon {
                margin-bottom: 5px;
            }

            .inspiration-message {
                font-size: 15px;
                line-height: 1.5;
            }

            .inspiration-author {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .inspiration-card {
                padding: 15px;
                border-radius: 15px;
            }

            .inspiration-icon {
                font-size: 24px;
                width: 40px;
                height: 40px;
                padding: 8px;
            }

            .inspiration-message {
                font-size: 14px;
                line-height: 1.4;
            }

            .inspiration-author {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="dashboard-container">
            <!-- Inspiration Message Section -->
            <div class="inspiration-section">
                <div class="inspiration-card">
                    <div class="inspiration-icon">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="inspiration-content">
                        <p class="inspiration-message" id="inspirationMessage"></p>
                        <div class="inspiration-author">
                            <i class="fas fa-star"></i>
                            <span>~ Mausooq</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: var(--primary-light); color: var(--primary-color);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['customers']['total']); ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-value">₹<?php echo number_format($stats['wallet']['balance'], 2); ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: var(--warning-color);">
                            <i class="fas fa-file-contract"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['schemes']); ?></div>
                    <div class="stat-label">Active Schemes</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(231, 76, 60, 0.1); color: var(--danger-color);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['earnings']['pending_payments']); ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
            </div>

            <!-- Quick Access Section -->
            <div class="quick-access-section">
                <h3 class="section-title">
                    <i class="fas fa-star"></i>
                    Quick Access
                </h3>
                <div class="quick-access-grid">
                    <a href="../Customers" class="quick-access-card">
                        <i class="fas fa-user-friends"></i>
                        <span>My Customers</span>
                    </a>
                    <a href="../childPromoter" class="quick-access-card">
                        <i class="fas fa-users"></i>
                        <span>My Promoters</span>
                    </a>
                    <a href="../schemes" class="quick-access-card">
                        <i class="fas fa-project-diagram"></i>
                        <span>Active Schemes</span>
                    </a>
                    <a href="../payments" class="quick-access-card">
                        <i class="fas fa-rupee-sign"></i>
                        <span>Payments</span>
                    </a>
                    <a href="../earnings" class="quick-access-card">
                        <i class="fas fa-wallet"></i>
                        <span>My Earnings</span>
                    </a>
                    <a href="../withdrawals" class="quick-access-card">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Withdrawals</span>
                    </a>
                </div>
            </div>

            <div class="quick-actions-grid">
                <div class="quick-actions-card">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h3>
                    <div class="quick-actions">
                        <a href="../profile/kyc.php" class="action-btn">
                            <i class="fas fa-id-card"></i>
                            <div>
                                <div>KYC</div>
                                <?php
                                // Get KYC status
                                $kycStmt = $conn->prepare("SELECT Status FROM KYC WHERE UserID = ? AND UserType = 'Promoter' LIMIT 1");
                                $kycStmt->execute([$_SESSION['promoter_id']]);
                                $kycStatus = $kycStmt->fetch(PDO::FETCH_COLUMN);
                                
                                $statusClass = 'kyc-pending';
                                if ($kycStatus === 'Verified') {
                                    $statusClass = 'kyc-verified';
                                } elseif ($kycStatus === 'Rejected') {
                                    $statusClass = 'kyc-rejected';
                                }
                                ?>
                                <span class="kyc-status <?php echo $statusClass; ?>">
                                    <?php echo $kycStatus ? ucfirst($kycStatus) : 'Not Submitted'; ?>
                                </span>
                            </div>
                        </a>
                        
                        <a href="../profile/change-password.php" class="action-btn">
                            <i class="fas fa-key"></i>
                            <div>Change Password</div>
                        </a>
                        
                        <a href="../profile/id-card.php" class="action-btn">
                            <i class="fas fa-address-card"></i>
                            <div>ID Card</div>
                        </a>
                        
                        <a href="../profile/welcome-letter.php" class="action-btn">
                            <i class="fas fa-envelope-open-text"></i>
                            <div>Welcome Letter</div>
                        </a>
                    </div>
                </div>

                <div class="referral-card">
                    <h3 class="section-title">
                        <i class="fas fa-link"></i>
                        Generate Referral Link
                    </h3>
                    <div class="referral-content">
                        <div class="referral-info">
                            <p>Share your referral link to earn commissions from new promoters who join through your link.</p>
                            <div class="commission-info">
                                <span>Your Commission Rate: <?php echo $stats['promoter']['Commission']; ?></span>
                            </div>
                        </div>
                        <div class="referral-form">
                            <div class="form-group">
                                <label>Set Commission Rate</label>
                                <div class="commission-input-group">
                                    <input type="number" id="commissionInput" min="0" max="<?php echo $stats['promoter']['Commission']; ?>" step="0.01" placeholder="Enter commission rate" value="0">
                                    <button id="generateBtn" class="generate-btn">
                                        <i class="fas fa-magic"></i>
                                        Generate
                                    </button>
                                </div>
                                <div class="error-message" id="commissionError"></div>
                            </div>
                            <div class="referral-result" id="referralResult">
                                <div class="referral-link-container">
                                    <input type="text" id="referralLink" readonly>
                                    <button id="copyBtn" class="copy-btn">
                                        <i class="fas fa-copy"></i>
                                        Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-card">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Profile Overview
                    </h3>
                    <div class="profile-info">
                        <div class="profile-image-container">
                            <img src="<?php 
                                if (!empty($stats['promoter']['ProfileImageURL']) && $stats['promoter']['ProfileImageURL'] !== '-'): 
                                    echo '../../' . htmlspecialchars($stats['promoter']['ProfileImageURL']);
                                else:
                                    echo '../../uploads/profile/image.png';
                                endif;
                            ?>" alt="Profile" class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h4><?php echo htmlspecialchars($stats['promoter']['Name']); ?></h4>
                            <p><i class="fas fa-id-badge"></i> ID: <?php echo htmlspecialchars($stats['promoter']['PromoterUniqueID']); ?></p>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($stats['promoter']['Email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($stats['promoter']['Contact']); ?></p>
                            <a href="../profile/" class="view-profile-btn">View Full Profile</a>
                        </div>
                    </div>
                </div>
            </div>

           

            <!-- Wallet Logs Section -->
            <div class="wallet-logs">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <i class="fas fa-wallet"></i>
                        Wallet Transactions
                    </h3>
                </div>
                <div class="wallet-logs-container">
                    <?php if (empty($stats['wallet']['recent_transactions'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-wallet"></i>
                            <p>No transactions yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stats['wallet']['recent_transactions'] as $transaction): ?>
                            <div class="wallet-transaction">
                                <div class="transaction-icon" style="background: <?php echo $transaction['TransactionType'] === 'Credit' ? 'rgba(46, 204, 113, 0.1)' : 'rgba(231, 76, 60, 0.1)'; ?>">
                                    <i class="fas fa-<?php echo $transaction['TransactionType'] === 'Credit' ? 'arrow-down' : 'arrow-up'; ?>" 
                                       style="color: <?php echo $transaction['TransactionType'] === 'Credit' ? 'var(--success-color)' : 'var(--error-color)'; ?>">
                                    </i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-info">
                                        <span class="transaction-message"><?php echo htmlspecialchars($transaction['Message']); ?></span>
                                        <span class="transaction-amount <?php echo $transaction['TransactionType'] === 'Credit' ? 'credit' : 'debit'; ?>">
                                            <?php echo ($transaction['TransactionType'] === 'Credit' ? '+' : '') . '₹' . number_format($transaction['Amount'], 2); ?>
                                        </span>
                                    </div>
                                    <div class="transaction-meta">
                                        <span class="transaction-type">
                                            <i class="fas fa-circle"></i>
                                            <?php echo $transaction['TransactionType']; ?>
                                        </span>
                                        <span class="transaction-date">
                                            <?php echo date('M d, Y H:i', strtotime($transaction['CreatedAt'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar adjustment
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
            observer.observe(sidebar, { attributes: true });
        });
    </script>

    <script>
        // Add this to your existing script
        const commissionInput = document.getElementById('commissionInput');
        const generateBtn = document.getElementById('generateBtn');
        const referralResult = document.getElementById('referralResult');
        const referralLink = document.getElementById('referralLink');
        const copyBtn = document.getElementById('copyBtn');
        const commissionError = document.getElementById('commissionError');
        const maxCommission = <?php echo $stats['promoter']['Commission']; ?>;

        // Inspiration Messages
        const inspirationMessages = [
            "Success is not final, failure is not fatal: it is the courage to continue that counts.",
            "Your dedication today will shape your success tomorrow. Keep pushing forward!",
            "Every customer you help is a step towards your financial freedom.",
            "The best way to predict your future is to create it. Keep building your network!",
            "Your success is determined by your daily actions. Stay consistent!",
            "Believe in yourself and all that you are. Know that there is something inside you that is greater than any obstacle.",
            "The only limit to your earnings is your determination to succeed.",
            "Your network is your net worth. Keep growing it!",
            "Success is the sum of small efforts repeated day in and day out.",
            "The harder you work, the luckier you get. Keep going!",
            "Your potential is limitless. Keep reaching for new heights!",
            "Every successful promoter started where you are now. Keep pushing!",
            "Your attitude determines your altitude. Stay positive!",
            "The road to success is always under construction. Keep building!",
            "Your success story is being written one customer at a time."
        ];

        // Function to display random inspiration message
        function displayRandomInspiration() {
            const messageElement = document.getElementById('inspirationMessage');
            const randomIndex = Math.floor(Math.random() * inspirationMessages.length);
            messageElement.textContent = inspirationMessages[randomIndex];
        }

        // Display initial message
        displayRandomInspiration();

        // Change message every 30 seconds
        setInterval(displayRandomInspiration, 30000);

        // Set initial value to 0
        commissionInput.value = 0;

        generateBtn.onclick = function() {
            const value = parseFloat(commissionInput.value);
            if (value < 0) {
                commissionError.textContent = 'Commission cannot be negative';
                commissionError.style.display = 'block';
                return;
            }
            
            if (value > maxCommission) {
                commissionError.textContent = `Commission cannot be greater than ${maxCommission}`;
                commissionError.style.display = 'block';
                return;
            }

            commissionError.style.display = 'none';
            generateReferralLink(value);
        }

        function generateReferralLink(commission) {
            const promoterId = '<?php echo $stats['promoter']['PromoterUniqueID']; ?>';
            const encodedRef = btoa(commission.toString());
            const baseUrl = '<?php echo Database::$baseUrl; ?>';
            const link = `${baseUrl}/refer?id=${promoterId}&ref=${encodedRef}`;
            referralLink.value = link;
            referralResult.style.display = 'flex';
        }

        copyBtn.onclick = function() {
            referralLink.select();
            navigator.clipboard.writeText(referralLink.value).then(() => {
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                }, 2000);
            });
        }

        // Add input validation
        commissionInput.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (value < 0) {
                commissionError.textContent = 'Commission cannot be negative';
                commissionError.style.display = 'block';
            } else if (value > maxCommission) {
                commissionError.textContent = `Commission cannot be greater than ${maxCommission}`;
                commissionError.style.display = 'block';
            } else {
                commissionError.style.display = 'none';
            }
        });
    </script>
</body>
</html> 