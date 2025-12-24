<?php
session_start();


$menuPath = "../";
$currentPage = "notifications";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Handle notification deletion
if (isset($_POST['delete_notification']) && isset($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM Notifications WHERE NotificationID = ?");
        $stmt->execute([$notificationId]);
        
        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Deleted notification #$notificationId",
            $_SERVER['REMOTE_ADDR']
        ]);
    
        
    $_SESSION['success_message'] = "Notification deleted successfully.";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to delete notification: " . $e->getMessage();
}

header("Location: index.php");
exit();
}

// Send new notification
if (isset($_POST['send_notification'])) {
$userType = $_POST['user_type'];
$userId = $_POST['user_id'] ?? null;
$message = trim($_POST['message']);

try {
    $conn->beginTransaction();
    
    // If sending to all users of a type
    if ($userId === "all") {
        if ($userType === "Customer") {
            $stmt = $conn->query("SELECT CustomerID FROM Customers WHERE Status = 'Active'");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                $stmt = $conn->prepare("
                    INSERT INTO Notifications (UserID, UserType, Message) 
                    VALUES (?, 'Customer', ?)
                ");
                $stmt->execute([$user['CustomerID'], $message]);
            }
        } else if ($userType === "Promoter") {
            $stmt = $conn->query("SELECT PromoterID FROM Promoters WHERE Status = 'Active'");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                $stmt = $conn->prepare("
                    INSERT INTO Notifications (UserID, UserType, Message) 
                    VALUES (?, 'Promoter', ?)
                ");
                $stmt->execute([$user['PromoterID'], $message]);
            }
        } else if ($userType === "Admin") {
            $stmt = $conn->query("SELECT AdminID FROM Admins WHERE Status = 'Active'");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                $stmt = $conn->prepare("
                    INSERT INTO Notifications (UserID, UserType, Message) 
                    VALUES (?, 'Admin', ?)
                ");
                $stmt->execute([$user['AdminID'], $message]);
            }
        }
        
        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Sent notification to all $userType users",
            $_SERVER['REMOTE_ADDR']
        ]);
    } else {
        // Send to specific user
        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $userType, $message]);
        
        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Sent notification to $userType #$userId",
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    $conn->commit();
    $_SESSION['success_message'] = "Notification sent successfully.";
} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Failed to send notification: " . $e->getMessage();
}

header("Location: index.php");
exit();
}

// Mark notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
$notificationId = $_POST['notification_id'];

try {
    $stmt = $conn->prepare("UPDATE Notifications SET IsRead = 1 WHERE NotificationID = ?");
    $stmt->execute([$notificationId]);
    
    $_SESSION['success_message'] = "Notification marked as read.";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to update notification: " . $e->getMessage();
}

header("Location: index.php");
exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userTypeFilter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$readStatus = isset($_GET['read_status']) ? $_GET['read_status'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
$conditions[] = "Message LIKE ?";
$params[] = "%$search%";
}

if (!empty($userTypeFilter)) {
$conditions[] = "UserType = ?";
$params[] = $userTypeFilter;
}

if ($readStatus !== '') {
$conditions[] = "IsRead = ?";
$params[] = ($readStatus === 'read') ? 1 : 0;
}

if (!empty($dateRange)) {
$dates = explode(' - ', $dateRange);
if (count($dates) == 2) {
    $startDate = date('Y-m-d', strtotime($dates[0]));
    $endDate = date('Y-m-d', strtotime($dates[1]. ' +1 day'));
    $conditions[] = "CreatedAt BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total notifications count
$countQuery = "SELECT COUNT(*) as total FROM Notifications" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get notifications with pagination
$query = "
SELECT n.*, 
       CASE 
           WHEN n.UserType = 'Customer' THEN c.Name 
           WHEN n.UserType = 'Promoter' THEN p.Name 
           WHEN n.UserType = 'Admin' THEN a.Name 
       END as UserName,
       CASE 
           WHEN n.UserType = 'Customer' THEN c.CustomerUniqueID
           WHEN n.UserType = 'Promoter' THEN p.PromoterUniqueID
           WHEN n.UserType = 'Admin' THEN NULL
       END as UserUniqueID
FROM Notifications n
LEFT JOIN Customers c ON n.UserType = 'Customer' AND n.UserID = c.CustomerID
LEFT JOIN Promoters p ON n.UserType = 'Promoter' AND n.UserID = p.PromoterID
LEFT JOIN Admins a ON n.UserType = 'Admin' AND n.UserID = a.AdminID"
. $whereClause . 
" ORDER BY n.CreatedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

foreach ($params as $key => $value) {
$stmt->bindValue($key + 1, $value);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active users for send notification form
$stmt = $conn->query("SELECT CustomerID, Name, CustomerUniqueID FROM Customers WHERE Status = 'Active' ORDER BY Name");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT PromoterID, Name, PromoterUniqueID FROM Promoters WHERE Status = 'Active' ORDER BY Name");
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT AdminID, Name, Email FROM Admins WHERE Status = 'Active' ORDER BY Name");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .notification-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border-left: 4px solid #3498db;
        position: relative;
        overflow: hidden;
    }
    
    .notification-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    
    .notification-card.unread {
        border-left-color: #e74c3c;
        background-color: rgba(231, 76, 60, 0.03);
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .notification-info {
        flex: 1;
        min-width: 250px;
    }
    
    .message-content {
        font-size: 1.1em;
        color: #2c3e50;
        margin-bottom: 15px;
        line-height: 1.5;
    }
    
    .user-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 15px;
        border-radius: 25px;
        font-size: 0.85em;
        font-weight: 500;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-right: 10px;
    }
    
    .customer-badge {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
    }
    
    .promoter-badge {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: white;
    }
    
    .admin-badge {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        color: white;
    }
    
    .read-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85em;
        color: #7f8c8d;
    }
    
    .read-status.unread {
        color: #e74c3c;
        font-weight: 500;
    }
    
    .notification-actions {
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
    }
    
    .mark-read-btn {
        background: linear-gradient(135deg, #3498db, #2980b9);
        box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
    }
    
    .delete-btn {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        box-shadow: 0 2px 4px rgba(231, 76, 60, 0.2);
    }
    
    .send-btn {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        box-shadow: 0 2px 4px rgba(46, 204, 113, 0.2);
    }
    
    .notification-meta {
        display: flex;
        justify-content: space-between;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        font-size: 0.9em;
        color: #7f8c8d;
    }
    
    .timestamp {
        display: flex;
        align-items: center;
        gap: 6px;
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
        max-width: 600px;
        position: relative;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #34495e;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
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
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .add-notification-btn {
        background: linear-gradient(135deg, #3a7bd5, #00d2ff);
        color: white;
        padding: 12px 24px;
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
    
    .add-notification-btn:hover {
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
</style>
</head>
<body>
<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">Notifications Management</h1>
        <button class="add-notification-btn" onclick="showSendNotificationModal()">
            <i class="fas fa-bell"></i> Send New Notification
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
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search notifications..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">User Type:</label>
                    <select class="filter-select" name="user_type">
                        <option value="">All Types</option>
                        <option value="Customer" <?php echo $userTypeFilter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="Promoter" <?php echo $userTypeFilter === 'Promoter' ? 'selected' : ''; ?>>Promoter</option>
                        <option value="Admin" <?php echo $userTypeFilter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Read Status:</label>
                    <select class="filter-select" name="read_status">
                        <option value="">All Status</option>
                        <option value="read" <?php echo $readStatus === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="unread" <?php echo $readStatus === 'unread' ? 'selected' : ''; ?>>Unread</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date Range:</label>
                    <input type="text" class="filter-select datepicker" name="date_range" placeholder="Select date range" value="<?php echo htmlspecialchars($dateRange); ?>">
                </div>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['IsRead'] ? '' : 'unread'; ?>">
                        <div class="notification-header">
                            <div class="notification-info">
                                <span class="user-type-badge <?php echo strtolower($notification['UserType']); ?>-badge">
                                    <i class="fas fa-<?php echo $notification['UserType'] === 'Customer' ? 'user' : ($notification['UserType'] === 'Promoter' ? 'user-tie' : 'user-shield'); ?>"></i>
                                    <?php echo $notification['UserType']; ?>
                                    <?php if (!empty($notification['UserName'])): ?>
                                        - <?php echo htmlspecialchars($notification['UserName']); ?>
                                    <?php endif; ?>
                                </span>
                                
                                <span class="read-status <?php echo $notification['IsRead'] ? '' : 'unread'; ?>">
                                    <i class="fas fa-<?php echo $notification['IsRead'] ? 'check-circle' : 'bell'; ?>"></i>
                                    <?php echo $notification['IsRead'] ? 'Read' : 'Unread'; ?>
                                </span>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notification['IsRead']): ?>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['NotificationID']; ?>">
                                        <button type="submit" name="mark_read" class="action-btn mark-read-btn">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form action="" method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['NotificationID']; ?>">
                                    <button type="submit" name="delete_notification" class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo htmlspecialchars($notification['Message']); ?>
                        </div>
                        
                        <div class="notification-meta">
                            <div class="timestamp">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d, Y h:i A', strtotime($notification['CreatedAt'])); ?>
                            </div>
                            
                            <?php if (!empty($notification['UserUniqueID'])): ?>
                                <div class="user-id">
                                    ID: <?php echo htmlspecialchars($notification['UserUniqueID']); ?>
                                </div>
                            <?php endif; ?>
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
                <div class="no-records">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications found</p>
                    <?php if(!empty($search) || !empty($userTypeFilter) || !empty($readStatus) || !empty($dateRange)): ?>
                        <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal" id="sendNotificationModal">
    <div class="modal-content">
        <span class="close-modal" onclick="hideSendNotificationModal()">&times;</span>
        <h2>Send New Notification</h2>
        <form action="" method="POST">
            <div class="form-group">
                <label>User Type</label>
                <select name="user_type" class="form-control" onchange="toggleUserSelect(this.value)" required>
                    <option value="">Select User Type</option>
                    <option value="Customer">Customer</option>
                    <option value="Promoter">Promoter</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            
            <div class="form-group" id="customerSelect" style="display: none;">
                <label>Select Customer</label>
                <select name="customer_id" class="form-control">
                    <option value="">Select Customer</option>
                    <option value="all">All Customers</option>
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
                <select name="promoter_id" class="form-control">
                    <option value="">Select Promoter</option>
                    <option value="all">All Promoters</option>
                    <?php foreach ($promoters as $promoter): ?>
                        <option value="<?php echo $promoter['PromoterID']; ?>">
                            <?php echo htmlspecialchars($promoter['Name']); ?> 
                            (<?php echo $promoter['PromoterUniqueID']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" id="adminSelect" style="display: none;">
                <label>Select Admin</label>
                <select name="admin_id" class="form-control">
                    <option value="">Select Admin</option>
                    <option value="all">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['AdminID']; ?>">
                            <?php echo htmlspecialchars($admin['Name']); ?> 
                            (<?php echo $admin['Email']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" class="form-control" required placeholder="Enter notification message"></textarea>
            </div>
            
            <input type="hidden" name="user_id">
            <button type="submit" name="send_notification" class="action-btn send-btn">
                <i class="fas fa-paper-plane"></i> Send Notification
            </button>
        </form>
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
    const readStatusSelect = document.querySelector('select[name="read_status"]');
    const dateRangeInput = document.querySelector('input[name="date_range"]');
    
    let searchTimeout;
    
    function updateFilters() {
        const search = searchInput.value.trim();
        const userType = userTypeSelect.value;
        const readStatus = readStatusSelect.value;
        const dateRange = dateRangeInput.value;
        
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (userType) params.append('user_type', userType);
        if (readStatus) params.append('read_status', readStatus);
        if (dateRange) params.append('date_range', dateRange);
        
        window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
    }
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateFilters, 500);
    });
    
    userTypeSelect.addEventListener('change', updateFilters);
    readStatusSelect.addEventListener('change', updateFilters);
    
    // Handle deletion confirmation
    function confirmDelete() {
        return confirm("Are you sure you want to delete this notification?");
    }
    
    // Send Notification Modal
    function showSendNotificationModal() {
        document.getElementById('sendNotificationModal').style.display = 'flex';
    }
    
    function hideSendNotificationModal() {
        document.getElementById('sendNotificationModal').style.display = 'none';
    }
    
    // Toggle user select based on user type
    function toggleUserSelect(userType) {
        const customerSelect = document.getElementById('customerSelect');
        const promoterSelect = document.getElementById('promoterSelect');
        const adminSelect = document.getElementById('adminSelect');
        
        // Hide all selects first
        customerSelect.style.display = 'none';
        promoterSelect.style.display = 'none';
        adminSelect.style.display = 'none';
        
        // Reset required attributes
        document.querySelector('select[name="customer_id"]').required = false;
        document.querySelector('select[name="promoter_id"]').required = false;
        document.querySelector('select[name="admin_id"]').required = false;
        
        // Show appropriate select based on user type
        if (userType === 'Customer') {
            customerSelect.style.display = 'block';
            document.querySelector('select[name="customer_id"]').required = true;
        } else if (userType === 'Promoter') {
            promoterSelect.style.display = 'block';
            document.querySelector('select[name="promoter_id"]').required = true;
        } else if (userType === 'Admin') {
            adminSelect.style.display = 'block';
            document.querySelector('select[name="admin_id"]').required = true;
        }
    }
    
    // Set user ID based on selection
    document.querySelector('select[name="customer_id"]').addEventListener('change', function() {
        document.querySelector('input[name="user_id"]').value = this.value;
    });
    
    document.querySelector('select[name="promoter_id"]').addEventListener('change', function() {
        document.querySelector('input[name="user_id"]').value = this.value;
    });
    
    document.querySelector('select[name="admin_id"]').addEventListener('change', function() {
        document.querySelector('input[name="user_id"]').value = this.value;
    });
    
    // Close modal when clicking outside
    document.getElementById('sendNotificationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideSendNotificationModal();
        }
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
</script>
</body>
</html>