<?php
session_start();


$menuPath = "../";
$currentPage = "withdrawals";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Function to send WhatsApp message
function sendWhatsAppMessage($phoneNumber, $message)
{
    global $conn;
    try {
        // Get WhatsApp API configuration
        $stmt = $conn->prepare("SELECT APIEndpoint, InstanceID, AccessToken, Status FROM WhatsAppAPIConfig WHERE Status = 'Active' ORDER BY ConfigID DESC LIMIT 1");
        $stmt->execute();
        $whatsappConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$whatsappConfig || $whatsappConfig['Status'] !== 'Active') {
            error_log("WhatsApp API is not configured or inactive");
            return false;
        }

        // Format phone number
        if (substr($phoneNumber, 0, 2) !== '91') {
            $phoneNumber = '91' . $phoneNumber;
        }

        // Prepare API URL
        $apiUrl = $whatsappConfig['APIEndpoint'] . 'send?number=' . $phoneNumber . '&type=text&message=' . urlencode($message) . '&instance_id=' . $whatsappConfig['InstanceID'] . '&access_token=' . $whatsappConfig['AccessToken'];

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("WhatsApp API Response: " . $response);

        if ($httpCode == 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error sending WhatsApp message: " . $e->getMessage());
        return false;
    }
}

// Handle withdrawal action
if (isset($_POST['action']) && isset($_POST['withdrawal_id'])) {
    $withdrawalId = $_POST['withdrawal_id'];
    $action = $_POST['action'];
    $adminRemark = trim($_POST['admin_remark'] ?? '');

    try {
        $conn->beginTransaction();

        // Get withdrawal details
        $stmt = $conn->prepare("
            SELECT w.*, 
                   CASE 
                       WHEN w.UserType = 'Promoter' THEN p.Contact 
                       ELSE c.Contact 
                   END as UserContact,
                   CASE 
                       WHEN w.UserType = 'Promoter' THEN p.Name 
                       ELSE c.Name 
                   END as UserName,
                   CASE 
                       WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID 
                       ELSE c.CustomerUniqueID 
                   END as UserUniqueID
            FROM Withdrawals w
            LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
            LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'
            WHERE w.WithdrawalID = ?
        ");
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$withdrawal) {
            throw new Exception("Withdrawal request not found");
        }

        $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

        // Update withdrawal status
        $stmt = $conn->prepare("
            UPDATE Withdrawals 
            SET Status = ?, AdminID = ?, ProcessedAt = CURRENT_TIMESTAMP, Remarks = ?
            WHERE WithdrawalID = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $adminRemark, $withdrawalId]);

        // Handle wallet operations based on action
        if ($withdrawal['UserType'] === 'Promoter') {
            if ($action === 'approve') {
                // Add debit log for approved withdrawal
                $stmt = $conn->prepare("
                    INSERT INTO WalletLogs (PromoterUniqueID, Amount, Message, TransactionType)
                    VALUES (?, ?, 'Withdrawal approved', 'Debit')
                ");
                $stmt->execute([$withdrawal['UserUniqueID'], $withdrawal['Amount']]);
            } else {
                // For rejection, credit back to wallet
                $stmt = $conn->prepare("
                    UPDATE PromoterWallet 
                    SET BalanceAmount = BalanceAmount + ?, 
                        Message = 'Refund for rejected withdrawal request',
                        LastUpdated = CURRENT_TIMESTAMP
                    WHERE PromoterUniqueID = ?
                ");
                $stmt->execute([$withdrawal['Amount'], $withdrawal['UserUniqueID']]);

                // Log the wallet transaction
                $stmt = $conn->prepare("
                    INSERT INTO WalletLogs (PromoterUniqueID, Amount, Message, TransactionType)
                    VALUES (?, ?, 'Refund for rejected withdrawal request', 'Credit')
                ");
                $stmt->execute([$withdrawal['UserUniqueID'], $withdrawal['Amount']]);
            }
        }

        // Create notification
        $notificationMessage = "Your withdrawal request of ₹" . number_format($withdrawal['Amount'], 2) .
            " has been " . strtolower($newStatus);
        if (!empty($adminRemark)) {
            $notificationMessage .= ". Remarks: " . $adminRemark;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$withdrawal['UserID'], $withdrawal['UserType'], $notificationMessage]);

        // Send WhatsApp notification
        $whatsappMessage = "Dear " . $withdrawal['UserName'] . ", your withdrawal request of ₹" .
            number_format($withdrawal['Amount'], 2) . " has been " . strtolower($newStatus);
        if (!empty($adminRemark)) {
            $whatsappMessage .= ". Remarks: " . $adminRemark;
        }
        sendWhatsAppMessage($withdrawal['UserContact'], $whatsappMessage);

        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "$newStatus withdrawal request #$withdrawalId for " . $withdrawal['UserName'] .
                (!empty($adminRemark) ? " with remarks: " . $adminRemark : ""),
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Withdrawal request has been $newStatus successfully.";
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Failed to process withdrawal: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to process withdrawal: " . $e->getMessage();
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
$userType = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(p.Name LIKE :search OR p.PromoterUniqueID LIKE :search OR c.Name LIKE :search OR c.CustomerUniqueID LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "w.Status = :status";
    $params[':status'] = $status;
}

if (!empty($userType)) {
    $conditions[] = "w.UserType = :userType";
    $params[':userType'] = $userType;
}

if (!empty($startDate)) {
    $conditions[] = "DATE(w.RequestedAt) >= :startDate";
    $params[':startDate'] = $startDate;
}

if (!empty($endDate)) {
    $conditions[] = "DATE(w.RequestedAt) <= :endDate";
    $params[':endDate'] = $endDate;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total withdrawals count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM Withdrawals w
    LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
    LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'" .
    $whereClause;

$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get withdrawals with related data
$query = "
    SELECT w.*, 
           CASE 
               WHEN w.UserType = 'Promoter' THEN p.Name 
               ELSE c.Name 
           END as UserName,
           CASE 
               WHEN w.UserType = 'Promoter' THEN p.PromoterUniqueID 
               ELSE c.CustomerUniqueID 
           END as UserUniqueID,
           CASE 
               WHEN w.UserType = 'Promoter' THEN p.Contact 
               ELSE c.Contact 
           END as UserContact,
           a.Name as AdminName
    FROM Withdrawals w
    LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
    LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'
    LEFT JOIN Admins a ON w.AdminID = a.AdminID" .
    $whereClause .
    " ORDER BY w.RequestedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind the search/filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind the pagination parameters
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .withdrawal-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .withdrawal-card[data-status="Pending"] {
            border-left-color: #f39c12;
        }

        .withdrawal-card[data-status="Approved"] {
            border-left-color: #2ecc71;
        }

        .withdrawal-card[data-status="Rejected"] {
            border-left-color: #e74c3c;
        }

        .withdrawal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .user-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .withdrawal-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 8px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .approve-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .approve-btn:hover {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transform: translateY(-2px);
        }

        .reject-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .reject-btn:hover {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            transform: translateY(-2px);
        }

        .withdrawal-details {
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

        .status-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-pending {
            background-color: rgba(243, 156, 18, 0.15);
            color: #f39c12;
        }

        .status-approved {
            background-color: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }

        .remarks-form {
            margin-top: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #3498db;
            display: none;
        }

        .remarks-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #dfe6e9;
            border-radius: 6px;
            margin-bottom: 10px;
            resize: vertical;
            min-height: 80px;
        }

        .remarks-submit {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .search-box {
            flex: 1 1 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            padding-left: 40px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23a4b0be" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
            background-repeat: no-repeat;
            background-position: 12px center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 200px;
        }

        .filter-label {
            font-size: 14px;
            color: #576574;
            white-space: nowrap;
        }

        .filter-select,
        .filter-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
        }

        .confirmation-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .confirmation-dialog {
            background: white;
            border-radius: 10px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
            text-align: center;
        }

        .confirmation-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .icon-approve {
            color: #2ecc71;
        }

        .icon-reject {
            color: #e74c3c;
        }

        .confirmation-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .confirmation-message {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .confirmation-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .confirm-btn {
            background: #3498db;
            color: white;
        }

        .cancel-btn {
            background: #f1f2f6;
            color: #576574;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner {
            border: 3px solid rgba(52, 152, 219, 0.2);
            border-radius: 50%;
            border-top: 3px solid #3498db;
            width: 28px;
            height: 28px;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Withdrawal Management</h1>
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
                            <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">User Type:</label>
                        <select class="filter-select" name="user_type">
                            <option value="">All Users</option>
                            <option value="Promoter" <?php echo $userType === 'Promoter' ? 'selected' : ''; ?>>Promoters</option>
                            <option value="Customer" <?php echo $userType === 'Customer' ? 'selected' : ''; ?>>Customers</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <input type="date" class="filter-input" name="start_date" value="<?php echo $startDate; ?>">
                        <span>to</span>
                        <input type="date" class="filter-input" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                </div>

                <?php if (count($withdrawals) > 0): ?>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <div class="withdrawal-card" data-status="<?php echo $withdrawal['Status']; ?>">
                            <div class="withdrawal-header">
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($withdrawal['UserName']); ?></div>
                                    <div class="user-id"><?php echo $withdrawal['UserUniqueID']; ?></div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($withdrawal['UserContact']); ?>
                                    </div>
                                </div>

                                <div class="withdrawal-actions">
                                    <?php if ($withdrawal['Status'] === 'Pending'): ?>
                                        <button class="action-btn approve-btn" onclick="showActionForm(this, 'approve', <?php echo $withdrawal['WithdrawalID']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="action-btn reject-btn" onclick="showActionForm(this, 'reject', <?php echo $withdrawal['WithdrawalID']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="withdrawal-details">
                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">₹<?php echo number_format($withdrawal['Amount'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Requested At</span>
                                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($withdrawal['RequestedAt'])); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="status-badge status-<?php echo strtolower($withdrawal['Status']); ?>">
                                        <?php echo $withdrawal['Status']; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">User Type</span>
                                    <span class="detail-value"><?php echo $withdrawal['UserType']; ?></span>
                                </div>
                            </div>

                            <?php if ($withdrawal['Status'] !== 'Pending' && $withdrawal['AdminName']): ?>
                                <div class="verifier-info">
                                    <?php echo $withdrawal['Status']; ?> by <?php echo htmlspecialchars($withdrawal['AdminName']); ?>
                                    on <?php echo date('M d, Y H:i', strtotime($withdrawal['ProcessedAt'])); ?>
                                    <?php if (!empty($withdrawal['Remarks'])): ?>
                                        <div class="verifier-remark">
                                            <strong>Remarks:</strong> <?php echo htmlspecialchars($withdrawal['Remarks']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST" class="remarks-form">
                                <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['WithdrawalID']; ?>">
                                <input type="hidden" name="action" value="">
                                <div class="form-group">
                                    <label for="admin_remark">Remarks</label>
                                    <textarea name="admin_remark" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                                </div>
                                <div class="remarks-submit">
                                    <button type="button" class="cancel-btn" onclick="hideActionForm(this)">Cancel</button>
                                    <button type="button" class="confirm-btn action-confirm-btn" data-action="">Confirm</button>
                                </div>
                                <div class="loading">
                                    <div class="loading-spinner"></div>
                                    <div>Processing request...</div>
                                </div>
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
                        <i class="fas fa-money-bill-wave"></i>
                        <p>No withdrawal requests found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($userType) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="index.php" class="btn-clear-filter">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Confirmation Overlay -->
    <div class="confirmation-overlay" id="confirmationOverlay">
        <div class="confirmation-dialog">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle icon-approve" id="confirmationIcon"></i>
            </div>
            <div class="confirmation-title" id="confirmationTitle">Approve Withdrawal</div>
            <div class="confirmation-message" id="confirmationMessage">Are you sure you want to approve this withdrawal request?</div>
            <div class="confirmation-buttons">
                <button class="confirmation-btn cancel-btn" onclick="hideConfirmation()">Cancel</button>
                <button class="confirmation-btn confirm-btn" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Handle search and filters
        const searchInput = document.querySelector('.search-input');
        const statusSelect = document.querySelector('select[name="status_filter"]');
        const userTypeSelect = document.querySelector('select[name="user_type"]');
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');

        let searchTimeout;

        function updateFilters() {
            const search = searchInput.value.trim();
            const status = statusSelect.value;
            const userType = userTypeSelect.value;
            const start = startDate.value;
            const end = endDate.value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);
            if (userType) params.append('user_type', userType);
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 500);
        });

        statusSelect.addEventListener('change', updateFilters);
        userTypeSelect.addEventListener('change', updateFilters);
        startDate.addEventListener('change', updateFilters);
        endDate.addEventListener('change', updateFilters);

        // Handle withdrawal actions
        function showActionForm(button, action, withdrawalId) {
            const card = button.closest('.withdrawal-card');
            const form = card.querySelector('.remarks-form');
            const actionField = form.querySelector('input[name="action"]');
            const confirmBtn = form.querySelector('.action-confirm-btn');

            // Reset any other open forms
            document.querySelectorAll('.remarks-form').forEach(f => {
                if (f !== form) f.style.display = 'none';
            });

            // Setup current form
            actionField.value = action;
            confirmBtn.dataset.action = action;
            form.style.display = 'block';
            form.querySelector('.remarks-input').focus();

            // Scroll to make form visible if needed
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideActionForm(button) {
            const form = button.closest('.remarks-form');
            form.style.display = 'none';
        }

        // Add event listeners to confirmation buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.action-confirm-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const form = this.closest('form');
                    if (!form) return;

                    const action = form.querySelector('input[name="action"]').value;
                    const withdrawalId = form.querySelector('input[name="withdrawal_id"]').value;
                    const adminRemark = form.querySelector('textarea[name="admin_remark"]')?.value || '';

                    showConfirmation(action, withdrawalId, adminRemark, form);
                });
            });
        });

        function showConfirmation(action, withdrawalId, remarks, form) {
            const overlay = document.getElementById('confirmationOverlay');
            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const icon = document.getElementById('confirmationIcon');
            const confirmBtn = document.getElementById('confirmButton');

            // Set content based on action
            if (action === 'approve') {
                title.textContent = 'Approve Withdrawal';
                message.textContent = 'Are you sure you want to approve this withdrawal request?';
                icon.className = 'fas fa-check-circle icon-approve';
            } else {
                title.textContent = 'Reject Withdrawal';
                message.textContent = 'Are you sure you want to reject this withdrawal request?';
                icon.className = 'fas fa-times-circle icon-reject';
            }

            // Setup confirm button
            confirmBtn.onclick = function() {
                hideConfirmation();

                // Show loading state
                const loading = form.querySelector('.loading');
                const remarksSubmit = form.querySelector('.remarks-submit');
                if (loading && remarksSubmit) {
                    remarksSubmit.style.display = 'none';
                    loading.style.display = 'block';
                }

                // Submit the form
                setTimeout(() => {
                    form.submit();
                }, 500);
            };

            // Show overlay
            overlay.style.display = 'flex';
        }

        function hideConfirmation() {
            document.getElementById('confirmationOverlay').style.display = 'none';
        }

        // Close confirmation on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideConfirmation();
            }
        });
    </script>
</body>

</html>