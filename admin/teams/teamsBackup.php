<?php
session_start();
$menuPath = "../";
$currentPage = "teams";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a longer timeout for the script
set_time_limit(300); // 5 minutes

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Add error handling for database connection
if (!$conn) {
    die("Database connection failed");
}

// Add debug logging
error_log("Starting teams page load at " . date('Y-m-d H:i:s'));

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(t.TeamName LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "t.Status = :status";
    $params[':status'] = $status;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total teams count with error handling
try {
    error_log("Executing count query at " . date('Y-m-d H:i:s'));
    $countQuery = "
        SELECT COUNT(DISTINCT TeamName) as total 
        FROM (
            SELECT TeamName FROM Promoters WHERE TeamName IS NOT NULL AND TeamName != ''
            UNION ALL
            SELECT TeamName FROM Customers WHERE TeamName IS NOT NULL AND TeamName != ''
        ) t" . $whereClause;

    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
    error_log("Count query completed. Total records: " . $totalRecords);
} catch (PDOException $e) {
    error_log("Error in count query: " . $e->getMessage());
    die("Error getting team count. Please try again later.");
}

// Get teams with member counts - optimized query
try {
    error_log("Executing main query at " . date('Y-m-d H:i:s'));

    // First get the base team list with pagination
    $baseQuery = "
        SELECT DISTINCT TeamName
        FROM (
            SELECT TeamName, CreatedAt 
            FROM Promoters 
            WHERE TeamName IS NOT NULL AND TeamName != ''
            UNION ALL
            SELECT TeamName, CreatedAt 
            FROM Customers 
            WHERE TeamName IS NOT NULL AND TeamName != ''
        ) t" . $whereClause . "
        ORDER BY CreatedAt DESC
        LIMIT :offset, :limit";

    $stmt = $conn->prepare($baseQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $teamNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($teamNames)) {
        $teams = [];
    } else {
        // Then get the detailed stats for these teams
        $placeholders = str_repeat('?,', count($teamNames) - 1) . '?';
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
                WHERE TeamName IN ($placeholders)
                UNION ALL
                SELECT TeamName, CreatedAt 
                FROM Customers 
                WHERE TeamName IN ($placeholders)
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
                WHERE cu.TeamName IN ($placeholders)
                GROUP BY cu.TeamName
            ) ps ON ps.TeamName = t.TeamName
            GROUP BY t.TeamName
            ORDER BY last_activity DESC";

        $stmt = $conn->prepare($statsQuery);
        $params = array_merge($teamNames, $teamNames, $teamNames); // For the three IN clauses
        $stmt->execute($params);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    error_log("Main query completed. Retrieved " . count($teams) . " teams");
} catch (PDOException $e) {
    error_log("Error in main query: " . $e->getMessage());
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
    <title>Team Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .content-wrapper {
            padding: 20px;
        }

        .error-message {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            border: 1px solid #fecaca;
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

        .team-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .team-name {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        .team-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .team-members {
            margin-top: 20px;
        }

        .member-section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 500;
            color: #34495e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .member-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .member-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .member-id {
            font-size: 12px;
            color: #7f8c8d;
        }

        .member-status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            background: #e8f5e9;
            color: #2ecc71;
        }

        .member-status.inactive {
            background: #fee2e2;
            color: #e74c3c;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #dfe6e9;
            border-radius: 6px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }

        .pagination .active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .no-teams {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }

        .no-teams i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .no-teams p {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .payment-stats {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .payment-stats .team-stats {
            margin-top: 10px;
        }

        .payment-stats .stat-item {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .payment-stats .stat-value {
            font-size: 16px;
        }

        .payment-stats .section-title {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .payment-stats .section-title i {
            color: #3498db;
        }

        .member-contact {
            font-size: 12px;
            color: #3498db;
            margin-top: 5px;
        }

        .member-contact i {
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Team Management</h1>
        </div>

        <div class="search-box">
            <form action="" method="GET" style="display: flex; gap: 10px; width: 100%;">
                <input type="text" name="search" class="search-input"
                    placeholder="Search teams..."
                    value="<?php echo htmlspecialchars($search); ?>">

                <select name="status_filter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>

                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="index.php" class="btn" style="background: #f1f2f6; color: #576574;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($teams) > 0): ?>
            <?php foreach ($teams as $team): ?>
                <div class="team-card">
                    <div class="team-header">
                        <div>
                            <h2 class="team-name"><?php echo htmlspecialchars($team['TeamName']); ?></h2>
                            <div class="team-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Promoters</div>
                                    <div class="stat-value"><?php echo $team['promoter_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Customers</div>
                                    <div class="stat-value"><?php echo $team['customer_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Total Members</div>
                                    <div class="stat-value"><?php echo $team['promoter_count'] + $team['customer_count']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Last Activity</div>
                                    <div class="stat-value"><?php echo date('M d, Y', strtotime($team['last_activity'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Payment Statistics Section -->
                    <div class="payment-stats">
                        <h3 class="section-title">
                            <i class="fas fa-money-bill-wave"></i> Payment Statistics
                        </h3>
                        <div class="team-stats">
                            <div class="stat-item">
                                <div class="stat-label">Total Payments</div>
                                <div class="stat-value"><?php echo $team['total_payments']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Total Amount</div>
                                <div class="stat-value">₹<?php echo number_format($team['total_amount'], 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Verified</div>
                                <div class="stat-value" style="color: #2ecc71;"><?php echo $team['verified_payments']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Pending</div>
                                <div class="stat-value" style="color: #f39c12;"><?php echo $team['pending_payments']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Rejected</div>
                                <div class="stat-value" style="color: #e74c3c;"><?php echo $team['rejected_payments']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="team-members">
                        <?php
                        try {
                            error_log("Getting promoters for team: " . $team['TeamName']);
                            // Get promoters in this team
                            $stmt = $conn->prepare("
                                SELECT PromoterID, PromoterUniqueID, Name, Status 
                                FROM Promoters 
                                WHERE TeamName = ? 
                                ORDER BY CreatedAt DESC
                            ");
                            $stmt->execute([$team['TeamName']]);
                            $promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            error_log("Retrieved " . count($promoters) . " promoters");

                            // Get customers in this team
                            $stmt = $conn->prepare("
                                SELECT CustomerID, CustomerUniqueID, Name, Status, Contact 
                                FROM Customers 
                                WHERE TeamName = ? 
                                ORDER BY CreatedAt DESC
                            ");
                            $stmt->execute([$team['TeamName']]);
                            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            error_log("Retrieved " . count($customers) . " customers");
                        } catch (PDOException $e) {
                            error_log("Error getting team members: " . $e->getMessage());
                            echo '<div class="error-message">Error loading team members. Please try again later.</div>';
                            $promoters = [];
                            $customers = [];
                        }
                        ?>

                        <?php if (!empty($promoters)): ?>
                            <div class="member-section">
                                <h3 class="section-title">
                                    <i class="fas fa-user-tie"></i> Promoters
                                </h3>
                                <div class="member-list">
                                    <?php foreach ($promoters as $promoter): ?>
                                        <div class="member-card">
                                            <div class="member-info">
                                                <div class="member-name"><?php echo htmlspecialchars($promoter['Name']); ?></div>
                                                <div class="member-id"><?php echo $promoter['PromoterUniqueID']; ?></div>
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
                            <div class="member-section">
                                <h3 class="section-title">
                                    <i class="fas fa-users"></i> Customers
                                </h3>
                                <div class="member-list">
                                    <?php foreach ($customers as $customer): ?>
                                        <div class="member-card">
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
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>"
                            class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-teams">
                <i class="fas fa-users"></i>
                <p>No teams found</p>
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="index.php" class="btn btn-primary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>