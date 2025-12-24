<?php
session_start();
// Check if user is logged in

require("../../config/config.php");
// Check if the logged-in admin has SuperAdmin privileges
if ($_SESSION['admin_role'] !== 'SuperAdmin') {
    $_SESSION['error_message'] = "You don't have permission to access settings.";
    header("Location: ../dashboard/index.php");
    exit();
}

$menuPath = "../";
$currentPage = "settings";

// Include header and sidebar
include("../components/sidebar.php");
// include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
                :root {
            --ad_primary-color: #3a7bd5;
            --ad_primary-hover: #2c60a9;
            --ad_secondary-color: #00d2ff;
            --ad_success-color: #2ecc71;
            --ad_success-hover: #27ae60;
            --warning-color: #f39c12;
            --warning-hover: #d35400;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
            --text-dark: #2c3e50;
            --text-medium: #34495e;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            padding: 20px 0;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--ad_primary-color), var(--ad_secondary-color));
        }

        .settings-card-content {
            padding: 25px;
        }

        .settings-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--ad_primary-color), var(--ad_secondary-color));
            color: white;
            font-size: 24px;
        }

        .settings-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .settings-description {
            color: var(--text-light);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .settings-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--bg-light);
            color: var(--text-medium);
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .settings-action:hover {
            background: var(--ad_primary-color);
            color: white;
        }

        .settings-action i {
            font-size: 14px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">System Settings</h1>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Payment Settings Section -->
        <div class="settings-section">
            <h2 class="section-title">Payment Configuration</h2>
            <div class="settings-grid">
                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h3 class="settings-title">Payment QR Management</h3>
                        <p class="settings-description">Configure and manage payment QR codes, bank account details, and Razorpay integration settings.</p>
                        <a href="payment-qr/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3 class="settings-title">Payment Gateway</h3>
                        <p class="settings-description">Configure payment gateway credentials, API keys, and webhook settings.</p>
                        <a href="payment-gateway/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Communication Settings Section -->
        <div class="settings-section">
            <h2 class="section-title">Communication Settings</h2>
            <div class="settings-grid">
                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h3 class="settings-title">WhatsApp Integration</h3>
                        <p class="settings-description">Configure WhatsApp Business API credentials and notification templates.</p>
                        <a href="whatsapp/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="settings-title">Email Configuration</h3>
                        <p class="settings-description">Set up email server settings, templates, and notification preferences.</p>
                        <a href="email/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Settings Section -->
        <div class="settings-section">
            <h2 class="section-title">System Configuration</h2>
            <div class="settings-grid">
                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="settings-title">Security Settings</h3>
                        <p class="settings-description">Configure password policies, session timeouts, and security parameters.</p>
                        <a href="security/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="settings-title">Notification Settings</h3>
                        <p class="settings-description">Configure system-wide notification preferences and alert thresholds.</p>
                        <a href="notifications/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-content">
                        <div class="settings-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <h3 class="settings-title">Backup & Maintenance</h3>
                        <p class="settings-description">Configure database backup settings and system maintenance schedules.</p>
                        <a href="backup/" class="settings-action">
                            <i class="fas fa-cog"></i>
                            Manage Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add fade-out effect for alerts
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>

</html>