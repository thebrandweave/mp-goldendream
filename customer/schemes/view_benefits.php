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
    header('Location: schemes.php');
    exit;
}

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get scheme details
$stmt = $db->prepare("SELECT * FROM Schemes WHERE SchemeID = ? AND Status = 'Active'");
$stmt->execute([$schemeId]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scheme) {
    header('Location: schemes.php');
    exit;
}

// Get installments for this scheme
$stmt = $db->prepare("
    SELECT * FROM Installments 
    WHERE SchemeID = ? AND Status = 'Active'
    ORDER BY InstallmentNumber ASC
");
$stmt->execute([$schemeId]);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Benefits - Golden Dream</title>
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

        .benefits-container {
            padding: 24px;
            margin-top: 70px;
        }

        .scheme-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .scheme-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .scheme-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .scheme-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .benefits-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .benefits-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .benefits-card h4 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .benefits-card h4 i {
            color: var(--accent-green);
        }

        .installment-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .installment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .installment-card::before {
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

        .installment-card:hover::before {
            opacity: 1;
        }

        .installment-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .installment-number {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .draw-date {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .amount {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .amount span {
            color: var(--accent-green);
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 16px 0;
        }

        .benefits-list li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .benefits-list li:last-child {
            border-bottom: none;
        }

        .benefits-list i {
            color: var(--accent-green);
            font-size: 14px;
        }

        .btn-back {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #248c6f;
            transform: translateY(-2px);
            color: white;
        }

        .section-title {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--accent-green);
        }

        @media (max-width: 768px) {
            .benefits-container {
                margin-left: 70px;
                padding: 16px;
            }

            .scheme-header {
                padding: 30px 20px;
            }

            .installment-card {
                padding: 20px;
            }

            .amount {
                font-size: 20px;
            }
        }

        .past-due {
            border-left: 4px solid #FF4C51;
        }

        .current-month {
            border-left: 4px solid #2F9B7F;
        }

        .payment-status {
            margin: 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .payment-summary-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            border: 1px solid var(--border-color);
        }

        .payment-summary-card h4 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .summary-item.total {
            background: rgba(47, 155, 127, 0.1);
            font-weight: 600;
        }

        .summary-item span:first-child {
            color: var(--text-secondary);
        }

        .summary-item span:last-child {
            color: var(--text-primary);
            font-weight: 500;
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="benefits-container">
            <div class="container">
                <div class="scheme-header">
                    <h2><i class="fas fa-gift me-2"></i> <?php echo htmlspecialchars($scheme['SchemeName']); ?></h2>
                    <p>Explore the exciting benefits and installments of this scheme</p>
                </div>

                <?php if ($scheme['Description']): ?>
                    <div class="benefits-card">
                        <h4><i class="fas fa-star"></i> General Benefits</h4>
                        <p class="text-secondary"><?php echo nl2br(htmlspecialchars($scheme['Description'])); ?></p>
                    </div>
                <?php endif; ?>

                <h4 class="section-title"><i class="fas fa-calendar-check"></i> Installments</h4>
                <div class="row">
                    <?php
                    $startDate = new DateTime($scheme['StartDate']);
                    $currentDate = new DateTime();
                    $totalInstallments = $scheme['TotalPayments'];
                    $monthlyPayment = $scheme['MonthlyPayment'];
                    $installmentsPaid = 0;

                    // Calculate how many installments have passed since start date
                    if ($currentDate > $startDate) {
                        $interval = $currentDate->diff($startDate);
                        $monthsPassed = ($interval->y * 12) + $interval->m;
                        $installmentsPaid = min($monthsPassed, $totalInstallments);
                    }

                    foreach ($installments as $installment):
                        $installmentDate = new DateTime($installment['DrawDate']);
                        $isPastDue = $installmentDate < $currentDate;
                        $isCurrentMonth = $installmentDate->format('Y-m') === $currentDate->format('Y-m');
                    ?>
                        <div class="col-md-6 mb-4">
                            <div class="installment-card <?php echo $isPastDue ? 'past-due' : ''; ?> <?php echo $isCurrentMonth ? 'current-month' : ''; ?>">
                                <?php if ($installment['ImageURL']): ?>
                                    <img class="img-fluid" src="../../<?php echo htmlspecialchars($installment['ImageURL']); ?>"
                                        alt="Installment <?php echo $installment['InstallmentNumber']; ?>"
                                        class="installment-image">
                                <?php endif; ?>

                                <div class="installment-number">
                                    <i class="fas fa-flag"></i>
                                    Installment <?php echo $installment['InstallmentNumber']; ?>
                                    <?php if ($isPastDue): ?>
                                        <span class="badge bg-danger ms-2">Past Due</span>
                                    <?php elseif ($isCurrentMonth): ?>
                                        <span class="badge bg-success ms-2">Current</span>
                                    <?php endif; ?>
                                </div>

                                <div class="draw-date">
                                    <i class="fas fa-calendar"></i>
                                    Draw Date: <?php echo date('M d, Y', strtotime($installment['DrawDate'])); ?>
                                </div>

                                <div class="amount">
                                    Amount: <span>₹<?php echo number_format($installment['Amount'], 2); ?></span>
                                </div>

                                <div class="payment-status">
                                    <i class="fas fa-info-circle"></i>
                                    <?php if ($installment['InstallmentNumber'] <= $installmentsPaid): ?>
                                        <span class="text-success">Payment Due</span>
                                    <?php else: ?>
                                        <span class="text-warning">Upcoming Payment</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($installment['Benefits']): ?>
                                    <div class="benefits-list">
                                        <?php
                                        $benefits = explode("\n", $installment['Benefits']);
                                        foreach ($benefits as $benefit):
                                            if (trim($benefit)):
                                        ?>
                                                <li>
                                                    <i class="fas fa-check-circle"></i>
                                                    <?php echo htmlspecialchars(trim($benefit)); ?>
                                                </li>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="payment-summary-card">
                    <h4><i class="fas fa-calculator"></i> Payment Summary</h4>
                    <div class="summary-details">
                        <div class="summary-item">
                            <span>Total Installments:</span>
                            <span><?php echo $totalInstallments; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Installments Paid:</span>
                            <span><?php echo $installmentsPaid; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Remaining Installments:</span>
                            <span><?php echo $totalInstallments - $installmentsPaid; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Monthly Payment:</span>
                            <span>₹<?php echo number_format($monthlyPayment, 2); ?></span>
                        </div>
                        <div class="summary-item total">
                            <span>Total Amount:</span>
                            <span>₹<?php echo number_format($monthlyPayment * $totalInstallments, 2); ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 mb-4">
                    <a href="index.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to Schemes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>