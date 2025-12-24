<?php include($menuPath . "components/loader.php"); ?>



<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .sidebar::-webkit-scrollbar {
        width: 0;
    }

    :root {
        --sidebar-width: 250px;
        --sidebar-collapsed-width: 70px;
        --primary-color: rgb(13, 106, 80);
        --secondary-color: #2c3e50;
        --text-color: #f0f0f0;
        --hover-color: rgb(175, 234, 161);
        --transition-speed: 0.3s;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        transition: all var(--transition-speed) ease;
        z-index: 1000;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    /* Add hover effect for collapsed sidebar */
    .sidebar.collapsed:hover {
        width: var(--sidebar-width);
    }

    /* Show text on hover when collapsed */
    .sidebar.collapsed:hover .sidebar-logo span,
    .sidebar.collapsed:hover .sidebar-menu .link-text,
    .sidebar.collapsed:hover .section-divider span {
        opacity: 1;
        width: auto;
        height: auto;
        overflow: visible;
    }

    .sidebar-header {
        padding: 20px 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        color: var(--text-color);
        font-size: 22px;
        font-weight: 700;
    }

    .sidebar-logo i {
        margin-right: 15px;
        font-size: 24px;
        color: var(--hover-color);
    }

    .sidebar-logo span {
        opacity: 1;
        transition: opacity var(--transition-speed) ease;
    }

    .collapsed .sidebar-logo span {
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
    }

    .toggle-btn {
        cursor: pointer;
        color: var(--text-color);
        font-size: 20px;
        transition: all var(--transition-speed) ease;
    }

    .toggle-btn:hover {
        color: var(--hover-color);
        transform: rotate(180deg);
    }

    .sidebar-menu {
        padding: 10px 0;
        list-style: none;
        margin: 0;
    }

    .sidebar-menu li {
        position: relative;
        margin: 5px 10px;
        border-radius: 8px;
        transition: all var(--transition-speed) ease;
    }

    .sidebar-menu li:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-menu li.active {
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .sidebar-menu li.active::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 0;
        height: 100%;
        width: 5px;
        background: var(--hover-color);
        border-radius: 0 5px 5px 0;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        color: var(--text-color);
        text-decoration: none;
        padding: 12px 15px;
        transition: all var(--transition-speed) ease;
        white-space: nowrap;
        overflow: hidden;
    }

    .sidebar-menu i {
        min-width: 30px;
        text-align: center;
        font-size: 18px;
        margin-right: 10px;
        transition: all var(--transition-speed) ease;
    }

    .sidebar-menu a:hover i {
        color: var(--hover-color);
        transform: translateX(5px);
    }

    .sidebar-menu .link-text {
        transition: opacity var(--transition-speed) ease;
        opacity: 1;
    }

    .collapsed .sidebar-menu .link-text {
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
    }

    .section-divider {
        margin: 15px 15px;
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        position: relative;
    }

    .section-divider span {
        position: absolute;
        left: 0;
        top: -8px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.5);
        padding: 0 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: opacity var(--transition-speed) ease;
    }

    .collapsed .section-divider span {
        opacity: 0;
    }

    .content-wrapper {
        margin-left: var(--sidebar-collapsed-width);
        transition: margin var(--transition-speed) ease, width var(--transition-speed) ease;
        min-height: 100vh;
        padding: 20px;
        width: calc(100% - var(--sidebar-collapsed-width));
        position: absolute;
        right: 0;
    }

    /* Top bar styles */
    .top-bar {
        position: fixed;
        top: 0;
        left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
        height: 60px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        z-index: 999;
        transition: all var(--transition-speed) ease;
    }

    /* Adjust top bar on sidebar hover */
    .sidebar:hover~.top-bar,
    .sidebar:hover+.top-bar,
    .sidebar.expanded~.top-bar {
        left: var(--sidebar-width);
        width: calc(100% - var(--sidebar-width));
    }

    /* Adjust top bar when sidebar is collapsed */
    .sidebar.collapsed~.top-bar {
        left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }

    /* Adjust content wrapper to account for top bar */
    .content-wrapper {
        margin-top: 60px;
        margin-left: var(--sidebar-collapsed-width);
        transition: all var(--transition-speed) ease;
        min-height: 100vh;
        padding: 20px;
        width: calc(100% - var(--sidebar-collapsed-width));
        position: absolute;
        right: 0;
    }

    /* Add hover effect for content wrapper */
    .sidebar:hover~.content-wrapper,
    .sidebar:hover+.content-wrapper {
        margin-left: var(--sidebar-width);
        width: calc(100% - var(--sidebar-width));
    }

    body.sidebar-collapsed .content-wrapper {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }

    /* Badge for notifications */
    .badge {
        background-color: #f1c40f;
        color: #2c3e50;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 11px;
        margin-left: 10px;
    }

    /* Tooltip for collapsed sidebar */
    .sidebar.collapsed .sidebar-menu a {
        position: relative;
    }

    .sidebar.collapsed .sidebar-menu a:hover::after {
        content: attr(data-title);
        position: absolute;
        left: 70px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--secondary-color);
        color: var(--text-color);
        padding: 5px 10px;
        border-radius: 5px;
        white-space: nowrap;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        z-index: 1000;
    }

    /* Animation for active link */
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(0, 210, 255, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(0, 210, 255, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(0, 210, 255, 0);
        }
    }

    .sidebar-menu li.active i {
        color: var(--hover-color);
        animation: pulse 2s infinite;
    }
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-users"></i>
            <span>Promoter Portal</span>
        </div>
        <div class="toggle-btn" id="toggle-sidebar">
            <i class="fas fa-angle-left"></i>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="<?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>dashboard" data-title="Dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>

        <div class="section-divider"><span>Customer Management</span></div>

        <li class="<?php echo ($currentPage == 'Customers') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>Customers" data-title="Customers">
                <i class="fas fa-user-friends"></i>
                <span class="link-text">My Customers</span>
            </a>
        </li>
        <!-- <li class="<?php echo ($currentPage == 'add-Customer') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>Customers/add.php" data-title="Add Customer">
                <i class="fas fa-user-plus"></i>
                <span class="link-text">Add Customer</span>
            </a>
        </li> -->

        <div class="section-divider"><span>Promoter Management</span></div>
        <li class="<?php echo ($currentPage == 'add-promoter') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>childPromoter/" data-title="Add Promoter">
                <i class="fas fa-user-friends"></i>
                <span class="link-text">My Promoters</span>
            </a>
        </li>

        <div class="section-divider"><span>Schemes & Payments</span></div>

        <li class="<?php echo ($currentPage == 'schemes') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>schemes" data-title="Schemes">
                <i class="fas fa-project-diagram"></i>
                <span class="link-text">Active Schemes</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage == 'payments') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>payments" data-title="Payments">
                <i class="fas fa-rupee-sign"></i>
                <span class="link-text">Payments</span>
            </a>
        </li>
        <!-- <li class="<?php echo ($currentPage == 'payment-codes') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>payment-codes" data-title="Payment Codes">
                <i class="fas fa-qrcode"></i>
                <span class="link-text">Payment Codes</span>
            </a>
        </li> -->

        <div class="section-divider"><span>Earnings</span></div>

        <li class="<?php echo ($currentPage == 'earnings') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>earnings" data-title="My Earnings">
                <i class="fas fa-wallet"></i>
                <span class="link-text">My Earnings</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage == 'withdrawals') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>withdrawals" data-title="Withdrawals">
                <i class="fas fa-money-bill-wave"></i>
                <span class="link-text">Withdrawals</span>
            </a>
        </li>
        <!-- <li class="<?php echo ($currentPage == 'winners') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>winners" data-title="Winners">
                <i class="fas fa-trophy"></i>
                <span class="link-text">Winners</span>
            </a>
        </li> -->

        <div class="section-divider"><span>Account</span></div>

        <li class="<?php echo ($currentPage == 'notifications') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>notifications" data-title="Notifications">
                <i class="fas fa-bell"></i>
                <span class="link-text">Notifications</span>
                <?php
                try {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Notifications WHERE UserType = 'Promoter' AND UserID = ? AND IsRead = 0");
                    $stmt->execute([$_SESSION['promoter_id']]);
                    $notificationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    if ($notificationCount > 0):
                ?>
                        <span class="badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php
                    endif;
                } catch (Exception $e) { /* Silently fail */
                }
                ?>
            </a>
        </li>
        <!-- <li class="<?php echo ($currentPage == 'activity') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>activity-logs" data-title="Activity Logs">
                <i class="fas fa-history"></i>
                <span class="link-text">Activity Logs</span>
            </a>
        </li> -->
        <li class="<?php echo ($currentPage == 'profile') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>profile" data-title="My Profile">
                <i class="fas fa-user"></i>
                <span class="link-text">My Profile</span>
            </a>
        </li>
        <!--         
        <li class="<?php echo ($currentPage == 'settings') ? 'active' : ''; ?>">
            <a href="<?php echo $menuPath; ?>settings" data-title="Settings">
                <i class="fas fa-cog"></i>
                <span class="link-text">Settings</span>
            </a>
        </li> -->
        <li>
            <a href="<?php echo $menuPath; ?>logout.php" data-title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="link-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggle-sidebar');
        const contentWrapper = document.querySelector('.content-wrapper');
        const topBar = document.querySelector('.top-bar'); // Select the top bar element

        // Set sidebar to collapsed by default
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');

        // Save sidebar state to localStorage
        localStorage.setItem('sidebarState', 'collapsed');

        // Add hover event listeners for sidebar
        sidebar.addEventListener('mouseenter', function() {
            if (sidebar.classList.contains('collapsed')) {
                contentWrapper.style.marginLeft = 'var(--sidebar-width)';
                contentWrapper.style.width = 'calc(100% - var(--sidebar-width))';

                // Adjust top bar margin
                if (topBar) {
                    topBar.style.marginLeft = 'var(--sidebar-width)';
                    topBar.style.width = 'calc(100% - var(--sidebar-width))';
                }
            }
        });

        sidebar.addEventListener('mouseleave', function() {
            if (sidebar.classList.contains('collapsed')) {
                contentWrapper.style.marginLeft = 'var(--sidebar-collapsed-width)';
                contentWrapper.style.width = 'calc(100% - var(--sidebar-collapsed-width))';

                // Adjust top bar margin
                if (topBar) {
                    topBar.style.marginLeft = 'var(--sidebar-collapsed-width)';
                    topBar.style.width = 'calc(100% - var(--sidebar-collapsed-width))';
                }
            }
        });

        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');

            // Save sidebar state to localStorage
            if (sidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
                contentWrapper.style.marginLeft = 'var(--sidebar-collapsed-width)';
                contentWrapper.style.width = 'calc(100% - var(--sidebar-collapsed-width))';

                // Adjust top bar margin
                if (topBar) {
                    topBar.style.marginLeft = 'var(--sidebar-collapsed-width)';
                    topBar.style.width = 'calc(100% - var(--sidebar-collapsed-width))';
                }
            } else {
                localStorage.setItem('sidebarState', 'expanded');
                document.body.classList.remove('sidebar-collapsed');
                contentWrapper.style.marginLeft = 'var(--sidebar-width)';
                contentWrapper.style.width = 'calc(100% - var(--sidebar-width))';

                // Adjust top bar margin
                if (topBar) {
                    topBar.style.marginLeft = 'var(--sidebar-width)';
                    topBar.style.width = 'calc(100% - var(--sidebar-width))';
                }
            }
        });

        // Set active menu item based on current page
        const currentLocation = window.location.href;
        const menuItems = document.querySelectorAll('.sidebar-menu a');

        menuItems.forEach(item => {
            if (currentLocation.includes(item.getAttribute('href'))) {
                item.parentElement.classList.add('active');
            }
        });
    });
</script>
