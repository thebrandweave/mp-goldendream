<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "payments";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter ID from session
$promoterId = $_SESSION['promoter_id'];

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$schemeFilter = isset($_GET['scheme']) ? $_GET['scheme'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query for payments
$query = "
    SELECT 
        p.PaymentID,
        p.Amount,
        p.PaymentCodeValue,
        p.ScreenshotURL,
        p.Status,
        p.SubmittedAt,
        p.VerifiedAt,
        c.CustomerID,
        c.CustomerUniqueID,
        c.Name AS CustomerName,
        c.Contact AS CustomerContact,
        s.SchemeID,
        s.SchemeName,
        i.InstallmentID,
        i.InstallmentName,
        i.InstallmentNumber,
        i.DrawDate
    FROM 
        Payments p
    JOIN 
        Customers c ON p.CustomerID = c.CustomerID
    JOIN 
        Schemes s ON p.SchemeID = s.SchemeID
    JOIN 
        Installments i ON p.InstallmentID = i.InstallmentID
    WHERE 
        c.PromoterID = :promoterId
";

// Add filters if provided
if (!empty($statusFilter)) {
    $query .= " AND p.Status = :status";
}

if (!empty($schemeFilter)) {
    $query .= " AND s.SchemeID = :schemeId";
}

if (!empty($dateFrom)) {
    $query .= " AND p.SubmittedAt >= :dateFrom";
}

if (!empty($dateTo)) {
    $query .= " AND p.SubmittedAt <= :dateTo";
}

$query .= " ORDER BY p.SubmittedAt DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bindParam(':promoterId', $promoterId);

if (!empty($statusFilter)) {
    $stmt->bindParam(':status', $statusFilter);
}

if (!empty($schemeFilter)) {
    $stmt->bindParam(':schemeId', $schemeFilter);
}

if (!empty($dateFrom)) {
    $stmt->bindParam(':dateFrom', $dateFrom);
}

if (!empty($dateTo)) {
    $stmt->bindParam(':dateTo', $dateTo);
}

$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all schemes for filter dropdown
$schemeQuery = "SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName";
$schemeStmt = $conn->prepare($schemeQuery);
$schemeStmt->execute();
$schemes = $schemeStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$totalPayments = count($payments);
$totalAmount = 0;
$pendingCount = 0;
$verifiedCount = 0;
$rejectedCount = 0;

foreach ($payments as $payment) {
    $totalAmount += $payment['Amount'];
    
    if ($payment['Status'] == 'Pending') {
        $pendingCount++;
    } elseif ($payment['Status'] == 'Verified') {
        $verifiedCount++;
    } elseif ($payment['Status'] == 'Rejected') {
        $rejectedCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.total {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .stat-icon.pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .stat-icon.verified {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-icon.rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .filters-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.15);
        }

        .btn-reset {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-reset:hover {
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .payments-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th {
            background: var(--bg-light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .payments-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-primary);
        }

        .payments-table tr:last-child td {
            border-bottom: none;
        }

        .payments-table tr:hover {
            background: var(--bg-light);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-verified {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .payment-amount {
            font-weight: 600;
        }

        .payment-date {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .payment-code {
            font-family: monospace;
            background: var(--bg-light);
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 13px;
        }

        .payment-screenshot {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .payment-screenshot:hover {
            transform: scale(1.1);
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 50px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
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
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 1001;
        }

        .modal-image {
            max-width: 100%;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .payments-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="section-info">
                        <h2>Payment Details</h2>
                        <p>View and manage all customer payments</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>₹<?php echo number_format($totalAmount, 2); ?></h3>
                        <p>Total Amount</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Pending Payments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon verified">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $verifiedCount; ?></h3>
                        <p>Verified Payments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $rejectedCount; ?></h3>
                        <p>Rejected Payments</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-container">
                <div class="filters-title">Filter Payments</div>
                <form class="filters-form" method="GET">
                    <div class="form-group">
                        <label for="status">Payment Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Verified" <?php echo $statusFilter == 'Verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="Rejected" <?php echo $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="scheme">Scheme</label>
                        <select class="form-control" id="scheme" name="scheme">
                            <option value="">All Schemes</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?php echo $scheme['SchemeID']; ?>" <?php echo $schemeFilter == $scheme['SchemeID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="index.php" class="btn btn-reset">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="payments-container">
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>No Payments Found</h3>
                        <p>There are no payments matching your current filters. Try adjusting your filters or check back later.</p>
                    </div>
                <?php else: ?>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Scheme</th>
                                <th>Installment</th>
                                <th>Amount</th>
                                <th>Payment Code</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Verified</th>
                                <th>Screenshot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                        <div class="payment-date"><?php echo htmlspecialchars($payment['CustomerUniqueID']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($payment['SchemeName']); ?></div>
                                        <div class="payment-date">Draw: <?php echo date('d M Y', strtotime($payment['DrawDate'])); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($payment['InstallmentName']); ?></div>
                                        <div class="payment-date">#<?php echo $payment['InstallmentNumber']; ?></div>
                                    </td>
                                    <td class="payment-amount">₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                    <td>
                                        <?php if ($payment['PaymentCodeValue'] > 0): ?>
                                            <span class="payment-code"><?php echo $payment['PaymentCodeValue']; ?></span>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                            <?php echo $payment['Status']; ?>
                                        </span>
                                    </td>
                                    <td class="payment-date"><?php echo date('d M Y, h:i A', strtotime($payment['SubmittedAt'])); ?></td>
                                    <td class="payment-date">
                                        <?php echo $payment['VerifiedAt'] ? date('d M Y, h:i A', strtotime($payment['VerifiedAt'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($payment['ScreenshotURL']): ?>
                                            <img src="../../<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>" 
                                                 alt="Payment Screenshot" 
                                                 class="payment-screenshot"
                                                 onclick="openModal('../../<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>')">
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for Screenshot -->
    <div id="screenshotModal" class="modal" onclick="closeModal()">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="modal-content">
            <img id="modalImage" src="" alt="Payment Screenshot" class="modal-image">
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });

        // Modal functions
        function openModal(imageSrc) {
            const modal = document.getElementById('screenshotModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImg.src = imageSrc;
            event.stopPropagation();
        }

        function closeModal() {
            const modal = document.getElementById('screenshotModal');
            modal.style.display = 'none';
        }
    </script>
</body>
</html> 