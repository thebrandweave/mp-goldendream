<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "notifications";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle mark as read action
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $db->prepare("UPDATE Notifications SET IsRead = TRUE WHERE NotificationID = ? AND UserID = ? AND UserType = 'Customer'");
    $stmt->execute([$notification_id, $userData['customer_id']]);
    header("Location: ./");
    exit;
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE Notifications SET IsRead = TRUE WHERE UserID = ? AND UserType = 'Customer'");
    $stmt->execute([$userData['customer_id']]);
    header("Location: ./");
    exit;
}

// Get notifications
$stmt = $db->prepare("
    SELECT * FROM Notifications 
    WHERE UserID = ? AND UserType = 'Customer'
    ORDER BY CreatedAt DESC
");
$stmt->execute([$userData['customer_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_notifications,
        SUM(CASE WHEN IsRead = FALSE THEN 1 ELSE 0 END) as unread_notifications
    FROM Notifications 
    WHERE UserID = ? AND UserType = 'Customer'
");
$stmt->execute([$userData['customer_id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Golden Dream</title>
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

        .notifications-container {
            padding: 24px;
            margin-top: 70px;
        }

        .notifications-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .notifications-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .notifications-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .stat-item {
            text-align: center;
            padding: 16px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--accent-green);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .notification-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .notification-card::before {
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

        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .notification-card:hover::before {
            opacity: 1;
        }

        .notification-card.unread {
            background: rgba(47, 155, 127, 0.1);
            border-color: var(--accent-green);
        }

        .notification-time {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 8px;
        }

        .btn-mark-read {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-mark-read:hover {
            background: #248c6f;
            color: white;
            transform: translateY(-2px);
        }

        .btn-mark-all-read {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-mark-all-read:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
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

        .notification-icon {
            font-size: 1.5rem;
            margin-right: 12px;
            color: var(--accent-green);
        }

        .notification-message {
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .notifications-container {
                margin-left: 70px;
                padding: 16px;
            }

            .notifications-header {
                padding: 30px 20px;
            }

            .notification-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="notifications-container">
            <div class="container">
                <div class="notifications-header text-center">
                    <h2><i class="fas fa-bell"></i> Notifications</h2>
                    <p class="mb-0">Stay updated with your account activities</p>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You don't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_notifications']; ?></div>
                                    <div class="stat-label">Total Notifications</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['unread_notifications']; ?></div>
                                    <div class="stat-label">Unread Notifications</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($stats['unread_notifications'] > 0): ?>
                        <div class="text-end mb-4">
                            <form method="POST" action="" style="display: inline;">
                                <button type="submit" name="mark_all_read" class="btn btn-mark-all-read">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo $notification['IsRead'] ? '' : 'unread'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="notification-message">
                                        <i class="fas fa-info-circle notification-icon"></i>
                                        <?php echo htmlspecialchars($notification['Message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notification['CreatedAt'])); ?>
                                    </div>
                                </div>
                                <?php if (!$notification['IsRead']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['NotificationID']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-mark-read">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>