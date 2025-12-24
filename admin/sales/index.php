<?php
session_start();

$menuPath = "../";
$currentPage = "sales";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get today's date
$today = date('Y-m-d');

// Function to get today's payment statistics
function getTodayPaymentStats($conn, $date)
{
    $stats = [];

    // Total payments
    $query = "SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN Status = 'Verified' THEN 1 ELSE 0 END) as verified_payments,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_payments,
        SUM(CASE WHEN Status = 'Verified' THEN Amount ELSE 0 END) as verified_amount,
        SUM(CASE WHEN Status = 'Pending' THEN Amount ELSE 0 END) as pending_amount
        FROM Payments 
        WHERE DATE(SubmittedAt) = :date";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    return $stats;
}

// Function to get today's customer statistics
function getTodayCustomerStats($conn, $date)
{
    $query = "SELECT 
        COUNT(*) as total_customers,
        GROUP_CONCAT(DISTINCT p.PromoterID) as promoter_ids
        FROM Customers c
        LEFT JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        WHERE DATE(c.CreatedAt) = :date";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get today's promoter statistics
function getTodayPromoterStats($conn, $date)
{
    $query = "SELECT 
        COUNT(*) as total_promoters,
        GROUP_CONCAT(DISTINCT ParentPromoterID) as parent_promoters
        FROM Promoters 
        WHERE DATE(CreatedAt) = :date";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get promoter details
function getPromoterDetails($conn, $promoterIds)
{
    if (empty($promoterIds)) return [];

    $query = "SELECT 
        p.PromoterID,
        p.PromoterUniqueID,
        p.Name,
        p.Contact,
        p.ParentPromoterID,
        parent.Name as ParentName,
        parent.PromoterUniqueID as ParentPromoterUniqueID
        FROM Promoters p
        LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterUniqueID
        WHERE p.PromoterID IN ($promoterIds)";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all statistics
$paymentStats = getTodayPaymentStats($conn, $today);
$customerStats = getTodayCustomerStats($conn, $today);
$promoterStats = getTodayPromoterStats($conn, $today);

// Get promoter details if there are any
$promoterDetails = [];
if (!empty($customerStats['promoter_ids'])) {
    $promoterDetails = getPromoterDetails($conn, $customerStats['promoter_ids']);
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard</title>
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

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-verified {
            background: #e3fcef;
            color: #00a854;
        }

        .status-pending {
            background: #fff7e6;
            color: #fa8c16;
        }

        .status-rejected {
            background: #fff1f0;
            color: #f5222d;
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
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Today's Sales Overview</h1>
                <div class="date-display" style="display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('F d, Y'); ?>
                    <a href="../teamSales/" class="btn btn-primary" style="margin-left: 20px; padding: 8px 18px; background: #3a7bd5; color: #fff; border-radius: 6px; font-weight: 500; text-decoration: none; transition: background 0.2s;">
                        <i class="fas fa-users"></i> View Team Sales
                    </a>
                </div>
            </div>

            <!-- Payment Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Payments</div>
                    <div class="stat-value"><?php echo number_format($paymentStats['total_payments'] ?? 0); ?></div>
                    <div class="stat-subtitle">
                        Verified: <?php echo number_format($paymentStats['verified_payments'] ?? 0); ?> |
                        Pending: <?php echo number_format($paymentStats['pending_payments'] ?? 0); ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Total Amount</div>
                    <div class="stat-value">₹<?php echo number_format(($paymentStats['verified_amount'] ?? 0) + ($paymentStats['pending_amount'] ?? 0)); ?></div>
                    <div class="stat-subtitle">
                        Verified: ₹<?php echo number_format($paymentStats['verified_amount'] ?? 0); ?> |
                        Pending: ₹<?php echo number_format($paymentStats['pending_amount'] ?? 0); ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">New Customers</div>
                    <div class="stat-value"><?php echo number_format($customerStats['total_customers'] ?? 0); ?></div>
                    <div class="stat-subtitle">Registered today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">New Promoters</div>
                    <div class="stat-value"><?php echo number_format($promoterStats['total_promoters'] ?? 0); ?></div>
                    <div class="stat-subtitle">Joined today</div>
                </div>
            </div>

            <!-- Promoter Details Section -->
            <div class="section">
                <h3 class="section-title">Today's Promoter Details</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Promoter ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Parent Promoter ID</th>
                                <th>Parent Promoter Name</th>
                                <th>Customers Today</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($promoterDetails)): ?>
                                <?php foreach ($promoterDetails as $promoter): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['ParentPromoterID'] ?? 'None'); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['ParentName'] ?? 'None'); ?></td>
                                        <td>
                                            <?php
                                            // Count customers for this promoter today
                                            $customerQuery = "SELECT COUNT(*) as customer_count 
                                                            FROM Customers 
                                                            WHERE PromoterID = :promoterId 
                                                            AND DATE(CreatedAt) = :today";
                                            $customerStmt = $conn->prepare($customerQuery);
                                            $customerStmt->bindParam(':promoterId', $promoter['PromoterUniqueID']);
                                            $customerStmt->bindParam(':today', $today);
                                            $customerStmt->execute();
                                            $customerCount = $customerStmt->fetch(PDO::FETCH_ASSOC)['customer_count'];
                                            echo $customerCount;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">No promoter activity today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Today's New Customers Section -->
            <div class="section">
                <h3 class="section-title">Today's New Customers</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Team Name</th>
                                <th>Promoter</th>
                                <th>Registration Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $newCustomersQuery = "SELECT c.*, p.Name as PromoterName 
                                                FROM Customers c 
                                                LEFT JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID 
                                                WHERE DATE(c.CreatedAt) = :today 
                                                ORDER BY c.CreatedAt DESC";
                            $newCustomersStmt = $conn->prepare($newCustomersQuery);
                            $newCustomersStmt->bindParam(':today', $today);
                            $newCustomersStmt->execute();
                            $newCustomers = $newCustomersStmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($newCustomers)):
                                foreach ($newCustomers as $customer):
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['TeamName'] ?? 'None'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['PromoterName'] ?? 'None'); ?></td>
                                        <td><?php echo date('h:i A', strtotime($customer['CreatedAt'])); ?></td>
                                    </tr>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="7" class="no-data">No new customers registered today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Today's New Promoters Section -->
            <div class="section">
                <h3 class="section-title">Today's New Promoters</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Promoter ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Team Name</th>
                                <th>Parent Promoter</th>
                                <th>Registration Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $newPromotersQuery = "SELECT p.*, parent.Name as ParentName 
                                                FROM Promoters p 
                                                LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterUniqueID 
                                                WHERE DATE(p.CreatedAt) = :today 
                                                ORDER BY p.CreatedAt DESC";
                            $newPromotersStmt = $conn->prepare($newPromotersQuery);
                            $newPromotersStmt->bindParam(':today', $today);
                            $newPromotersStmt->execute();
                            $newPromoters = $newPromotersStmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($newPromoters)):
                                foreach ($newPromoters as $promoter):
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['TeamName'] ?? 'None'); ?></td>
                                        <td><?php echo htmlspecialchars($promoter['ParentName'] ?? 'None'); ?></td>
                                        <td><?php echo date('h:i A', strtotime($promoter['CreatedAt'])); ?></td>
                                    </tr>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="7" class="no-data">No new promoters registered today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payments Section -->
            <div class="section">
                <h3 class="section-title">Today's Payments</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Promoter</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $paymentsQuery = "SELECT p.*, c.Name as CustomerName, pr.Name as PromoterName 
                                            FROM Payments p 
                                            LEFT JOIN Customers c ON p.CustomerID = c.CustomerID 
                                            LEFT JOIN Promoters pr ON p.PromoterID = pr.PromoterID 
                                            WHERE DATE(p.SubmittedAt) = :today 
                                            ORDER BY p.SubmittedAt DESC";
                            $paymentsStmt = $conn->prepare($paymentsQuery);
                            $paymentsStmt->bindParam(':today', $today);
                            $paymentsStmt->execute();
                            $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($payments)):
                                foreach ($payments as $payment):
                            ?>
                                    <tr>
                                        <td><?php echo $payment['PaymentID']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['PromoterName']); ?></td>
                                        <td class="amount">₹<?php echo number_format($payment['Amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                                <?php echo $payment['Status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('h:i A', strtotime($payment['SubmittedAt'])); ?></td>
                                    </tr>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="6" class="no-data">No payments recorded today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>