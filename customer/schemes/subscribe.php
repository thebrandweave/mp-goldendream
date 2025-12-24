<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "schemes";

// Get user data and validate session
$userData = checkSession();

// Get scheme ID from URL
$schemeId = isset($_GET['scheme_id']) ? (int)$_GET['scheme_id'] : 0;

if (!$schemeId) {
    header('Location: index.php');
    exit;
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get scheme details
$stmt = $db->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
$stmt->execute([$schemeId]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scheme) {
    header('Location: index.php');
    exit;
}

// Check if customer already has an active subscription
$stmt = $db->prepare("
    SELECT * FROM Subscriptions 
    WHERE CustomerID = ? AND SchemeID = ? AND RenewalStatus = 'Active'
");
$stmt->execute([$userData['customer_id'], $schemeId]);
$existingSubscription = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSubscription) {
    header('Location: view_benefits.php?scheme_id=' . $schemeId);
    exit;
}

// Get customer's bank details
$stmt = $db->prepare("
    SELECT BankName, BankAccountName, BankAccountNumber, IFSCCode 
    FROM Customers 
    WHERE CustomerID = ?
");
$stmt->execute([$userData['customer_id']]);
$customerSub = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate file upload
        if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please upload a payment screenshot');
        }

        $file = $_FILES['payment_screenshot'];
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Only JPEG and PNG files are allowed');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size should not exceed 5MB');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('payment_') . '.' . $extension;
        $uploadPath = '../uploads/payments/';

        // Create directory if it doesn't exist
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath . $filename)) {
            throw new Exception('Failed to upload file');
        }

        // Get installments for the scheme
        $stmt = $db->prepare("
            SELECT * FROM Installments 
            WHERE SchemeID = ? AND Status = 'Active'
            ORDER BY InstallmentNumber ASC
        ");
        $stmt->execute([$schemeId]);
        $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create a mapping of installment numbers to installment IDs
        $installmentMap = [];
        foreach ($installments as $installment) {
            $installmentMap[$installment['InstallmentNumber']] = $installment['InstallmentID'];
        }

        // Get selected installment
        $selectedInstallmentId = isset($_POST['selected_installment']) ? (int)$_POST['selected_installment'] : 0;

        // Validate selected installment
        if ($selectedInstallmentId <= 0) {
            throw new Exception('Please select a valid installment');
        }

        // Calculate subscription dates
        $startDate = new DateTime();
        $endDate = clone $startDate;
        $endDate->modify('+' . $scheme['TotalPayments'] . ' months');

        // Begin transaction
        $db->beginTransaction();

        // Create subscription
        $stmt = $db->prepare("
            INSERT INTO Subscriptions (
                CustomerID, SchemeID, StartDate, EndDate, RenewalStatus
            ) VALUES (?, ?, ?, ?, 'Active')
        ");
        $stmt->execute([
            $userData['customer_id'],
            $schemeId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ]);

        // Get the current installment number based on scheme start date
        $currentDate = new DateTime();
        $schemeStartDate = new DateTime($scheme['StartDate']);
        $interval = $currentDate->diff($schemeStartDate);
        $currentInstallment = ($interval->y * 12) + $interval->m + 1;

        // Create payment record for selected installment
        $stmt = $db->prepare("
            INSERT INTO Payments (
                CustomerID, SchemeID, InstallmentID, Amount, Status, ScreenshotURL, SubmittedAt
            ) VALUES (?, ?, ?, ?, 'Pending', ?, NOW())
        ");
        $stmt->execute([
            $userData['customer_id'],
            $schemeId,
            $selectedInstallmentId,
            $scheme['MonthlyPayment'],
            'uploads/payments/' . $filename
        ]);

        // If previous installments are selected, create payment records for them
        if (isset($_POST['previous_installments']) && is_array($_POST['previous_installments'])) {
            foreach ($_POST['previous_installments'] as $installmentNumber) {
                if ($installmentNumber < $currentInstallment && isset($installmentMap[$installmentNumber])) {
                    $installmentId = $installmentMap[$installmentNumber];
                    $stmt = $db->prepare("
                        INSERT INTO Payments (
                            CustomerID, SchemeID, InstallmentID, Amount, Status, ScreenshotURL, SubmittedAt
                        ) VALUES (?, ?, ?, ?, 'Pending', ?, NOW())
                    ");
                    $stmt->execute([
                        $userData['customer_id'],
                        $schemeId,
                        $installmentId,
                        $scheme['MonthlyPayment'],
                        'uploads/payments/' . $filename
                    ]);
                }
            }
        }

        // Commit transaction
        $db->commit();

        // Redirect to view benefits
        header('Location: view_benefits.php?scheme_id=' . $schemeId);
        exit;
    } catch (Exception $e) {
        // Only rollback if a transaction is active
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Get installments for the scheme
$stmt = $db->prepare("
    SELECT * FROM Installments 
    WHERE SchemeID = ? AND Status = 'Active'
    ORDER BY InstallmentNumber ASC
");
$stmt->execute([$schemeId]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate current installment
$currentDate = new DateTime();
$schemeStartDate = new DateTime($scheme['StartDate']);
$interval = $currentDate->diff($schemeStartDate);
$currentInstallment = ($interval->y * 12) + $interval->m + 1;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Scheme - Golden Dream</title>
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
        }

        .subscribe-container {
            padding: 24px;
            margin-top: 70px;
        }

        .subscribe-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .scheme-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .scheme-icon {
            width: 60px;
            height: 60px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--accent-green);
            font-size: 24px;
        }

        .scheme-header h4 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 500;
            margin: 0;
        }

        .subscription-details {
            margin: 24px 0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-secondary);
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .bank-details {
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }

        .bank-details h5 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bank-details h5 i {
            color: var(--accent-green);
        }

        .bank-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .bank-field {
            margin-bottom: 12px;
        }

        .bank-label {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 4px;
        }

        .bank-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .installments-section {
            margin: 24px 0;
            padding: 20px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
        }

        .installments-section h5 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .installment-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .installment-item:last-child {
            border-bottom: none;
        }

        .installment-info {
            flex-grow: 1;
        }

        .installment-number {
            color: var(--text-primary);
            font-weight: 500;
        }

        .installment-date {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .installment-amount {
            color: var(--accent-green);
            font-weight: 500;
            margin-left: 16px;
        }

        .current-installment {
            background: rgba(47, 155, 127, 0.2);
            border-radius: 4px;
        }

        .btn-subscribe {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-subscribe:hover {
            background: #248c6f;
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: transparent;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .alert {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .alert-danger {
            border-color: #FF4C51;
            background: rgba(255, 76, 81, 0.1);
        }

        @media (max-width: 768px) {
            .subscribe-container {
                margin-left: 70px;
                padding: 16px;
            }

            .subscribe-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="subscribe-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="subscribe-card">
                            <div class="scheme-header">
                                <div class="scheme-icon">
                                    <i class="fas fa-gem"></i>
                                </div>
                                <h4>Subscribe to <?php echo htmlspecialchars($scheme['SchemeName']); ?></h4>
                            </div>

                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <div class="subscription-details">
                                <div class="detail-item">
                                    <span class="detail-label">Monthly Payment</span>
                                    <span class="detail-value">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Duration</span>
                                    <span class="detail-value"><?php echo $scheme['TotalPayments']; ?> months</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Amount</span>
                                    <span class="detail-value">₹<?php echo number_format($scheme['MonthlyPayment'] * $scheme['TotalPayments'], 2); ?></span>
                                </div>
                            </div>

                            <div class="bank-details">
                                <h5><i class="fas fa-university"></i> Bank Details</h5>
                                <div class="bank-info">
                                    <div class="bank-field">
                                        <div class="bank-label">Bank Name</div>
                                        <div class="bank-value"><?php echo htmlspecialchars($customerSub['BankName'] ?: 'Not provided'); ?></div>
                                    </div>
                                    <div class="bank-field">
                                        <div class="bank-label">Account Name</div>
                                        <div class="bank-value"><?php echo htmlspecialchars($customerSub['BankAccountName'] ?: 'Not provided'); ?></div>
                                    </div>
                                    <div class="bank-field">
                                        <div class="bank-label">Account Number</div>
                                        <div class="bank-value"><?php echo htmlspecialchars($customerSub['BankAccountNumber'] ?: 'Not provided'); ?></div>
                                    </div>
                                    <div class="bank-field">
                                        <div class="bank-label">IFSC Code</div>
                                        <div class="bank-value"><?php echo htmlspecialchars($customerSub['IFSCCode'] ?: 'Not provided'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="installments-section">
                                <h5><i class="fas fa-calendar-check"></i> Installments</h5>
                                <form method="POST" enctype="multipart/form-data">
                                    <?php foreach ($installments as $installment): ?>
                                        <?php
                                        $isCurrent = $installment['InstallmentNumber'] == $currentInstallment;
                                        $isPast = $installment['InstallmentNumber'] < $currentInstallment;
                                        ?>
                                        <div class="installment-item <?php echo $isCurrent ? 'current-installment' : ''; ?>">
                                            <div class="installment-info">
                                                <div class="installment-number">
                                                    Installment <?php echo $installment['InstallmentNumber']; ?>
                                                    <?php if ($isCurrent): ?>
                                                        <span class="badge bg-success ms-2">Current</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="installment-date">
                                                    <?php echo date('M d, Y', strtotime($installment['DrawDate'])); ?>
                                                </div>
                                            </div>
                                            <div class="installment-amount">
                                                ₹<?php echo number_format($installment['Amount'], 2); ?>
                                            </div>
                                            <?php if ($isPast): ?>
                                                <div class="form-check ms-3">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="previous_installments[]"
                                                        value="<?php echo $installment['InstallmentNumber']; ?>"
                                                        id="installment_<?php echo $installment['InstallmentNumber']; ?>">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="payment-screenshot mt-4">
                                        <h5><i class="fas fa-camera"></i> Payment Screenshot</h5>
                                        <div class="mb-3">
                                            <label for="selected_installment" class="form-label">Select Installment</label>
                                            <select class="form-control mb-3" id="selected_installment" name="selected_installment" required>
                                                <option value="">Select an installment</option>
                                                <?php foreach ($installments as $installment): ?>
                                                    <option value="<?php echo $installment['InstallmentID']; ?>">
                                                        Installment <?php echo $installment['InstallmentNumber']; ?>
                                                        (₹<?php echo number_format($installment['Amount'], 2); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <label for="payment_screenshot" class="form-label">Upload payment screenshot (JPEG/PNG, max 5MB)</label>
                                            <input type="file" class="form-control" id="payment_screenshot" name="payment_screenshot" accept="image/jpeg,image/png" required>
                                            <div class="form-text text-secondary">Please upload a clear screenshot of your payment transaction</div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Back
                                        </a>
                                        <button type="submit" class="btn btn-subscribe">
                                            <i class="fas fa-check-circle me-2"></i> Confirm Subscription
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>