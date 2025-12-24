<?php
session_start();
$menuPath = "../";
$currentPage = "teams";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get team name from URL
$teamName = isset($_GET['team']) ? $_GET['team'] : '';

if (empty($teamName)) {
    header("Location: index.php");
    exit;
}

// Get team details
try {
    // Get team stats
    $statsQuery = "
        SELECT 
            t.TeamName,
            COUNT(DISTINCT p.PromoterID) as promoter_count,
            COUNT(DISTINCT c.CustomerID) as customer_count,
            MAX(t.CreatedAt) as last_activity,
            COALESCE(ps.total_payments, 0) as total_payments,
            COALESCE(ps.total_amount, 0) as total_amount,
            COALESCE(ps.verified_payments, 0) as verified_payments,
            COALESCE(ps.pending_payments, 0) as pending_payments,
            COALESCE(ps.rejected_payments, 0) as rejected_payments
        FROM (
            SELECT TeamName, CreatedAt 
            FROM Promoters 
            WHERE TeamName = :teamName
            UNION ALL
            SELECT TeamName, CreatedAt 
            FROM Customers 
            WHERE TeamName = :teamName
        ) t
        LEFT JOIN Promoters p ON p.TeamName = t.TeamName
        LEFT JOIN Customers c ON c.TeamName = t.TeamName
        LEFT JOIN (
            SELECT 
                cu.TeamName,
                COUNT(*) as total_payments,
                COALESCE(SUM(Amount), 0) as total_amount,
                COUNT(CASE WHEN py.Status = 'Verified' THEN 1 END) as verified_payments,
                COUNT(CASE WHEN py.Status = 'Pending' THEN 1 END) as pending_payments,
                COUNT(CASE WHEN py.Status = 'Rejected' THEN 1 END) as rejected_payments
            FROM Payments py 
            JOIN Customers cu ON py.CustomerID = cu.CustomerID 
            WHERE cu.TeamName = :teamName
            GROUP BY cu.TeamName
        ) ps ON ps.TeamName = t.TeamName
        GROUP BY t.TeamName";

    $stmt = $conn->prepare($statsQuery);
    $stmt->bindParam(':teamName', $teamName);
    $stmt->execute();
    $teamStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teamStats) {
        header("Location: index.php");
        exit;
    }

    // Get promoters in this team
    $stmt = $conn->prepare("
        SELECT PromoterID, PromoterUniqueID, Name, Contact, Email, Status, CreatedAt 
        FROM Promoters 
        WHERE TeamName = ? 
        ORDER BY CreatedAt DESC
    ");
    $stmt->execute([$teamName]);
    $promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get customers in this team
    $stmt = $conn->prepare("
        SELECT CustomerID, CustomerUniqueID, Name, Contact, Email, Status, CreatedAt 
        FROM Customers 
        WHERE TeamName = ? 
        ORDER BY CreatedAt DESC
    ");
    $stmt->execute([$teamName]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent payments for this team
    $stmt = $conn->prepare("
        SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, p.VerifiedAt,
               c.Name as CustomerName, c.CustomerUniqueID,
               s.SchemeName
        FROM Payments p
        JOIN Customers c ON p.CustomerID = c.CustomerID
        JOIN Schemes s ON p.SchemeID = s.SchemeID
        WHERE c.TeamName = ?
        ORDER BY p.SubmittedAt DESC
        LIMIT 10
    ");
    $stmt->execute([$teamName]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving team data. Please try again later.");
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Details - <?php echo htmlspecialchars($teamName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .content-wrapper {
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            color: #2980b9;
        }

        .team-overview {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .team-name {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 20px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #3498db;
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .member-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .member-id {
            font-size: 12px;
            color: #7f8c8d;
        }

        .member-contact {
            font-size: 12px;
            color: #3498db;
            margin-top: 5px;
        }

        .member-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background: #e8f5e9;
            color: #2ecc71;
        }

        .member-status.inactive {
            background: #fee2e2;
            color: #e74c3c;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th,
        .payments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .payments-table th {
            font-weight: 600;
            color: #2c3e50;
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-verified {
            background: #e8f5e9;
            color: #2ecc71;
        }

        .status-pending {
            background: #fff3e0;
            color: #f39c12;
        }

        .status-rejected {
            background: #fee2e2;
            color: #e74c3c;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .members-grid {
                grid-template-columns: 1fr;
            }

            .payments-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Team Details</h1>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Teams
            </a>
        </div>

        <div class="team-overview">
            <h2 class="team-name"><?php echo htmlspecialchars($teamName); ?></h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Members</div>
                    <div class="stat-value"><?php echo $teamStats['promoter_count'] + $teamStats['customer_count']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Promoters</div>
                    <div class="stat-value"><?php echo $teamStats['promoter_count']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Customers</div>
                    <div class="stat-value"><?php echo $teamStats['customer_count']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Payments</div>
                    <div class="stat-value"><?php echo $teamStats['total_payments']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-value">₹<?php echo number_format($teamStats['total_amount']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Last Activity</div>
                    <div class="stat-value"><?php echo date('M d, Y', strtotime($teamStats['last_activity'])); ?></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Verified Payments</div>
                    <div class="stat-value" style="color: #2ecc71;"><?php echo $teamStats['verified_payments']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-value" style="color: #f39c12;"><?php echo $teamStats['pending_payments']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Rejected Payments</div>
                    <div class="stat-value" style="color: #e74c3c;"><?php echo $teamStats['rejected_payments']; ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($promoters)): ?>
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-user-tie"></i> Promoters
                </h3>
                <div class="members-grid">
                    <?php foreach ($promoters as $promoter): ?>
                        <div class="member-card">
                            <div class="member-avatar">
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
                            <div class="member-info">
                                <div class="member-name"><?php echo htmlspecialchars($promoter['Name']); ?></div>
                                <div class="member-id"><?php echo $promoter['PromoterUniqueID']; ?></div>
                                <div class="member-contact">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($promoter['Contact']); ?>
                                </div>
                            </div>
                            <span class="member-status <?php echo strtolower($promoter['Status']); ?>">
                                <?php echo $promoter['Status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($customers)): ?>
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-users"></i> Customers
                </h3>
                <div class="members-grid">
                    <?php foreach ($customers as $customer): ?>
                        <div class="member-card">
                            <div class="member-avatar">
                                <?php
                                $initials = '';
                                $nameParts = explode(' ', $customer['Name']);
                                if (count($nameParts) >= 2) {
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($customer['Name'], 0, 2));
                                }
                                echo $initials;
                                ?>
                            </div>
                            <div class="member-info">
                                <div class="member-name"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                <div class="member-id"><?php echo $customer['CustomerUniqueID']; ?></div>
                                <div class="member-contact">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['Contact']); ?>
                                </div>
                            </div>
                            <span class="member-status <?php echo strtolower($customer['Status']); ?>">
                                <?php echo $customer['Status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($recentPayments)): ?>
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-money-bill-wave"></i> Recent Payments
                </h3>
                <div class="table-responsive">
                    <table class="payments-table">
                        <thead>
                            <tr>
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
                                    <td>
                                        <div class="member-name"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                        <div class="member-id"><?php echo $payment['CustomerUniqueID']; ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                    <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                            <?php echo $payment['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($payment['SubmittedAt'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>