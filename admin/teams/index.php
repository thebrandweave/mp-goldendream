<?php
session_start();
$menuPath = "../";
$currentPage = "teams";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 12; // Show 12 teams per page
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

try {
    // Optimized query to get teams with their stats in a single query
    $query = "
        WITH TeamBase AS (
            SELECT DISTINCT TeamName
            FROM (
                SELECT TeamName, CreatedAt 
                FROM Promoters 
                WHERE TeamName IS NOT NULL AND TeamName != ''
                UNION ALL
                SELECT TeamName, CreatedAt 
                FROM Customers 
                WHERE TeamName IS NOT NULL AND TeamName != ''
            ) t
            WHERE 1=1
    ";

    $params = [];

    if (!empty($search)) {
        $query .= " AND TeamName LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= "),
    TeamStats AS (
        SELECT 
            t.TeamName,
            COUNT(DISTINCT p.PromoterID) as promoter_count,
            COUNT(DISTINCT c.CustomerID) as customer_count,
            MAX(GREATEST(COALESCE(p.CreatedAt, '1970-01-01'), COALESCE(c.CreatedAt, '1970-01-01'))) as last_activity,
            COALESCE(ps.total_payments, 0) as total_payments,
            COALESCE(ps.total_amount, 0) as total_amount,
            COALESCE(ps.verified_payments, 0) as verified_payments,
            COALESCE(ps.pending_payments, 0) as pending_payments,
            COALESCE(ps.rejected_payments, 0) as rejected_payments
        FROM TeamBase t
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
            GROUP BY cu.TeamName
        ) ps ON ps.TeamName = t.TeamName
        GROUP BY t.TeamName
    )
    SELECT * FROM TeamStats
    ORDER BY last_activity DESC
    LIMIT :offset, :limit";

    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT TeamName) as total 
        FROM (
            SELECT TeamName FROM Promoters WHERE TeamName IS NOT NULL AND TeamName != ''
            UNION ALL
            SELECT TeamName FROM Customers WHERE TeamName IS NOT NULL AND TeamName != ''
        ) t
        WHERE 1=1";

    if (!empty($search)) {
        $countQuery .= " AND TeamName LIKE :search";
    }

    // Execute count query
    $stmt = $conn->prepare($countQuery);
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Execute main query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
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

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .team-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .team-header {
            margin-bottom: 15px;
        }

        .team-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 10px 0;
        }

        .team-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .team-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .last-activity {
            font-size: 12px;
            color: #7f8c8d;
        }

        .view-link {
            color: #3498db;
            font-size: 14px;
            font-weight: 500;
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
        }

        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }

        .loading.active {
            display: block;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Team Management</h1>
        </div>

        <div class="search-box">
            <form action="" method="GET" style="display: flex; gap: 10px; width: 100%;" id="searchForm">
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

        <div class="loading">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>

        <?php if (count($teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($teams as $team): ?>
                    <a href="view.php?team=<?php echo urlencode($team['TeamName']); ?>" class="team-card">
                        <div class="team-header">
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
                                    <div class="stat-label">Total Payments</div>
                                    <div class="stat-value"><?php echo $team['total_payments']; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Total Amount</div>
                                    <div class="stat-value">₹<?php echo number_format($team['total_amount']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="team-footer">
                            <div class="last-activity">
                                <i class="fas fa-clock"></i> Last activity: <?php echo date('M d, Y', strtotime($team['last_activity'])); ?>
                            </div>
                            <span class="view-link">
                                View Details <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

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

    <script>
        document.getElementById('searchForm').addEventListener('submit', function() {
            document.querySelector('.loading').classList.add('active');
        });
    </script>
</body>

</html>