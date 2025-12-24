<?php
session_start();


$menuPath = "../";
$currentPage = "dashboard";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get stats data
function getStats($conn, $startDate = null, $endDate = null)
{
    $stats = [];

    // Set default date range if not provided
    if (!$startDate) {
        $startDate = date('Y-m-01');
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }

    // Total Customers
    $query = "SELECT COUNT(*) as total FROM Customers WHERE Status = 'Active' AND CreatedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['customers'] = $result['total'] ?? 0;

    // Get previous month customer count for comparison
    $prevStartDate = date('Y-m-01', strtotime($startDate . ' -1 month'));
    $prevEndDate = date('Y-m-t', strtotime($startDate . ' -1 month'));

    $query = "SELECT COUNT(*) as prev_month FROM Customers WHERE Status = 'Active' AND CreatedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $prevStartDate);
    $stmt->bindParam(':endDate', $prevEndDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthCustomers = $result['prev_month'] ?? 0;

    // Calculate percentage change
    if ($prevMonthCustomers > 0) {
        $stats['customers_growth'] = round((($stats['customers'] - $prevMonthCustomers) / $prevMonthCustomers) * 100, 1);
    } else {
        $stats['customers_growth'] = 100;
    }

    // Total Revenue - Only count verified payments
    $query = "SELECT COALESCE(SUM(Amount), 0) as total 
              FROM Payments 
              WHERE Status = 'Verified' 
              AND SubmittedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['revenue'] = $result['total'] ?? 0;

    // Previous month revenue
    $query = "SELECT COALESCE(SUM(Amount), 0) as prev_month 
              FROM Payments 
              WHERE Status = 'Verified' 
              AND SubmittedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $prevStartDate);
    $stmt->bindParam(':endDate', $prevEndDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthRevenue = $result['prev_month'] ?? 0;

    // Calculate percentage change
    if ($prevMonthRevenue > 0) {
        $stats['revenue_growth'] = round((($stats['revenue'] - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1);
    } else {
        $stats['revenue_growth'] = 100;
    }

    // Active Schemes
    $query = "SELECT COUNT(*) as total FROM Schemes WHERE Status = 'Active' AND CreatedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['schemes'] = $result['total'] ?? 0;

    // New schemes this month
    $query = "SELECT COUNT(*) as new_schemes FROM Schemes WHERE Status = 'Active' AND CreatedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['new_schemes'] = $result['new_schemes'] ?? 0;

    // Total Payments - Only count verified payments
    $query = "SELECT COUNT(*) as total 
              FROM Payments 
              WHERE Status = 'Verified' 
              AND SubmittedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['payments'] = $result['total'] ?? 0;

    // Previous month payments
    $query = "SELECT COUNT(*) as prev_month 
              FROM Payments 
              WHERE Status = 'Verified' 
              AND SubmittedAt BETWEEN :startDate AND :endDate";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':startDate', $prevStartDate);
    $stmt->bindParam(':endDate', $prevEndDate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prevMonthPayments = $result['prev_month'] ?? 0;

    // Calculate percentage change
    if ($prevMonthPayments > 0) {
        $stats['payments_growth'] = round((($stats['payments'] - $prevMonthPayments) / $prevMonthPayments) * 100, 1);
    } else {
        $stats['payments_growth'] = 100;
    }

    return $stats;
}

// Get recent payments
function getRecentPayments($conn, $limit = 5)
{
    $query = "SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, p.VerifiedAt, 
              c.Name as CustomerName, s.SchemeName 
              FROM Payments p 
              JOIN Customers c ON p.CustomerID = c.CustomerID 
              JOIN Schemes s ON p.SchemeID = s.SchemeID 
              ORDER BY p.SubmittedAt DESC 
              LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent activity
function getRecentActivity($conn, $limit = 7)
{
    $query = "SELECT a.Action, a.CreatedAt, a.UserType, a.UserID 
              FROM ActivityLogs a 
              ORDER BY a.CreatedAt DESC 
              LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $activities = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get user name
        if ($row['UserType'] == 'Admin') {
            $userQuery = "SELECT Name FROM Admins WHERE AdminID = :userId";
        } else {
            $userQuery = "SELECT Name FROM Promoters WHERE PromoterID = :userId";
        }

        $userStmt = $conn->prepare($userQuery);
        $userStmt->bindParam(':userId', $row['UserID'], PDO::PARAM_INT);
        $userStmt->execute();
        $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = $userResult['Name'] ?? 'Unknown User';

        $row['UserName'] = $userName;
        $activities[] = $row;
    }

    return $activities;
}

// Get today's registered customers
function getTodayCustomers($conn, $date = null)
{
    if (!$date) {
        $date = date('Y-m-d');
    }

    $query = "SELECT CustomerID, CustomerUniqueID, Name, Contact, Email, CreatedAt 
              FROM Customers 
              WHERE DATE(CreatedAt) = :date 
              ORDER BY CreatedAt DESC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get today's payments
function getTodayPayments($conn, $date = null)
{
    if (!$date) {
        $date = date('Y-m-d');
    }
    $query = "SELECT p.PaymentID, p.Amount, p.Status, p.SubmittedAt, 
                     c.Name as CustomerName, s.SchemeName
              FROM Payments p
              JOIN Customers c ON p.CustomerID = c.CustomerID
              JOIN Schemes s ON p.SchemeID = s.SchemeID
              WHERE DATE(p.SubmittedAt) = :date
              ORDER BY p.SubmittedAt DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get today's payments totals
function getTodayPaymentsTotals($conn, $date = null)
{
    if (!$date) {
        $date = date('Y-m-d');
    }
    $query = "SELECT COUNT(*) as total_count, COALESCE(SUM(Amount),0) as total_amount FROM Payments WHERE DATE(SubmittedAt) = :date";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Try to fetch stats with date range
try {
    $startDate = $selectedMonth . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $stats = getStats($conn, $startDate, $endDate);
    $recentPayments = getRecentPayments($conn);
    $recentActivity = getRecentActivity($conn);

    // Get today's customers
    $selectedDate = isset($_GET['customer_date']) ? $_GET['customer_date'] : date('Y-m-d');
    $todayCustomers = getTodayCustomers($conn, $selectedDate);
    // Get today's payments
    $selectedPaymentDate = isset($_GET['payment_date']) ? $_GET['payment_date'] : date('Y-m-d');
    $todayPayments = getTodayPayments($conn, $selectedPaymentDate);
    $todayPaymentsTotals = getTodayPaymentsTotals($conn, $selectedPaymentDate);
} catch (PDOException $e) {
    // If tables don't exist yet, use sample data
    $stats = [
        'customers' => 0,
        'customers_growth' => 0,
        'revenue' => 0,
        'revenue_growth' => 0,
        'schemes' => 0,
        'new_schemes' => 0,
        'payments' => 0,
        'payments_growth' => 0
    ];
    $recentPayments = [];
    $recentActivity = [];
    $todayCustomers = [];
    $todayPayments = [];
    $todayPaymentsTotals = ['total_count' => 0, 'total_amount' => 0];

    // If in development, show the error
    if (ini_get('display_errors')) {
        echo "<div style='color:red; padding:10px; background:#ffeeee; border:1px solid #ff0000;'>";
        echo "Database Error: " . $e->getMessage();
        echo "<br>Note: This error is only shown because display_errors is enabled.";
        echo "</div>";
    }
}

// Format revenue for display
function formatAmount($amount)
{
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . 'L';
    } elseif ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . 'K';
    } else {
        return '₹' . number_format($amount, 0);
    }
}

// Get initials from name
function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';

    if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        $initials = strtoupper(substr($name, 0, 2));
    }

    return $initials;
}

// Format date for display
function formatDate($date)
{
    $datetime = new DateTime($date);
    $datetime->modify('+5 hours +30 minutes');
    return $datetime->format('M d, Y');
}

// Format activity message
function formatActivity($action, $userName)
{
    $actionLower = strtolower($action);

    if (strpos($actionLower, 'customer') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New customer</strong> added by {$userName}";
    } elseif (strpos($actionLower, 'customer') !== false && strpos($actionLower, 'edit') !== false) {
        return "<strong>Customer updated</strong> by {$userName}";
    } elseif (strpos($actionLower, 'payment') !== false && strpos($actionLower, 'verify') !== false) {
        return "<strong>Payment verified</strong> by {$userName}";
    } elseif (strpos($actionLower, 'payment') !== false && strpos($actionLower, 'reject') !== false) {
        return "<strong>Payment rejected</strong> by {$userName}";
    } elseif (strpos($actionLower, 'promoter') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New promoter</strong> added by {$userName}";
    } elseif (strpos($actionLower, 'scheme') !== false && strpos($actionLower, 'add') !== false) {
        return "<strong>New scheme</strong> created by {$userName}";
    } elseif (strpos($actionLower, 'winner') !== false) {
        return "<strong>Winner announced</strong> by {$userName}";
    } else {
        return "<strong>{$action}</strong> by {$userName}";
    }
}

// Convert time to "X time ago" format
function timeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $ago->modify('+5 hours +30 minutes');
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        if ($diff->d == 1) {
            return 'Yesterday';
        }
        return $diff->d . ' days ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

// Add a new function to format time with IST
function formatTime($datetime)
{
    $date = new DateTime($datetime);
    $date->modify('+5 hours +30 minutes');
    return $date->format('h:i A');
}

// Add a new function to format datetime with IST
function formatDateTime($datetime)
{
    $date = new DateTime($datetime);
    $date->modify('+5 hours +30 minutes');
    return $date->format('M d, Y h:i A');
}

// Get the current month range for display
$currentMonthStart = date('F 1');
$currentMonthEnd = date('F t');
$dateRangeText = $currentMonthStart . ' - ' . $currentMonthEnd;

// Add date filtering variables
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$currentDate = new DateTime($selectedMonth . '-01');
$prevMonth = (clone $currentDate)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $currentDate)->modify('+1 month')->format('Y-m');
$dateRangeText = $currentDate->format('F Y');

// Get Admin info (normally would come from session)
$adminName = $_SESSION['admin_name'] ?? 'Admin User';
$adminRole = $_SESSION['admin_role'] ?? 'Administrator';

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Fix for layout collisions and improve responsiveness */
        .dashboard-container {
            padding: 20px;
            max-width: 100%;
        }

        /* Ensure proper spacing between each section */
        .recent-payments {
            margin-bottom: 30px;
            clear: both;
            /* Prevent any floating elements from affecting layout */
            overflow: hidden;
            /* Contain any overflowing content */
        }

        /* Make the payments table more responsive */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .payments-table {
                display: table;
            }
        }

        /* Make sure table cells don't shrink too much */
        .payments-table th,
        .payments-table td {
            min-width: 100px;
            padding: 12px 15px;
            text-align: left;
            white-space: nowrap;
        }

        /* Customer cell can be more flexible */
        .payments-table td:first-child {
            min-width: 150px;
        }

        /* Add horizontal scrolling for the table on small screens */
        @media (max-width: 768px) {
            .recent-payments {
                overflow-x: auto;
            }
        }

        /* Fix bottom section layout */
        .bottom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
            clear: both;
        }

        /* Ensure content wrapper follows a proper box model */
        .content-wrapper {
            box-sizing: border-box;
            padding: 15px;
        }

        /* Add a clearfix for any floating elements */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        .date-range {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-nav-btn {
            color: var(--primary-color);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .date-nav-btn:hover {
            background: rgba(58, 123, 213, 0.1);
            transform: scale(1.1);
        }

        .current-month {
            font-size: 16px;
            font-weight: 500;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-month i {
            color: var(--primary-color);
        }

        /* Today's Customers Section Styles */
        .today-customers {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0;
        }

        .date-filter input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .customers-table th,
        .customers-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .customers-table th {
            font-weight: 600;
            color: var(--secondary-color);
            background: #f8f9fa;
        }

        .customers-table .customer-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customers-table .customer-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .customers-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Today's Payments Section Styles */
        .today-payments {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .today-payments .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .today-payments .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary-color);
            margin: 0;
        }

        .today-payments .date-filter input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .today-payments .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .today-payments .payments-table th,
        .today-payments .payments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .today-payments .payments-table th {
            font-weight: 600;
            color: var(--secondary-color);
            background: #f8f9fa;
        }

        .today-payments .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .today-payments .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .today-payments .payments-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body class="">
    <div class="content-wrapper">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Dashboard Overview</h1>
                <div class="date-range">
                    <a href="?month=<?php echo $prevMonth; ?>" class="date-nav-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="current-month">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $dateRangeText; ?>
                    </span>
                    <a href="?month=<?php echo $nextMonth; ?>" class="date-nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon customers-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-title">Total Customers</div>
                    <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                    <div class="stat-change <?php echo $stats['customers_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                        <i class="fas fa-arrow-<?php echo $stats['customers_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                        <span><?php echo abs($stats['customers_growth']); ?>% this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value"><?php echo formatAmount($stats['revenue']); ?></div>
                    <div class="stat-change <?php echo $stats['revenue_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                        <i class="fas fa-arrow-<?php echo $stats['revenue_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                        <span><?php echo abs($stats['revenue_growth']); ?>% this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon schemes-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="stat-title">Active Schemes</div>
                    <div class="stat-value"><?php echo $stats['schemes']; ?></div>
                    <div class="stat-change positive-change">
                        <i class="fas fa-arrow-up"></i>
                        <span><?php echo $stats['new_schemes']; ?> new this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon payments-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-title">New Payments</div>
                    <div class="stat-value"><?php echo number_format($stats['payments']); ?></div>
                    <div class="stat-change <?php echo $stats['payments_growth'] >= 0 ? 'positive-change' : 'negative-change'; ?>">
                        <i class="fas fa-arrow-<?php echo $stats['payments_growth'] >= 0 ? 'up' : 'down'; ?>"></i>
                        <span><?php echo abs($stats['payments_growth']); ?>% this month</span>
                    </div>
                </div>
            </div>

            <!-- Today's Customers Section -->
            <div class="today-customers">
                <div class="section-header">
                    <h3 class="section-title">Today's Registered Customers</h3>
                    <div class="date-filter">
                        <form method="GET" class="date-form">
                            <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                            <input type="date" name="customer_date" value="<?php echo $selectedDate; ?>" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Registration Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($todayCustomers) > 0): ?>
                                <?php foreach ($todayCustomers as $customer): ?>
                                    <tr>
                                        <td><?php echo $customer['CustomerUniqueID']; ?></td>
                                        <td>
                                            <div class="customer-cell">
                                                <div class="customer-avatar">
                                                    <?php echo getInitials($customer['Name']); ?>
                                                </div>
                                                <?php echo htmlspecialchars($customer['Name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Email']); ?></td>
                                        <td><?php echo formatDateTime($customer['CreatedAt']); ?></td>
                                        <td>
                                            <a href="<?php echo $menuPath; ?>customers/view.php?id=<?php echo $customer['CustomerID']; ?>" class="action-btn custom-tooltip" data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">No customers registered on this date</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Today's Payments Section -->
            <div class="today-payments">
                <div class="section-header">
                    <h3 class="section-title">Today's Payments</h3>
                    <div class="date-filter">
                        <form method="GET" class="date-form">
                            <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                            <input type="date" name="payment_date" value="<?php echo $selectedPaymentDate; ?>" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                <div class="totals-row" style="margin-bottom: 15px; font-weight: 500; color: var(--secondary-color);">
                    Total Payments: <?php echo $todayPaymentsTotals['total_count']; ?> |
                    Total Amount: ₹<?php echo number_format($todayPaymentsTotals['total_amount']); ?>
                </div>
                <div class="table-responsive">
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Scheme</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($todayPayments) > 0): ?>
                                <?php foreach ($todayPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                        <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                                <?php echo $payment['Status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDateTime($payment['SubmittedAt']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">No payments made on this date</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payments Section -->
            <div class="recent-payments">
                <h3 class="section-title">Recent Payments</h3>
                <div class="table-responsive">
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Scheme</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['CustomerName']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['SchemeName']); ?></td>
                                    <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                            <?php echo $payment['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDateTime($payment['SubmittedAt']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="recent-activity">
                <h3 class="section-title">Recent Activity</h3>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <?php echo formatActivity($activity['Action'], $activity['UserName']); ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo timeAgo($activity['CreatedAt']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>