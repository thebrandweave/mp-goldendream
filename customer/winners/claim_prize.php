<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "winners";

// Get user data and validate session
$userData = checkSession();

// Get winner ID and user ID from URL
$winner_id = isset($_GET['winner_id']) ? (int)$_GET['winner_id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get winner details
$stmt = $db->prepare("
    SELECT 
        w.*,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Name
            ELSE p.Name
        END as WinnerName,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
            ELSE p.PromoterUniqueID
        END as WinnerID,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Contact
            ELSE p.Contact
        END as Contact,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Email
            ELSE p.Email
        END as Email,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Address
            ELSE p.Address
        END as Address
    FROM Winners w
    LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'
    LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
    WHERE w.WinnerID = ? AND w.UserType = 'Customer' AND w.Status = 'Pending'
");
$stmt->execute([$winner_id]);
$winner = $stmt->fetch(PDO::FETCH_ASSOC);

// If winner not found or not pending, redirect to winners page
if (!$winner) {
    header("Location: index.php?error=invalid_prize");
    exit;
}

// Additional security check to ensure the logged-in user matches the winner
if ($winner['UserID'] != $userData['customer_id']) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['delivery_address']) || empty($_POST['delivery_date'])) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate delivery date is not in the past
        $delivery_date = new DateTime($_POST['delivery_date']);
        $today = new DateTime();
        if ($delivery_date < $today) {
            throw new Exception("Delivery date cannot be in the past.");
        }

        // Start transaction
        $db->beginTransaction();

        // Update winner status and delivery details
        $stmt = $db->prepare("
            UPDATE Winners 
            SET Status = 'Claimed', 
                VerifiedAt = CURRENT_TIMESTAMP,
                AdminID = (SELECT AdminID FROM Admins WHERE Role = 'SuperAdmin' LIMIT 1),
                DeliveryAddress = ?,
                PreferredDeliveryDate = ?
            WHERE WinnerID = ? AND Status = 'Pending'
        ");

        $result = $stmt->execute([
            $_POST['delivery_address'],
            $_POST['delivery_date'],
            $winner_id
        ]);

        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception("Failed to update winner status. The prize may have already been claimed or expired.");
        }

        // Create notification for admin
        $stmt = $db->prepare("
            INSERT INTO Notifications (UserID, UserType, Message)
            SELECT AdminID, 'Admin', CONCAT('New prize claim from ', ?)
            FROM Admins
            WHERE Role = 'SuperAdmin'
        ");
        $stmt->execute([$winner['WinnerName']]);

        // Commit transaction
        $db->commit();

        // Redirect to winners page with success message
        header("Location: index.php?success=1");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        // Log the error for debugging
        error_log("Prize claim error: " . $e->getMessage() . " for winner_id: " . $winner_id);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Prize - Golden Dream</title>
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

        .claim-container {
            padding: 24px;
            margin-top: 70px;
        }

        .claim-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .claim-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .claim-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .claim-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .prize-info {
            text-align: center;
            margin-bottom: 32px;
        }

        .prize-icon {
            font-size: 3rem;
            color: var(--accent-green);
            margin-bottom: 16px;
        }

        .prize-type {
            font-size: 24px;
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 16px;
        }

        .winner-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }

        .detail-item {
            background: rgba(47, 155, 127, 0.1);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            color: var(--text-primary);
            box-shadow: none;
        }

        .btn-claim {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-claim:hover {
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
            .claim-container {
                margin-left: 70px;
                padding: 16px;
            }

            .claim-header {
                padding: 30px 20px;
            }

            .claim-card {
                padding: 20px;
            }

            .winner-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="claim-container">
            <div class="container">
                <div class="claim-header text-center">
                    <h2><i class="fas fa-gift"></i> Claim Your Prize</h2>
                    <p class="mb-0">Complete the form below to claim your prize</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="claim-card">
                    <div class="prize-info">
                        <?php
                        $icon = match ($winner['PrizeType']) {
                            'Surprise Prize' => 'fas fa-gift',
                            'Bumper Prize' => 'fas fa-star',
                            'Gift Hamper' => 'fas fa-box',
                            'Education Scholarship' => 'fas fa-graduation-cap',
                            default => 'fas fa-trophy'
                        };
                        ?>
                        <div class="prize-icon">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="prize-type">
                            <?php echo htmlspecialchars($winner['PrizeType']); ?>
                        </div>
                    </div>

                    <div class="winner-details">
                        <div class="detail-item">
                            <div class="detail-label">Winning Date</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y', strtotime($winner['WinningDate'])); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Winner Name</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($winner['WinnerName']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Contact Number</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($winner['Contact']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($winner['Email']); ?>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="mt-4">
                        <div class="mb-4">
                            <label class="form-label">Delivery Address</label>
                            <textarea class="form-control" rows="3" name="delivery_address" required><?php echo htmlspecialchars($winner['Address']); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Preferred Delivery Date</label>
                            <input type="date" class="form-control" name="delivery_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" rows="3" name="notes" placeholder="Any special instructions for delivery?"></textarea>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-claim">
                                <i class="fas fa-check-circle"></i> Confirm Claim
                            </button>
                            <a href="index.php" class="btn btn-back">
                                <i class="fas fa-arrow-left"></i> Back to Winners
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>