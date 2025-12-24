<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "winners";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching promoter data";
    $messageType = "error";
}

// Get promoter's winning history
try {
    $stmt = $conn->prepare("
        SELECT * FROM Winners 
        WHERE UserID = ? AND UserType = 'Promoter' 
        ORDER BY WinningDate DESC
    ");
    $stmt->execute([$_SESSION['promoter_id']]);
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching winning history";
    $messageType = "error";
}

// Get total prizes won
$totalPrizes = count($winners);
$pendingPrizes = 0;
$claimedPrizes = 0;
$expiredPrizes = 0;

foreach ($winners as $winner) {
    if ($winner['Status'] === 'Pending') {
        $pendingPrizes++;
    } elseif ($winner['Status'] === 'Claimed') {
        $claimedPrizes++;
    } elseif ($winner['Status'] === 'Expired') {
        $expiredPrizes++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prizes | Golden Dreams</title>
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
            overflow-x: hidden;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
            max-width: 100%;
            overflow-x: hidden;
        }

        .profile-container {
            max-width: 1100px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-areas: 
                "sidebar main"
                "quick-actions main";
            gap: 20px;
            padding: 0 20px;
            box-sizing: border-box;
            width: 100%;
        }

        .profile-sidebar {
            grid-area: sidebar;
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            height: fit-content;
            box-shadow: var(--card-shadow);
        }

        .profile-image-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-light);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .profile-id {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .main-content {
            grid-area: main;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            grid-row: span 2;
            width: 100%;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
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

        .winners-container {
            margin-top: 30px;
        }

        .winner-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .winner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .winner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .winner-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .winner-date {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .winner-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .winner-detail {
            flex: 1;
            min-width: 200px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .winner-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .status-claimed {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-expired {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .winner-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }

        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .no-winners {
            text-align: center;
            padding: 50px 0;
            color: var(--text-secondary);
        }

        .no-winners i {
            font-size: 50px;
            margin-bottom: 20px;
            color: var(--border-color);
        }

        .no-winners h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .no-winners p {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .profile-container {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "sidebar"
                    "main";
                padding: 0 15px;
            }

            .main-content {
                padding: 20px;
            }
            
            .profile-image-container {
                width: 100px;
                height: 100px;
            }
            
            .winner-details {
                flex-direction: column;
                gap: 10px;
            }
            
            .winner-detail {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-image-container">
                    <img src="<?php 
                        if ($promoter['ProfileImageURL'] && file_exists('../../uploads/profile/' . $promoter['ProfileImageURL'])) {
                            echo '../../uploads/profile/' . htmlspecialchars($promoter['ProfileImageURL']);
                        } else {
                            echo '../../uploads/profile/image.png';
                        }
                    ?>" alt="Profile Image" class="profile-image">
                </div>
                <h2 class="profile-name"><?php echo htmlspecialchars($promoter['Name']); ?></h2>
                <p class="profile-id">ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></p>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $totalPrizes; ?></span>
                        <span class="stat-label">Total Prizes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $claimedPrizes; ?></span>
                        <span class="stat-label">Claimed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $pendingPrizes; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
            </div>

            <div class="main-content">
                <?php if ($showNotification && $message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="section-info">
                        <h2>My Prizes</h2>
                        <p>View your winning history and prize details</p>
                    </div>
                </div>

                <div class="winners-container">
                    <?php if (empty($winners)): ?>
                        <div class="no-winners">
                            <i class="fas fa-trophy"></i>
                            <h3>No Prizes Yet</h3>
                            <p>You haven't won any prizes yet. Keep participating in our schemes to increase your chances of winning!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($winners as $winner): ?>
                            <div class="winner-card">
                                <div class="winner-header">
                                    <h3 class="winner-title"><?php echo htmlspecialchars($winner['PrizeType']); ?></h3>
                                    <span class="winner-date">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date('d M Y', strtotime($winner['WinningDate'])); ?>
                                    </span>
                                </div>
                                
                                <div class="winner-details">
                                    <div class="winner-detail">
                                        <div class="detail-label">Prize Type</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($winner['PrizeType']); ?></div>
                                    </div>
                                    <div class="winner-detail">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value">
                                            <span class="winner-status status-<?php echo strtolower($winner['Status']); ?>">
                                                <?php echo htmlspecialchars($winner['Status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($winner['Remarks']): ?>
                                    <div class="winner-detail">
                                        <div class="detail-label">Remarks</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($winner['Remarks']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="winner-actions">
                                    <?php if ($winner['Status'] === 'Pending'): ?>
                                        <button class="btn-action btn-primary">
                                            <i class="fas fa-check-circle"></i> Claim Prize
                                        </button>
                                    <?php elseif ($winner['Status'] === 'Claimed'): ?>
                                        <button class="btn-action btn-secondary" disabled>
                                            <i class="fas fa-check"></i> Claimed
                                        </button>
                                    <?php elseif ($winner['Status'] === 'Expired'): ?>
                                        <button class="btn-action btn-secondary" disabled>
                                            <i class="fas fa-times-circle"></i> Expired
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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