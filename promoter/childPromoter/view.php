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

// Get promoter ID from URL parameter
$promoterUniqueId = isset($_GET['id']) ? $_GET['id'] : '';

// Redirect if no promoter ID is provided
if (empty($promoterUniqueId)) {
    header("Location: index.php");
    exit();
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$itemsPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Build query for child promoters count
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
$countStmt->bindParam(':promoterUniqueId', $promoterUniqueId);

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
        p.TeamName,
        p.Status,
        p.Commission,
        p.CreatedAt,
        (SELECT COUNT(DISTINCT c.CustomerID) 
         FROM Customers c 
         WHERE c.PromoterID = p.PromoterUniqueID 
         AND c.Status = 'Active') AS CustomerCount,
        COUNT(DISTINCT pay.PaymentID) AS PaymentCount,
        COALESCE(pw.BalanceAmount, 0) AS TotalVerifiedAmount
    FROM 
        Promoters p
    LEFT JOIN 
        Payments pay ON p.PromoterID = pay.PromoterID
    LEFT JOIN
        PromoterWallet pw ON p.PromoterUniqueID = pw.PromoterUniqueID
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

$query .= " GROUP BY p.PromoterID ORDER BY p.CreatedAt DESC LIMIT :offset, :limit";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bindParam(':promoterUniqueId', $promoterUniqueId);
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

// Get parent promoter details
$parentQuery = "
    SELECT 
        PromoterID,
        PromoterUniqueID,
        Name,
        Contact,
        Email,
        ProfileImageURL,
        TeamName,
        Status
    FROM 
        Promoters
    WHERE 
        PromoterUniqueID = :promoterUniqueId
";

$parentStmt = $conn->prepare($parentQuery);
$parentStmt->bindParam(':promoterUniqueId', $promoterUniqueId);
$parentStmt->execute();
$parentPromoter = $parentStmt->fetch(PDO::FETCH_ASSOC);

// Get current promoter's customer count
$currentPromoterCustomersQuery = "
    SELECT COUNT(DISTINCT c.CustomerID) as total_customers
    FROM Customers c
    WHERE c.PromoterID = :promoterUniqueId
    AND c.Status = 'Active'
";
$currentPromoterCustomersStmt = $conn->prepare($currentPromoterCustomersQuery);
$currentPromoterCustomersStmt->bindParam(':promoterUniqueId', $promoterUniqueId);
$currentPromoterCustomersStmt->execute();
$currentPromoterCustomersResult = $currentPromoterCustomersStmt->fetch(PDO::FETCH_ASSOC);
$currentPromoterCustomers = $currentPromoterCustomersResult['total_customers'];

// Get child promoters' customer count
$childPromotersCustomersQuery = "
    SELECT COUNT(DISTINCT c.CustomerID) as total_customers
    FROM Customers c
    JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
    WHERE p.ParentPromoterID = :promoterUniqueId
    AND c.Status = 'Active'
";
$childPromotersCustomersStmt = $conn->prepare($childPromotersCustomersQuery);
$childPromotersCustomersStmt->bindParam(':promoterUniqueId', $promoterUniqueId);
$childPromotersCustomersStmt->execute();
$childPromotersCustomersResult = $childPromotersCustomersStmt->fetch(PDO::FETCH_ASSOC);
$childPromotersCustomers = $childPromotersCustomersResult['total_customers'];

// Total customers (current promoter + child promoters)
$totalCustomers = $currentPromoterCustomers + $childPromotersCustomers;

// Calculate statistics
$totalChildPromoters = $totalPromoters;
$activeChildPromoters = 0;
$totalPayments = 0;
$totalVerifiedAmount = 0;

foreach ($childPromoters as $promoter) {
    if ($promoter['Status'] == 'Active') {
        $activeChildPromoters++;
    }
    $totalPayments += $promoter['PaymentCount'];
    $totalVerifiedAmount += $promoter['TotalVerifiedAmount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Promoters of <?php echo htmlspecialchars($parentPromoter['Name']); ?> | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Copy the same CSS from index.php */
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
        }

        .promoter-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
        }

        .promoter-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            text-decoration: none;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary-color);
            border-color: var(--primary-color);
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
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
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

        .promoters-table th,
        .promoters-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .promoters-table th {
            background: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
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

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .promoters-table {
                display: block;
                overflow-x: auto;
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
                        <h2>Child Promoters of <?php echo htmlspecialchars($parentPromoter['Name']); ?></h2>
                        <p>View and manage child promoters under <?php echo htmlspecialchars($parentPromoter['PromoterUniqueID']); ?></p>
                    </div>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to My Promoters
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3><?php echo $totalChildPromoters; ?></h3>
                    <p>Total Child Promoters</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $activeChildPromoters; ?></h3>
                    <p>Active Promoters</p>
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
                    <h3>₹<?php echo number_format($totalVerifiedAmount, 2); ?></h3>
                    <p>Total Verified Amount</p>
                </div>
            </div>

            <!-- Promoters Table -->
            <?php if (empty($childPromoters)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Child Promoters Found</h3>
                    <p>There are no child promoters under <?php echo htmlspecialchars($parentPromoter['Name']); ?> yet.</p>
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
                            <th>Payments</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($childPromoters as $promoter): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="promoter-avatar">
                                            <?php if (!empty($promoter['ProfileImageURL']) && $promoter['ProfileImageURL'] !== '-'): ?>
                                                <img src="../../<?php echo htmlspecialchars($promoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($promoter['Name']); ?>">
                                            <?php else: ?>
                                                <img src="../image.png" alt="Default Profile">
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($promoter['Name']); ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></div>
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
                                <td><?php echo $promoter['CustomerCount']; ?></td>
                                <td><?php echo $promoter['PaymentCount']; ?></td>
                                <td>₹<?php echo number_format($promoter['TotalVerifiedAmount'], 2); ?></td>
                                <td>
                                    <div class="promoter-actions">
                                        <a href="view.php?id=<?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>" class="action-btn" title="View Details">
                                            <i class="fas fa-eye"></i>
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
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&id=<?php echo htmlspecialchars($promoterUniqueId); ?>" 
                               class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 