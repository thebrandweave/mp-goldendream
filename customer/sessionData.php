<?php
require_once 'config/config.php';
require_once 'config/session_check.php';

// Get user data and validate session
$userData = checkSession();

// Extract user data
$customerId = $userData['customer_id'];
$customerName = $userData['customer_name'];
$customerUniqueId = $userData['customer_unique_id'];
$token = $userData['token'];
$decoded = $userData['decoded'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Golden Dream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding-top: 60px;
            /* Add padding for topbar */
        }

        .dashboard-container {
            padding: 20px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .token-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            word-break: break-all;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
            color: white;
        }
    </style>
</head>

<body>
    <?php include 'c_includes/sidebar.php'; ?>
    <?php include 'c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="container">
                <div class="welcome-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>Welcome, <?php echo htmlspecialchars($customerName); ?>!</h2>
                            <p class="mb-0">Customer Dashboard</p>
                        </div>
                        <a href="logout.php" class="btn logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="info-card">
                            <h4><i class="fas fa-user-circle"></i> Profile Information</h4>
                            <hr>
                            <p><strong>Customer ID:</strong> <?php echo htmlspecialchars($customerId); ?></p>
                            <p><strong>Unique ID:</strong> <?php echo htmlspecialchars($customerUniqueId); ?></p>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($customerName); ?></p>
                            <p><strong>Account Type:</strong> Customer</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="info-card">
                            <h4><i class="fas fa-key"></i> JWT Token Information</h4>
                            <hr>
                            <p><strong>Token Status:</strong> <span class="text-success">Valid</span></p>
                            <p><strong>Issued At:</strong> <?php echo date('Y-m-d H:i:s', $decoded->iat); ?></p>
                            <p><strong>Expires At:</strong> <?php echo date('Y-m-d H:i:s', $decoded->exp); ?></p>
                            <div class="token-info">
                                <small class="text-muted">Token Preview:</small><br>
                                <?php echo substr($token, 0, 50) . '...'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="info-card">
                            <h4><i class="fas fa-clock"></i> Session Information</h4>
                            <hr>
                            <p><strong>Last Activity:</strong> <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
                            <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                            <p><strong>Session Status:</strong> <span class="text-success">Active</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>