<?php
session_start();


$menuPath = "../";
$currentPage = "schemes";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Delete Scheme
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $schemeId = $_GET['delete'];

    try {
        $conn->beginTransaction();

        // Check if scheme has any active subscriptions
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Subscriptions WHERE SchemeID = ? AND RenewalStatus = 'Active'");
        $stmt->execute([$schemeId]);
        $activeSubscriptions = $stmt->fetchColumn();

        if ($activeSubscriptions > 0) {
            $_SESSION['error_message'] = "Cannot delete scheme: There are active subscriptions.";
        } else {
            // Log the activity
            $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Deleted scheme ID: $schemeId",
                $_SERVER['REMOTE_ADDR']
            ]);

            // Delete the scheme
            $stmt = $conn->prepare("DELETE FROM Schemes WHERE SchemeID = ?");
            $stmt->execute([$schemeId]);

            $conn->commit();
            $_SESSION['success_message'] = "Scheme deleted successfully.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete scheme: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Scheme Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id'])) {
    $schemeId = $_GET['id'];
    $newStatus = $_GET['status'];

    if (!in_array($newStatus, ['Active', 'Inactive'])) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: index.php");
        exit();
    }

    try {
        $conn->beginTransaction();

        // Log the activity
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Changed scheme status to $newStatus for scheme ID: $schemeId",
            $_SERVER['REMOTE_ADDR']
        ]);

        // Update scheme status
        $stmt = $conn->prepare("UPDATE Schemes SET Status = ? WHERE SchemeID = ?");
        $stmt->execute([$newStatus, $schemeId]);

        $conn->commit();
        $_SESSION['success_message'] = "Scheme status updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update scheme status: " . $e->getMessage();
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

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(SchemeName LIKE :search OR Description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "Status = :status";
    $params[':status'] = $status;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total number of schemes
$countQuery = "SELECT COUNT(*) as total FROM Schemes" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get schemes with pagination
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM Subscriptions WHERE SchemeID = s.SchemeID AND RenewalStatus = 'Active') as active_subscriptions,
          (SELECT COUNT(*) FROM Installments WHERE SchemeID = s.SchemeID) as total_installments
          FROM Schemes s" . $whereClause . " ORDER BY s.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind all parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .scheme-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .scheme-card:hover {
            transform: translateY(-2px);
        }

        .scheme-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .scheme-name {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
        }

        .scheme-actions {
            display: flex;
            gap: 8px;
        }

        .scheme-action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .view-btn {
            background: #3498db;
        }

        .view-btn:hover {
            background: #2980b9;
        }

        .edit-btn {
            background: #2ecc71;
        }

        .edit-btn:hover {
            background: #27ae60;
        }

        .delete-btn {
            background: #e74c3c;
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .scheme-details {
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

        .scheme-description {
            font-size: 14px;
            color: #34495e;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .scheme-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .stat-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .subscription-count {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .installment-count {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .search-filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .add-scheme-btn {
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

        .add-scheme-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .no-schemes {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .no-schemes i {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Scheme Management</h1>
            <br>

            <a href="add.php" class="add-scheme-btn">
                <i class="fas fa-plus"></i> Add New Scheme
            </a>
 <br>
            <br>
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
                <div class="search-filter-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search schemes..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Status:</label>
                        <select class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <?php if (count($schemes) > 0): ?>
                    <?php foreach ($schemes as $scheme): ?>
                        <div class="scheme-card">
                            <div class="scheme-header">
                                <span class="scheme-name"><?php echo htmlspecialchars($scheme['SchemeName']); ?></span>
                                <div class="scheme-actions">
                                    <a href="view.php?id=<?php echo $scheme['SchemeID']; ?>" class="scheme-action-btn view-btn">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="edit.php?id=<?php echo $scheme['SchemeID']; ?>" class="scheme-action-btn edit-btn">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($scheme['active_subscriptions'] == 0): ?>
                                        <a href="index.php?delete=<?php echo $scheme['SchemeID']; ?>"
                                            class="scheme-action-btn delete-btn"
                                            onclick="return confirm('Are you sure you want to delete this scheme?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="scheme-details">
                                <div class="detail-item">
                                    <span class="detail-label">Monthly Payment</span>
                                    <span class="detail-value">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Total Payments</span>
                                    <span class="detail-value"><?php echo $scheme['TotalPayments']; ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Created Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($scheme['CreatedAt'])); ?></span>
                                </div>
                            </div>

                            <div class="scheme-description">
                                <?php echo nl2br(htmlspecialchars($scheme['Description'])); ?>
                            </div>

                            <div class="scheme-stats">
                                <span class="stat-badge status-<?php echo strtolower($scheme['Status']); ?>">
                                    <?php echo $scheme['Status']; ?>
                                </span>

                                <span class="stat-badge subscription-count">
                                    <?php echo $scheme['active_subscriptions']; ?> Active Subscriptions
                                </span>

                                <span class="stat-badge installment-count">
                                    <?php echo $scheme['total_installments']; ?> Installments
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                        echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                        echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : ''; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-schemes">
                        <i class="fas fa-folder-open"></i>
                        <p>No schemes found</p>
                        <?php if (!empty($search) || !empty($status)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Handle search input
        const searchInput = document.querySelector('.search-input');
        const statusSelect = document.querySelector('.filter-select');
        let searchTimeout;

        function updateSearch() {
            const search = searchInput.value.trim();
            const status = statusSelect.value;
            let url = 'index.php';

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);

            const queryString = params.toString();
            if (queryString) url += '?' + queryString;

            window.location.href = url;
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateSearch, 500);
        });

        statusSelect.addEventListener('change', updateSearch);

        // Confirm delete
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this scheme? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>

</html>