<?php
// Get user data from session


// Get customer data with profile image
$top_statementdatabase = new database();
$top_statementdb = $top_statementdatabase->getConnection();
$top_statement = $top_statementdb->prepare("SELECT ProfileImageURL FROM Customers WHERE CustomerID = ?");
$top_statement->execute([$userData['customer_id']]);
$customer = $top_statement->fetch(PDO::FETCH_ASSOC);

// Get unread notifications count
$top_statement = $top_statementdb->prepare("SELECT COUNT(*) as count FROM Notifications WHERE UserID = ? AND UserType = 'Customer' AND IsRead = 0");
$top_statement->execute([$userData['customer_id']]);
$notificationCount = $top_statement->fetch(PDO::FETCH_ASSOC)['count'];

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Set default avatar if no profile image
$profileImage = $customer['ProfileImageURL'] ? $customer['ProfileImageURL'] : 'assets/images/default-avatar.png';
?>

<nav class="topbar">
    <div class="topbar-content">
        <h1 class="topbar-title"><?php echo ucfirst(str_replace('.php', '', $current_page)); ?></h1>
        <div class="topbar-actions">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                <?php endif; ?>
            </div>
            <div class="user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="../profile/<?php echo htmlspecialchars($profileImage); ?>" alt="User Avatar" class="user-avatar" onerror="this.src='../<?php echo $c_path; ?>uploads/default-avatar.png'">
                <span class="user-name"> <?php echo htmlspecialchars($userData['customer_name']); ?></span>
            </div>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?php echo $c_path; ?>profile"><i class="fas fa-user"></i> Profile</a></li>
                <!-- <li><a class="dropdown-item" href="<?php echo $c_path; ?>settings"><i class="fas fa-cog"></i> Settings</a></li> -->
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="<?php echo $c_path; ?>logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<style>
    .topbar {
        background: #1A1D21;
        /* Match sidebar dark background */
        padding: 15px 24px;
        position: fixed;
        top: 0;
        right: 0;
        left: 250px;
        z-index: 999;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        height: 70px;
    }

    .topbar-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 100%;
    }

    .topbar-title {
        color: #fff;
        font-size: 20px;
        font-weight: 500;
        margin: 0;
    }

    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .notification-icon {
        position: relative;
        color: rgba(255, 255, 255, 0.7);
        font-size: 1.2rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .notification-icon:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #2F9B7F !important;
        /* Match sidebar green */
        color: #fff;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 50%;
        border: 2px solid #1A1D21;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .user-profile:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--accent-green);
        background-color: var(--card-bg);
    }

    .user-name {
        color: rgba(255, 255, 255, 0.9);
        font-weight: 400;
        font-size: 14px;
    }

    .dropdown-menu {
        background: #1A1D21;
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        padding: 8px;
        min-width: 180px;
        margin-top: 8px;
    }

    .dropdown-item {
        color: rgba(255, 255, 255, 0.7);
        padding: 8px 16px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }

    .dropdown-item i {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.7);
    }

    .dropdown-divider {
        border-color: rgba(255, 255, 255, 0.05);
        margin: 8px 0;
    }

    @media (max-width: 768px) {
        .topbar {
            left: 70px;
            padding: 15px 16px;
        }

        .topbar-title {
            font-size: 18px;
        }

        .user-name {
            display: none;
        }

        .user-profile {
            padding: 6px;
        }
    }
</style>

