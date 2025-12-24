<?php
require_once '../../../config/config.php';
require_once '../../config/session_check.php';
$c_path = "../../";
$current_page = "profile";

// Get user data and validate session
$userData = checkSession();

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All fields are required');
        }

        // Verify current password
        $stmt = $db->prepare("SELECT PasswordHash FROM Customers WHERE CustomerID = ?");
        $stmt->execute([$userData['customer_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $customer['PasswordHash'])) {
            throw new Exception('Current password is incorrect');
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            throw new Exception('New password must be at least 8 characters long');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match');
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE Customers SET PasswordHash = ? WHERE CustomerID = ?");
        $stmt->execute([$hashed_password, $userData['customer_id']]);

        $success_message = 'Password changed successfully';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Golden Dream</title>
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
        }

        .change-password-container {
            padding: 24px;
            margin-top: 70px;
        }

        .password-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .password-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .password-icon {
            width: 60px;
            height: 60px;
            background: rgba(47, 155, 127, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--accent-green);
            font-size: 24px;
        }

        .password-header h4 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 500;
            margin: 0;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            background: var(--dark-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: var(--dark-bg);
            border-color: var(--accent-green);
            color: var(--text-primary);
            box-shadow: none;
        }

        .form-text {
            color: var(--text-secondary);
            font-size: 12px;
            margin-top: 6px;
        }

        .btn-save {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: #248c6f;
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: transparent;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .alert {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .alert-success {
            border-color: var(--accent-green);
            background: rgba(47, 155, 127, 0.1);
        }

        .alert-danger {
            border-color: #FF4C51;
            background: rgba(255, 76, 81, 0.1);
        }

        @media (max-width: 768px) {
            .change-password-container {
                margin-left: 70px;
                padding: 16px;
            }

            .password-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../../c_includes/sidebar.php'; ?>
    <?php include '../../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="change-password-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="password-card">
                            <div class="password-header">
                                <div class="password-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <h4>Change Password</h4>
                            </div>

                            <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters long</div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="../" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>