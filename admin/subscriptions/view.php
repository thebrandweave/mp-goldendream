<?php
session_start();
require_once("../middleware/auth.php");
verifyAuth();

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get subscription ID from URL
$subscriptionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($subscriptionId <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch subscription details
$query = "SELECT s.*, c.Name as CustomerName, c.Contact, c.Email, 
          sch.SchemeName, sch.Description as SchemeDescription,
          sch.MonthlyPayment as Amount
          FROM Subscriptions s
          JOIN Customers c ON s.CustomerID = c.CustomerID
          JOIN Schemes sch ON s.SchemeID = sch.SchemeID
          WHERE s.SubscriptionID = :id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $subscriptionId);
$stmt->execute();
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header("Location: index.php");
    exit();
}

// Calculate days remaining
$endDate = new DateTime($subscription['EndDate']);
$today = new DateTime();
$daysRemaining = $today->diff($endDate)->days;
$isExpired = $endDate < $today;

// Format dates
$startDate = date('d M Y', strtotime($subscription['StartDate']));
$endDate = date('d M Y', strtotime($subscription['EndDate']));
$createdAt = date('d M Y H:i', strtotime($subscription['CreatedAt']));
$updatedAt = date('d M Y H:i', strtotime($subscription['UpdatedAt']));

// Calculate duration in months
$start = new DateTime($subscription['StartDate']);
$end = new DateTime($subscription['EndDate']);
$interval = $start->diff($end);
$duration = ($interval->y * 12) + $interval->m;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Subscription - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            background-color: #f8f9fc;
        }

        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            margin-bottom: 0;
        }

        .status-badge {
            padding: 0.5em 1em;
            border-radius: 0.35rem;
            font-weight: 600;
        }

        .status-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }

        .status-expired {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }

        .status-cancelled {
            background-color: rgba(133, 135, 150, 0.1);
            color: var(--secondary-color);
        }

        .info-item {
            padding: 1rem;
            border-bottom: 1px solid #e3e6f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--secondary-color);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .days-remaining {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn-back {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .btn-back:hover {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Subscription Details</h5>
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Subscriptions
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Subscription ID</div>
                                    <div class="info-value">#<?php echo $subscription['SubscriptionID']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="status-badge status-<?php echo strtolower($subscription['RenewalStatus']); ?>">
                                        <?php echo $subscription['RenewalStatus']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Customer Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($subscription['CustomerName']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Contact</div>
                                    <div class="info-value"><?php echo htmlspecialchars($subscription['Contact']); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($subscription['Email']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Scheme</div>
                                    <div class="info-value"><?php echo htmlspecialchars($subscription['SchemeName']); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Start Date</div>
                                    <div class="info-value"><?php echo $startDate; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">End Date</div>
                                    <div class="info-value"><?php echo $endDate; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Monthly Amount</div>
                                    <div class="info-value">₹<?php echo number_format($subscription['Amount'], 2); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Duration</div>
                                    <div class="info-value"><?php echo $duration; ?> months</div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Created At</div>
                                    <div class="info-value"><?php echo $createdAt; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <div class="info-label">Last Updated</div>
                                    <div class="info-value"><?php echo $updatedAt; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-item">
                                    <div class="info-label">Scheme Description</div>
                                    <div class="info-value"><?php echo htmlspecialchars($subscription['SchemeDescription']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Subscription Status</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="days-remaining mb-3">
                            <?php if ($isExpired): ?>
                                <span class="text-danger">Subscription Expired</span>
                            <?php else: ?>
                                <?php echo $daysRemaining; ?> days remaining
                            <?php endif; ?>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <?php
                            $totalDays = (new DateTime($subscription['StartDate']))->diff(new DateTime($subscription['EndDate']))->days;
                            $progress = $isExpired ? 100 : (($totalDays - $daysRemaining) / $totalDays) * 100;
                            ?>
                            <div class="progress-bar bg-primary" role="progressbar"
                                style="width: <?php echo $progress; ?>%"
                                aria-valuenow="<?php echo $progress; ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                            </div>
                        </div>
                        <div class="text-muted">
                            <?php if ($isExpired): ?>
                                This subscription ended on <?php echo $endDate; ?>
                            <?php else: ?>
                                Valid until <?php echo $endDate; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>