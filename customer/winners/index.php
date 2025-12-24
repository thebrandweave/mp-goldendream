<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "winners";

// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all winners with their details for current user
$stmt = $db->prepare("
    SELECT 
        w.*,
        w.WinnerID as ActualWinnerID,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.Name
            ELSE p.Name
        END as WinnerName,
        CASE 
            WHEN w.UserType = 'Customer' THEN c.CustomerUniqueID
            ELSE p.PromoterUniqueID
        END as WinnerUniqueID,
        CASE 
            WHEN w.Status = 'Claimed' THEN 'success'
            WHEN w.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Winners w
    LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'
    LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
    WHERE w.UserID = ? AND w.UserType = 'Customer'
    ORDER BY w.WinningDate DESC
");
$stmt->execute([$userData['customer_id']]);
$my_winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all winners for the public section
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
            WHEN w.Status = 'Claimed' THEN 'success'
            WHEN w.Status = 'Pending' THEN 'warning'
            ELSE 'danger'
        END as status_color
    FROM Winners w
    LEFT JOIN Customers c ON w.UserID = c.CustomerID AND w.UserType = 'Customer'
    LEFT JOIN Promoters p ON w.UserID = p.PromoterID AND w.UserType = 'Promoter'
    ORDER BY w.WinningDate DESC
    LIMIT 10
");
$stmt->execute();
$all_winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get winner statistics for current user
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_prizes,
        SUM(CASE WHEN Status = 'Claimed' THEN 1 ELSE 0 END) as claimed_prizes,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_prizes,
        SUM(CASE WHEN Status = 'Expired' THEN 1 ELSE 0 END) as expired_prizes
    FROM Winners
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
    <title>Winners - Golden Dream</title>
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

        .winners-container {
            padding: 24px;
            margin-top: 70px;
        }

        .winners-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .winners-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .winners-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .winner-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .winner-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .winner-card::before {
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

        .winner-card:hover::before {
            opacity: 1;
        }

        .winner-name {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .winner-name i {
            color: var(--accent-green);
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

        .prize-type {
            font-size: 18px;
            font-weight: 600;
            color: var(--accent-green);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-claimed {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-expired {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
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

        @media (max-width: 768px) {
            .winners-container {
                margin-left: 70px;
                padding: 16px;
            }

            .winners-header {
                padding: 30px 20px;
            }

            .winner-card {
                padding: 20px;
            }

            .winner-details {
                grid-template-columns: 1fr;
            }
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-green);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .prize-icon {
            font-size: 2.5rem;
            color: var(--accent-green);
            margin-bottom: 16px;
        }

        .btn-claim {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-claim:hover {
            background: #248c6f;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="winners-container">
            <div class="container">
                <div class="winners-header text-center">
                    <h2><i class="fas fa-trophy"></i> My Prizes</h2>
                    <p class="mb-0">View all your prizes and rewards</p>
                </div>

                <?php if (empty($my_winners)): ?>
                    <div class="empty-state">
                        <i class="fas fa-gift"></i>
                        <h3>No Prizes Found</h3>
                        <p>You haven't won any prizes yet.</p>
                        <a href="../schemes" class="btn btn-primary">
                            <i class="fas fa-gem"></i> Explore Schemes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_prizes']; ?></div>
                                    <div class="stat-label">Total Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['claimed_prizes']; ?></div>
                                    <div class="stat-label">Claimed Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['pending_prizes']; ?></div>
                                    <div class="stat-label">Pending Prizes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['expired_prizes']; ?></div>
                                    <div class="stat-label">Expired Prizes</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($my_winners as $winner): ?>
                        <div class="winner-card">
                            <div class="text-center">
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
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <span class="status-badge status-<?php echo strtolower($winner['Status']); ?>">
                                            <?php echo $winner['Status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($winner['Status'] === 'Claimed'): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Claimed Date</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($winner['VerifiedAt'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($winner['Remarks']): ?>
                                <div class="detail-item mt-3">
                                    <div class="detail-label">Additional Information</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($winner['Remarks']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($winner['Status'] === 'Pending'): ?>
                                <div class="text-center mt-3">
                                    <a href="claim_prize.php?winner_id=<?php echo $winner['ActualWinnerID']; ?>&user_id=<?php echo $winner['UserID']; ?>"
                                        class="btn btn-claim">
                                        <i class="fas fa-check-circle"></i> Claim Prize
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- All Winners Section -->
                <div class="winners-header text-center mt-5">
                    <h2><i class="fas fa-users"></i> Recent Winners</h2>
                    <p class="mb-0">Celebrating our lucky winners</p>
                </div>

                <?php if (empty($all_winners)): ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h3>No Winners Yet</h3>
                        <p>Stay tuned for upcoming prize announcements!</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($all_winners as $winner): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="winner-card">
                                    <div class="text-center">
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
                                        <div class="winner-name">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($winner['WinnerName']); ?>
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
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">
                                                <span class="status-badge status-<?php echo strtolower($winner['Status']); ?>">
                                                    <?php echo $winner['Status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>