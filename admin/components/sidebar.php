<?php
include($menuPath . "components/loader.php");
require_once($menuPath . "middleware/auth.php");
verifyAuth();

?>
<li class="<?php echo ($currentPage == 'index') ? 'active' : ''; ?>" nk rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar::-webkit-scrollbar {
            width: 0;
        }

        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --primary-color: #3a7bd5;
            --secondary-color: #2c3e50;
            --text-color: #f0f0f0;
            --hover-color: #00d2ff;
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

        .sidebar.collapsed:hover .sidebar-logo span,
        .sidebar.collapsed:hover .link-text,
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
            margin-left: var(--sidebar-width);
            transition: margin var(--transition-speed) ease, width var(--transition-speed) ease;
            min-height: 100vh;
            padding: 20px;
            width: calc(95% - var(--sidebar-width));
            position: absolute;
            right: a;
        }

        body.sidebar-collapsed .content-wrapper {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(95% - var(--sidebar-collapsed-width));
        }

        /* Add hover effect for content wrapper when sidebar is expanded */
        .sidebar.collapsed:hover~.content-wrapper,
        .sidebar.collapsed:hover+.content-wrapper {
            margin-left: var(--sidebar-width);
            width: calc(95% - var(--sidebar-width));
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

        .alert {
            padding: 15px;
            border-radius: 6px;
            font-size: 16px;
            margin: 10px 0;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Success Alert */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Warning Alert */
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-chart-line"></i>
                <span>Admin Dashboard</span>
            </div>
            <div class="toggle-btn" id="toggle-sidebar">
                <!-- <i class="fas fa-angle-left"></i> -->
            </div>
        </div>

        <ul class="sidebar-menu">
            <?php if ($_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <li class="<?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>dashboard" data-title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>

            <div class="section-divider"><span>User Management</span></div>

            <?php if ($_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <li class="<?php echo ($currentPage == 'admins') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>admins" data-title="Admins">
                        <i class="fas fa-user-shield"></i>
                        <span class="link-text">Admins</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="<?php echo ($currentPage == 'promoters') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>promoter" data-title="Promoters">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Promoters</span>
                </a>
            </li>
            <li class="<?php echo ($currentPage == 'customers') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>customers" data-title="Customers">
                    <i class="fas fa-user-friends"></i>
                    <span class="link-text">Customers</span>
                </a>
            </li>
            <li class="<?php echo ($currentPage == 'teams') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>teams" data-title="Teams">
                    <i class="fas fa-people-group"></i>
                    <span class="link-text">Teams</span>
                </a>
            </li>

            <div class="section-divider"><span>Scheme Management</span></div>

            <li class="<?php echo ($currentPage == 'schemes') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>schemes" data-title="Schemes">
                    <i class="fas fa-project-diagram"></i>
                    <span class="link-text">Schemes</span>
                </a>
            </li>
            <li class="<?php echo ($currentPage == 'subscriptions') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>subscriptions" data-title="Subscriptions">
                    <i class="fas fa-calendar-check"></i>
                    <span class="link-text">Subscriptions</span>
                </a>
            </li>

            <div class="section-divider"><span>Finance</span></div>

            <li class="<?php echo ($currentPage == 'payments') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>payments" data-title="Payments">
                    <i class="fas fa-credit-card"></i>
                    <span class="link-text">Payments</span>
                </a>
            </li>
            <?php if ($_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <li class="<?php echo ($currentPage == 'wallets') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>wallets" data-title="Wallets">
                        <i class="fas fa-wallet"></i>
                        <span class="link-text">Wallets</span>
                    </a>
                </li>
                <li class="<?php echo ($currentPage == 'sales') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>sales" data-title="Sales">
                        <i class="fas fa-chart-bar"></i>
                        <span class="link-text">Sales</span>
                    </a>
                </li>
                <li class="<?php echo ($currentPage == 'withdrawals') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>withdrawals" data-title="Withdrawals">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span class="link-text">Withdrawals</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="<?php echo ($currentPage == 'commissions') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>commissions" data-title="Commissions">
                    <i class="fas fa-coins"></i>
                    <span class="link-text">Commissions</span>
                </a>
            </li>

            <li class="<?php echo ($currentPage == 'Pending') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>Pending" data-title="Pending">
                    <i class="fas fa-hourglass-half"></i>
                    <span class="link-text">Pending Payments</span>
                </a>
            </li>

            <li class="<?php echo ($currentPage == 'winners') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>winners" data-title="Winners">
                    <i class="fas fa-trophy"></i>
                    <span class="link-text">Winners</span>
                </a>
            </li>

            <div class="section-divider"><span>System</span></div>

            <li class="<?php echo ($currentPage == 'notifications') ? 'active' : ''; ?>">
                <a href="<?php echo $menuPath; ?>notifications" data-title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="link-text">Notifications</span>
                    <span class="badge">5</span>
                </a>
            </li>
            <?php if ($_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <li class="<?php echo ($currentPage == 'activity') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>activity-logs" data-title="Activity Logs">
                        <i class="fas fa-history"></i>
                        <span class="link-text">Activity Logs</span>
                    </a>
                </li>
                <li class="<?php echo ($currentPage == 'settings') ? 'active' : ''; ?>">
                    <a href="<?php echo $menuPath; ?>settings" data-title="Settings">
                        <i class="fas fa-cog"></i>
                        <span class="link-text">Settings</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="<?php echo ($currentPage == 'Logout') ? 'active' : ''; ?>">
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

            // Set sidebar to collapsed by default
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');

            // Save initial collapsed state
            localStorage.setItem('sidebarState', 'collapsed');

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed');

                // Save sidebar state to localStorage
                if (sidebar.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarState', 'collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'expanded');
                    document.body.classList.remove('sidebar-collapsed');
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
