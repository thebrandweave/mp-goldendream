<?php
session_start();


$menuPath = "../";
$currentPage = "winners";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle Winner Status Update
if (isset($_POST['action']) && isset($_POST['winner_id'])) {
    $winnerId = $_POST['winner_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $conn->beginTransaction();

        $newStatus = ($action === 'claim') ? 'Claimed' : 'Expired';

        // Get winner details first
        $stmt = $conn->prepare("
            SELECT w.*, 
                   CASE 
                       WHEN w.UserType = 'Customer' THEN c.Name 
                       WHEN w.UserType = 'Promoter' THEN p.Name 
                   END as UserName,
                   CASE 
                       WHEN w.UserType = 'Customer' THEN c.Email 
                       WHEN w.UserType = 'Promoter' THEN p.Email 
                   END as UserEmail
            FROM Winners w
            LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
            LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID
            WHERE w.WinnerID = ?
        ");
        $stmt->execute([$winnerId]);
        $winner = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update winner status
        $stmt = $conn->prepare("
            UPDATE Winners 
            SET Status = ?, 
                AdminID = ?,
                Remarks = ?
            WHERE WinnerID = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $remarks, $winnerId]);

        // Create notification for user
        $notificationMessage = "Congratulations! Your " . $winner['PrizeType'] .
            " has been marked as " . strtolower($newStatus);
        if (!empty($remarks)) {
            $notificationMessage .= ". Remarks: " . $remarks;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$winner['UserID'], $winner['UserType'], $notificationMessage]);

        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Marked " . $winner['PrizeType'] . " as $newStatus for " . $winner['UserName'],
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Winner status updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update winner status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Add New Winner
if (isset($_POST['add_winner'])) {
    $userId = $_POST['user_id'];
    $userType = $_POST['user_type'];
    $prizeType = $_POST['prize_type'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $conn->beginTransaction();

        // Add winner
        $stmt = $conn->prepare("
            INSERT INTO Winners (UserID, UserType, PrizeType, Status, AdminID, Remarks)
            VALUES (?, ?, ?, 'Pending', ?, ?)
        ");
        $stmt->execute([$userId, $userType, $prizeType, $_SESSION['admin_id'], $remarks]);

        // Create notification
        $notificationMessage = "Congratulations! You have won a $prizeType!";
        if (!empty($remarks)) {
            $notificationMessage .= " Remarks: " . $remarks;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $userType, $notificationMessage]);

        // Log activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Added new winner for $prizeType",
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "New winner added successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to add winner: " . $e->getMessage();
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
$prizeType = isset($_GET['prize_type']) ? $_GET['prize_type'] : '';
$userType = isset($_GET['user_type']) ? $_GET['user_type'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Name 
            WHEN w.UserType = 'Promoter' THEN p.Name 
        END LIKE ? OR
        CASE 
            WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
            WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID
        END LIKE ?
    )";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "w.Status = ?";
    $params[] = $status;
}

if (!empty($prizeType)) {
    $conditions[] = "w.PrizeType = ?";
    $params[] = $prizeType;
}

if (!empty($userType)) {
    $conditions[] = "w.UserType = ?";
    $params[] = $userType;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total winners count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM Winners w
    LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
    LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID"
    . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get winners with related data
$query = "
    SELECT w.*,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.Name 
               WHEN w.UserType = 'Promoter' THEN p.Name 
           END as UserName,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
               WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID
           END as UserUniqueID,
           CASE 
               WHEN w.UserType = 'Customer' THEN c.Contact
               WHEN w.UserType = 'Promoter' THEN p.Contact
           END as UserContact,
           a.Name as AdminName
    FROM Winners w
    LEFT JOIN Customers c ON w.UserType = 'Customer' AND w.UserID = c.CustomerID
    LEFT JOIN Promoters p ON w.UserType = 'Promoter' AND w.UserID = p.PromoterID
    LEFT JOIN Admins a ON w.AdminID = a.AdminID"
    . $whereClause .
    " ORDER BY w.WinningDate DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active customers and promoters for add winner form
$stmt = $conn->query("SELECT CustomerID, Name, CustomerUniqueID FROM Customers WHERE Status = 'Active' ORDER BY Name");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active' ORDER BY Name");
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winners Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .winner-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #f1c40f;
            position: relative;
            overflow: hidden;
        }

        .winner-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .winner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .user-info {
            flex: 1;
            min-width: 250px;
        }

        .user-name {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .prize-type-badge {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: white;
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 0.8em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(243, 156, 18, 0.2);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }

        .winner-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .claim-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 2px 4px rgba(46, 204, 113, 0.2);
        }

        .expire-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 2px 4px rgba(231, 76, 60, 0.2);
        }

        .winner-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 0.85em;
            color: #7f8c8d;
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.95em;
            color: #2c3e50;
            font-weight: 500;
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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #2c3e50;
            cursor: pointer;
        }

        .remarks-input {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            display: none;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
        }

        .remarks-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
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

        /* Add missing styles for the Add New Winner button */
        .add-winner-btn {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .add-winner-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        /* Styles for the modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #e74c3c;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        /* Error message styles */
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            color: #c0392b;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Status badges */
        .status-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-claimed {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-expired {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Winners Management</h1>
            <button class="add-winner-btn" onclick="showAddWinnerModal()">
                <i class="fas fa-trophy"></i> Add New Winner
            </button>
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
                        <input type="text" class="search-input" placeholder="Search by name or ID..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status:</label>
                        <select class="filter-select" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Claimed" <?php echo $status === 'Claimed' ? 'selected' : ''; ?>>Claimed</option>
                            <option value="Expired" <?php echo $status === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Prize Type:</label>
                        <select class="filter-select" name="prize_type">
                            <option value="">All Types</option>
                            <option value="Surprise Prize" <?php echo $prizeType === 'Surprise Prize' ? 'selected' : ''; ?>>Surprise Prize</option>
                            <option value="Bumper Prize" <?php echo $prizeType === 'Bumper Prize' ? 'selected' : ''; ?>>Bumper Prize</option>
                            <option value="Gift Hamper" <?php echo $prizeType === 'Gift Hamper' ? 'selected' : ''; ?>>Gift Hamper</option>
                            <option value="Education Scholarship" <?php echo $prizeType === 'Education Scholarship' ? 'selected' : ''; ?>>Education Scholarship</option>
                            <option value="Other" <?php echo $prizeType === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">User Type:</label>
                        <select class="filter-select" name="user_type">
                            <option value="">All Users</option>
                            <option value="Customer" <?php echo $userType === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="Promoter" <?php echo $userType === 'Promoter' ? 'selected' : ''; ?>>Promoter</option>
                        </select>
                    </div>
                </div>

                <?php if (count($winners) > 0): ?>
                    <?php foreach ($winners as $winner): ?>
                        <div class="winner-card">
                            <div class="winner-header">
                                <div class="user-info">
                                    <div class="user-name">
                                        <?php echo htmlspecialchars($winner['UserName']); ?>
                                        <span class="prize-type-badge">
                                            <i class="fas fa-gift"></i> <?php echo $winner['PrizeType']; ?>
                                        </span>
                                    </div>
                                    <div class="user-id">
                                        <?php echo $winner['UserUniqueID']; ?>
                                        <span class="status-badge status-<?php echo strtolower($winner['Status']); ?>">
                                            <?php echo $winner['Status']; ?>
                                        </span>
                                    </div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($winner['UserContact']); ?>
                                    </div>
                                </div>

                                <div class="winner-actions">
                                    <?php if ($winner['Status'] === 'Pending'): ?>
                                        <button class="action-btn claim-btn" onclick="showRemarks(this, 'claim', <?php echo $winner['WinnerID']; ?>)">
                                            <i class="fas fa-check"></i> Mark Claimed
                                        </button>
                                        <button class="action-btn expire-btn" onclick="showRemarks(this, 'expire', <?php echo $winner['WinnerID']; ?>)">
                                            <i class="fas fa-times"></i> Mark Expired
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="winner-details">
                                <div class="detail-item">
                                    <span class="detail-label">Winning Date</span>
                                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($winner['WinningDate'])); ?></span>
                                </div>

                                <?php if (!empty($winner['Remarks'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Remarks</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($winner['Remarks']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($winner['AdminName']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Processed By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($winner['AdminName']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="" method="POST" class="remarks-form" style="display: none;">
                                <input type="hidden" name="winner_id" value="<?php echo $winner['WinnerID']; ?>">
                                <input type="hidden" name="action" value="">
                                <textarea name="remarks" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                            </form>
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
                    <div class="no-records">
                        <i class="fas fa-trophy"></i>
                        <p>No winners found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($prizeType) || !empty($userType)): ?>
                            <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Winner Modal -->
    <div class="modal" id="addWinnerModal">
        <div class="modal-content">
            <span class="close-modal" onclick="hideAddWinnerModal()">&times;</span>
            <h2>Add New Winner</h2>
            <form action="" method="POST">
                <input type="hidden" name="add_winner" value="1">
                <input type="hidden" name="user_id" value="">

                <div class="form-group">
                    <label>User Type</label>
                    <select name="user_type" class="form-control" onchange="toggleUserSelect(this.value)" required>
                        <option value="">Select User Type</option>
                        <option value="Customer">Customer</option>
                        <option value="Promoter">Promoter</option>
                    </select>
                </div>

                <div class="form-group" id="customerSelect" style="display: none;">
                    <label>Select Customer</label>
                    <select name="customer_id" class="form-control select2" onchange="updateUserId(this.value, 'customer')">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['CustomerID']; ?>">
                                <?php echo htmlspecialchars($customer['Name']); ?>
                                (<?php echo $customer['CustomerUniqueID']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="promoterSelect" style="display: none;">
                    <label>Select Promoter</label>
                    <select name="promoter_id" class="form-control select2" onchange="updateUserId(this.value, 'promoter')">
                        <option value="">Select Promoter</option>
                        <?php foreach ($promoters as $promoter): ?>
                            <option value="<?php echo $promoter['PromoterID']; ?>">
                                <?php echo htmlspecialchars($promoter['Name']); ?>
                                (<?php echo $promoter['PromoterUniqueID']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prize Type</label>
                    <select name="prize_type" class="form-control" required>
                        <option value="">Select Prize Type</option>
                        <option value="Surprise Prize">Surprise Prize</option>
                        <option value="Bumper Prize">Bumper Prize</option>
                        <option value="Gift Hamper">Gift Hamper</option>
                        <option value="Education Scholarship">Education Scholarship</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_scheme_winner" id="isSchemeWinner" onchange="toggleSchemeFields()">
                        Scheme Winner
                    </label>
                </div>

                <div class="form-group" id="schemeFields" style="display: none;">
                    <label>Select Scheme</label>
                    <select name="scheme_id" class="form-control" onchange="loadInstallments(this.value)">
                        <option value="">Select Scheme</option>
                        <?php
                        $stmt = $conn->query("SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active'");
                        while ($scheme = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <option value="<?php echo $scheme['SchemeID']; ?>">
                                <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group" id="installmentFields" style="display: none;">
                    <label>Select Installment</label>
                    <select name="installment_id" class="form-control">
                        <option value="">Select Installment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Enter any additional remarks"></textarea>
                </div>

                <button type="submit" class="action-btn claim-btn">
                    <i class="fas fa-plus"></i> Add Winner
                </button>
            </form>
        </div>
    </div>

    <!-- Add Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for customer and promoter dropdowns
        $(document).ready(function() {
            $('select[name="customer_id"]').select2({
                placeholder: "Search and select customer...",
                allowClear: true,
                width: '100%'
            });

            $('select[name="promoter_id"]').select2({
                placeholder: "Search and select promoter...",
                allowClear: true,
                width: '100%'
            });

            // Handle search and filters
            const searchInput = document.querySelector('.search-input');
            const statusSelect = document.querySelector('select[name="status_filter"]');
            const prizeTypeSelect = document.querySelector('select[name="prize_type"]');
            const userTypeSelect = document.querySelector('select[name="user_type"]');

            let searchTimeout;

            function updateFilters() {
                const search = searchInput.value.trim();
                const status = statusSelect.value;
                const prizeType = prizeTypeSelect.value;
                const userType = userTypeSelect.value;

                const params = new URLSearchParams();
                if (search) params.append('search', search);
                if (status) params.append('status_filter', status);
                if (prizeType) params.append('prize_type', prizeType);
                if (userType) params.append('user_type', userType);

                window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
            }

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(updateFilters, 500);
            });

            statusSelect.addEventListener('change', updateFilters);
            prizeTypeSelect.addEventListener('change', updateFilters);
            userTypeSelect.addEventListener('change', updateFilters);
        });

        // Handle remarks form
        function showRemarks(button, action, winnerId) {
            const card = button.closest('.winner-card');
            const form = card.querySelector('.remarks-form');
            const remarksInput = form.querySelector('.remarks-input');

            // Hide any other visible remarks inputs
            document.querySelectorAll('.remarks-input').forEach(input => {
                if (input !== remarksInput) {
                    input.style.display = 'none';
                }
            });

            if (remarksInput.style.display === 'block') {
                remarksInput.style.display = 'none';
                return;
            }

            form.querySelector('input[name="action"]').value = action;
            remarksInput.style.display = 'block';
            remarksInput.focus();

            remarksInput.onkeypress = function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (confirm(`Are you sure you want to mark this winner as ${action}ed?`)) {
                        form.submit();
                    }
                }
            };
        }

        // Improved Add Winner Modal handling
        function showAddWinnerModal() {
            const modal = document.getElementById('addWinnerModal');
            const form = modal.querySelector('form');

            // Reset form fields
            form.reset();
            document.querySelector('input[name="user_id"]').value = '';
            document.getElementById('customerSelect').style.display = 'none';
            document.getElementById('promoterSelect').style.display = 'none';

            // Reset any error styling
            form.querySelectorAll('.form-control').forEach(el => {
                el.style.borderColor = '';
            });

            // Remove any error messages
            const existingError = form.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }

            // Display the modal with animation
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.querySelector('.modal-content').style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'translateY(0)';
            }, 10);
        }

        function hideAddWinnerModal() {
            const modal = document.getElementById('addWinnerModal');
            modal.style.display = 'none';
        }

        // Toggle user select based on user type
        function toggleUserSelect(userType) {
            const customerSelect = document.getElementById('customerSelect');
            const promoterSelect = document.getElementById('promoterSelect');
            const userIdField = document.querySelector('input[name="user_id"]');

            // Reset user ID when changing user type
            userIdField.value = '';

            if (userType === 'Customer') {
                customerSelect.style.display = 'block';
                promoterSelect.style.display = 'none';
                document.querySelector('select[name="customer_id"]').required = true;
                document.querySelector('select[name="promoter_id"]').required = false;
                document.querySelector('select[name="promoter_id"]').value = '';
            } else if (userType === 'Promoter') {
                customerSelect.style.display = 'none';
                promoterSelect.style.display = 'block';
                document.querySelector('select[name="customer_id"]').required = false;
                document.querySelector('select[name="promoter_id"]').required = true;
                document.querySelector('select[name="customer_id"]').value = '';
            } else {
                customerSelect.style.display = 'none';
                promoterSelect.style.display = 'none';
                document.querySelector('select[name="customer_id"]').required = false;
                document.querySelector('select[name="promoter_id"]').required = false;
                document.querySelector('select[name="customer_id"]').value = '';
                document.querySelector('select[name="promoter_id"]').value = '';
            }
        }

        // Update the user ID hidden field based on selection
        function updateUserId(value, type) {
            const userIdField = document.querySelector('input[name="user_id"]');
            userIdField.value = value;
        }

        // Toggle scheme fields based on checkbox
        function toggleSchemeFields() {
            const isSchemeWinner = document.getElementById('isSchemeWinner').checked;
            const schemeFields = document.getElementById('schemeFields');
            const installmentFields = document.getElementById('installmentFields');

            schemeFields.style.display = isSchemeWinner ? 'block' : 'none';
            installmentFields.style.display = isSchemeWinner ? 'block' : 'none';

            if (!isSchemeWinner) {
                document.querySelector('select[name="scheme_id"]').value = '';
                document.querySelector('select[name="installment_id"]').value = '';
            }
        }

        // Load installments for selected scheme
        function loadInstallments(schemeId) {
            const installmentSelect = document.querySelector('select[name="installment_id"]');
            installmentSelect.innerHTML = '<option value="">Select Installment</option>';

            if (!schemeId) {
                return;
            }

            // Make AJAX request to get installments
            fetch(`get_installments.php?scheme_id=${schemeId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(installment => {
                        const option = document.createElement('option');
                        option.value = installment.InstallmentID;
                        option.textContent = `${installment.InstallmentName} (${installment.DrawDate})`;
                        installmentSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading installments:', error));
        }

        // Make sure the form event listener is specific to the Add Winner form
        document.addEventListener('DOMContentLoaded', function() {
            const addWinnerForm = document.querySelector('#addWinnerModal form');

            addWinnerForm.addEventListener('submit', function(e) {
                // Reset any previous error styling
                this.querySelectorAll('.form-control').forEach(el => {
                    el.style.borderColor = '';
                });

                // Remove any previous error messages
                const existingError = this.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }

                const userType = this.querySelector('select[name="user_type"]').value;
                const userId = this.querySelector('input[name="user_id"]').value;
                const prizeType = this.querySelector('select[name="prize_type"]').value;

                let hasError = false;
                let errorMessage = '';

                if (!userType) {
                    this.querySelector('select[name="user_type"]').style.borderColor = '#e74c3c';
                    errorMessage = 'Please select a user type';
                    hasError = true;
                } else if (!userId) {
                    if (userType === 'Customer') {
                        this.querySelector('select[name="customer_id"]').style.borderColor = '#e74c3c';
                        errorMessage = 'Please select a customer';
                    } else {
                        this.querySelector('select[name="promoter_id"]').style.borderColor = '#e74c3c';
                        errorMessage = 'Please select a promoter';
                    }
                    hasError = true;
                } else if (!prizeType) {
                    this.querySelector('select[name="prize_type"]').style.borderColor = '#e74c3c';
                    errorMessage = 'Please select a prize type';
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();

                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMessage}`;

                    this.insertBefore(errorDiv, this.firstChild);
                    return false;
                }

                // Validate scheme winner fields if checked
                const isSchemeWinner = document.getElementById('isSchemeWinner').checked;
                if (isSchemeWinner) {
                    const schemeId = this.querySelector('select[name="scheme_id"]').value;
                    const installmentId = this.querySelector('select[name="installment_id"]').value;

                    if (!schemeId) {
                        this.querySelector('select[name="scheme_id"]').style.borderColor = '#e74c3c';
                        errorMessage = 'Please select a scheme';
                        hasError = true;
                    } else if (!installmentId) {
                        this.querySelector('select[name="installment_id"]').style.borderColor = '#e74c3c';
                        errorMessage = 'Please select an installment';
                        hasError = true;
                    }

                    if (hasError) {
                        e.preventDefault();
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'error-message';
                        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMessage}`;
                        this.insertBefore(errorDiv, this.firstChild);
                        return false;
                    }
                }

                // Show loading state on button
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;

                return true;
            });
        });
    </script>
</body>

</html>