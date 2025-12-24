<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "profile";
// Get user data and validate session
$userData = checkSession();

// Get additional customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1A1D21;
            --card-bg: #222529;
            --accent-green: #2F9B7F;
            --text-primary: rgba(255, 255, 255, 0.9);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --card-hover: #2A2D31;
            --border-color: rgba(255, 255, 255, 0.05);
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .profile-container {
            padding: 24px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .profile-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .profile-avatar-wrapper {
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .profile-info {
            flex-grow: 1;
            text-align: left;
        }

        .profile-info h2 {
            color: #fff;
            font-size: clamp(24px, 5vw, 28px);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .profile-info p {
            color: rgba(255, 255, 255, 0.9);
            font-size: clamp(14px, 3vw, 16px);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-status {
            margin-top: 12px;
            display: inline-block;
        }

        .profile-actions {
            margin-left: auto;
            flex-shrink: 0;
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
        }

        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .profile-section h4 {
            color: var(--text-primary);
            font-size: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-section h4 i {
            color: var(--accent-green);
        }

        .info-label {
            color: var(--text-secondary);
            font-size: clamp(11px, 2.5vw, 12px);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .info-value {
            color: var(--text-primary);
            font-size: clamp(13px, 3vw, 14px);
            font-weight: 500;
            margin-bottom: 12px;
        }

        .btn-edit {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: #248c6f;
            transform: translateY(-1px);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(47, 155, 127, 0.1);
            color: #2F9B7F;
        }

        .status-inactive {
            background: rgba(255, 76, 81, 0.1);
            color: #FF4C51;
        }

        .status-suspended {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .btn-outline-primary {
            border: 1px solid var(--accent-green);
            color: var(--accent-green);
            background: transparent;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--accent-green);
            color: white;
        }

        .row {
            margin: 0 -8px;
        }

        .col-md-6 {
            padding: 0 8px;
        }

        .mb-3 {
            margin-bottom: 12px !important;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .profile-container {
                padding: 20px;
            }
            
            .profile-header {
                padding: 30px;
            }

            .profile-card {
                padding: 18px;
                margin-bottom: 14px;
            }
        }

        @media (max-width: 992px) {
            .profile-container {
                margin-left: 0;
                padding: 16px;
            }

            .profile-header {
                padding: 25px;
            }

            .profile-card {
                padding: 16px;
                margin-bottom: 12px;
            }

            .profile-section h4 {
                font-size: 14px;
                margin-bottom: 12px;
                padding-bottom: 8px;
            }

            .col-md-4 .profile-card {
                margin-top: 16px;
            }
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 12px;
            }

            .profile-header {
                padding: 20px;
                margin-bottom: 16px;
            }

            .profile-header-content {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }

            .profile-info {
                text-align: center;
            }

            .profile-info p {
                justify-content: center;
            }

            .profile-actions {
                margin-left: 0;
                width: 100%;
                display: flex;
                justify-content: center;
            }

            .profile-avatar {
                width: 90px;
                height: 90px;
            }

            .profile-card {
                padding: 14px;
                margin-bottom: 10px;
            }

            .row {
                margin: 0;
            }

            .col-md-6 {
                padding: 0 8px;
            }

            .btn-edit {
                width: 100%;
                text-align: center;
            }

            .info-label {
                font-size: 11px;
            }

            .info-value {
                font-size: 13px;
                margin-bottom: 10px;
            }

            .mb-3 {
                margin-bottom: 10px !important;
            }
        }

        @media (max-width: 576px) {
            .profile-container {
                padding: 8px;
            }

            .profile-header {
                padding: 16px;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
            }

            .profile-card {
                padding: 12px;
                margin-bottom: 8px;
            }

            .profile-section h4 {
                font-size: 13px;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }

            .info-label {
                font-size: 10px;
            }

            .info-value {
                font-size: 12px;
                margin-bottom: 8px;
            }

            .mb-3 {
                margin-bottom: 8px !important;
            }

            .btn-outline-primary {
                padding: 6px 12px;
                font-size: 13px;
            }

            .status-badge {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        /* Fix for sidebar margin on mobile */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>


    <div class="main-content">
        <div class="profile-container">
            <div class="container">
                <div class="profile-header">
                    <div class="profile-header-content">
                        <div class="profile-avatar-wrapper">
                            <img src="<?php echo $customer['ProfileImageURL'] ?: '../../uploads/default-avatar.png'; ?>"
                                alt="Profile" class="profile-avatar">
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($customer['Name']); ?></h2>
                            <p>
                                <i class="fas fa-id-card"></i>
                                <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?>
                            </p>
                            <p>
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($customer['Contact']); ?>
                            </p>
                            <div class="profile-status">
                                <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                                    <i class="fas fa-circle me-1"></i>
                                    <?php echo htmlspecialchars($customer['Status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <a href="./editProfile/" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Personal Information -->
                        <div class="profile-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4><i class="fas fa-user"></i> Personal Information</h4>
                                <!-- <a href="edit_profile.php" class="btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a> -->
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Name']); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Contact']); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['Email'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Status</div>
                                    <div>
                                        <span class="status-badge status-<?php echo strtolower($customer['Status']); ?>">
                                            <?php echo htmlspecialchars($customer['Status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Information -->
                        <div class="profile-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4><i class="fas fa-university"></i> Bank Information</h4>
                                <a href="./editBank/" class="btn btn-edit">
                                    <i class="fas fa-edit"></i> Edit Bank Details
                                </a>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Bank Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankName'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankAccountName'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">Account Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['BankAccountNumber'] ?: 'Not provided'); ?></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="info-label">IFSC Code</div>
                                    <div class="info-value"><?php echo htmlspecialchars($customer['IFSCCode'] ?: 'Not provided'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Account Security -->
                        <div class="profile-card">
                            <h4><i class="fas fa-shield-alt"></i> Account Security</h4> <br>
                            <div class="row g-3 center">
                                <div class="col-md-8">
                                    <a href="./changePassword/" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-key me-2"></i> Change Password
                                    </a>
                                </div>
                                <!-- <div class="col-md-6">
                                    <a href="two_factor.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-lock me-2"></i> Two-Factor Authentication
                                    </a>
                                </div> -->
                            </div>
                        </div>

                        <!-- Account Activity -->
                        <div class="profile-card">
                            <h4><i class="fas fa-history"></i> Account Activity</h4>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="activity-item">
                                        <div class="info-label">Member Since</div>
                                        <div class="info-value">
                                            <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                            <?php echo date('M d, Y', strtotime($customer['CreatedAt'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="activity-item">
                                        <div class="info-label">Last Updated</div>
                                        <div class="info-value">
                                            <i class="fas fa-clock me-2 text-muted"></i>
                                            <?php echo date('M d, Y', strtotime($customer['UpdatedAt'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>