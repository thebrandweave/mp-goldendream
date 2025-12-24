<?php
session_start();


$menuPath = "../";
$currentPage = "subscriptions";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Subscription Status Change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $subscriptionId = $_GET['id'];
    $action = $_GET['action'];

    try {
        $conn->beginTransaction();

        $newStatus = '';
        $actionMessage = '';

        switch ($action) {
            case 'cancel':
                $newStatus = 'Cancelled';
                $actionMessage = "Cancelled subscription ID: $subscriptionId";
                break;
            case 'activate':
                $newStatus = 'Active';
                $actionMessage = "Activated subscription ID: $subscriptionId";
                break;
            case 'expire':
                $newStatus = 'Expired';
                $actionMessage = "Marked subscription ID: $subscriptionId as expired";
                break;
            default:
                throw new Exception("Invalid action");
        }

        // Log the activity
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            $actionMessage,
            $_SERVER['REMOTE_ADDR']
        ]);

        // Update subscription status
        $stmt = $conn->prepare("UPDATE Subscriptions SET RenewalStatus = ?, UpdatedAt = CURRENT_TIMESTAMP WHERE SubscriptionID = ?");
        $stmt->execute([$newStatus, $subscriptionId]);

        $conn->commit();
        $_SESSION['success_message'] = "Subscription status updated successfully.";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update subscription status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$schemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE ? OR c.CustomerUniqueID LIKE ? OR c.Contact LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "s.RenewalStatus = ?";
    $params[] = $status;
}

if (!empty($schemeId)) {
    $conditions[] = "s.SchemeID = ?";
    $params[] = $schemeId;
}

if (!empty($startDate)) {
    $conditions[] = "s.StartDate >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $conditions[] = "s.EndDate <= ?";
    $params[] = $endDate;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total subscriptions count
$countQuery = "SELECT COUNT(*) as total FROM Subscriptions s 
               LEFT JOIN Customers c ON s.CustomerID = c.CustomerID" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get all active schemes for filter
$stmt = $conn->query("SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName");
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subscriptions with related data
$query = "SELECT s.*, c.Name as CustomerName, c.CustomerUniqueID, c.Contact, 
          sch.SchemeName, sch.MonthlyPayment,
          (SELECT COUNT(*) FROM Payments p 
           WHERE p.CustomerID = s.CustomerID 
           AND p.SchemeID = s.SchemeID 
           AND p.Status = 'Verified') as paid_installments
          FROM Subscriptions s
          LEFT JOIN Customers c ON s.CustomerID = c.CustomerID
          LEFT JOIN Schemes sch ON s.SchemeID = sch.SchemeID"
    . $whereClause .
    " ORDER BY s.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .subscription-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .subscription-card:hover {
            transform: translateY(-2px);
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .customer-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .subscription-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .view-btn {
            background: #3498db;
        }

        .view-btn:hover {
            background: #2980b9;
        }

        .cancel-btn {
            background: #e74c3c;
        }

        .cancel-btn:hover {
            background: #c0392b;
        }

        .activate-btn {
            background: #2ecc71;
        }

        .activate-btn:hover {
            background: #27ae60;
        }

        .expire-btn {
            background: #f39c12;
        }

        .expire-btn:hover {
            background: #d35400;
        }

        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .subscription-progress {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
        }

        .progress-bar {
            height: 6px;
            background: #ecf0f1;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #3498db, #2ecc71);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-expired {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-cancelled {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            color: #34495e;
            font-weight: 500;
        }

        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .add-subscription-btn {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-subscription-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .contact-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-top: 5px;
        }

        .no-subscriptions {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .no-subscriptions i {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Subscription Management</h1>
            <a href="add.php" class="add-subscription-btn">
                <i class="fas fa-plus"></i> Add New Subscription
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-body">
                <div class="filter-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search by customer name, ID or contact..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status:</label>
                        <select class="filter-select" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Expired" <?php echo $status === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Scheme:</label>
                        <select class="filter-select" name="scheme_id">
                            <option value="">All Schemes</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?php echo $scheme['SchemeID']; ?>"
                                    <?php echo $schemeId == $scheme['SchemeID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <input type="date" class="filter-input" name="start_date" value="<?php echo $startDate; ?>">
                        <span>to</span>
                        <input type="date" class="filter-input" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                </div>

                <?php if (count($subscriptions) > 0): ?>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <div class="subscription-card">
                            <div class="subscription-header">
                                <div class="customer-info">
                                    <div class="customer-name"><?php echo htmlspecialchars($subscription['CustomerName']); ?></div>
                                    <div class="customer-id"><?php echo $subscription['CustomerUniqueID']; ?></div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($subscription['Contact']); ?>
                                    </div>
                                </div>

                                <div class="subscription-actions">
                                    <a href="view.php?id=<?php echo $subscription['SubscriptionID']; ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>

                                    <?php if ($subscription['RenewalStatus'] === 'Active'): ?>
                                        <button onclick="updateStatus(<?php echo $subscription['SubscriptionID']; ?>, 'cancel')"
                                            class="action-btn cancel-btn">
                                            <i class="fas fa-ban"></i> Cancel
                                        </button>
                                        <button onclick="updateStatus(<?php echo $subscription['SubscriptionID']; ?>, 'expire')"
                                            class="action-btn expire-btn">
                                            <i class="fas fa-clock"></i> Mark Expired
                                        </button>
                                    <?php elseif ($subscription['RenewalStatus'] === 'Cancelled'): ?>
                                        <button onclick="updateStatus(<?php echo $subscription['SubscriptionID']; ?>, 'activate')"
                                            class="action-btn activate-btn">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="subscription-details">
                                <div class="detail-item">
                                    <span class="detail-label">Scheme</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($subscription['SchemeName']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Monthly Payment</span>
                                    <span class="detail-value">₹<?php echo number_format($subscription['MonthlyPayment'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Start Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">End Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($subscription['EndDate'])); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="status-badge status-<?php echo strtolower($subscription['RenewalStatus']); ?>">
                                        <?php echo $subscription['RenewalStatus']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="subscription-progress">
                                <div class="detail-label">Payment Progress</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($subscription['paid_installments'] / 12) * 100; ?>%"></div>
                                </div>
                                <div class="detail-value">
                                    <?php echo $subscription['paid_installments']; ?> of 12 installments paid
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-subscriptions">
                        <i class="fas fa-file-invoice"></i>
                        <p>No subscriptions found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($schemeId) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Handle search and filters
        const searchInput = document.querySelector('.search-input');
        const statusSelect = document.querySelector('select[name="status_filter"]');
        const schemeSelect = document.querySelector('select[name="scheme_id"]');
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');

        let searchTimeout;

        function updateFilters() {
            const search = searchInput.value.trim();
            const status = statusSelect.value;
            const schemeId = schemeSelect.value;
            const start = startDate.value;
            const end = endDate.value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);
            if (schemeId) params.append('scheme_id', schemeId);
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 500);
        });

        statusSelect.addEventListener('change', updateFilters);
        schemeSelect.addEventListener('change', updateFilters);
        startDate.addEventListener('change', updateFilters);
        endDate.addEventListener('change', updateFilters);

        // Handle status updates
        function updateStatus(subscriptionId, action) {
            if (confirm('Are you sure you want to ' + action + ' this subscription?')) {
                window.location.href = `index.php?action=${action}&id=${subscriptionId}`;
            }
        }
    </script>
</body>

</html>