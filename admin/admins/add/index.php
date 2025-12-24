<?php
session_start();


// Check if the logged-in admin has permission to add admins
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'SuperAdmin') {
    $_SESSION['error_message'] = "You don't have permission to add new admins.";
    header("Location: ../../dashboard/index.php");
    exit();
}

$menuPath = "../../";
$currentPage = "admins";

// Database connection
require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Define variables and set to empty values
$name = $email = $role = $password = $confirmPassword = '';
$nameErr = $emailErr = $roleErr = $passwordErr = $confirmPasswordErr = '';

// Form submission handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formValid = true;

    // Validate name
    if (empty($_POST["name"])) {
        $nameErr = "Name is required";
        $formValid = false;
    } else {
        $name = trim($_POST["name"]);
        if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
            $nameErr = "Only letters and white space allowed";
            $formValid = false;
        }
    }

    // Validate email
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
        $formValid = false;
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
            $formValid = false;
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Admins WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $emailErr = "Email already in use";
                $formValid = false;
            }
        }
    }

    // Validate role
    if (empty($_POST["role"])) {
        $roleErr = "Role is required";
        $formValid = false;
    } else {
        $role = $_POST["role"];
        if (!in_array($role, ['SuperAdmin', 'Verifier'])) {
            $roleErr = "Invalid role selected";
            $formValid = false;
        }
    }

    // Validate password
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
        $formValid = false;
    } else {
        $password = $_POST["password"];
        if (strlen($password) < 8) {
            $passwordErr = "Password must be at least 8 characters long";
            $formValid = false;
        }
    }

    // Validate confirm password
    if (empty($_POST["confirm_password"])) {
        $confirmPasswordErr = "Please confirm your password";
        $formValid = false;
    } else {
        $confirmPassword = $_POST["confirm_password"];
        if ($password !== $confirmPassword) {
            $confirmPasswordErr = "Passwords do not match";
            $formValid = false;
        }
    }

    // If form is valid, add admin to database
    if ($formValid) {
        try {
            $conn->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO Admins (Name, Email, PasswordHash, Role, Status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->execute([$name, $email, $passwordHash, $role]);

            $action = "Added new admin: " . $name;
            $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();

            $_SESSION['success_message'] = "Admin added successfully.";
            header("Location: ../index.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = "Failed to add admin: " . $e->getMessage();
        }
    }
}

// Include header and sidebar
include("../../components/sidebar.php");
include("../../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        /* Add Admin Form Styles */
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
        .add-admin-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--ad_primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-select:focus {
            border-color: var(--ad_primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--ad_primary-color) !important;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--ad_primary-hover);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
            transform: translateY(-2px);
        }

        .error-text {
            color: var(--danger-color);
            font-size: 12px;
            margin-top: 5px;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            margin: 0;
            color: var(--text-dark);
            font-size: 24px;
            font-weight: 600;
        }

        .form-header p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 14px;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--text-dark);
        }

        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            transition: all 0.3s ease;
            background: var(--border-color);
        }

        .strength-weak {
            background: var(--danger-color);
            width: 33.3%;
        }

        .strength-medium {
            background: var(--warning-color);
            width: 66.6%;
        }

        .strength-strong {
            background: var(--ad_success-color);
            width: 100%;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: var(--text-light);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        @media (max-width: 768px) {
            .add-admin-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Admin</h1>
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admins
            </a>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="add-admin-form">
            <div class="form-header">
                <h2>Admin Information</h2>
                <p>Fill in the details to create a new admin account</p>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control"
                        value="<?php echo htmlspecialchars($name); ?>" required>
                    <?php if (!empty($nameErr)): ?>
                        <div class="error-text"><?php echo $nameErr; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (!empty($emailErr)): ?>
                        <div class="error-text"><?php echo $emailErr; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="">Select a role</option>
                        <option value="SuperAdmin" <?php if ($role === 'SuperAdmin') echo 'selected'; ?>>Super Admin</option>
                        <option value="Verifier" <?php if ($role === 'Verifier') echo 'selected'; ?>>Verifier</option>
                    </select>
                    <?php if (!empty($roleErr)): ?>
                        <div class="error-text"><?php echo $roleErr; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <i class="password-toggle fas fa-eye" onclick="togglePassword('password')"></i>
                    </div>
                    <div class="password-strength" id="password-strength"></div>
                    <div class="strength-text" id="strength-text"></div>
                    <?php if (!empty($passwordErr)): ?>
                        <div class="error-text"><?php echo $passwordErr; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <i class="password-toggle fas fa-eye" onclick="togglePassword('confirm_password')"></i>
                    </div>
                    <?php if (!empty($confirmPasswordErr)): ?>
                        <div class="error-text"><?php echo $confirmPasswordErr; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = passwordField.nextElementSibling;

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength meter
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength');
        const strengthText = document.getElementById('strength-text');

        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 0;

            // Calculate password strength
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/\d/)) strength += 1;
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;

            // Update strength meter
            strengthBar.className = 'password-strength';

            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
            } else if (strength < 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = 'var(--danger-color)';
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium strength password';
                strengthText.style.color = 'var(--warning-color)';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = 'var(--ad_success-color)';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>

</html>