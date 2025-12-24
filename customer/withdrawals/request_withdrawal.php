<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "withdrawals";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total balance from Balances table
$stmt = $db->prepare("
    SELECT SUM(BalanceAmount) as total_balance 
    FROM Balances 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$balance_result = $stmt->fetch(PDO::FETCH_ASSOC);
$available_balance = $balance_result['total_balance'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $remarks = trim($_POST['remarks'] ?? '');

    // Validate amount
    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } elseif ($amount > $available_balance) {
        $error = "Amount cannot exceed your available balance of ₹" . number_format($available_balance, 2);
    } else {
        try {
            $db->beginTransaction();

            // Insert withdrawal request
            $stmt = $db->prepare("
                INSERT INTO Withdrawals (UserID, UserType, Amount, Status, Remarks)
                VALUES (?, 'Customer', ?, 'Pending', ?)
            ");
            $stmt->execute([$userData['customer_id'], $amount, $remarks]);

            // Create notification for admin
            $stmt = $db->prepare("
                INSERT INTO Notifications (UserID, UserType, Message)
                SELECT AdminID, 'Admin', CONCAT('New withdrawal request of ₹', ?, ' from customer ', ?)
                FROM Admins
                WHERE Role = 'SuperAdmin'
            ");
            $stmt->execute([$amount, $customer['Name']]);

            $db->commit();
            $success = "Withdrawal request submitted successfully!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error submitting withdrawal request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Withdrawal - Golden Dream</title>
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

        .request-container {
            padding: 24px;
            margin-top: 70px;
        }

        .request-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .request-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .request-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .request-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .balance-info {
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .balance-amount {
            font-size: 2rem;
            font-weight: 600;
            color: var(--accent-green);
            margin: 8px 0;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            color: var(--text-primary);
            box-shadow: none;
        }

        .form-text {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }

        .btn-submit {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background: #248c6f;
            color: white;
            transform: translateY(-2px);
        }

        .btn-back {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .alert {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .request-container {
                margin-left: 70px;
                padding: 16px;
            }

            .request-header {
                padding: 30px 20px;
            }

            .request-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/topbar.php'; ?>
    <?php include '../c_includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="request-container">
            <div class="container">
                <div class="request-header text-center">
                    <h2><i class="fas fa-money-bill-wave"></i> Request Withdrawal</h2>
                    <p class="mb-0">Submit your withdrawal request</p>
                </div>

                <!-- Balance Card -->
                <div class="balance-info">
                    <h4>Available Balance</h4>
                    <div class="balance-amount">₹<?php echo number_format($available_balance, 2); ?></div>
                </div>

                <?php if ($available_balance <= 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> You don't have any available balance for withdrawal.
                    </div>
                <?php else: ?>
                    <!-- Withdrawal Form -->
                    <div class="request-card">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Amount (₹)</label>
                                <input type="number"
                                    class="form-control"
                                    name="amount"
                                    step="0.01"
                                    min="0"
                                    max="<?php echo $available_balance; ?>"
                                    required>
                                <div class="form-text">
                                    Maximum amount: ₹<?php echo number_format($available_balance, 2); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks (Optional)</label>
                                <textarea class="form-control"
                                    name="remarks"
                                    rows="3"
                                    placeholder="Add any additional information here..."></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <a href="withdrawals.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>