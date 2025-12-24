<?php
session_start();
require_once 'login/helpers/JWT.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login/login.php');
    exit;
}

// Database connection
class Database
{
    private $host = "localhost";
    private $db_name = "goldendream";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// Initialize default values
$customer = [
    'MonthlyPayment' => 0,
    'TotalPayments' => 0,
    'LastPaymentDate' => null,
    'StartDate' => null,
    'SchemeName' => 'No Active Scheme',
    'Description' => ''
];
$payments = [];
$verified_payments = 0;
$pending_payments = 0;
$next_payment_date = date('Y-m-d');
$total_amount_paid = 0;
$remaining_amount = 0;

// Get customer data and payment history
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Debug: Log customer ID
    error_log("Checking subscriptions for Customer ID: " . $_SESSION['customer_id']);

    // First, check if customer has any subscriptions
    $subscription_query = "SELECT sub.*, s.SchemeName, s.Description, s.MonthlyPayment, s.TotalPayments
                          FROM Subscriptions sub
                          INNER JOIN Schemes s ON sub.SchemeID = s.SchemeID
                          WHERE sub.CustomerID = :customer_id
                          ORDER BY sub.StartDate DESC";

    $sub_stmt = $db->prepare($subscription_query);
    $sub_stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $sub_stmt->execute();
    $subscriptions = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log subscription data
    error_log("Found subscriptions: " . count($subscriptions));
    error_log("Subscription data: " . print_r($subscriptions, true));

    if (!empty($subscriptions)) {
        // Get the most recent subscription
        $latest_subscription = $subscriptions[0];

        // Get customer details
        $customer_query = "SELECT c.*, s.SchemeName, s.Description, s.MonthlyPayment, s.TotalPayments
                          FROM Customers c
                          INNER JOIN Schemes s ON c.SchemeID = s.SchemeID
                          WHERE c.CustomerID = :customer_id";

        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->bindParam(':customer_id', $_SESSION['customer_id']);
        $customer_stmt->execute();
        $customer_data = $customer_stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer_data) {
            $customer = array_merge($customer_data, [
                'StartDate' => $latest_subscription['StartDate'],
                'EndDate' => $latest_subscription['EndDate'],
                'RenewalStatus' => $latest_subscription['RenewalStatus']
            ]);
        }
    } else {
        // If no subscriptions found, check if customer has a scheme assigned
        $scheme_query = "SELECT c.*, s.SchemeName, s.Description, s.MonthlyPayment, s.TotalPayments
                        FROM Customers c
                        INNER JOIN Schemes s ON c.SchemeID = s.SchemeID
                        WHERE c.CustomerID = :customer_id";

        $scheme_stmt = $db->prepare($scheme_query);
        $scheme_stmt->bindParam(':customer_id', $_SESSION['customer_id']);
        $scheme_stmt->execute();
        $scheme_data = $scheme_stmt->fetch(PDO::FETCH_ASSOC);

        if ($scheme_data) {
            $customer = $scheme_data;
        }
    }

    // Debug: Log final customer data
    error_log("Final customer data: " . print_r($customer, true));

    // Get payment history
    $payment_query = "SELECT * FROM Payments WHERE CustomerID = :customer_id ORDER BY SubmittedAt DESC";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $payment_stmt->execute();
    $payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate payment statistics
    foreach ($payments as $payment) {
        if ($payment['Status'] === 'Verified') {
            $verified_payments++;
            $total_amount_paid += $payment['Amount'];
        } elseif ($payment['Status'] === 'Pending') {
            $pending_payments++;
        }
    }

    // Calculate remaining amount and payments
    $total_scheme_amount = $customer['MonthlyPayment'] * $customer['TotalPayments'];
    $remaining_amount = $total_scheme_amount - $total_amount_paid;
    $remaining_payments = $customer['TotalPayments'] - $verified_payments;

    // Calculate next payment date
    if ($customer['LastPaymentDate']) {
        $next_payment_date = date('Y-m-d', strtotime('+1 month', strtotime($customer['LastPaymentDate'])));
    } elseif ($customer['StartDate']) {
        $next_payment_date = date('Y-m-d', strtotime('+1 month', strtotime($customer['StartDate'])));
    }
} catch (Exception $e) {
    error_log("Error in payments.php: " . $e->getMessage());
    // Keep the default values set above
}

// Debug information
error_log("Customer ID: " . $_SESSION['customer_id']);
error_log("Customer Data: " . print_r($customer, true));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Dream - Payments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/payments.css">
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-crown"></i>
                <h2>Golden Dream</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php" class="nav-link active">
                        <i class="fas fa-wallet"></i>
                        Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-history"></i>
                        Transaction History
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="welcome-section">
                    <div class="profile-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="welcome-text">
                        <h1>Payments</h1>
                        <p>Manage your payments and view history</p>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </header>

            <!-- Scheme Information -->
            <div class="scheme-info">
                <h2><?php echo htmlspecialchars($customer['SchemeName']); ?></h2>
                <p><?php echo htmlspecialchars($customer['Description']); ?></p>
            </div>

            <!-- Payment Stats -->
            <div class="payment-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Next Payment</h3>
                        <p><?php echo date('d M Y', strtotime($next_payment_date)); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Monthly Payment</h3>
                        <p>₹<?php echo number_format($customer['MonthlyPayment'], 2); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Payments Made</h3>
                        <p><?php echo $verified_payments; ?> / <?php echo $customer['TotalPayments']; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Remaining Amount</h3>
                        <p>₹<?php echo number_format($remaining_amount, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="payment-section">
                <!-- Payment Form -->
                <div class="payment-form">
                    <h2 class="form-title">Make a Payment</h2>
                    <form id="paymentForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-input" name="amount" value="<?php echo $customer['MonthlyPayment']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Code</label>
                            <input type="number" class="form-input" name="payment_code" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Screenshot</label>
                            <div class="file-upload">
                                <input type="file" name="screenshot" accept="image/*" required>
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload payment screenshot</p>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Payment
                        </button>
                    </form>
                </div>

                <!-- Payment History -->
                <div class="payment-history">
                    <h2 class="history-title">Payment History</h2>
                    <?php if (empty($payments)): ?>
                        <p class="no-payments">No payment history found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Screenshot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($payment['SubmittedAt'])); ?></td>
                                        <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                        <td><?php echo $payment['PaymentCodeValue']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                                <?php echo $payment['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>"
                                                alt="Payment Screenshot"
                                                class="screenshot-preview"
                                                onclick="window.open(this.src)">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="js/payments.js"></script>
</body>

</html>