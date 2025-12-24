<?php
session_start();
//Check if user is logged in, redirect if not

// Check if the logged-in admin has permission to manage admins
// Superadmin role check
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'SuperAdmin') {
    // Redirect to dashboard with error message
    $_SESSION['error_message'] = "You don't have permission to access the admin management page.";
    header("Location: ../dashboard/index.php");
    exit();
}

$menuPath = "../";
$currentPage = "admins";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Delete Admin
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $adminId = $_GET['delete'];

    // Prevent self-deletion
    if ($adminId == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Deleted admin account";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now delete the admin
        $stmt = $conn->prepare("DELETE FROM Admins WHERE AdminID = ?");
        $stmt->execute([$adminId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Admin deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete admin: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Admin Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $adminId = $_GET['id'];
    $newStatus = $_GET['status'] === 'activate' ? 'Active' : 'Inactive';

    // Prevent changing own status
    if ($adminId == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "You cannot change your own account status.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Changed admin status to " . $newStatus;
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now update the admin status
        $stmt = $conn->prepare("UPDATE Admins SET Status = ? WHERE AdminID = ?");
        $stmt->execute([$newStatus, $adminId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Admin status updated successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update admin status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = " WHERE (Name LIKE ? OR Email LIKE ? OR Role LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// Get total number of admins (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM Admins" . $searchCondition;
$stmt = $conn->prepare($countQuery);

if (!empty($searchParams)) {
    $stmt->execute($searchParams);
} else {
    $stmt->execute();
}

$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get admins with pagination
$query = "SELECT AdminID, Name, Email, Role, Status, CreatedAt FROM Admins" . $searchCondition .
    " ORDER BY CreatedAt DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);

if (!empty($searchParams)) {
    foreach ($searchParams as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Admin Management Styles */
        :root {
            --ad_primary-color: #3a7bd5;
            --ad_primary-hover: #2c60a9;
            --ad_secondary-color: #00d2ff;
            --ad_success-color: #2ecc71;
            --ad_success-hover: #27ae60;
            --warning-color: #f39c12;
            --warning-hover: #d35400;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
            --text-dark: #2c3e50;
            --text-medium: #34495e;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
        }

        .card-header h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .card-body {
            padding: 0;
        }

        /* Admin Table Styles */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .admin-table th,
        .admin-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .admin-table th {
            background-color: var(--bg-light);
            color: var(--text-medium);
            font-weight: 600;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .admin-table th:hover {
            background-color: #edf2f7;
        }

        .admin-table th::after {
            content: '↕';
            position: absolute;
            right: 15px;
            color: #cbd5e0;
            font-size: 14px;
        }

        .admin-table th.asc::after {
            content: '↑';
            color: var(--ad_primary-color);
        }

        .admin-table th.desc::after {
            content: '↓';
            color: var(--ad_primary-color);
        }

        .admin-table tbody tr {
            transition: background-color 0.2s;
        }

        .admin-table tbody tr:hover {
            background-color: rgba(58, 123, 213, 0.03);
        }

        .admin-table tbody tr:last-child td {
            border-bottom: none;
        }

        .current-user-row {
            background-color: rgba(58, 123, 213, 0.05);
        }

        .current-user-row:hover {
            background-color: rgba(58, 123, 213, 0.08) !important;
        }

        /* Column widths for better alignment */
        .admin-table th:nth-child(1),
        .admin-table td:nth-child(1) {
            width: 18%;
        }

        .admin-table th:nth-child(2),
        .admin-table td:nth-child(2) {
            width: 25%;
        }

        .admin-table th:nth-child(3),
        .admin-table td:nth-child(3) {
            width: 14%;
        }

        .admin-table th:nth-child(4),
        .admin-table td:nth-child(4) {
            width: 12%;
        }

        .admin-table th:nth-child(5),
        .admin-table td:nth-child(5) {
            width: 16%;
        }

        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6) {
            width: 15%;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--ad_success-color);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        /* Admin Actions Styles */
        .admin-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .admin-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            color: white;
            transition: all 0.2s ease;
        }

        .admin-actions .edit-btn {
            background: var(--ad_primary-color);
        }

        .admin-actions .edit-btn:hover {
            background: var(--ad_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(44, 96, 169, 0.2);
        }

        .admin-actions .delete-btn {
            background: var(--danger-color);
        }

        .admin-actions .delete-btn:hover {
            background: var(--danger-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(192, 57, 43, 0.2);
        }

        .admin-actions .activate-btn {
            background: var(--ad_success-color);
        }

        .admin-actions .activate-btn:hover {
            background: var(--ad_success-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(39, 174, 96, 0.2);
        }

        .admin-actions .deactivate-btn {
            background: var(--warning-color);
        }

        .admin-actions .deactivate-btn:hover {
            background: var(--warning-hover);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(211, 84, 0, 0.2);
        }

        /* Search Box Styles */
        .admin-search-box {
            margin-bottom: 0;
            display: flex;
            gap: 10px;
        }

        .admin-search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .admin-search-box input:focus {
            border-color: var(--ad_primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .admin-search-box button {
            background: var(--ad_primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .admin-search-box button:hover {
            background: var(--ad_primary-hover);
        }

        /* Add Admin Button Styles */
        .add-admin-btn {
            background: linear-gradient(135deg, var(--ad_primary-color), var(--ad_secondary-color));
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .add-admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 25px 0;
            justify-content: center;
            gap: 6px;
        }

        .pagination li {
            margin: 0;
        }

        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-medium);
            background: white;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .pagination a:hover {
            background: var(--bg-light);
            border-color: var(--ad_primary-color);
            color: var(--ad_primary-color);
        }

        .pagination .active a {
            background: var(--ad_primary-color);
            color: white;
            border-color: var(--ad_primary-color);
            box-shadow: 0 2px 5px rgba(58, 123, 213, 0.3);
        }

        /* No Data State */
        .no-data {
            padding: 50px 20px;
            text-align: center;
            color: var(--text-light);
        }

        .no-data p {
            margin-bottom: 20px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--ad_primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--ad_primary-hover);
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: opacity 0.5s ease;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--ad_success-color);
            color: #2d6a4f;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--danger-color);
            color: #ae1e2f;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {

            .admin-table th:nth-child(5),
            .admin-table td:nth-child(5) {
                display: none;
            }

            .admin-table th:nth-child(1),
            .admin-table td:nth-child(1) {
                width: 22%;
            }

            .admin-table th:nth-child(2),
            .admin-table td:nth-child(2) {
                width: 30%;
            }

            .admin-table th:nth-child(3),
            .admin-table td:nth-child(3) {
                width: 18%;
            }

            .admin-table th:nth-child(4),
            .admin-table td:nth-child(4) {
                width: 15%;
            }

            .admin-table th:nth-child(6),
            .admin-table td:nth-child(6) {
                width: 15%;
            }
        }

        @media (max-width: 768px) {
            .admin-table {
                display: block;
                overflow-x: auto;
            }

            .admin-table th,
            .admin-table td {
                padding: 12px 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .add-admin-btn {
                align-self: flex-start;
            }
        }

        @media (max-width: 576px) {
            .admin-actions a {
                width: 30px;
                height: 30px;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Admin Management</h1>
            <a href="./add/" class="add-admin-btn">
                <i class="fas fa-plus"></i> Add New Admin
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
            <div class="card-header">
                <h2>Manage Administrators</h2>
                <form action="" method="GET" class="admin-search-box">
                    <input type="text" name="search" placeholder="Search by name, email or role..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body">
                <?php if (count($admins) > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr <?php if ($admin['AdminID'] == $_SESSION['admin_id']) echo 'class="current-user-row"'; ?>>
                                    <td><?php echo htmlspecialchars($admin['Name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['Role']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($admin['Status']); ?>">
                                            <?php echo $admin['Status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($admin['CreatedAt'])); ?></td>
                                    <td>
                                        <div class="admin-actions">
                                            <a href="edit.php?id=<?php echo $admin['AdminID']; ?>" class="edit-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($admin['Status'] == 'Active'): ?>
                                                <a href="index.php?status=deactivate&id=<?php echo $admin['AdminID']; ?>"
                                                    class="deactivate-btn"
                                                    title="Deactivate"
                                                    onclick="return confirm('Are you sure you want to deactivate this admin?');">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="index.php?status=activate&id=<?php echo $admin['AdminID']; ?>"
                                                    class="activate-btn"
                                                    title="Activate"
                                                    onclick="return confirm('Are you sure you want to activate this admin?');">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($admin['AdminID'] != $_SESSION['admin_id']): ?>
                                                <a href="index.php?delete=<?php echo $admin['AdminID']; ?>"
                                                    class="delete-btn"
                                                    title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this admin? This action cannot be undone.');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?page=1<?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-double-left"></i></a></li>
                                <li><a href="?page=<?php echo $page - 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-left"></i></a></li>
                            <?php endif; ?>

                            <?php
                            // Show limited page numbers with current page in the middle
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Always show first page button
                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li><span>...</span></li>';
                                }
                            }

                            // Display page links
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="' . ($page == $i ? 'active' : '') . '"><a href="?page=' . $i .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a></li>';
                            }

                            // Always show last page button
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li><span>...</span></li>';
                                }
                                echo '<li><a href="?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li><a href="?page=<?php echo $page + 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-right"></i></a></li>
                                <li><a href="?page=<?php echo $totalPages; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?>"><i class="fas fa-angle-double-right"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-data">
                        <?php if (!empty($search)): ?>
                            <p>No admins found matching your search criteria.</p>
                            <a href="index.php" class="btn btn-primary">Clear Search</a>
                        <?php else: ?>
                            <p>No admins found in the system.</p>
                            <a href="add/" class="btn btn-primary">Add Your First Admin</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Table sorting functionality
            const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;

            const comparer = (idx, asc) => (a, b) => ((v1, v2) =>
                v1 !== '' && v2 !== '' && !isNaN(v1) && !isNaN(v2) ? v1 - v2 : v1.toString().localeCompare(v2)
            )(getCellValue(asc ? a : b, idx), getCellValue(asc ? b : a, idx));

            document.querySelectorAll('th').forEach(th => th.addEventListener('click', (() => {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                Array.from(tbody.querySelectorAll('tr'))
                    .sort(comparer(Array.from(th.parentNode.children).indexOf(th), this.asc = !this.asc))
                    .forEach(tr => tbody.appendChild(tr));

                // Update header classes for sort direction indication
                table.querySelectorAll('th').forEach(header => {
                    header.classList.remove('asc', 'desc');
                });

                th.classList.toggle('asc', this.asc);
                th.classList.toggle('desc', !this.asc);
            })));

            // Flash messages fade out
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>

</html>