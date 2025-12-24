<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "payment_qr";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers");
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all payment QR details
$stmt = $db->prepare("
    SELECT pq.*, c.Name as CustomerName 
    FROM PaymentQR pq 
    JOIN Customers c ON pq.CustomerID = c.CustomerID
");
$stmt->execute();
$payment_qrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment QR - Golden Dream</title>
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

        .qr-container {
            padding: 24px;
            margin-top: 70px;
        }

        .qr-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .qr-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .qr-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .section-header {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .section-header h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header h3 i {
            color: var(--accent-green);
        }

        .qr-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .qr-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .qr-section {
            text-align: center;
            padding: 24px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .qr-image {
            max-width: 200px;
            margin-bottom: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .bank-details {
            background: rgba(47, 155, 127, 0.1);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .bank-detail-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .bank-detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .bank-detail-label {
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .bank-detail-value {
            color: var(--text-primary);
            font-size: 1.1rem;
            word-break: break-all;
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

        .customer-name {
            color: var(--accent-green);
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-name i {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .qr-container {
                margin-left: 70px;
                padding: 16px;
            }

            .qr-header {
                padding: 30px 20px;
            }

            .qr-card {
                padding: 20px;
            }

            .qr-section,
            .bank-details {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="qr-container">
            <div class="container">
                <div class="qr-header text-center">
                    <h2><i class="fas fa-qrcode"></i> Payment QR Codes</h2>
                    <p class="mb-0">Available payment QR codes and bank details</p>
                </div>

                <?php if (!empty($payment_qrs)): ?>
                    <!-- QR Codes Section -->
                    <div class="section-header">
                        <h3><i class="fas fa-qrcode"></i> QR Codes</h3>
                    </div>
                    <div class="row mb-5">
                        <?php foreach ($payment_qrs as $payment_qr): ?>
                            <div class="col-md-6 mb-4">
                                <div class="qr-card">
                                    <div class="customer-name">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($payment_qr['CustomerName']); ?>
                                    </div>
                                    <div class="qr-section">
                                        <img src="<?php echo htmlspecialchars($payment_qr['UPIQRImageURL']); ?>"
                                            alt="Payment QR Code"
                                            class="qr-image">
                                        <p class="text-muted">Scan this QR code using any UPI payment app</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bank Details Section -->
                    <div class="section-header">
                        <h3><i class="fas fa-university"></i> Bank Details</h3>
                    </div>
                    <div class="row">
                        <?php foreach ($payment_qrs as $payment_qr): ?>
                            <div class="col-md-6 mb-4">
                                <div class="qr-card">
                                    <div class="customer-name">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($payment_qr['CustomerName']); ?>
                                    </div>
                                    <div class="bank-details">
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Account Name</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankAccountName']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Account Number</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankAccountNumber']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">IFSC Code</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['IFSCCode']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Name</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankName']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Branch</div>
                                            <div class="bank-detail-value"><?php echo htmlspecialchars($payment_qr['BankBranch']); ?></div>
                                        </div>
                                        <div class="bank-detail-item">
                                            <div class="bank-detail-label">Bank Address</div>
                                            <div class="bank-detail-value"><?php echo nl2br(htmlspecialchars($payment_qr['BankAddress'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-qrcode"></i>
                        <h3>No Payment QR Codes Available</h3>
                        <p>No payment QR codes have been set up yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>