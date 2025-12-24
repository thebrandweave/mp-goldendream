<?php
session_start();
$menuPath = "../";
$currentPage = "Pending";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get latest active scheme
$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM Installments WHERE SchemeID = s.SchemeID) as total_installments
    FROM Schemes s 
    WHERE s.Status = 'Active' 
    ORDER BY s.CreatedAt DESC 
    LIMIT 1
");
$stmt->execute();
$latestScheme = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all active schemes for filter
$stmt = $conn->prepare("SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName");
$stmt->execute();
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected scheme (default to latest)
$selectedSchemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : ($latestScheme['SchemeID'] ?? null);

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$installmentId = isset($_GET['installment_id']) ? $_GET['installment_id'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE :search OR c.CustomerUniqueID LIKE :search OR c.Contact LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($selectedSchemeId)) {
    $conditions[] = "s.SchemeID = :scheme_id";
    $params[':scheme_id'] = $selectedSchemeId;
}

if (!empty($installmentId)) {
    $conditions[] = "i.InstallmentID = :installment_id";
    $params[':installment_id'] = $installmentId;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total records count
$countQuery = "
    SELECT COUNT(DISTINCT c.CustomerID) as total
    FROM Customers c
    JOIN Schemes s ON 1=1
    JOIN Installments i ON i.SchemeID = s.SchemeID
    JOIN Subscriptions sub ON sub.CustomerID = c.CustomerID AND sub.SchemeID = s.SchemeID
    LEFT JOIN Payments p ON p.CustomerID = c.CustomerID AND p.InstallmentID = i.InstallmentID
    $whereClause
    AND (p.PaymentID IS NULL OR p.Status != 'Verified')
    AND c.Status = 'Active'
    AND sub.RenewalStatus = 'Active'";

$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get pending payments
$query = "
    SELECT 
        c.CustomerID,
        c.CustomerUniqueID,
        c.Name as CustomerName,
        c.Contact,
        s.SchemeName,
        i.InstallmentName,
        i.InstallmentNumber,
        i.Amount as InstallmentAmount,
        i.DrawDate,
        p.Status as PaymentStatus,
        p.SubmittedAt as PaymentSubmittedAt
    FROM Customers c
    JOIN Schemes s ON 1=1
    JOIN Installments i ON i.SchemeID = s.SchemeID
    JOIN Subscriptions sub ON sub.CustomerID = c.CustomerID AND sub.SchemeID = s.SchemeID
    LEFT JOIN Payments p ON p.CustomerID = c.CustomerID AND p.InstallmentID = i.InstallmentID
    $whereClause
    AND (p.PaymentID IS NULL OR p.Status != 'Verified')
    AND c.Status = 'Active'
    AND sub.RenewalStatus = 'Active'
    ORDER BY i.InstallmentNumber ASC, c.Name ASC
    LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get installments for the selected scheme
$installments = [];
if (!empty($selectedSchemeId)) {
    $stmt = $conn->prepare("
        SELECT InstallmentID, InstallmentName, InstallmentNumber, Amount, DrawDate 
        FROM Installments 
        WHERE SchemeID = ? 
        ORDER BY InstallmentNumber ASC
    ");
    $stmt->execute([$selectedSchemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments</title>
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

        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            color: #576574;
            white-space: nowrap;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            padding-left: 35px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a4b0be;
        }

        .pending-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .pending-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .customer-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .customer-details {
            flex: 1;
        }

        .customer-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .customer-id {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #3498db;
            font-size: 14px;
        }

        .promoter-info {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .installment-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .installment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .installment-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .installment-amount {
            font-weight: 600;
            color: #27ae60;
        }

        .installment-date {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-not-submitted {
            background: #f8d7da;
            color: #721c24;
        }

        .no-pending {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }

        .no-pending i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
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

        .whatsapp-action {
            margin-bottom: 20px;
            text-align: right;
        }

        .btn-success {
            background: #25D366;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: #128C7E;
            transform: translateY(-2px);
        }

        .btn-success:disabled {
            background: #a8a8a8;
            cursor: not-allowed;
            transform: none;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-excel {
            background: #217346;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-excel:hover {
            background: #1e5e3a;
            transform: translateY(-2px);
            color: white;
        }

        .filter-container {
            flex-direction: column;
        }

        .filter-container form {
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn-excel,
            .btn-success {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Pending Payments</h1>
        </div>

        <div class="filter-container">
            <form action="" method="GET" style="display: flex; gap: 15px; width: 100%;">
                <div class="filter-group">
                    <label class="filter-label">Scheme:</label>
                    <select name="scheme_id" class="filter-select">
                        <?php foreach ($schemes as $scheme): ?>
                            <option value="<?php echo $scheme['SchemeID']; ?>"
                                <?php echo $selectedSchemeId == $scheme['SchemeID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Installment:</label>
                    <select name="installment_id" class="filter-select">
                        <option value="">All Installments</option>
                        <?php foreach ($installments as $installment): ?>
                            <option value="<?php echo $installment['InstallmentID']; ?>"
                                <?php echo $installmentId == $installment['InstallmentID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($installment['InstallmentName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input"
                        placeholder="Search by customer name, ID or contact..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>

                <?php if (!empty($search) || !empty($installmentId)): ?>
                    <a href="?scheme_id=<?php echo $selectedSchemeId; ?>" class="btn" style="background: #f1f2f6; color: #576574;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="action-buttons">
                <?php if (!empty($installmentId)): ?>
                    <button type="button" class="btn btn-success" onclick="sendWhatsAppReminders()">
                        <i class="fab fa-whatsapp"></i> Send WhatsApp Reminders
                    </button>
                <?php endif; ?>

                <a href="export_pending.php?scheme_id=<?php echo $selectedSchemeId; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-excel">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>

        <?php if (count($pendingPayments) > 0): ?>
            <?php foreach ($pendingPayments as $payment): ?>
                <div class="pending-card">
                    <div class="customer-info">
                        <div class="customer-details">
                            <div class="customer-name"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                            <div class="customer-id"><?php echo $payment['CustomerUniqueID']; ?></div>
                            <div class="contact-info">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment['Contact']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="installment-details">
                        <div class="installment-header">
                            <div class="installment-name">
                                <?php echo htmlspecialchars($payment['SchemeName']); ?> -
                                <?php echo htmlspecialchars($payment['InstallmentName']); ?>
                            </div>
                            <div class="installment-amount">
                                ₹<?php echo number_format($payment['InstallmentAmount'], 2); ?>
                            </div>
                        </div>
                        <div class="installment-date">
                            <i class="fas fa-calendar"></i> Draw Date: <?php echo date('M d, Y', strtotime($payment['DrawDate'])); ?>
                        </div>
                        <div style="margin-top: 10px;">
                            <span class="payment-status <?php echo $payment['PaymentStatus'] ? 'status-pending' : 'status-not-submitted'; ?>">
                                <?php echo $payment['PaymentStatus'] ? 'Payment Pending Verification' : 'Payment Not Submitted'; ?>
                            </span>
                            <?php if ($payment['PaymentSubmittedAt']): ?>
                                <span style="margin-left: 10px; color: #7f8c8d; font-size: 14px;">
                                    Submitted on: <?php echo date('M d, Y H:i', strtotime($payment['PaymentSubmittedAt'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selectedSchemeId) ? '&scheme_id=' . $selectedSchemeId : ''; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selectedSchemeId) ? '&scheme_id=' . $selectedSchemeId : ''; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selectedSchemeId) ? '&scheme_id=' . $selectedSchemeId : ''; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?>"
                            class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selectedSchemeId) ? '&scheme_id=' . $selectedSchemeId : ''; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($selectedSchemeId) ? '&scheme_id=' . $selectedSchemeId : ''; ?><?php echo !empty($installmentId) ? '&installment_id=' . $installmentId : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-pending">
                <i class="fas fa-check-circle"></i>
                <p>No pending payments found</p>
                <?php if (!empty($search) || !empty($installmentId)): ?>
                    <a href="?scheme_id=<?php echo $selectedSchemeId; ?>" class="btn btn-primary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when scheme or installment changes
        document.querySelector('select[name="scheme_id"]').addEventListener('change', function() {
            this.form.submit();
        });

        document.querySelector('select[name="installment_id"]').addEventListener('change', function() {
            this.form.submit();
        });

        function sendWhatsAppReminders() {
            if (confirm('Are you sure you want to send WhatsApp reminders to all customers with pending payments for this installment?')) {
                // Show loading state
                const button = document.querySelector('.whatsapp-action button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                button.disabled = true;

                // Send AJAX request
                fetch('send_reminders.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            installment_id: <?php echo $installmentId; ?>,
                            scheme_id: <?php echo $selectedSchemeId; ?>
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('WhatsApp reminders sent successfully!');
                        } else {
                            alert('Error sending reminders: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error sending reminders. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
            }
        }
    </script>
</body>

</html>