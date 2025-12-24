<?php
session_start();
// require_once("../middleware/auth.php");
// verifyAuth();

$menuPath = "../";
$currentPage = "wallets";
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

        // Check if WhatsApp is active
        if (!$whatsappConfig || $whatsappConfig['Status'] !== 'Active') {
            error_log("WhatsApp API is not configured or inactive");
            return false;
        }

        // Format phone number (add country code if not present)
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

        // Log the response
        error_log("WhatsApp API Response: " . $response);

        // Check if request was successful
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

// Handle Wallet Update
if (isset($_POST['action']) && isset($_POST['promoter_id'])) {
    $promoterId = $_POST['promoter_id'];
    $action = $_POST['action'];
    $amount = floatval($_POST['amount']);
    $message = trim($_POST['message'] ?? '');

    try {
        $conn->beginTransaction();

        // Get promoter details
        $stmt = $conn->prepare("SELECT p.*, pw.BalanceAmount FROM Promoters p LEFT JOIN PromoterWallet pw ON p.PromoterUniqueID = pw.PromoterUniqueID WHERE p.PromoterID = ?");
        $stmt->execute([$promoterId]);
        $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$promoter) {
            throw new Exception("Promoter not found");
        }

        // Calculate new balance
        $currentBalance = floatval($promoter['BalanceAmount'] ?? 0);
        $newBalance = ($action === 'add') ? $currentBalance + $amount : $currentBalance - $amount;

        if ($newBalance < 0) {
            throw new Exception("Insufficient balance for deduction");
        }

        // Update or insert wallet balance
        if (isset($promoter['BalanceAmount'])) {
            $stmt = $conn->prepare("UPDATE PromoterWallet SET BalanceAmount = ?, Message = ?, LastUpdated = CURRENT_TIMESTAMP WHERE PromoterUniqueID = ?");
            $stmt->execute([$newBalance, $message, $promoter['PromoterUniqueID']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO PromoterWallet (PromoterUniqueID, BalanceAmount, Message) VALUES (?, ?, ?)");
            $stmt->execute([$promoter['PromoterUniqueID'], $newBalance, $message]);
        }

        // Log the transaction
        $stmt = $conn->prepare("INSERT INTO WalletLogs (PromoterUniqueID, Amount, Message, TransactionType) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $promoter['PromoterUniqueID'],
            $amount,
            $message,
            $action === 'add' ? 'Credit' : 'Debit'
        ]);

        // Send WhatsApp notification
        $whatsappMessage = "Dear " . $promoter['Name'] . ", your wallet has been " .
            ($action === 'add' ? 'credited' : 'debited') . " with ₹" . number_format($amount, 2) .
            ". New balance: ₹" . number_format($newBalance, 2);
        if (!empty($message)) {
            $whatsappMessage .= ". Remarks: " . $message;
        }
        sendWhatsAppMessage($promoter['Contact'], $whatsappMessage);

        // Log the activity
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            ($action === 'add' ? 'Added' : 'Deducted') . " ₹" . number_format($amount, 2) . " to/from promoter " . $promoter['Name'] . "'s wallet",
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Wallet updated successfully.";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update wallet: " . $e->getMessage();
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
    $conditions[] = "(p.Name LIKE :search OR p.PromoterUniqueID LIKE :search OR p.Contact LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "p.Status = :status";
    $params[':status'] = $status;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total promoters count
$countQuery = "SELECT COUNT(*) as total FROM Promoters p" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get promoters with wallet information
$query = "SELECT p.*, pw.BalanceAmount, pw.LastUpdated as WalletLastUpdated 
          FROM Promoters p 
          LEFT JOIN PromoterWallet pw ON p.PromoterUniqueID = pw.PromoterUniqueID" .
    $whereClause .
    " ORDER BY p.Name ASC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind the search/filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind the pagination parameters
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .wallet-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .wallet-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .wallet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .promoter-info {
            flex: 1;
        }

        .promoter-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .promoter-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .wallet-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .add-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .deduct-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .wallet-details {
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

        .balance-amount {
            font-size: 1.2em;
            font-weight: 600;
            color: #2ecc71;
        }

        .last-updated {
            font-size: 12px;
            color: #7f8c8d;
            font-style: italic;
        }

        .wallet-form {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #3498db;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dfe6e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .cancel-btn {
            padding: 8px 15px;
            border-radius: 6px;
            background: #f1f2f6;
            color: #576574;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .submit-btn {
            padding: 8px 15px;
            border-radius: 6px;
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #2980b9;
        }

        .cancel-btn:hover {
            background: #dfe4ea;
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
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
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

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .wallet-logs-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .wallet-logs-modal .modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }

        .wallet-logs-modal .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wallet-logs-modal .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: #2c3e50;
        }

        .wallet-logs-modal .close-modal {
            font-size: 24px;
            color: #666;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .wallet-logs-modal .close-modal:hover {
            color: #e74c3c;
        }

        .wallet-logs-modal .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .promoter-info-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .promoter-info-header h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .logs-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .log-entry {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            transition: transform 0.2s ease;
        }

        .log-entry:hover {
            transform: translateX(5px);
        }

        .log-entry.credit {
            border-left-color: #2ecc71;
        }

        .log-entry.debit {
            border-left-color: #e74c3c;
        }

        .log-amount {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .log-amount.credit {
            color: #2ecc71;
        }

        .log-amount.debit {
            color: #e74c3c;
        }

        .log-message {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .log-date {
            color: #999;
            font-size: 0.8em;
        }

        .view-logs-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .view-logs-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .transaction-type {
            font-size: 0.9em;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 8px;
        }

        .log-amount.credit .transaction-type {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .log-amount.debit .transaction-type {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Wallet Management</h1>
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
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search by promoter name, ID or contact..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <?php if (count($promoters) > 0): ?>
                    <?php foreach ($promoters as $promoter): ?>
                        <div class="wallet-card" data-promoter-id="<?php echo $promoter['PromoterUniqueID']; ?>">
                            <div class="wallet-header">
                                <div class="promoter-info">
                                    <h3><?php echo htmlspecialchars($promoter['Name']); ?></h3>
                                    <span class="promoter-id"><?php echo $promoter['PromoterUniqueID']; ?></span>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($promoter['Contact']); ?>
                                    </div>
                                    <div class="status-badge status-<?php echo strtolower($promoter['Status']); ?>">
                                        <?php echo $promoter['Status']; ?>
                                    </div>
                                </div>

                                <div class="wallet-actions">
                                    <button class="action-btn view-logs-btn" onclick="viewWalletLogs('<?php echo $promoter['PromoterUniqueID']; ?>', '<?php echo htmlspecialchars($promoter['Name']); ?>')">
                                        <i class="fas fa-history"></i> View Logs
                                    </button>
                                    <button class="action-btn add-btn" onclick="showWalletForm(this, 'add')">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                    <button class="action-btn deduct-btn" onclick="showWalletForm(this, 'deduct')">
                                        <i class="fas fa-minus"></i> Deduct
                                    </button>
                                </div>
                            </div>

                            <div class="wallet-details">
                                <div class="detail-item">
                                    <span class="detail-label">Current Balance</span>
                                    <span class="balance-amount">₹<?php echo number_format($promoter['BalanceAmount'] ?? 0, 2); ?></span>
                                </div>
                                <?php if (isset($promoter['WalletLastUpdated'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Last Updated</span>
                                        <span class="last-updated"><?php echo date('M d, Y H:i', strtotime($promoter['WalletLastUpdated'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="" method="POST" class="wallet-form">
                                <input type="hidden" name="promoter_id" value="<?php echo $promoter['PromoterID']; ?>">
                                <input type="hidden" name="action" value="">
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label for="message">Message</label>
                                    <textarea name="message" class="form-control" rows="3" placeholder="Enter a message for the transaction"></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="cancel-btn" onclick="hideWalletForm(this)">Cancel</button>
                                    <button type="submit" class="submit-btn">Confirm</button>
                                </div>
                                <div class="loading">
                                    <div class="loading-spinner"></div>
                                    <div>Processing transaction...</div>
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
                        <i class="fas fa-wallet"></i>
                        <p>No promoters found</p>
                        <?php if (!empty($search)): ?>
                            <a href="index.php" class="btn-clear-filter">Clear Search</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="wallet-logs-modal" id="walletLogsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Wallet Transaction History</h2>
                <span class="close-modal" onclick="closeWalletLogsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="promoter-info-header">
                    <h3 id="modalPromoterName"></h3>
                    <span id="modalPromoterId" class="promoter-id"></span>
                </div>
                <div class="logs-container" id="walletLogsContainer">
                    <!-- Logs will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle search
        const searchInput = document.querySelector('.search-input');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const search = this.value.trim();
                window.location.href = 'index.php' + (search ? '?search=' + encodeURIComponent(search) : '');
            }, 500);
        });

        // Handle wallet form
        function showWalletForm(button, action, promoterId) {
            const card = button.closest('.wallet-card');
            const form = card.querySelector('.wallet-form');
            const actionField = form.querySelector('input[name="action"]');

            // Reset any other open forms
            document.querySelectorAll('.wallet-form').forEach(f => {
                if (f !== form) f.style.display = 'none';
            });

            // Setup current form
            actionField.value = action;
            form.style.display = 'block';
            form.querySelector('input[name="amount"]').focus();

            // Scroll to make form visible if needed
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideWalletForm(button) {
            const form = button.closest('.wallet-form');
            form.style.display = 'none';
        }

        // Handle form submission
        document.querySelectorAll('.wallet-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const loading = this.querySelector('.loading');
                const formActions = this.querySelector('.form-actions');

                if (loading && formActions) {
                    formActions.style.display = 'none';
                    loading.style.display = 'block';
                }

                setTimeout(() => {
                    this.submit();
                }, 500);
            });
        });

        function viewWalletLogs(promoterId, promoterName) {
            const modal = document.getElementById('walletLogsModal');
            const container = document.getElementById('walletLogsContainer');
            const nameElement = document.getElementById('modalPromoterName');
            const idElement = document.getElementById('modalPromoterId');

            // Set promoter info
            nameElement.textContent = promoterName;
            idElement.textContent = promoterId;

            // Show loading state
            container.innerHTML = '<div class="loading">Loading transaction history...</div>';
            modal.style.display = 'block';

            // Fetch wallet logs
            fetch(`get_wallet_logs.php?promoter_id=${promoterId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<div class="no-logs">No transaction history found</div>';
                        return;
                    }

                    container.innerHTML = data.map(log => {
                        const amount = parseFloat(log.Amount);
                        const date = new Date(log.created_at);
                        const formattedDate = date.toLocaleString('en-IN', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });

                        const isCredit = log.TransactionType === 'Credit';

                        return `
                            <div class="log-entry ${isCredit ? 'credit' : 'debit'}">
                                <div class="log-amount ${isCredit ? 'credit' : 'debit'}">
                                    <span class="transaction-type">${isCredit ? 'Credit' : 'Debit'}</span>
                                    ${isCredit ? '+' : '-'}₹${Math.abs(amount).toFixed(2)}
                                </div>
                                <div class="log-message">${log.Message}</div>
                                <div class="log-date">${formattedDate}</div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    container.innerHTML = '<div class="error">Failed to load transaction history</div>';
                    console.error('Error:', error);
                });
        }

        function closeWalletLogsModal() {
            document.getElementById('walletLogsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('walletLogsModal');
            if (event.target === modal) {
                closeWalletLogsModal();
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeWalletLogsModal();
            }
        });
    </script>
</body>

</html>