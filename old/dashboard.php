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
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Get customer data
try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT c.*, s.SchemeName, s.MonthlyPayment, p.Name as PromoterName 
              FROM Customers c 
              LEFT JOIN Schemes s ON c.SchemeID = s.SchemeID 
              LEFT JOIN Promoters p ON c.PromoterID = p.PromoterID 
              WHERE c.CustomerID = :customer_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $stmt->execute();
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payments
    $payment_query = "SELECT * FROM Payments WHERE CustomerID = :customer_id ORDER BY SubmittedAt DESC LIMIT 5";
    $payment_stmt = $db->prepare($payment_query);
    $payment_stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $payment_stmt->execute();
    $recent_payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Dream - Customer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #ffd700;
            --secondary-color: #000000;
            --text-color: #ffffff;
            --card-bg: rgba(255, 255, 255, 0.05);
            --border-color: rgba(255, 215, 0, 0.1);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #000000, #1a1a1a);
            color: var(--text-color);
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem;
            border-right: 1px solid var(--border-color);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }

        .logo i {
            font-size: 2rem;
        }

        .logo h2 {
            font-size: 1.5rem;
            background: linear-gradient(45deg, var(--primary-color), #ffed4a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--primary-color);
            color: var(--secondary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .welcome-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
            font-size: 1.5rem;
        }

        .welcome-text h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: #888;
        }

        .logout-btn {
            padding: 0.8rem 1.5rem;
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--primary-color);
            color: var(--secondary-color);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .card-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .card-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .card-label {
            color: #888;
            font-size: 0.9rem;
        }

        /* Recent Payments Table */
        .recent-payments {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .table-title {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .view-all:hover {
            color: #ffed4a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            color: #888;
            font-weight: normal;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-verified {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }

        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    <a href="#" class="nav-link active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
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
                        <h1>Welcome, <?php echo htmlspecialchars($customer['Name']); ?></h1>
                        <p>Customer ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </header>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Current Scheme</h3>
                        <div class="card-icon">
                            <i class="fas fa-gem"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo htmlspecialchars($customer['SchemeName'] ?? 'No Scheme'); ?></div>
                    <div class="card-label">Monthly Payment: ₹<?php echo number_format($customer['MonthlyPayment'] ?? 0, 2); ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Amount Paid</h3>
                        <div class="card-icon">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                    </div>
                    <div class="card-value">₹<?php echo number_format($customer['TotalAmountPaid'], 2); ?></div>
                    <div class="card-label">Last Payment: <?php echo $customer['LastPaymentDate'] ? date('d M Y', strtotime($customer['LastPaymentDate'])) : 'No payments yet'; ?></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Payment Codes</h3>
                        <div class="card-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo $customer['PaymentCodes']; ?></div>
                    <div class="card-label">Available for this month</div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="recent-payments">
                <div class="table-header">
                    <h3 class="table-title">Recent Payments</h3>
                    <a href="#" class="view-all">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment Code</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($payment['SubmittedAt'])); ?></td>
                                <td>₹<?php echo number_format($payment['Amount'], 2); ?></td>
                                <td><?php echo $payment['PaymentCodeValue']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                        <?php echo $payment['Status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Add active class to current nav item
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === window.location.pathname) {
                link.classList.add('active');
            }
        });
    </script>
</body>

</html>