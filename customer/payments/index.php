<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "payments";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all payments with scheme details and unpaid installments
$stmt = $db->prepare("
    SELECT 
        p.*,
        s.SchemeName,
        s.MonthlyPayment,
        sub.StartDate,
        sub.EndDate,
        sub.RenewalStatus,
        i.InstallmentNumber,
        i.DrawDate,
        CASE 
            WHEN p.Status = 'Verified' THEN 'success'
            WHEN p.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Payments p
    JOIN Schemes s ON p.SchemeID = s.SchemeID
    LEFT JOIN Subscriptions sub ON p.CustomerID = sub.CustomerID 
        AND p.SchemeID = sub.SchemeID
    LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID
    WHERE p.CustomerID = ?
    ORDER BY p.SubmittedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active subscriptions with unpaid installments
$stmt = $db->prepare("
    SELECT 
        s.SchemeID,
        s.SchemeName,
        s.MonthlyPayment,
        i.InstallmentID,
        i.InstallmentNumber,
        i.Amount,
        i.DrawDate,
        sub.StartDate,
        sub.EndDate
    FROM Subscriptions sub
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    JOIN Installments i ON s.SchemeID = i.SchemeID
    LEFT JOIN Payments p ON i.InstallmentID = p.InstallmentID 
        AND p.CustomerID = sub.CustomerID
    WHERE sub.CustomerID = ? 
    AND sub.RenewalStatus = 'Active'
    AND (p.PaymentID IS NULL OR p.Status = 'Rejected')
    ORDER BY i.DrawDate ASC
");
$stmt->execute([$userData['customer_id']]);
$unpaid_installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN Status = 'Verified' THEN 1 ELSE 0 END) as verified_payments,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_payments,
        SUM(CASE WHEN Status = 'Verified' THEN Amount ELSE 0 END) as total_paid
    FROM Payments
    WHERE CustomerID = ?
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.05);
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .payments-container {
            padding: 24px;
            margin-top: 70px;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        .payment-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 30px 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            text-align: center;
            width: 100%;
        }

        .payment-header h2 {
            color: #fff;
            font-size: clamp(22px, 4vw, 28px);
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .payment-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: clamp(14px, 2.5vw, 16px);
            margin: 0;
            position: relative;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            height: 100%;
        }

        .stat-item {
            text-align: center;
            padding: 14px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-value {
            font-size: clamp(20px, 3vw, 24px);
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 6px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: clamp(12px, 2.5vw, 14px);
        }

        .payment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
            word-break: break-word;
        }

        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-green), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .payment-card:hover::before {
            opacity: 1;
        }

        .scheme-name {
            color: var(--text-primary);
            font-size: clamp(16px, 3vw, 18px);
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scheme-name i {
            color: var(--accent-green);
        }

        .payment-amount {
            font-size: clamp(20px, 3vw, 24px);
            font-weight: 600;
            color: var(--accent-green);
        }

        .payment-date {
            color: var(--text-secondary);
            font-size: clamp(12px, 2.5vw, 14px);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-verified {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 16px 0;
            width: 100%;
        }

        .detail-item {
            background: rgba(47, 155, 127, 0.1);
            padding: 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: clamp(12px, 2.5vw, 14px);
            margin-bottom: 6px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: clamp(14px, 2.5vw, 16px);
        }

        .payment-actions {
            display: flex;
            flex-direction: row;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
            width: 100%;
            align-items: stretch;
            overflow: hidden;
        }

        .btn-view,
        .btn-payment {
            flex: 1 1 48%;
            min-width: 140px;
            margin: 0;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-view {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }

        .btn-view:hover {
            background: var(--accent-green);
            color: white;
        }

        .btn-payment {
            background: var(--accent-green);
            color: white;
            border: none;
        }

        .btn-payment:hover {
            background: #248c6f;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .payment-code {
            background: rgba(47, 155, 127, 0.1);
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            color: var(--accent-green);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .payments-container {
                padding: 20px;
                margin-left: 0;
                margin-right: 0;
            }
            
            .container {
                padding-right: 10px;
                padding-left: 10px;
            }
            
            .payment-card {
                padding: 18px;
            }

            .stats-card {
                padding: 16px;
            }
        }

        @media (max-width: 992px) {
            .payments-container {
                margin-left: 0;
                padding: 16px;
                width: 100%;
            }

            .container {
                padding-right: 8px;
                padding-left: 8px;
            }

            .payment-header {
                padding: 25px 16px;
                margin-left: 0;
                margin-right: 0;
            }

            .payment-card {
                margin-bottom: 14px;
                margin-left: 0;
                margin-right: 0;
            }

            .row {
                margin: 0;
                width: 100%;
            }

            .col-md-3 {
                padding: 0 8px;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .payments-container {
                padding: 12px;
                margin-left: 0;
                margin-right: 0;
            }

            .container {
                padding-right: 6px;
                padding-left: 6px;
            }

            .payment-header {
                padding: 20px 12px;
                margin-bottom: 16px;
                margin-left: 0;
                margin-right: 0;
            }

            .payment-card {
                padding: 16px;
                margin-left: 0;
                margin-right: 0;
            }

            .payment-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-item {
                padding: 12px;
            }

            .payment-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-view,
            .btn-payment {
                width: 100%;
                margin: 0;
            }
        }

        @media (max-width: 576px) {
            .payments-container {
                padding: 8px;
                margin-left: 0;
                margin-right: 0;
            }

            .container {
                padding-right: 4px;
                padding-left: 4px;
            }

            .payment-header {
                padding: 16px 10px;
                margin-left: 0;
                margin-right: 0;
            }

            .payment-card {
                padding: 10px;
                margin-left: 0;
                margin-right: 0;
                max-width: 100vw;
            }

            .detail-item {
                padding: 12px;
            }

            .stat-item {
                padding: 10px;
            }

            .btn-view,
            .btn-payment {
                font-size: 12px;
                padding: 6px 4px;
            }
            .payment-actions {
                gap: 6px;
            }
        }

        @media (max-width: 400px) {
            .payment-card {
                padding: 6px;
                max-width: 100vw;
            }
            .payment-actions {
                flex-direction: column;
                gap: 6px;
            }
            .btn-view,
            .btn-payment {
                flex: 1 1 100%;
            }
        }

        /* Fix for sidebar margin on mobile */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        .modal-title {
            color: var(--text-primary);
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .screenshot-link {
            color: var(--accent-green);
            cursor: pointer;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .screenshot-link:hover {
            color: #248c6f;
        }

        .screenshot-modal img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        @media (min-width: 576px) {
            .payment-actions {
                flex-direction: row;
                gap: 10px;
            }
            .btn-view,
            .btn-payment {
                width: 50%;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="payments-container">
            <div class="container">
                <div class="payment-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Payment History</h2>
                    <p class="mb-0">Track all your payments and their status</p>
                    <?php if (!empty($unpaid_installments)): ?>
                        <div class="mt-4">
                            <a href="make_payment.php" class="btn btn-payment">
                                <i class="fas fa-plus-circle"></i> Make New Payment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Payments Found</h3>
                        <p>You haven't made any payments yet.</p>
                        <?php if (!empty($unpaid_installments)): ?>
                            <a href="make_payment.php" class="btn btn-payment">
                                <i class="fas fa-plus-circle"></i> Make New Payment
                            </a>
                        <?php else: ?>
                            <a href="../schemes" class="btn btn-view">
                                <i class="fas fa-gem"></i> Explore Schemes
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_payments']; ?></div>
                                    <div class="stat-label">Total Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['verified_payments']; ?></div>
                                    <div class="stat-label">Verified Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['pending_payments']; ?></div>
                                    <div class="stat-label">Pending Payments</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value">₹<?php echo number_format($stats['total_paid'], 2); ?></div>
                                    <div class="stat-label">Total Amount Paid</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="scheme-name">
                                <?php echo htmlspecialchars($payment['SchemeName']); ?>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <div class="detail-label">Amount</div>
                                    <div class="payment-amount">
                                        ₹<?php echo number_format($payment['Amount'], 2); ?>
                                    </div>
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
                                    <div class="detail-label">Submitted Date</div>
                                    <div class="detail-value">
                                        <?php echo date('M d, Y', strtotime($payment['SubmittedAt'])); ?>
                                    </div>
                                </div>
                                <?php if ($payment['VerifiedAt']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Verified Date</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($payment['VerifiedAt'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($payment['ScreenshotURL']): ?>
                                <div class="payment-actions">
                                    <a href="#" class="screenshot-link" data-bs-toggle="modal" data-bs-target="#screenshotModal"
                                        data-screenshot="<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>">
                                        <i class="fas fa-image"></i> View Screenshot
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="payment-actions">
                                    <span class="text-secondary">No screenshot</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div class="modal fade screenshot-modal" id="screenshotModal" tabindex="-1" aria-labelledby="screenshotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="screenshotModalLabel">Payment Screenshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Payment Screenshot" id="screenshotImage">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-back" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const screenshotLinks = document.querySelectorAll('.screenshot-link');
            const screenshotModal = document.getElementById('screenshotModal');
            const screenshotImage = document.getElementById('screenshotImage');

            screenshotLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const screenshotUrl = this.getAttribute('data-screenshot');
                    screenshotImage.src = `../../${screenshotUrl}`;
                });
            });
        });
    </script>
</body>

</html>