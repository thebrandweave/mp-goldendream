<?php
session_start();

$menuPath = "../";
$currentPage = "teamSales";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get selected date from GET or default to today
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Function to get team statistics
function getTeamStats($conn, $date = null)
{
    if (!$date) {
        $date = date('Y-m-d');
    }

    // Get all unique team names
    $teamQuery = "SELECT DISTINCT TeamName FROM Customers WHERE TeamName IS NOT NULL";
    $teamStmt = $conn->prepare($teamQuery);
    $teamStmt->execute();
    $teams = $teamStmt->fetchAll(PDO::FETCH_COLUMN);

    $stats = [];
    foreach ($teams as $teamName) {
        // New customers today
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Customers WHERE TeamName = :teamName AND DATE(CreatedAt) = :date");
        $stmt->execute([':teamName' => $teamName, ':date' => $date]);
        $total_customers = $stmt->fetchColumn();

        // New promoters today
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Promoters WHERE TeamName = :teamName AND DATE(CreatedAt) = :date");
        $stmt->execute([':teamName' => $teamName, ':date' => $date]);
        $total_promoters = $stmt->fetchColumn();

        // Payments for today (for customers in this team)
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN Status = 'Verified' THEN Amount ELSE 0 END) as verified_amount,
                SUM(CASE WHEN Status = 'Pending' THEN Amount ELSE 0 END) as pending_amount,
                COUNT(CASE WHEN Status = 'Verified' THEN 1 END) as verified_payments,
                COUNT(CASE WHEN Status = 'Pending' THEN 1 END) as pending_payments,
                COUNT(*) as total_payments
            FROM Payments
            WHERE CustomerID IN (SELECT CustomerID FROM Customers WHERE TeamName = :teamName)
            AND DATE(SubmittedAt) = :date
        ");
        $stmt->execute([':teamName' => $teamName, ':date' => $date]);
        $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats[] = [
            'TeamName' => $teamName,
            'total_customers' => $total_customers,
            'total_promoters' => $total_promoters,
            'total_payments' => $paymentStats['total_payments'] ?? 0,
            'verified_amount' => $paymentStats['verified_amount'] ?? 0,
            'pending_amount' => $paymentStats['pending_amount'] ?? 0,
            'verified_payments' => $paymentStats['verified_payments'] ?? 0,
            'pending_payments' => $paymentStats['pending_payments'] ?? 0,
        ];
    }

    // Sort by verified_amount descending
    usort($stats, function ($a, $b) {
        return ($b['verified_amount'] ?? 0) <=> ($a['verified_amount'] ?? 0);
    });

    return $stats;
}

// Function to get team members (customers only)
function getTeamMembers($conn, $teamName, $date = null)
{
    $today = $date ?: date('Y-m-d');
    $query = "SELECT 
        c.CustomerUniqueID as unique_id,
        c.Name,
        c.Contact,
        c.Email,
        c.PromoterID as ParentPromoterID,
        CONCAT(p.PromoterUniqueID, ' - ', p.Name) as ParentName,
        COUNT(DISTINCT CASE WHEN DATE(pay.SubmittedAt) = :today THEN pay.PaymentID END) as total_payments,
        SUM(CASE WHEN DATE(pay.SubmittedAt) = :today AND pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) as total_amount
        FROM Customers c
        LEFT JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        LEFT JOIN Payments pay ON pay.CustomerID = c.CustomerID
        WHERE c.TeamName = :teamName
        AND (DATE(c.CreatedAt) = :today OR DATE(pay.SubmittedAt) = :today)
        GROUP BY c.CustomerID, c.CustomerUniqueID, c.Name, c.Contact, c.Email, c.PromoterID, p.Name, p.PromoterUniqueID";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':teamName', $teamName);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get team statistics
$teamStats = getTeamStats($conn, $selectedDate);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Sales Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .stat-subtitle {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .amount {
            font-weight: 600;
            color: #333;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .team-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .team-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .team-id {
            color: #666;
            font-size: 14px;
        }

        .team-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .team-stat {
            text-align: center;
        }

        .team-stat-value {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .team-stat-label {
            font-size: 12px;
            color: #666;
        }

        .member-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .type-promoter {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-customer {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #1565c0;
        }

        .btn i {
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Team Sales Overview</h1>
                <div class="date-display">
                    <form method="GET" style="display:inline-block;">
                        <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()" style="padding:6px 10px; border-radius:5px; border:1px solid #ccc;">
                    </form>
                    <span style="margin-left:10px;"><i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($selectedDate)); ?></span>
                </div>
            </div>

            <!-- Team Statistics -->
            <?php foreach ($teamStats as $team): ?>
                <div class="team-card">
                    <div class="team-header">
                        <div>
                            <div class="team-name"><?php echo htmlspecialchars($team['TeamName']); ?></div>
                        </div>
                        <div>
                            <a href="export.php?team=<?php echo urlencode($team['TeamName']); ?>&date=<?php echo urlencode($selectedDate); ?>" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </a>
                        </div>
                    </div>

                    <div class="team-stats">
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo number_format($team['total_customers']); ?></div>
                            <div class="team-stat-label">New Customers Today</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo number_format($team['total_promoters']); ?></div>
                            <div class="team-stat-label">New Promoters Today</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value"><?php echo number_format($team['total_payments']); ?></div>
                            <div class="team-stat-label">Payments Today</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value">₹<?php echo number_format($team['verified_amount']); ?></div>
                            <div class="team-stat-label">Verified Amount Today</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-value">₹<?php echo number_format($team['pending_amount']); ?></div>
                            <div class="team-stat-label">Pending Amount Today</div>
                        </div>
                    </div>

                    <!-- Team Members Table -->
                    <div class="section">
                        <h3 class="section-title">Today's Team Activity</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Parent</th>
                                        <th>Today's Payments</th>
                                        <th>Today's Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $teamMembers = getTeamMembers($conn, $team['TeamName'], $selectedDate);
                                    if (!empty($teamMembers)):
                                        foreach ($teamMembers as $member):
                                    ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['unique_id']); ?></td>
                                                <td><?php echo htmlspecialchars($member['Name']); ?></td>
                                                <td><?php echo htmlspecialchars($member['Contact']); ?></td>
                                                <td><?php echo htmlspecialchars($member['Email']); ?></td>
                                                <td><?php echo htmlspecialchars($member['ParentName']); ?></td>
                                                <td><?php echo number_format($member['total_payments']); ?></td>
                                                <td class="amount">₹<?php echo number_format($member['total_amount']); ?></td>
                                            </tr>
                                        <?php
                                        endforeach;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="7" class="no-data">No activity found for this team today</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($teamStats)): ?>
                <div class="no-data">No team data available for today</div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>