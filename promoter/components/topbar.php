<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --topbar-height: 70px;
        --Topprimary-color: #3a7bd5;
        --Topsecondary-color: #00d2ff;
        --Toptext-dark: #2c3e50;
        --Toptext-light: #7f8c8d;
        --topborder-color: #e5e9f2;
        --Topbg-light: #f8f9fa;
        --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
        --transition-speed: 0.3s;
    }

    .topbar {
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        height: var(--topbar-height);
        background: white;
        box-shadow: var(--shadow-sm);
        z-index: 900;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 25px;
        transition: left var(--transition-speed) ease;
        border-bottom: 1px solid var(--topborder-color);
    }

    .content-wrapper {
        padding-top: calc(var(--topbar-height) + 20px) !important;
    }

    .sidebar.collapsed+.topbar {
        left: var(--sidebar-collapsed-width);
    }

    /* Left section */
    .topbar-left {
        display: flex;
        align-items: center;
    }

    .page-title-container {
        margin-left: 10px;
    }

    .page-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--Toptext-dark);
        margin: 0;
    }

    .breadcrumb {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: 12px;
        color: var(--text-light);
    }

    .breadcrumb-item {
        display: flex;
        align-items: center;
    }

    .breadcrumb-item:not(:last-child)::after {
        content: '/';
        margin: 0 5px;
        color: #ccc;
    }

    .breadcrumb-item a {
        color: var(--text-dark);
        text-decoration: none;
    }

    .breadcrumb-item a:hover {
        color: var(--Topprimary-color);
    }

    .breadcrumb-item.active {
        color: var(--Topprimary-color);
    }

    /* Right section */
    .topbar-right {
        display: flex;
        align-items: center;
    }

    .action-icon {
        position: relative;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--Toptext-dark);
        margin-left: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        background-color: var(--Topbg-light);
    }

    .action-icon:hover {
        background-color: rgba(58, 123, 213, 0.1);
        color: var(--Topprimary-color);
    }

    .action-icon .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #f1c40f;
        color: #2c3e50;
        font-size: 10px;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* User profile styles */
    .user-profile {
        display: flex;
        align-items: center;
        margin-left: 20px;
        cursor: pointer;
        position: relative;
        border-left: 1px solid var(--topborder-color);
        padding-left: 20px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        overflow: hidden;
        margin-right: 12px;
        background: linear-gradient(135deg, var(--Topprimary-color), var(--Topsecondary-color));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 16px;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--Toptext-dark);
    }

    .user-role {
        font-size: 12px;
        color: var(--text-light);
    }

    .dropdown-toggle {
        display: flex;
        align-items: center;
    }

    .dropdown-icon {
        margin-left: 8px;
        color: var(--text-light);
        transition: transform 0.2s ease;
        font-size: 12px;
    }

    .user-profile:hover .dropdown-icon {
        transform: rotate(180deg);
        color: var(--Topprimary-color);
    }

    .user-dropdown {
        position: absolute;
        top: 60px;
        right: 0;
        width: 200px;
        background: white;
        border-radius: 8px;
        box-shadow: var(--shadow-md);
        padding: 8px 0;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.2s ease;
        z-index: 1000;
        border: 1px solid var(--topborder-color);
    }

    .user-profile:hover .user-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        padding: 10px 15px;
        display: flex;
        align-items: center;
        color: var(--Toptext-dark);
        font-size: 13px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .dropdown-item i {
        margin-right: 10px;
        font-size: 15px;
        width: 18px;
        text-align: center;
        color: var(--text-light);
    }

    .dropdown-item:hover {
        background-color: rgba(58, 123, 213, 0.05);
        color: var(--Topprimary-color);
    }

    .dropdown-item:hover i {
        color: var(--Topprimary-color);
    }

    .dropdown-divider {
        height: 1px;
        background-color: var(--topborder-color);
        margin: 6px 0;
    }

    .logout-item {
        color: #e74c3c;
    }

    .logout-item:hover {
        background-color: rgba(231, 76, 60, 0.05);
        color: #e74c3c;
    }

    .logout-item i {
        color: #e74c3c;
    }

    /* Responsive styles */
    @media (max-width: 991px) {
        .breadcrumb {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .user-info {
            display: none;
        }

        .topbar {
            padding: 0 15px;
        }

        .user-profile {
            padding-left: 12px;
            margin-left: 12px;
        }
    }

    @media (max-width: 480px) {
        .action-icon {
            width: 34px;
            height: 34px;
            margin-left: 8px;
        }

        .page-title {
            font-size: 16px;
        }

        .topbar-left {
            max-width: 55%;
        }

        .page-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>

<div class="topbar" id="topbar">
    <div class="topbar-left">
        <div class="page-title-container">
            <h1 class="page-title"><?php echo ucfirst(str_replace('-', ' ', $currentPage)); ?></h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $menuPath; ?>dashboard/">Dashboard</a></li>
                <?php if ($currentPage != 'dashboard'): ?>
                    <li class="breadcrumb-item active"><?php echo ucfirst(str_replace('-', ' ', $currentPage)); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="topbar-right">
        <div class="action-icon" title="Payment Codes">
            <i class="fas fa-qrcode"></i>
            <?php
            try {
                $stmt = $conn->prepare("SELECT PaymentCodeCounter FROM Promoters WHERE PromoterID = ?");
                $stmt->execute([$_SESSION['promoter_id']]);
                $codeCount = $stmt->fetch(PDO::FETCH_ASSOC)['PaymentCodeCounter'];
                if ($codeCount > 0):
            ?>
                <span class="badge"><?php echo $codeCount; ?></span>
            <?php 
                endif;
            } catch (Exception $e) { /* Silently fail */ }
            ?>
        </div>

        <a href="<?php echo $menuPath; ?>notifications/" class="action-icon" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Notifications WHERE UserType = 'Promoter' AND UserID = ? AND IsRead = 0");
                $stmt->execute([$_SESSION['promoter_id']]);
                $notificationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                if ($notificationCount > 0):
            ?>
                    <span class="badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php endif; ?>
            <?php } catch (Exception $e) { /* Silently fail */ } ?>
        </a>

        <a href="<?php echo $menuPath; ?>activity-logs/" class="action-icon" title="Activity Logs">
            <i class="fas fa-history"></i>
        </a>

        <div class="user-profile">
            <?php
            // Get promoter info from session
            $promoterName = isset($_SESSION['promoter_name']) ? $_SESSION['promoter_name'] : 'Promoter';
            $promoterImage = isset($_SESSION['promoter_image']) ? $_SESSION['promoter_image'] : '';

            // Fetch PromoterUniqueID from database
            try {
                $stmt = $conn->prepare("SELECT PromoterUniqueID FROM Promoters WHERE PromoterID = ?");
                $stmt->execute([$_SESSION['promoter_id']]);
                $promoterData = $stmt->fetch(PDO::FETCH_ASSOC);
                $promoterID = $promoterData['PromoterUniqueID'] ?? 'N/A';
            } catch (Exception $e) {
                $promoterID = 'N/A';
            }

            // Get promoter initials for avatar if no image is available
            $initials = '';
            $nameParts = explode(' ', $promoterName);
            if (count($nameParts) >= 2) {
                $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($promoterName, 0, 2));
            }
            ?>

            <div class="dropdown-toggle">
                <div class="user-avatar">
                    <?php if (!empty($promoterImage) && file_exists($promoterImage)): ?>
                        <img src="<?php echo $promoterImage; ?>" alt="<?php echo $promoterName; ?>">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>

                <div class="user-info">
                    <span class="user-name"><?php echo $promoterName; ?></span>
                    <span class="user-role">ID: <?php echo $promoterID; ?></span>
                </div>

                <i class="fas fa-chevron-down dropdown-icon"></i>
            </div>

            <div class="user-dropdown">
                <a href="<?php echo $menuPath; ?>profile" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="<?php echo $menuPath; ?>earnings" class="dropdown-item">
                    <i class="fas fa-wallet"></i> My Earnings
                </a>
                <a href="<?php echo $menuPath; ?>customers" class="dropdown-item">
                    <i class="fas fa-users"></i> My Customers
                </a>
                <a href="<?php echo $menuPath; ?>settings" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo $menuPath; ?>logout.php" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adjust content and topbar when sidebar is toggled
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');

        // Ensure topbar adjusts when sidebar state changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    if (sidebar.classList.contains('collapsed')) {
                        topbar.style.left = 'var(--sidebar-collapsed-width)';
                    } else {
                        topbar.style.left = 'var(--sidebar-width)';
                    }
                }
            });
        });

        observer.observe(sidebar, {
            attributes: true
        });

        // Initialize topbar position based on sidebar state
        if (sidebar.classList.contains('collapsed')) {
            topbar.style.left = 'var(--sidebar-collapsed-width)';
        } else {
            topbar.style.left = 'var(--sidebar-width)';
        }
    });
</script>