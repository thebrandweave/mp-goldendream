<?php
session_start();
// Check if user is logged in, redirect if not


$menuPath = "../";
$currentPage = "mp_promoters";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No promoter ID provided.";
    header("Location: index.php");
    exit();
}

$promoterId = $_GET['id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get parent promoters for dropdown
try {
    $stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID, Commission FROM mp_promoters WHERE Status = 'Active' AND PromoterID != ?");
    $stmt->execute([$promoterId]);
    $parentPromoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving parent promoters: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Get promoter details
try {
    $query = "SELECT * FROM mp_promoters WHERE PromoterID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$promoterId]);

    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promoter) {
        $_SESSION['error_message'] = "Promoter not found.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving promoter details: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Get parent promoter details for display and commission validation
$parentPromoterName = "None (Top Level)";
$parentCommission = null;
$parentPromoterID = null;
if (!empty($promoter['ParentPromoterID'])) {
    try {
        // Simple query to get parent promoter by PromoterUniqueID
        $stmt = $conn->prepare("SELECT * FROM promoters WHERE PromoterUniqueID = ?");
        $stmt->execute([$promoter['ParentPromoterID']]);
        $parentPromoter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($parentPromoter) {
            $parentPromoterName = htmlspecialchars($parentPromoter['Name'] . ' (' . $parentPromoter['PromoterUniqueID'] . ')');
            $parentCommission = $parentPromoter['Commission'];
            $parentPromoterID = $parentPromoter['PromoterID'];
        } else {
            $parentPromoterName = "Unknown Parent (ID: " . $promoter['ParentPromoterID'] . ")";
        }
    } catch (PDOException $e) {
        $parentPromoterName = "Error fetching parent details";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Validate and sanitize input
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
        $parentPromoterID = $promoter['ParentPromoterID'];
        $status = $_POST['status'];

        // Only update commission if it's currently empty and parent has commission
        $commission = $promoter['Commission'];
        if (empty($commission) && !empty($_POST['commission']) && !empty($parentCommission)) {
            $commission = trim($_POST['commission']);

            // Validate commission is less than parent's commission
            if (intval($commission) > intval($parentCommission)) {
                throw new Exception("Commission must be less than or equal to parent promoter's commission ({$parentCommission})");
            }
        }

        // Handle password change if provided
        $passwordUpdate = "";
        $params = [];

        if (!empty($_POST['new_password'])) {
            $newPassword = trim($_POST['new_password']);

            // Validate password
            if (strlen($newPassword) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }

            // Hash the new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordUpdate = ", PasswordHash = ?";
            $params[] = $passwordHash;
        }

        // Bank details
        $bankName = !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null;
        $bankAccountName = !empty($_POST['bank_account_name']) ? trim($_POST['bank_account_name']) : null;
        $bankAccountNumber = !empty($_POST['bank_account_number']) ? trim($_POST['bank_account_number']) : null;
        $ifscCode = !empty($_POST['ifsc_code']) ? trim($_POST['ifsc_code']) : null;

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required";
        }

        if (empty($contact)) {
            $errors[] = "Contact number is required";
        } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
            $errors[] = "Contact number should be 10 digits";
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Check bank details consistency
        if ((!empty($bankName) || !empty($bankAccountName) || !empty($bankAccountNumber) || !empty($ifscCode)) &&
            (empty($bankName) || empty($bankAccountName) || empty($bankAccountNumber) || empty($ifscCode))
        ) {
            $errors[] = "All bank details are required if any bank detail is provided";
        }

        if (!empty($errors)) {
            $_SESSION['error_message'] = implode("<br>", $errors);
            $conn->rollBack();
        } else {
            // Update promoter
            $query = "UPDATE mp_promoters SET 
                      Name = ?, 
                      Contact = ?, 
                      Email = ?, 
                      Address = ?, 
                      ParentPromoterID = ?, 
                      Status = ?, 
                      Commission = ?,
                      BankName = ?, 
                      BankAccountName = ?, 
                      BankAccountNumber = ?, 
                      IFSCCode = ?,
                      UpdatedAt = NOW()" . $passwordUpdate . " 
                      WHERE PromoterID = ?";

            $stmt = $conn->prepare($query);

            // Build parameters array
            $params = array_merge([
                $name,
                $contact,
                $email,
                $address,
                $parentPromoterID,
                $status,
                $commission,
                $bankName,
                $bankAccountName,
                $bankAccountNumber,
                $ifscCode,
                $promoterId
            ], $params);

            $stmt->execute($params);

            // Log the activity
            $adminId = $_SESSION['admin_id'];
            $action = "Updated mp_promoters details for " . $name . " (ID: " . $promoter['PromoterUniqueID'] . ")";
            $ipAddress = $_SERVER['REMOTE_ADDR'];

            $logQuery = "INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, ?, ?, ?)";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->execute([$adminId, 'Admin', $action, $ipAddress]);

            $conn->commit();
            $_SESSION['success_message'] = "Promoter updated successfully.";
            header("Location: view.php?id=" . $promoterId);
            exit();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error updating promoter: " . $e->getMessage();
    }
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Promoter - <?php echo htmlspecialchars($promoter['Name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Edit Promoter Page Styles */
        :root {
            --pr_primary: #3a7bd5;
            --pr_primary-hover: #2c60a9;
            --pr_secondary: #00d2ff;
            --pr_success: #2ecc71;
            --pr_success-hover: #27ae60;
            --pr_warning: #f39c12;
            --pr_warning-hover: #d35400;
            --pr_danger: #e74c3c;
            --pr_danger-hover: #c0392b;
            --pr_info: #3498db;
            --pr_info-hover: #2980b9;
            --pr_text-dark: #2c3e50;
            --pr_text-medium: #34495e;
            --pr_text-light: #7f8c8d;
            --pr_bg-light: #f8f9fa;
            --pr_border-color: #e0e0e0;
            --pr_shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --pr_shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --pr_transition: 0.25s;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--pr_text-medium);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color var(--pr_transition);
        }

        .back-link:hover {
            color: var(--pr_primary);
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--pr_text-dark);
            margin-bottom: 20px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--pr_shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .form-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--pr_border-color);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--pr_text-dark);
        }

        .form-card-body {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--pr_text-medium);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--pr_text-dark);
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid var(--pr_border-color);
            border-radius: 8px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--pr_primary);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--pr_text-light);
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--pr_transition);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--pr_primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--pr_primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(58, 123, 213, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--pr_text-dark);
            margin: 20px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--pr_border-color);
        }

        .promoter-unique-id {
            font-size: 14px;
            background-color: var(--pr_bg-light);
            padding: 10px;
            border-radius: 6px;
            color: var(--pr_text-medium);
            display: inline-block;
            margin-bottom: 20px;
        }

        .required-indicator {
            color: var(--pr_danger);
            margin-left: 3px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <a href="view.php?id=<?php echo $promoterId; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Promoter Details
        </a>

        <h1 class="page-title">Edit Promoter</h1>

        <div class="promoter-unique-id">
            <i class="fas fa-id-card"></i> Promoter ID: <?php echo $promoter['PromoterUniqueID']; ?>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="background-color: rgba(231, 76, 60, 0.1); color: var(--pr_danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(231, 76, 60, 0.2);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message'];
                                                            unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <form action="edit.php?id=<?php echo $promoterId; ?>" method="post">
            <div class="form-card">
                <div class="form-card-header">
                    <h3>Basic Information</h3>
                </div>
                <div class="form-card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name<span class="required-indicator">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($promoter['Name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact" class="form-label">Contact Number<span class="required-indicator">*</span></label>
                            <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($promoter['Contact']); ?>" required>
                            <small class="form-text">10-digit mobile number without country code</small>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($promoter['Email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status<span class="required-indicator">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="Active" <?php echo $promoter['Status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $promoter['Status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="parent_promoter_id" class="form-label">Parent Promoter</label>
                            <input type="text" class="form-control" id="parent_promoter_id" name="parent_promoter_id"
                                value="<?php echo $parentPromoterName; ?>" disabled>
                            <small class="form-text">Parent promoter cannot be changed after creation</small>
                            <!-- Hidden input to preserve the value when form is submitted -->
                            <input type="hidden" name="parent_promoter_id" value="<?php echo $promoter['ParentPromoterID']; ?>">
                        </div>

                        <div class="form-group">
                            <label for="commission" class="form-label">Commission</label>
                            <?php if (empty($parentCommission)): ?>
                                <div class="alert alert-warning" style="background-color: rgba(243, 156, 18, 0.1); color: #f39c12; padding: 10px; border-radius: 8px; margin-bottom: 10px; border: 1px solid rgba(243, 156, 18, 0.2);">
                                    <i class="fas fa-exclamation-triangle"></i> Parent promoter does not have a commission set. Please set the parent promoter's commission first.
                                </div>
                                <?php if (!empty($parentPromoterID)): ?>
                                    <div style="margin-bottom: 15px;">
                                        <a href="edit.php?id=<?php echo $parentPromoterID; ?>" class="btn btn-warning" style="background-color: #f39c12; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; display: inline-block;">
                                            <i class="fas fa-cog"></i> Set Parent Commission Now
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="number" class="form-control" id="commission" name="commission" min="0"
                                    value="<?php echo htmlspecialchars($promoter['Commission'] ?? ''); ?>" readonly>
                            <?php else: ?>
                                <input type="number" class="form-control" id="commission" name="commission" min="0"
                                    value="<?php echo htmlspecialchars($promoter['Commission'] ?? ''); ?>"
                                    <?php echo !empty($promoter['Commission']) ? 'readonly' : ''; ?>>
                                <small class="form-text">
                                    <?php if (!empty($promoter['Commission'])): ?>
                                        Commission cannot be changed after it has been set
                                    <?php else: ?>
                                        Set the commission value (must be less than parent promoter's commission: <?php echo $parentCommission; ?>)
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">Change Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                            <small class="form-text">Leave blank to keep current password. Minimum 6 characters.</small>
                        </div>

                        <div class="form-group full-width">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($promoter['Address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <h3>Bank Details</h3>
                </div>
                <div class="form-card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bank_name" class="form-label">Bank Name</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($promoter['BankName'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="bank_account_name" class="form-label">Account Holder Name</label>
                            <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" value="<?php echo htmlspecialchars($promoter['BankAccountName'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="bank_account_number" class="form-label">Account Number</label>
                            <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($promoter['BankAccountNumber'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="ifsc_code" class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" id="ifsc_code" name="ifsc_code" value="<?php echo htmlspecialchars($promoter['IFSCCode'] ?? ''); ?>">
                        </div>
                    </div>

                    <small class="form-text" style="display: block; margin-top: 10px;">
                        <i class="fas fa-info-circle"></i> All bank details are required if any bank detail is provided.
                    </small>
                </div>
            </div>

            <div class="btn-group">
                <a href="view.php?id=<?php echo $promoterId; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Bank details validation
            const bankFields = [
                document.getElementById('bank_name'),
                document.getElementById('bank_account_name'),
                document.getElementById('bank_account_number'),
                document.getElementById('ifsc_code')
            ];

            bankFields.forEach(field => {
                field.addEventListener('input', function() {
                    const hasValue = bankFields.some(f => f.value.trim() !== '');

                    if (hasValue) {
                        bankFields.forEach(f => {
                            f.required = true;
                            f.classList.add('required-field');

                            // Add asterisk to labels if not already present
                            const label = f.previousElementSibling;
                            if (!label.querySelector('.required-indicator')) {
                                const indicator = document.createElement('span');
                                indicator.className = 'required-indicator';
                                indicator.textContent = '*';
                                label.appendChild(indicator);
                            }
                        });
                    } else {
                        bankFields.forEach(f => {
                            f.required = false;
                            f.classList.remove('required-field');

                            // Remove asterisks
                            const label = f.previousElementSibling;
                            const indicator = label.querySelector('.required-indicator');
                            if (indicator) {
                                label.removeChild(indicator);
                            }
                        });
                    }
                });
            });

            // Trigger the validation on page load
            if (bankFields.some(f => f.value.trim() !== '')) {
                bankFields.forEach(f => {
                    f.required = true;
                    f.classList.add('required-field');

                    // Add asterisk to labels if not already present
                    const label = f.previousElementSibling;
                    if (!label.querySelector('.required-indicator')) {
                        const indicator = document.createElement('span');
                        indicator.className = 'required-indicator';
                        indicator.textContent = '*';
                        label.appendChild(indicator);
                    }
                });
            }

            // Phone number validation
            const contactInput = document.getElementById('contact');
            contactInput.addEventListener('input', function() {
                const phoneNumber = this.value.replace(/\D/g, '');
                this.value = phoneNumber;

                if (phoneNumber.length > 10) {
                    this.value = phoneNumber.substring(0, 10);
                }
            });

            // Commission validation - only if commission is empty and parent has commission
            const commissionInput = document.getElementById('commission');

            // Only add validation if commission is not readonly (meaning it's empty) and parent has commission
            if (!commissionInput.readOnly && <?php echo !empty($parentCommission) ? 'true' : 'false'; ?>) {
                const parentCommission = <?php echo !empty($parentCommission) ? $parentCommission : 0; ?>;

                // Set up validation for commission
                commissionInput.addEventListener('input', function() {
                    // Ensure commission is not greater than parent's commission
                    if (parseInt(this.value) > parentCommission) {
                        this.value = parentCommission;
                        alert("Commission must be less than or equal to parent promoter's commission (" + parentCommission + ")");
                    }
                });

                // Set max attribute
                commissionInput.setAttribute('max', parentCommission);
            }
        });
    </script>
</body>

</html>