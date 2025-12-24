<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "activity-logs";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userTypeFilter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$ipAddress = isset($_GET['ip_address']) ? $_GET['ip_address'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "Action LIKE ?";
    $params[] = "%$search%";
}

if (!empty($userTypeFilter)) {
    $conditions[] = "UserType = ?";
    $params[] = $userTypeFilter;
}

if (!empty($ipAddress)) {
    $conditions[] = "IPAddress LIKE ?";
    $params[] = "%$ipAddress%";
}

if (!empty($dateRange)) {
    $dates = explode(' - ', $dateRange);
    if (count($dates) == 2) {
        $startDate = date('Y-m-d', strtotime($dates[0]));
        $endDate = date('Y-m-d', strtotime($dates[1] . ' +1 day'));
        $conditions[] = "CreatedAt BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total logs count
$countQuery = "SELECT COUNT(*) as total FROM ActivityLogs" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get activity logs with pagination
$query = "
    SELECT al.*, 
           CASE 
               WHEN al.UserType = 'Admin' THEN a.Name 
               WHEN al.UserType = 'Promoter' THEN p.Name 
           END as UserName,
           CASE 
               WHEN al.UserType = 'Admin' THEN a.Email 
               WHEN al.UserType = 'Promoter' THEN p.Email
           END as UserEmail
    FROM ActivityLogs al
    LEFT JOIN Admins a ON al.UserType = 'Admin' AND al.UserID = a.AdminID
    LEFT JOIN Promoters p ON al.UserType = 'Promoter' AND al.UserID = p.PromoterID"
    . $whereClause .
    " ORDER BY al.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique IP addresses for filter dropdown
$ipQuery = "SELECT DISTINCT IPAddress FROM ActivityLogs ORDER BY IPAddress ASC";
$ipStmt = $conn->query($ipQuery);
$uniqueIPs = $ipStmt->fetchAll(PDO::FETCH_COLUMN);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .log-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
            position: relative;
            overflow: hidden;
        }

        .log-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .user-info {
            flex: 1;
            min-width: 250px;
        }

        .user-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .user-email {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .timestamp {
            font-size: 0.85em;
            color: #95a5a6;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-text {
            font-size: 1.05em;
            color: #34495e;
            line-height: 1.5;
            margin: 15px 0;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #3498db;
        }

        .log-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .ip-address {
            font-size: 0.85em;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .log-id {
            font-size: 0.85em;
            color: #95a5a6;
        }

        .user-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            margin-left: 10px;
        }

        .admin-badge {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .promoter-badge {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }

        .filter-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }

        .search-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 0.9em;
            font-weight: 500;
            color: #34495e;
        }

        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .export-btn {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            font-weight: 500;
            cursor: pointer;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }

        .pagination a {
            padding: 8px 12px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #ddd;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }

        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .no-records {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            color: #7f8c8d;
        }

        .no-records i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .activity-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .stat-icon {
            font-size: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: white;
        }

        .admin-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .promoter-icon {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }

        .today-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .total-icon {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Activity Logs</h1>
            <a href="export.php" class="export-btn">
                <i class="fas fa-file-export"></i> Export to CSV
            </a>
        </div>

        <?php
        // Get activity statistics
        $statsQuery = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN UserType = 'Admin' THEN 1 ELSE 0 END) as admin_count,
            SUM(CASE WHEN UserType = 'Promoter' THEN 1 ELSE 0 END) as promoter_count,
            SUM(CASE WHEN DATE(CreatedAt) = CURDATE() THEN 1 ELSE 0 END) as today_count
        FROM ActivityLogs";
        $statsStmt = $conn->query($statsQuery);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="activity-stats">
            <div class="stat-card">
                <div class="stat-icon admin-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-label">Admin Activities</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon promoter-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo $stats['promoter_count']; ?></div>
                <div class="stat-label">Promoter Activities</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon today-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo $stats['today_count']; ?></div>
                <div class="stat-label">Today's Activities</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-body">
                <div class="filter-container">

                    <div class="search-box">
                        <label class="filter-label">Search:</label>

                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search activities..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">User Type:</label>
                        <select class="filter-select" name="user_type">
                            <option value="">All Types</option>
                            <option value="Admin" <?php echo $userTypeFilter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Promoter" <?php echo $userTypeFilter === 'Promoter' ? 'selected' : ''; ?>>Promoter</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">IP Address:</label>
                        <select class="filter-select" name="ip_address">
                            <option value="">All IP Addresses</option>
                            <?php foreach ($uniqueIPs as $ip): ?>
                                <option value="<?php echo $ip; ?>" <?php echo $ipAddress === $ip ? 'selected' : ''; ?>>
                                    <?php echo $ip; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <input type="text" class="filter-select datepicker" name="date_range"
                            placeholder="Select date range" value="<?php echo htmlspecialchars($dateRange); ?>">
                    </div>
                </div>

                <?php if (count($activityLogs) > 0): ?>
                    <?php foreach ($activityLogs as $log): ?>
                        <div class="log-card">
                            <div class="log-header">
                                <div class="user-info">
                                    <div class="user-name">
                                        <?php if (!empty($log['UserName'])): ?>
                                            <?php echo htmlspecialchars($log['UserName']); ?>
                                        <?php else: ?>
                                            Unknown User
                                        <?php endif; ?>

                                        <span class="user-type-badge <?php echo strtolower($log['UserType']); ?>-badge">
                                            <i class="fas fa-<?php echo $log['UserType'] === 'Admin' ? 'user-shield' : 'user-tie'; ?>"></i>
                                            <?php echo $log['UserType']; ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($log['UserEmail'])): ?>
                                        <div class="user-email">
                                            <i class="far fa-envelope"></i> <?php echo htmlspecialchars($log['UserEmail']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="timestamp">
                                    <i class="far fa-clock"></i>
                                    <?php
                                    $timestamp = strtotime($log['CreatedAt']);
                                    $istTimestamp = $timestamp + (5 * 3600) + (30 * 60); // Add 5 hours and 30 minutes
                                    echo date('M d, Y h:i:s A', $istTimestamp);
                                    ?>
                                </div>
                            </div>

                            <div class="action-text">
                                <?php echo htmlspecialchars($log['Action']); ?>
                            </div>

                            <div class="log-footer">
                                <div class="ip-address">
                                    <i class="fas fa-network-wired"></i>
                                    IP: <?php echo $log['IPAddress']; ?>
                                </div>

                                <div class="log-id">
                                    Log ID: <?php echo $log['LogID']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($userTypeFilter) ? '&user_type=' . urlencode($userTypeFilter) : ''; ?><?php echo !empty($ipAddress) ? '&ip_address=' . urlencode($ipAddress) : ''; ?><?php echo !empty($dateRange) ? '&date_range=' . urlencode($dateRange) : ''; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($userTypeFilter) ? '&user_type=' . urlencode($userTypeFilter) : ''; ?><?php echo !empty($ipAddress) ? '&ip_address=' . urlencode($ipAddress) : ''; ?><?php echo !empty($dateRange) ? '&date_range=' . urlencode($dateRange) : ''; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($userTypeFilter) ? '&user_type=' . urlencode($userTypeFilter) : ''; ?><?php echo !empty($ipAddress) ? '&ip_address=' . urlencode($ipAddress) : ''; ?><?php echo !empty($dateRange) ? '&date_range=' . urlencode($dateRange) : ''; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($userTypeFilter) ? '&user_type=' . urlencode($userTypeFilter) : ''; ?><?php echo !empty($ipAddress) ? '&ip_address=' . urlencode($ipAddress) : ''; ?><?php echo !empty($dateRange) ? '&date_range=' . urlencode($dateRange) : ''; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($userTypeFilter) ? '&user_type=' . urlencode($userTypeFilter) : ''; ?><?php echo !empty($ipAddress) ? '&ip_address=' . urlencode($ipAddress) : ''; ?><?php echo !empty($dateRange) ? '&date_range=' . urlencode($dateRange) : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-history"></i>
                        <p>No activity logs found</p>
                        <?php if (!empty($search) || !empty($userTypeFilter) || !empty($ipAddress) || !empty($dateRange)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr(".datepicker", {
            mode: "range",
            dateFormat: "Y-m-d",
            maxDate: "today",
            onChange: function() {
                setTimeout(updateFilters, 100);
            }
        });

        // Handle search and filters
        const searchInput = document.querySelector('.search-input');
        const userTypeSelect = document.querySelector('select[name="user_type"]');
        const ipAddressSelect = document.querySelector('select[name="ip_address"]');
        const dateRangeInput = document.querySelector('input[name="date_range"]');

        let searchTimeout;

        function updateFilters() {
            const search = searchInput.value.trim();
            const userType = userTypeSelect.value;
            const ipAddress = ipAddressSelect.value;
            const dateRange = dateRangeInput.value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (userType) params.append('user_type', userType);
            if (ipAddress) params.append('ip_address', ipAddress);
            if (dateRange) params.append('date_range', dateRange);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 500);
        });

        userTypeSelect.addEventListener('change', updateFilters);
        ipAddressSelect.addEventListener('change', updateFilters);
    </script>
</body>

</html>