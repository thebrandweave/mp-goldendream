<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if the logged-in admin has SuperAdmin privileges
if ($_SESSION['admin_role'] !== 'SuperAdmin') {
    $_SESSION['error_message'] = "You don't have permission to access payment settings.";
    header("Location: ../../dashboard/index.php");
    exit();
}

$menuPath = "../../";
$currentPage = "settings";

// Database connection
require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get current settings
try {
    $stmt = $conn->query("SELECT * FROM PaymentQR ORDER BY QRID DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no settings exist, create a default record
    if (!$settings) {
        $stmt = $conn->prepare("INSERT INTO PaymentQR (CustomerID, BankAccountName, BankName, RazorpayQRStatus) VALUES (NULL, '', '', 'Active')");
        $stmt->execute();
        $settings = [
            'QRID' => $conn->lastInsertId(),
            'UPIQRImageURL' => '',
            'BankAccountName' => '',
            'BankAccountNumber' => '',
            'IFSCCode' => '',
            'BankName' => '',
            'BankBranch' => '',
            'BankAddress' => '',
            'RazorpayKeyID' => '',
            'RazorpayKeySecret' => '',
            'RazorpayContactID' => '',
            'RazorpayFundAccountID' => '',
            'RazorpayQRID' => '',
            'RazorpayQRStatus' => 'Active'
        ];
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to fetch settings: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Handle file upload for UPI QR
        $upiQRURL = $settings['UPIQRImageURL']; // Keep existing QR URL by default
        if (isset($_FILES['upi_qr']) && $_FILES['upi_qr']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../../../uploads/qr/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Delete old QR image if exists
            if (!empty($settings['UPIQRImageURL'])) {
                $oldFile = "../../../" . $settings['UPIQRImageURL'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $fileName = uniqid() . '_' . basename($_FILES['upi_qr']['name']);
            $targetFile = $targetDir . $fileName;

            if (move_uploaded_file($_FILES['upi_qr']['tmp_name'], $targetFile)) {
                $upiQRURL = 'uploads/qr/' . $fileName;
            }
        }

        // Update existing record
        $stmt = $conn->prepare("
            UPDATE PaymentQR SET 
                UPIQRImageURL = :upiQR,
                BankAccountName = :bankName,
                BankAccountNumber = :accountNumber,
                IFSCCode = :ifscCode,
                BankName = :bankName,
                BankBranch = :bankBranch,
                BankAddress = :bankAddress,
                RazorpayKeyID = :razorpayKeyID,
                RazorpayKeySecret = :razorpayKeySecret,
                RazorpayContactID = :razorpayContactID,
                RazorpayFundAccountID = :razorpayFundAccountID,
                RazorpayQRID = :razorpayQRID
            WHERE QRID = :qrId
        ");

        $params = [
            ':qrId' => $settings['QRID'],
            ':upiQR' => $upiQRURL,
            ':bankName' => $_POST['bank_name'],
            ':accountNumber' => $_POST['account_number'],
            ':ifscCode' => $_POST['ifsc_code'],
            ':bankBranch' => $_POST['bank_branch'],
            ':bankAddress' => $_POST['bank_address'],
            ':razorpayKeyID' => $_POST['razorpay_key_id'],
            ':razorpayKeySecret' => $_POST['razorpay_key_secret'],
            ':razorpayContactID' => $_POST['razorpay_contact_id'],
            ':razorpayFundAccountID' => $_POST['razorpay_fund_account_id'],
            ':razorpayQRID' => $_POST['razorpay_qr_id']
        ];

        $stmt->execute($params);

        // Log the activity
        $action = "Updated payment QR settings";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        $conn->commit();
        $_SESSION['success_message'] = "Payment QR settings updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update settings: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
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
    <title>Payment QR Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
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
        .settings-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-medium);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--ad_primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--ad_primary-color);
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

        .current-qr {
            max-width: 200px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px;
        }

        .current-qr img {
            width: 100%;
            height: auto;
        }

        .help-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .settings-form {
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Payment QR Settings</h1>
            <a href="../" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
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

        <form class="settings-form" method="POST" enctype="multipart/form-data">
            <!-- UPI QR Section -->
            <div class="form-section">
                <h2 class="section-title">UPI QR Code</h2>
                <div class="form-group">
                    <label for="upi_qr">Upload UPI QR Code</label>
                    <input type="file" id="upi_qr" name="upi_qr" class="form-control" accept="image/*">
                    <?php if (!empty($settings['UPIQRImageURL'])): ?>
                        <div class="current-qr">
                            <p>Current QR Code:</p>
                            <img src="../<?php echo $menuPath . $settings['UPIQRImageURL']; ?>" alt="Current UPI QR">
                        </div>
                    <?php endif; ?>
                    <p class="help-text">Upload a clear QR code image in PNG or JPG format</p>
                </div>
            </div>

            <!-- Bank Account Section -->
            <div class="form-section">
                <h2 class="section-title">Bank Account Details</h2>
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name" class="form-control"
                        value="<?php echo htmlspecialchars($settings['BankName'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number" class="form-control"
                        value="<?php echo htmlspecialchars($settings['BankAccountNumber'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="ifsc_code">IFSC Code</label>
                    <input type="text" id="ifsc_code" name="ifsc_code" class="form-control"
                        value="<?php echo htmlspecialchars($settings['IFSCCode'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="bank_branch">Branch Name</label>
                    <input type="text" id="bank_branch" name="bank_branch" class="form-control"
                        value="<?php echo htmlspecialchars($settings['BankBranch'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="bank_address">Branch Address</label>
                    <textarea id="bank_address" name="bank_address" class="form-control" rows="3" required><?php echo htmlspecialchars($settings['BankAddress'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Razorpay Section -->
            <div class="form-section">
                <h2 class="section-title">Razorpay Configurations</h2>
                <div class="form-group">
                    <label for="razorpay_key_id">Key ID</label>
                    <input type="text" id="razorpay_key_id" name="razorpay_key_id" class="form-control"
                        value="<?php echo htmlspecialchars($settings['RazorpayKeyID'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="razorpay_key_secret">Key Secret</label>
                    <input type="password" id="razorpay_key_secret" name="razorpay_key_secret" class="form-control"
                        value="<?php echo htmlspecialchars($settings['RazorpayKeySecret'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="razorpay_contact_id">Contact ID</label>
                    <input type="text" id="razorpay_contact_id" name="razorpay_contact_id" class="form-control"
                        value="<?php echo htmlspecialchars($settings['RazorpayContactID'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="razorpay_fund_account_id">Fund Account ID</label>
                    <input type="text" id="razorpay_fund_account_id" name="razorpay_fund_account_id" class="form-control"
                        value="<?php echo htmlspecialchars($settings['RazorpayFundAccountID'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="razorpay_qr_id">QR ID</label>
                    <input type="text" id="razorpay_qr_id" name="razorpay_qr_id" class="form-control"
                        value="<?php echo htmlspecialchars($settings['RazorpayQRID'] ?? ''); ?>">
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='../'">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
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