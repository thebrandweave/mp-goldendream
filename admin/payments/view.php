<?php
session_start();


$menuPath = "../";
$currentPage = "payments";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Check if payment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Payment ID is required to view details.";
    header("Location: index.php");
    exit();
}

$paymentId = $_GET['id'];

// Fetch payment details with related data
try {
    $query = "
        SELECT p.*,
            c.Name as CustomerName, c.CustomerUniqueID, c.Contact as CustomerContact, c.Email as CustomerEmail, c.ProfileImageURL as CustomerImage,
            s.SchemeName, s.MonthlyPayment, s.TotalPayments,
            pr.Name as PromoterName, pr.PromoterUniqueID, pr.Contact as PromoterContact,
            a.Name as VerifierName
        FROM Payments p
        LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
        LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
        LEFT JOIN Promoters pr ON p.PromoterID = pr.PromoterID
        LEFT JOIN Admins a ON p.AdminID = a.AdminID
        WHERE p.PaymentID = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['error_message'] = "Payment not found.";
        header("Location: index.php");
        exit();
    }

    // Fetch customer's other payments for this scheme
    $otherPaymentsQuery = "
        SELECT PaymentID, Amount, Status, SubmittedAt, VerifiedAt
        FROM Payments
        WHERE CustomerID = ? AND SchemeID = ? AND PaymentID != ?
        ORDER BY SubmittedAt DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($otherPaymentsQuery);
    $stmt->execute([$payment['CustomerID'], $payment['SchemeID'], $paymentId]);
    $otherPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch customer's active subscriptions
    $subscriptionsQuery = "
        SELECT s.SubscriptionID, s.StartDate, s.EndDate, s.RenewalStatus,
               sch.SchemeName
        FROM Subscriptions s
        JOIN Schemes sch ON s.SchemeID = sch.SchemeID
        WHERE s.CustomerID = ? AND s.RenewalStatus = 'Active'
        ORDER BY s.StartDate DESC
    ";
    $stmt = $conn->prepare($subscriptionsQuery);
    $stmt->execute([$payment['CustomerID']]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching payment details: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Handle payment verification from view page
if (isset($_POST['action']) && isset($_POST['payment_id'])) {
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $conn->beginTransaction();

        $newStatus = ($action === 'verify') ? 'Verified' : 'Rejected';

        // Update payment status
        $stmt = $conn->prepare("
            UPDATE Payments 
            SET Status = ?, AdminID = ?, VerifiedAt = CURRENT_TIMESTAMP 
            WHERE PaymentID = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $paymentId]);

        // Create notification for customer
        $notificationMessage = "Your payment of ₹" . number_format($payment['Amount'], 2) .
            " for " . $payment['SchemeName'] . " has been " . strtolower($newStatus);
        if (!empty($remarks)) {
            $notificationMessage .= ". Remarks: " . $remarks;
        }

        $stmt = $conn->prepare("
            INSERT INTO Notifications (UserID, UserType, Message) 
            VALUES (?, 'Customer', ?)
        ");
        $stmt->execute([$payment['CustomerID'], $notificationMessage]);

        // Log the activity
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "$newStatus payment #$paymentId for customer " . $payment['CustomerName'],
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Payment has been $newStatus successfully.";

        // Redirect to refresh page with updated data
        header("Location: view.php?id=$paymentId");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to process payment: " . $e->getMessage();
    }
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .payment-details-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .payment-info-card,
        .customer-info-card,
        .payment-history-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .payment-screenshot-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .payment-screenshot {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .payment-screenshot:hover {
            transform: scale(1.02);
        }

        .payment-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        .customer-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            margin-right: 15px;
            overflow: hidden;
        }

        .customer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .customer-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .customer-id {
            font-size: 14px;
            color: #7f8c8d;
        }

        .contact-badge {
            display: inline-flex;
            align-items: center;
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 13px;
            margin-top: 5px;
            gap: 5px;
        }

        .payment-history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .payment-history-table th,
        .payment-history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .payment-history-table th {
            font-size: 14px;
            font-weight: 600;
            color: #7f8c8d;
        }

        .payment-history-table td {
            font-size: 14px;
            color: #2c3e50;
        }

        .subscription-item {
            padding: 12px 15px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .subscription-name {
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
        }

        .subscription-dates {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            margin-right: 10px;
            text-decoration: none;
        }

        .verify-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        }

        .verify-btn:hover {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.4);
        }

        .reject-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }

        .reject-btn:hover {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.4);
        }

        .back-btn {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            box-shadow: 0 2px 5px rgba(127, 140, 141, 0.3);
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4);
        }

        .action-btns {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .verifier-info {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ecf0f1;
        }

        .verifier-info i {
            margin-right: 5px;
            color: #3498db;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
        }

        .modal-image {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            position: absolute;
            top: -30px;
            right: -30px;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(231, 76, 60, 0.8);
            transform: rotate(90deg);
        }

        .action-modal {
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
            backdrop-filter: blur(3px);
        }

        .action-modal-content {
            background: white;
            border-radius: 10px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .action-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .action-modal-body {
            margin-bottom: 20px;
        }

        .remarks-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
            resize: vertical;
            min-height: 80px;
            font-family: 'Poppins', sans-serif;
        }

        .remarks-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .action-modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-btn {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .modal-cancel-btn {
            background: #f1f2f6;
            color: #576574;
        }

        .modal-cancel-btn:hover {
            background: #dfe4ea;
        }

        .modal-confirm-btn {
            background: #3498db;
            color: white;
        }

        .modal-confirm-btn:hover {
            background: #2980b9;
        }

        @media (max-width: 768px) {
            .payment-details-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Payment Details</h1>
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

        <div class="action-btns">
            <a href="index.php" class="action-btn back-btn">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>

            <?php if ($payment['Status'] === 'Pending'): ?>
                <button class="action-btn verify-btn" onclick="showActionModal('verify')">
                    <i class="fas fa-check"></i> Verify Payment
                </button>
                <button class="action-btn reject-btn" onclick="showActionModal('reject')">
                    <i class="fas fa-times"></i> Reject Payment
                </button>
            <?php endif; ?>
        </div>

        <div class="payment-details-container">
            <div class="left-column">
                <div class="payment-info-card">
                    <h2 class="section-title">Payment Information</h2>

                    <div class="payment-screenshot-container">
                        <?php if ($payment['ScreenshotURL']): ?>
                            <img src="../../customer<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>"
                                alt="Payment Screenshot"
                                class="payment-screenshot"
                                onclick="showImageModal(this.src)">
                        <?php else: ?>
                            <div class="no-screenshot">
                                <i class="fas fa-image"></i>
                                <p>No screenshot available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="payment-details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Payment ID</div>
                            <div class="detail-value">#<?php echo $payment['PaymentID']; ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Amount</div>
                            <div class="detail-value">₹<?php echo number_format($payment['Amount'], 2); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                    <?php echo $payment['Status']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Scheme</div>
                            <div class="detail-value"><?php echo htmlspecialchars($payment['SchemeName']); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Submitted Date</div>
                            <div class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['SubmittedAt'])); ?></div>
                        </div>

                        <?php if ($payment['Status'] !== 'Pending' && $payment['VerifiedAt']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Verified/Rejected Date</div>
                                <div class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['VerifiedAt'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($payment['Status'] !== 'Pending' && $payment['VerifierName']): ?>
                        <div class="verifier-info">
                            <i class="fas fa-user-shield"></i> <?php echo $payment['Status']; ?> by <strong><?php echo htmlspecialchars($payment['VerifierName']); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="payment-history-card">
                    <h2 class="section-title">Payment History</h2>

                    <?php if (!empty($otherPayments)): ?>
                        <table class="payment-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($otherPayments as $otherPayment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($otherPayment['SubmittedAt'])); ?></td>
                                        <td>₹<?php echo number_format($otherPayment['Amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($otherPayment['Status']); ?>">
                                                <?php echo $otherPayment['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $otherPayment['PaymentID']; ?>" class="action-btn view-btn" style="padding: 4px 8px; font-size: 12px;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">No previous payments found for this customer and scheme.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-column">
                <div class="customer-info-card">
                    <h2 class="section-title">Customer Information</h2>

                    <div class="customer-header">
                        <div class="customer-avatar">
                            <?php if ($payment['CustomerImage']): ?>
                                <img src="../../customer/profile/<?php echo htmlspecialchars($payment['CustomerImage']); ?>" alt="<?php echo htmlspecialchars($payment['CustomerName']); ?>">
                            <?php else: ?>
                                <?php
                                $initials = '';
                                $nameParts = explode(' ', $payment['CustomerName']);
                                if (count($nameParts) >= 2) {
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($payment['CustomerName'], 0, 2));
                                }
                                echo $initials;
                                ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="customer-name"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                            <div class="customer-id"><?php echo $payment['CustomerUniqueID']; ?></div>
                        </div>
                    </div>

                    <div class="contact-badge">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment['CustomerContact']); ?>
                    </div>

                    <?php if ($payment['CustomerEmail']): ?>
                        <div class="contact-badge" style="margin-left: 10px;">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($payment['CustomerEmail']); ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px;">
                        <a href="../customers/view.php?id=<?php echo $payment['CustomerID']; ?>" class="action-btn view-btn" style="width: 100%; justify-content: center; margin-right: 0;">
                            <i class="fas fa-user"></i> View Customer Profile
                        </a>
                    </div>
                </div>

                <?php if ($payment['PromoterName']): ?>
                    <div class="customer-info-card">
                        <h2 class="section-title">Promoter Information</h2>

                        <div class="detail-item">
                            <div class="detail-label">Promoter Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($payment['PromoterName']); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Promoter ID</div>
                            <div class="detail-value"><?php echo $payment['PromoterUniqueID']; ?></div>
                        </div>

                        <?php if ($payment['PromoterContact']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Contact</div>
                                <div class="detail-value"><?php echo htmlspecialchars($payment['PromoterContact']); ?></div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 15px;">
                            <a href="../promoters/view.php?id=<?php echo $payment['PromoterID']; ?>" class="action-btn view-btn" style="width: 100%; justify-content: center; margin-right: 0;">
                                <i class="fas fa-user-tie"></i> View Promoter Profile
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($subscriptions)): ?>
                    <div class="customer-info-card">
                        <h2 class="section-title">Active Subscriptions</h2>

                        <?php foreach ($subscriptions as $subscription): ?>
                            <div class="subscription-item">
                                <div>
                                    <div class="subscription-name"><?php echo htmlspecialchars($subscription['SchemeName']); ?></div>
                                    <div class="subscription-dates">
                                        <?php echo date('M d, Y', strtotime($subscription['StartDate'])); ?> -
                                        <?php echo date('M d, Y', strtotime($subscription['EndDate'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-verified">
                                    <?php echo $subscription['RenewalStatus']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal" id="imageModal" onclick="hideImageModal()">
        <div class="modal-content">
            <span class="close-modal" onclick="hideImageModal()">&times;</span>
            <img src="" alt="Payment Screenshot" class="modal-image" id="modalImage">
        </div>
    </div>

    <!-- Action Modal -->
    <div class="action-modal" id="actionModal">
        <div class="action-modal-content">
            <div class="action-modal-title" id="actionModalTitle">Verify Payment</div>
            <div class="action-modal-body">
                <form id="actionForm" method="POST">
                    <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">
                    <input type="hidden" name="action" id="actionType" value="">
                    <textarea name="remarks" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                    <div class="action-modal-buttons">
                        <button type="button" class="modal-btn modal-cancel-btn" onclick="hideActionModal()">Cancel</button>
                        <button type="submit" class="modal-btn modal-confirm-btn" id="confirmActionBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide image modal
        function showImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');

            modalImage.src = imageSrc;
            modal.style.display = 'flex';

            // Prevent body scrolling when modal is open
            document.body.style.overflow = 'hidden';
        }

        function hideImageModal() {
            document.getElementById('imageModal').style.display = 'none';

            // Re-enable body scrolling
            document.body.style.overflow = 'auto';
        }

        // Show/hide action modal
        function showActionModal(action) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('actionModalTitle');
            const actionType = document.getElementById('actionType');
            const confirmBtn = document.getElementById('confirmActionBtn');

            // Set appropriate title and button style based on action
            if (action === 'verify') {
                modalTitle.textContent = 'Verify Payment';
                confirmBtn.className = 'modal-btn modal-confirm-btn';
                confirmBtn.style.background = '#2ecc71';
            } else {
                modalTitle.textContent = 'Reject Payment';
                confirmBtn.className = 'modal-btn modal-confirm-btn';
                confirmBtn.style.background = '#e74c3c';
            }

            // Set form action
            actionType.value = action;

            // Show modal
            modal.style.display = 'flex';

            // Focus on remarks input
            setTimeout(() => {
                document.querySelector('.remarks-input').focus();
            }, 100);

            // Prevent body scrolling
            document.body.style.overflow = 'hidden';
        }

        function hideActionModal() {
            document.getElementById('actionModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideImageModal();
                hideActionModal();
            }
        });

        // Handle form validation
        document.getElementById('actionForm').addEventListener('submit', function(e) {
            const action = document.getElementById('actionType').value;
            const confirmMessage = action === 'verify' ?
                'Are you sure you want to verify this payment?' :
                'Are you sure you want to reject this payment?';

            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }

            // Add loading state to button
            if (!e.defaultPrevented) {
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                confirmBtn.disabled = true;
            }
        });

        // Stop propagation for modal content clicks
        document.querySelector('.modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.querySelector('.action-modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Close action modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideActionModal();
            }
        });

        // Add print functionality
        function printPaymentDetails() {
            const originalContents = document.body.innerHTML;

            // Create a version optimized for printing
            const printContents = document.querySelector('.payment-details-container').innerHTML;

            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <h1 style="text-align: center; margin-bottom: 20px;">Payment Details #<?php echo $payment['PaymentID']; ?></h1>
                    ${printContents}
                </div>
            `;

            window.print();

            // Restore original contents
            document.body.innerHTML = originalContents;

            // Reattach event handlers
            attachEventHandlers();
        }

        // Function to reattach event handlers after printing
        function attachEventHandlers() {
            // Re-attach modal events
            document.querySelector('.payment-screenshot')?.addEventListener('click', function() {
                showImageModal(this.src);
            });

            // Re-attach action buttons
            document.querySelector('.verify-btn')?.addEventListener('click', function() {
                showActionModal('verify');
            });

            document.querySelector('.reject-btn')?.addEventListener('click', function() {
                showActionModal('reject');
            });

            // Re-initialize other event handlers...
        }

        // Add a print button to the action buttons area
        document.addEventListener('DOMContentLoaded', function() {
            const actionBtns = document.querySelector('.action-btns');
            const printButton = document.createElement('button');
            printButton.className = 'action-btn';
            printButton.style.background = 'linear-gradient(135deg, #3498db, #2980b9)';
            printButton.style.boxShadow = '0 2px 5px rgba(52, 152, 219, 0.3)';
            printButton.innerHTML = '<i class="fas fa-print"></i> Print';
            printButton.addEventListener('click', printPaymentDetails);
            actionBtns.appendChild(printButton);

            // Add copy functionality for payment ID, amount, etc.
            document.querySelectorAll('.detail-value').forEach(element => {
                if (!element.querySelector('.status-badge')) {
                    element.style.cursor = 'pointer';
                    element.setAttribute('title', 'Click to copy');
                    element.addEventListener('click', function() {
                        const textToCopy = this.textContent.trim();
                        navigator.clipboard.writeText(textToCopy).then(() => {
                            // Show temporary tooltip
                            const tooltip = document.createElement('div');
                            tooltip.textContent = 'Copied!';
                            tooltip.style.position = 'absolute';
                            tooltip.style.background = '#333';
                            tooltip.style.color = 'white';
                            tooltip.style.padding = '5px 10px';
                            tooltip.style.borderRadius = '3px';
                            tooltip.style.fontSize = '12px';
                            tooltip.style.zIndex = '1000';
                            tooltip.style.opacity = '0';
                            tooltip.style.transition = 'opacity 0.3s ease';

                            // Position the tooltip
                            const rect = this.getBoundingClientRect();
                            tooltip.style.top = `${rect.top - 30}px`;
                            tooltip.style.left = `${rect.left + rect.width / 2 - 30}px`;

                            document.body.appendChild(tooltip);

                            // Show, then hide
                            setTimeout(() => {
                                tooltip.style.opacity = '1';
                            }, 10);
                            setTimeout(() => {
                                tooltip.style.opacity = '0';
                                setTimeout(() => {
                                    document.body.removeChild(tooltip);
                                }, 300);
                            }, 1500);
                        });
                    });
                }
            });
        });

        // Enable image zoom functionality
        document.addEventListener('DOMContentLoaded', function() {
            const paymentImage = document.querySelector('.payment-screenshot');
            const modalImage = document.getElementById('modalImage');

            if (paymentImage && modalImage) {
                let scale = 1;
                let panning = false;
                let pointX = 0;
                let pointY = 0;
                let start = {
                    x: 0,
                    y: 0
                };

                function setTransform() {
                    modalImage.style.transform = `translate(${pointX}px, ${pointY}px) scale(${scale})`;
                }

                // Zoom in and out with mouse wheel
                modalImage.addEventListener('wheel', function(e) {
                    e.preventDefault();

                    const xs = (e.clientX - pointX) / scale;
                    const ys = (e.clientY - pointY) / scale;

                    // Adjust scale based on wheel direction
                    scale += e.deltaY * -0.01;

                    // Restrict scale
                    scale = Math.min(Math.max(1, scale), 4);

                    pointX = e.clientX - xs * scale;
                    pointY = e.clientY - ys * scale;

                    setTransform();
                });

                // Reset zoom when the modal is closed or a new image is shown
                document.getElementById('imageModal').addEventListener('hide', function() {
                    scale = 1;
                    pointX = 0;
                    pointY = 0;
                    setTransform();
                });
            }
        });
    </script>
</body>

</html>