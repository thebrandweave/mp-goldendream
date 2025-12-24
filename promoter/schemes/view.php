<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "schemes";
$promoterUniqueID = $_SESSION['promoter_id'];

// Check if scheme ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$schemeId = $_GET['id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

try {
    // Get scheme details
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            (
                SELECT COUNT(*) 
                FROM Subscriptions sub 
                JOIN Customers c ON sub.CustomerID = c.CustomerID 
                WHERE sub.SchemeID = s.SchemeID 
                AND c.PromoterID = ? 
                AND sub.RenewalStatus = 'Active'
            ) as ActiveSubscribers
        FROM Schemes s 
        WHERE s.SchemeID = ? AND s.Status = 'Active'
    ");
    $stmt->execute([$promoterUniqueID, $schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        throw new Exception("Scheme not found or inactive.");
    }

    // Get installments for this scheme
    $stmt = $conn->prepare("
        SELECT * FROM Installments 
        WHERE SchemeID = ? AND Status = 'Active'
        ORDER BY InstallmentNumber ASC
    ");
    $stmt->execute([$schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme Details | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            padding: 24px;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding-top: calc(var(--topbar-height) + 24px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }

        .header-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: rgb(11, 90, 68);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.1);
        }

        .scheme-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .scheme-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 12px;
        }

        .scheme-info {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
        }

        .scheme-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .scheme-description {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .scheme-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .meta-item {
            background: var(--bg-light);
            padding: 16px;
            border-radius: 12px;
        }

        .meta-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .installments-section {
            margin-top: 24px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .installments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .installment-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .installment-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .installment-details {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 16px;
        }

        .installment-benefits {
            background: var(--primary-light);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 16px;
            }

            .scheme-details {
                grid-template-columns: 1fr;
            }

            .scheme-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="page-header">
                <div class="header-actions">
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Schemes
                    </a>
                    <a href="subscriptions.php?scheme_id=<?php echo $schemeId; ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Add Customers
                    </a>
                </div>

                <div class="scheme-details">
                    <img src="<?php echo !empty($scheme['SchemeImageURL']) ? '../../' . htmlspecialchars($scheme['SchemeImageURL']) : '../../uploads/schemes/default.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($scheme['SchemeName']); ?>" 
                         class="scheme-image">
                    
                    <div class="scheme-info">
                        <h1 class="scheme-title"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h1>
                        <p class="scheme-description"><?php echo nl2br(htmlspecialchars($scheme['Description'])); ?></p>
                        
                        <div class="scheme-meta">
                            <div class="meta-item">
                                <div class="meta-label">Monthly Payment</div>
                                <div class="meta-value">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Total Payments</div>
                                <div class="meta-value"><?php echo $scheme['TotalPayments']; ?> months</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Active Subscribers</div>
                                <div class="meta-value"><?php echo $scheme['ActiveSubscribers']; ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Start Date</div>
                                <div class="meta-value"><?php echo date('d M Y', strtotime($scheme['StartDate'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="installments-section">
                    <h2 class="section-title">Installments & Benefits</h2>
                    <div class="installments-grid">
                        <?php foreach ($installments as $installment): ?>
                            <div class="installment-card">
                                <h3 class="installment-title"><?php echo htmlspecialchars($installment['InstallmentName']); ?></h3>
                                <div class="installment-details">
                                    <div>Amount: ₹<?php echo number_format($installment['Amount'], 2); ?></div>
                                    <div>Draw Date: <?php echo date('d M Y', strtotime($installment['DrawDate'])); ?></div>
                                </div>
                                <div class="installment-benefits">
                                    <?php echo nl2br(htmlspecialchars($installment['Benefits'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        const sidebar = document.getElementById('sidebar');
        const content = document.querySelector('.content-wrapper');

        function adjustContent() {
            if (sidebar.classList.contains('collapsed')) {
                content.style.marginLeft = 'var(--sidebar-collapsed-width)';
            } else {
                content.style.marginLeft = 'var(--sidebar-width)';
            }
        }

        adjustContent();
        const observer = new MutationObserver(adjustContent);
        observer.observe(sidebar, { attributes: true });
    </script>
</body>
</html> 