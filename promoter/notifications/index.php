<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "notifications";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        $stmt = $conn->prepare("UPDATE Notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ? AND UserType = 'Promoter'");
        $stmt->execute([$_GET['mark_read'], $_SESSION['promoter_id']]);
        
        $message = "Notification marked as read";
        $messageType = "success";
        $showNotification = true;
    } catch (PDOException $e) {
        $message = "Error marking notification as read: " . $e->getMessage();
        $messageType = "error";
        $showNotification = true;
    }
}

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    try {
        $stmt = $conn->prepare("UPDATE Notifications SET IsRead = 1 WHERE UserID = ? AND UserType = 'Promoter'");
        $stmt->execute([$_SESSION['promoter_id']]);
        
        $message = "All notifications marked as read";
        $messageType = "success";
        $showNotification = true;
    } catch (PDOException $e) {
        $message = "Error marking notifications as read: " . $e->getMessage();
        $messageType = "error";
        $showNotification = true;
    }
}

// Get all notifications for the promoter
try {
    $stmt = $conn->prepare("
        SELECT * FROM Notifications 
        WHERE UserID = ? AND UserType = 'Promoter' 
        ORDER BY CreatedAt DESC
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread notifications
    $unreadCount = 0;
    foreach ($notifications as $notification) {
        if (!$notification['IsRead']) {
            $unreadCount++;
        }
    }
} catch (PDOException $e) {
    $message = "Error fetching notifications: " . $e->getMessage();
    $messageType = "error";
    $showNotification = true;
    $notifications = [];
    $unreadCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .notifications-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .notifications-header {
            padding: 20px;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notifications-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .notifications-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(13, 106, 80, 0.15);
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: background-color 0.2s ease;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: var(--bg-light);
        }

        .notification-item.unread {
            background-color: rgba(13, 106, 80, 0.03);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-icon i {
            font-size: 18px;
            color: var(--primary-color);
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-message {
            margin-bottom: 5px;
            font-size: 15px;
            color: var(--text-primary);
        }

        .notification-time {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .notification-action {
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .notification-action:hover {
            color: var(--primary-color);
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 50px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .notifications-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .notification-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="section-info">
                        <h2>Notifications</h2>
                        <p>Stay updated with your account activities</p>
                    </div>
                </div>
            </div>

            <div class="notifications-container">
                <div class="notifications-header">
                    <div class="notifications-title">
                        <?php echo $unreadCount > 0 ? "You have {$unreadCount} unread notification" . ($unreadCount > 1 ? "s" : "") : "All notifications"; ?>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <div class="notifications-actions">
                            <a href="?mark_all_read=1" class="btn btn-outline">
                                <i class="fas fa-check-double"></i>
                                Mark All as Read
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You don't have any notifications yet. They will appear here when you receive updates.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['IsRead'] ? '' : 'unread'; ?>">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['Message']); ?>
                                </div>
                                <div class="notification-time">
                                    <?php 
                                        $date = new DateTime($notification['CreatedAt']);
                                        $now = new DateTime();
                                        $interval = $now->diff($date);
                                        
                                        if ($interval->y > 0) {
                                            echo $date->format('M j, Y');
                                        } elseif ($interval->m > 0) {
                                            echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->d > 0) {
                                            echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->h > 0) {
                                            echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                        } elseif ($interval->i > 0) {
                                            echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                                        } else {
                                            echo 'Just now';
                                        }
                                    ?>
                                </div>
                            </div>
                            <?php if (!$notification['IsRead']): ?>
                                <div class="notification-actions">
                                    <a href="?mark_read=<?php echo $notification['NotificationID']; ?>" class="notification-action" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 