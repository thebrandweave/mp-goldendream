<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$menuPath = "";
$currentPage = "profile";

// Database connection
require_once("../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get admin details
$adminId = $_SESSION['admin_id'];
$query = "SELECT * FROM Admins WHERE AdminID = :adminId";
$stmt = $conn->prepare($query);
$stmt->bindParam(':adminId', $adminId);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate input
    if (empty($name) || empty($email)) {
        $message = "Name and email are required fields.";
        $messageType = "error";
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();

            // Update basic info
            $query = "UPDATE Admins SET Name = :name, Email = :email WHERE AdminID = :adminId";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':adminId', $adminId);
            $stmt->execute();

            // If password change is requested
            if (!empty($currentPassword)) {
                // Verify current password
                if (password_verify($currentPassword, $admin['PasswordHash'])) {
                    if ($newPassword === $confirmPassword) {
                        if (strlen($newPassword) >= 8) {
                            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                            $query = "UPDATE Admins SET PasswordHash = :passwordHash WHERE AdminID = :adminId";
                            $stmt = $conn->prepare($query);
                            $stmt->bindParam(':passwordHash', $newPasswordHash);
                            $stmt->bindParam(':adminId', $adminId);
                            $stmt->execute();
                        } else {
                            throw new Exception("New password must be at least 8 characters long.");
                        }
                    } else {
                        throw new Exception("New passwords do not match.");
                    }
                } else {
                    throw new Exception("Current password is incorrect.");
                }
            }

            $conn->commit();
            $message = "Profile updated successfully!";
            $messageType = "success";

            // Refresh admin data
            $query = "SELECT * FROM Admins WHERE AdminID = :adminId";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':adminId', $adminId);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $conn->rollBack();
            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

// Include header and sidebar
include("components/sidebar.php");
include("components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .profile-header {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 600;
        }

        .profile-info h2 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 24px;
        }

        .profile-info p {
            margin: 5px 0 0;
            color: #666;
        }

        .profile-form {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section h3 {
            margin: 0 0 20px;
            color: var(--secondary-color);
            font-size: 18px;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="profile-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="profile-avatar">
                    <?php
                    $initials = '';
                    $nameParts = explode(' ', $admin['Name']);
                    if (count($nameParts) >= 2) {
                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($admin['Name'], 0, 2));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($admin['Name']); ?></h2>
                    <p><?php echo htmlspecialchars($admin['Email']); ?></p>
                    <p>Role: <?php echo htmlspecialchars($admin['Role']); ?></p>
                </div>
            </div>

            <form class="profile-form" method="POST" action="">
                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($admin['Name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['Email']); ?>" required>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Change Password</h3>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Update Profile</button>
            </form>
        </div>
    </div>

    <script>
        // Add password validation
        document.querySelector('.profile-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;

            if (currentPassword || newPassword || confirmPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change it.');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return;
                }
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('New password must be at least 8 characters long.');
                    return;
                }
            }
        });
    </script>
</body>

</html>