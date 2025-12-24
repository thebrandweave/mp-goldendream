<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "childPromoter";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter ID from session
$promoterId = $_SESSION['promoter_id'];

// Get current promoter's unique ID
$currentPromoterQuery = "SELECT PromoterUniqueID FROM Promoters WHERE PromoterID = :promoterId";
$currentStmt = $conn->prepare($currentPromoterQuery);
$currentStmt->bindParam(':promoterId', $promoterId);
$currentStmt->execute();
$currentPromoter = $currentStmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$itemsPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Build query for child promoters count (for pagination)
$countQuery = "
    SELECT COUNT(*) as total
    FROM Promoters p
    WHERE p.ParentPromoterID = :promoterUniqueId
";

// Add filters if provided
if (!empty($statusFilter)) {
    $countQuery .= " AND p.Status = :status";
}

if (!empty($searchQuery)) {
    $countQuery .= " AND (p.Name LIKE :search OR p.Contact LIKE :search OR p.Email LIKE :search OR p.PromoterUniqueID LIKE :search)";
}

// Prepare and execute the count query
$countStmt = $conn->prepare($countQuery);
$countStmt->bindParam(':promoterUniqueId', $currentPromoter['PromoterUniqueID']);

if (!empty($statusFilter)) {
    $countStmt->bindParam(':status', $statusFilter);
}

if (!empty($searchQuery)) {
    $searchParam = "%$searchQuery%";
    $countStmt->bindParam(':search', $searchParam);
}

$countStmt->execute();
$totalPromoters = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalPromoters / $itemsPerPage);

// Build query for child promoters
$query = "
    SELECT 
        p.PromoterID,
        p.PromoterUniqueID,
        p.Name,
        p.Contact,
        p.Email,
        p.Address,
        p.ProfileImageURL,
        p.BankAccountName,
        p.BankAccountNumber,
        p.IFSCCode,
        p.BankName,
        p.PaymentCodeCounter,
        p.TeamName,
        p.Status,
        p.Commission,
        p.CreatedAt,
        (SELECT COUNT(*) FROM Customers c WHERE c.PromoterID = p.PromoterUniqueID AND c.Status = 'Active') as CustomerCount
    FROM 
        Promoters p
    WHERE 
        p.ParentPromoterID = :promoterUniqueId
";

// Add filters if provided
if (!empty($statusFilter)) {
    $query .= " AND p.Status = :status";
}

if (!empty($searchQuery)) {
    $query .= " AND (p.Name LIKE :search OR p.Contact LIKE :search OR p.Email LIKE :search OR p.PromoterUniqueID LIKE :search)";
}

$query .= " ORDER BY p.CreatedAt DESC LIMIT :offset, :limit";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bindParam(':promoterUniqueId', $currentPromoter['PromoterUniqueID']);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);

if (!empty($statusFilter)) {
    $stmt->bindParam(':status', $statusFilter);
}

if (!empty($searchQuery)) {
    $searchParam = "%$searchQuery%";
    $stmt->bindParam(':search', $searchParam);
}

$stmt->execute();
$childPromoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all promoters for statistics (not just the current page)
$statsQuery = "
    SELECT 
        p.Status,
        COUNT(DISTINCT c.CustomerID) AS CustomerCount,
        COUNT(DISTINCT pay.PaymentID) AS PaymentCount,
        SUM(CASE WHEN pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) AS TotalVerifiedAmount
    FROM 
        Promoters p
    LEFT JOIN 
        Customers c ON p.PromoterUniqueID = c.PromoterID
    LEFT JOIN 
        Payments pay ON p.PromoterID = pay.PromoterID
    WHERE 
        p.ParentPromoterID = :promoterUniqueId
";

if (!empty($statusFilter)) {
    $statsQuery .= " AND p.Status = :status";
}

if (!empty($searchQuery)) {
    $statsQuery .= " AND (p.Name LIKE :search OR p.Contact LIKE :search OR p.Email LIKE :search OR p.PromoterUniqueID LIKE :search)";
}

$statsQuery .= " GROUP BY p.PromoterID";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bindParam(':promoterUniqueId', $currentPromoter['PromoterUniqueID']);

if (!empty($statusFilter)) {
    $statsStmt->bindParam(':status', $statusFilter);
}

if (!empty($searchQuery)) {
    $searchParam = "%$searchQuery%";
    $statsStmt->bindParam(':search', $searchParam);
}

$statsStmt->execute();
$allPromoters = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get total customers count for the current promoter
$currentPromoterCustomersQuery = "
    SELECT COUNT(DISTINCT c.CustomerID) as total_customers
    FROM Customers c
    WHERE c.PromoterID = :promoterUniqueId
    AND c.Status = 'Active'
";
$currentPromoterCustomersStmt = $conn->prepare($currentPromoterCustomersQuery);
$currentPromoterCustomersStmt->bindParam(':promoterUniqueId', $currentPromoter['PromoterUniqueID']);
$currentPromoterCustomersStmt->execute();
$currentPromoterCustomersResult = $currentPromoterCustomersStmt->fetch(PDO::FETCH_ASSOC);
$currentPromoterCustomers = $currentPromoterCustomersResult['total_customers'];

// Get total customers count for all child promoters
$childPromotersCustomersQuery = "
    SELECT COUNT(DISTINCT c.CustomerID) as total_customers
    FROM Customers c
    JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
    WHERE p.ParentPromoterID = :promoterUniqueId
    AND c.Status = 'Active'
";
$childPromotersCustomersStmt = $conn->prepare($childPromotersCustomersQuery);
$childPromotersCustomersStmt->bindParam(':promoterUniqueId', $currentPromoter['PromoterUniqueID']);
$childPromotersCustomersStmt->execute();
$childPromotersCustomersResult = $childPromotersCustomersStmt->fetch(PDO::FETCH_ASSOC);
$childPromotersCustomers = $childPromotersCustomersResult['total_customers'];

// Total customers (current promoter + child promoters)
$totalCustomers = $currentPromoterCustomers + $childPromotersCustomers;

$activeChildPromoters = 0;
$totalPayments = 0;
$totalVerifiedAmount = 0;

foreach ($allPromoters as $promoter) {
    if ($promoter['Status'] == 'Active') {
        $activeChildPromoters++;
    }
    $totalPayments += $promoter['PaymentCount'];
    $totalVerifiedAmount += $promoter['TotalVerifiedAmount'];
}

// Get current promoter details
$promoterQuery = "
    SELECT 
        PromoterID,
        PromoterUniqueID,
        Name,
        Contact,
        Email,
        ProfileImageURL
    FROM 
        Promoters
    WHERE 
        PromoterID = :promoterId
";

$promoterStmt = $conn->prepare($promoterQuery);
$promoterStmt->bindParam(':promoterId', $promoterId);
$promoterStmt->execute();
$currentPromoter = $promoterStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Promoters of <?php echo htmlspecialchars($currentPromoter['Name']); ?> | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
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

        .stat-icon.total {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .stat-icon.active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-icon.customers {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-icon.payments {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .filters-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.15);
        }

        .btn-reset {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-reset:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .promoters-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .promoters-table th {
            background: var(--bg-light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .promoters-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
        }

        .promoters-table tr:last-child td {
            border-bottom: none;
        }

        .promoters-table tr:hover {
            background: var(--bg-light);
        }

        .promoter-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--primary-color);
        }

        .promoter-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .promoter-info {
            display: flex;
            flex-direction: column;
        }

        .promoter-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .promoter-id {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-inactive {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .promoter-stats {
            display: flex;
            gap: 15px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .promoter-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 50px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }

        .pagination-item {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .pagination-item:hover {
            background: var(--primary-light);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination-item.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .parent-promoter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .parent-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--primary-color);
        }

        .parent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .parent-info {
            flex: 1;
        }

        .parent-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .parent-id {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .parent-contact {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .contact-item i {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .promoters-table {
                display: block;
                overflow-x: auto;
            }

            .parent-promoter-card {
                flex-direction: column;
                text-align: center;
            }

            .parent-contact {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="section-info">
                        <h2>Child Promoters of <?php echo htmlspecialchars($currentPromoter['Name']); ?></h2>
                        <p>View and manage child promoters under <?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?></p>
                    </div>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to All Promoters
                </a>
            </div>

            <!-- Parent Promoter Card -->
            <div class="parent-promoter-card">
                <div class="parent-avatar">
                    <?php if (!empty($currentPromoter['ProfileImageURL'])): ?>
                        <img src="../../<?php echo htmlspecialchars($currentPromoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($currentPromoter['Name']); ?>">
                    <?php else: ?>
                        <img src="./image.png" alt="Default Profile">
                    <?php endif; ?>
                </div>
                <div class="parent-info">
                    <h3 class="parent-name"><?php echo htmlspecialchars($currentPromoter['Name']); ?></h3>
                    <div class="parent-id"><?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?></div>
                    <div class="parent-contact">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($currentPromoter['Contact']); ?>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($currentPromoter['Email']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalPromoters; ?></h3>
                        <p>Total Child Promoters</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $activeChildPromoters; ?></h3>
                        <p>Active Promoters</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon customers">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalCustomers; ?></h3>
                        <p>Total Customers</p>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            <div>My Customers: <?php echo $currentPromoterCustomers; ?></div>
                            <div>Child Promoters' Customers: <?php echo $childPromotersCustomers; ?></div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon payments">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>₹<?php echo number_format($totalVerifiedAmount, 2); ?></h3>
                        <p>Total Verified Amount</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-container">
                <div class="filters-title">Filter Promoters</div>
                <form class="filters-form" method="GET">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?>">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $statusFilter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Name, Contact, Email, ID" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="viewByParent.php?id=<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?>" class="btn btn-reset">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Promoters Table -->
            <?php if (empty($childPromoters)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Child Promoters Found</h3>
                    <p>There are no child promoters under <?php echo htmlspecialchars($currentPromoter['Name']); ?> yet.</p>
                </div>
            <?php else: ?>
                <table class="promoters-table">
                    <thead>
                        <tr>
                            <th>Promoter</th>
                            <th>Contact</th>
                            <th>Team</th>
                            <th>Status</th>
                            <th>Customers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($childPromoters as $promoter): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="promoter-avatar">
                                            <?php if (!empty($promoter['ProfileImageURL'])): ?>
                                                <img src="../../<?php echo htmlspecialchars($promoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($promoter['Name']); ?>">
                                            <?php else: ?>
                                                <img src="./image.png" alt="Default Profile">
                                            <?php endif; ?>
                                        </div>
                                        <div class="promoter-info">
                                            <span class="promoter-name"><?php echo htmlspecialchars($promoter['Name']); ?></span>
                                            <span class="promoter-id"><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($promoter['Contact']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($promoter['Email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($promoter['TeamName'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                                        <?php echo $promoter['Status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stat-value"><?php echo $promoter['CustomerCount']; ?></div>
                                    <div class="stat-label">Active Customers</div>
                                </td>
                                <td>
                                    <div class="promoter-actions">
                                        <a href="view.php?id=<?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>" class="action-btn" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $promoter['PromoterID']; ?>" class="action-btn" title="Edit Promoter">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $promoter['PromoterID']; ?>" class="action-btn" title="Delete Promoter" onclick="return confirm('Are you sure you want to delete this promoter? This action cannot be undone.');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1&id=<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="pagination-item">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1; ?>&id=<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="pagination-item">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-item disabled">
                                <i class="fas fa-angle-double-left"></i>
                            </span>
                            <span class="pagination-item disabled">
                                <i class="fas fa-angle-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1) {
                            echo '<a href="?page=1&id=' . htmlspecialchars($currentPromoter['PromoterUniqueID']) . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '') . '" class="pagination-item">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="pagination-item disabled">...</span>';
                            }
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $page) {
                                echo '<span class="pagination-item active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '&id=' . htmlspecialchars($currentPromoter['PromoterUniqueID']) . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '') . '" class="pagination-item">' . $i . '</a>';
                            }
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="pagination-item disabled">...</span>';
                            }
                            echo '<a href="?page=' . $totalPages . '&id=' . htmlspecialchars($currentPromoter['PromoterUniqueID']) . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '') . '" class="pagination-item">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&id=<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="pagination-item">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $totalPages; ?>&id=<?php echo htmlspecialchars($currentPromoter['PromoterUniqueID']); ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" class="pagination-item">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-item disabled">
                                <i class="fas fa-angle-right"></i>
                            </span>
                            <span class="pagination-item disabled">
                                <i class="fas fa-angle-double-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
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

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html>