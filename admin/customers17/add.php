<?php
session_start();


$menuPath = "../";
$currentPage = "mp_customers";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get all active promoters for dropdown
$stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID FROM mp_promoters WHERE Status = 'Active' ORDER BY Name");
$stmt->execute();
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $promoterId = $_POST['promoter_id'] ?? '';
    $referredBy = trim($_POST['referred_by'] ?? '');

    // Basic validation
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }

    if (empty($contact)) {
        $errors['contact'] = 'Contact number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
        $errors['contact'] = 'Invalid contact number format';
    }

    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        // Check if email already exists
        // $stmt = $conn->prepare("SELECT CustomerID FROM Customers WHERE Email = ?");
        // $stmt->execute([$email]);
        // if ($stmt->rowCount() > 0) {
        //     $errors['email'] = 'Email already registered';
        // }
    }

    // Check if contact already exists
    // $stmt = $conn->prepare("SELECT CustomerID FROM Customers WHERE Contact = ?");
    // $stmt->execute([$contact]);
    // if ($stmt->rowCount() > 0) {
    //     $errors['contact'] = 'Contact number already registered';
    // }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }

    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // Handle profile image upload
    $profileImageURL = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
            $errors['profile_image'] = 'Only JPG, JPEG & PNG files are allowed';
        } elseif ($_FILES['profile_image']['size'] > $maxSize) {
            $errors['profile_image'] = 'File size must be less than 5MB';
        } else {
            $uploadDir = '../../uploads/customers/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                $profileImageURL = 'uploads/customers/' . $fileName;
            } else {
                $errors['profile_image'] = 'Failed to upload image';
            }
        }
    }

    // If no errors, proceed with customer creation
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Generate unique customer ID
            // Get the latest CustomerID from the Customers table
            $stmt = $conn->query("SELECT MAX(CustomerID) as max_id FROM mp_customers");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextCustomerID = ($result['max_id'] ?? 0) + 1;

            // Format: GDB0[customerid]
            $customerUniqueID = 'GDB0' . $nextCustomerID;

            // Check if the unique ID already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM mp_customers WHERE CustomerUniqueID = ?");
            $stmt->execute([$customerUniqueID]);
            $count = $stmt->fetchColumn();

            // If exists, generate a new one (this should rarely happen)
            while ($count > 0) {
                $nextCustomerID++;
                $customerUniqueID = 'GDB0' . $nextCustomerID;
                $stmt->execute([$customerUniqueID]);
                $count = $stmt->fetchColumn();
            }

            // Insert customer
            $stmt = $conn->prepare("
                INSERT INTO mp_customers (
                    CustomerUniqueID, Name, Contact, Email, PasswordHash, 
                    Address, ProfileImageURL, PromoterID, ReferredBy, Status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active'
                )
            ");

            $stmt->execute([
                $customerUniqueID,
                $name,
                $contact,
                $email ?: null,
                password_hash($password, PASSWORD_DEFAULT),
                $address,
                $profileImageURL,
                $promoterId ?: null,
                $referredBy ?: null
            ]);

            // Log the activity
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                VALUES (?, 'Admin', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Added new customer: $customerUniqueID",
                $_SERVER['REMOTE_ADDR']
            ]);

            $conn->commit();
            $success = true;
            $_SESSION['success_message'] = "Customer added successfully. Customer ID: $customerUniqueID";
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors['general'] = "Failed to add customer: " . $e->getMessage();
        }
    }
}

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #3a7bd5;
            outline: none;
        }

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-cancel {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #e9ecef;
        }

        .password-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-weak {
            color: #e74c3c;
        }

        .strength-medium {
            color: #f39c12;
        }

        .strength-strong {
            color: #2ecc71;
        }

        .image-preview {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 6px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Customer</h1>
        </div>

        <div class="content-card">
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" class="form-container">
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="error-message"><?php echo $errors['name']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact Number *</label>
                        <input type="tel" id="contact" name="contact" class="form-control" value="<?php echo htmlspecialchars($_POST['contact'] ?? ''); ?>" required>
                        <?php if (isset($errors['contact'])): ?>
                            <div class="error-message"><?php echo $errors['contact']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="error-message"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="promoter_id">Promoter</label>
                        <select name="promoter_id" id="promoter_id" class="form-control">
                            <option value="">Select Promoter</option>
                            <?php foreach ($promoters as $promoter): ?>
                                <option value="<?php echo $promoter['PromoterID']; ?>" <?php echo (isset($_POST['promoter_id']) && $_POST['promoter_id'] == $promoter['PromoterID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($promoter['Name']); ?> (<?php echo $promoter['PromoterUniqueID']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="referred_by">Referral Code</label>
                        <input type="text" id="referred_by" name="referred_by" class="form-control" value="<?php echo htmlspecialchars($_POST['referred_by'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="profile_image">Profile Image</label>
                        <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                        <img id="image_preview" class="image-preview">
                        <?php if (isset($errors['profile_image'])): ?>
                            <div class="error-message"><?php echo $errors['profile_image']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="password-group">
                            <input type="password" id="password" name="password" class="form-control" required>
                            <i class="fas fa-eye password-toggle"></i>
                        </div>
                        <div class="password-strength"></div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="error-message"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="password-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <i class="fas fa-eye password-toggle"></i>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="error-message"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Add Customer
                        </button>
                        <a href="index.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type');
                input.setAttribute('type', type === 'password' ? 'text' : 'password');
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Password strength meter
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.querySelector('.password-strength');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            switch (strength) {
                case 0:
                    message = '';
                    break;
                case 1:
                    message = '<span class="strength-weak">Weak password</span>';
                    break;
                case 2:
                    message = '<span class="strength-medium">Medium password</span>';
                    break;
                case 3:
                case 4:
                    message = '<span class="strength-strong">Strong password</span>';
                    break;
            }

            strengthDiv.innerHTML = message;
        });

        // Image preview
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const preview = document.getElementById('image_preview');
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            return true;
        });
    </script>
</body>

</html>