<?php
require_once '../../../config/config.php';
require_once '../../config/session_check.php';
$c_path = "../../";
$current_page = "profile";
// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customerBank = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $bankName = trim($_POST['bank_name']);
        $accountName = trim($_POST['account_name']);
        $accountNumber = trim($_POST['account_number']);
        $ifscCode = trim($_POST['ifsc_code']);

        if (empty($bankName) || empty($accountName) || empty($accountNumber) || empty($ifscCode)) {
            throw new Exception('All fields are required');
        }

        // Validate account number (assuming 9-18 digits)
        if (!preg_match('/^[0-9]{9,18}$/', $accountNumber)) {
            throw new Exception('Invalid account number format');
        }

        // Validate IFSC code (11 characters, alphanumeric)
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifscCode))) {
            throw new Exception('Invalid IFSC code format');
        }

        // Update bank information
        $stmt = $db->prepare("
            UPDATE Customers 
            SET BankName = ?, BankAccountName = ?, BankAccountNumber = ?, IFSCCode = ?
            WHERE CustomerID = ?
        ");

        $stmt->execute([
            $bankName,
            $accountName,
            $accountNumber,
            strtoupper($ifscCode),
            $userData['customer_id']
        ]);

        $success_message = 'Bank details updated successfully';
        echo "<script>setTimeout(() => {
    window.location.href = './';
}, 1000);
</script>";


        // Refresh customer data
        $stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
        $stmt->execute([$userData['customer_id']]);
        $customerBank = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Bank Details - Golden Dream</title>
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

        .edit-bank-container {
            padding: 24px;
            margin-top: 70px;
        }

        .bank-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .bank-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .bank-icon {
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

        .bank-header h4 {
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
            .edit-bank-container {
                margin-left: 70px;
                padding: 16px;
            }

            .bank-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../../c_includes/sidebar.php'; ?>
    <?php include '../../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="edit-bank-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="bank-card">
                            <div class="bank-header">
                                <div class="bank-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <h4>Edit Bank Details</h4>
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
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name"
                                        value="<?php echo htmlspecialchars($customerBank['BankName']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="account_name" class="form-label">Account Holder Name</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name"
                                        value="<?php echo htmlspecialchars($customerBank['BankAccountName']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number"
                                        value="<?php echo htmlspecialchars($customerBank['BankAccountNumber']); ?>" required>
                                    <div class="form-text">Enter 9-18 digit account number</div>
                                </div>

                                <div class="form-group">
                                    <label for="ifsc_code" class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control" id="ifsc_code" name="ifsc_code"
                                        value="<?php echo htmlspecialchars($customerBank['IFSCCode']); ?>" required>
                                    <div class="form-text">Enter 11 character IFSC code</div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="../" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i> Save Changes
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
    <script>
        // Format account number input
        document.getElementById('account_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Format IFSC code input
        document.getElementById('ifsc_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
</body>

</html>