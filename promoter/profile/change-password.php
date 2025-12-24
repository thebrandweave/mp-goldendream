<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "profile";

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

// Handle password change
if (isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All fields are required";
        $messageType = "error";
        $showNotification = true;
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirm password do not match";
        $messageType = "error";
        $showNotification = true;
    } elseif (strlen($newPassword) < 8) {
        $message = "New password must be at least 8 characters long";
        $messageType = "error";
        $showNotification = true;
    } else {
        try {
            // Verify old password
            $stmt = $conn->prepare("SELECT PasswordHash FROM Promoters WHERE PromoterID = ?");
            $stmt->execute([$_SESSION['promoter_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($oldPassword, $result['PasswordHash'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE Promoters SET PasswordHash = ? WHERE PromoterID = ?");
                $stmt->execute([$hashedPassword, $_SESSION['promoter_id']]);
                
                $message = "Password changed successfully";
                $messageType = "success";
                $showNotification = true;
            } else {
                $message = "Current password is incorrect";
                $messageType = "error";
                $showNotification = true;
            }
        } catch (PDOException $e) {
            $message = "Error changing password: " . $e->getMessage();
            $messageType = "error";
            $showNotification = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Golden Dreams</title>
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

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
        }

        .password-field {
            position: relative;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
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

        .password-requirements {
            margin-top: 30px;
            padding: 20px;
            background: var(--bg-light);
            border-radius: 10px;
        }

        .password-requirements h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .password-requirements ul {
            margin-left: 20px;
        }

        .password-requirements li {
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--text-secondary);
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
                            echo '../../' . htmlspecialchars($promoter['ProfileImageURL']);
                        } else {
                            echo '../../uploads/profile/image.png';
                        }
                    ?>" alt="Profile Image" class="profile-image">
                </div>
                <h2 class="profile-name"><?php echo htmlspecialchars($promoter['Name']); ?></h2>
                <p class="profile-id">ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></p>
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
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="section-info">
                        <h2>Change Password</h2>
                        <p>Update your account password</p>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="old_password">Current Password</label>
                        <div class="password-field">
                            <input type="password" id="old_password" name="old_password" class="form-control" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('old_password')"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-field">
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Update Password
                    </button>
                </form>

                <div class="password-requirements">
                    <h3>Password Requirements</h3>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Include a mix of uppercase and lowercase letters</li>
                        <li>Include at least one number</li>
                        <li>Include at least one special character (e.g., !, @, #, $)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

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