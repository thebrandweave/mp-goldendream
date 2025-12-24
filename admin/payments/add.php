<?php
session_start();
require_once("../../config/config.php");

// Check if user is logged in as admin

$menuPath = "../";
$currentPage = "payments";
$database = new Database();
$conn = $database->getConnection();

// Get all active customers
$stmt = $conn->prepare("
    SELECT DISTINCT c.CustomerID, c.Name, c.CustomerUniqueID 
    FROM Customers c 
    WHERE c.Status = 'Active' 
    ORDER BY c.Name ASC
");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active schemes
$stmt = $conn->prepare("
    SELECT s.SchemeID, s.SchemeName, s.MonthlyPayment 
    FROM Schemes s 
    WHERE s.Status = 'Active' 
    ORDER BY s.SchemeName ASC
");
$stmt->execute();
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active installments
$stmt = $conn->prepare("
    SELECT i.InstallmentID, i.SchemeID, i.InstallmentNumber, i.Amount, i.DrawDate
    FROM Installments i
    JOIN Schemes s ON i.SchemeID = s.SchemeID
    WHERE i.Status = 'Active' AND s.Status = 'Active'
    ORDER BY i.InstallmentNumber ASC
");
$stmt->execute();
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get submitted values for repopulating the form
$submittedCustomerId = $_POST['customer_id'] ?? '';
$submittedSchemeId = $_POST['scheme_id'] ?? '';
$submittedInstallmentId = $_POST['installment_id'] ?? '';
$submittedAmount = $_POST['amount'] ?? '';
$submittedPayerRemark = $_POST['payer_remark'] ?? '';
$submittedIsCashPayment = isset($_POST['is_cash_payment']);
$submittedIsExtraPayment = isset($_POST['is_extra_payment']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerId = $_POST['customer_id'];
        $amount = $_POST['amount'];
        $isCashPayment = isset($_POST['is_cash_payment']) ? true : false;
        $isExtraPayment = isset($_POST['is_extra_payment']) ? true : false;
        $payerRemark = trim($_POST['payer_remark'] ?? '');
        $screenshotUrl = null;

        // Validate required fields
        if (empty($customerId) || empty($amount)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Only validate scheme and installment if not an extra payment
        if (!$isExtraPayment) {
            $schemeId = $_POST['scheme_id'];
            $installmentId = $_POST['installment_id'];

            if (empty($schemeId) || empty($installmentId)) {
                throw new Exception("Please select scheme and installment.");
            }

            // Check if payment already exists for this customer, scheme and installment
            $stmt = $conn->prepare("
                SELECT p.*, s.SchemeName, i.InstallmentNumber 
                FROM Payments p
                JOIN Schemes s ON p.SchemeID = s.SchemeID
                JOIN Installments i ON p.InstallmentID = i.InstallmentID
                WHERE p.CustomerID = ? AND p.SchemeID = ? AND p.InstallmentID = ?
            ");
            $stmt->execute([$customerId, $schemeId, $installmentId]);
            $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingPayment) {
                throw new Exception("A payment already exists for this scheme's installment. Status: " . $existingPayment['Status'] .
                    " (Scheme: " . $existingPayment['SchemeName'] .
                    ", Installment: " . $existingPayment['InstallmentNumber'] . ")");
            }
        }

        // Handle screenshot upload
        if (!$isCashPayment) {
            if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['screenshot']['type'], $allowedTypes)) {
                    throw new Exception("Invalid file type. Only JPG and PNG files are allowed.");
                }

                if ($_FILES['screenshot']['size'] > $maxSize) {
                    throw new Exception("File size exceeds 5MB limit.");
                }

                $uploadDir = '../../customer/uploads/payments/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = uniqid() . '_' . basename($_FILES['screenshot']['name']);
                $targetPath = "../../customer/uploads/payments/" . $fileName;

                if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $targetPath)) {
                    //  $screenshotUrl = $targetPath;
                } else {
                    throw new Exception("Failed to upload file.");
                }
            } else {
                throw new Exception("Please upload a payment screenshot or mark as cash payment.");
            }
        } else {
            $fileName = 'cashPayment.png';
        }

        // Insert payment record
        if ($isExtraPayment) {
            $stmt = $conn->prepare("
                INSERT INTO Payments (CustomerID, Amount, ScreenshotURL, Status, PayerRemark)
                VALUES (?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $customerId,
                $amount,
                "uploads/payments/" . $fileName,
                $payerRemark
            ]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO Payments (CustomerID, SchemeID, InstallmentID, Amount, ScreenshotURL, Status, PayerRemark)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $customerId,
                $schemeId,
                $installmentId,
                $amount,
                "uploads/payments/" . $fileName,
                $payerRemark
            ]);
        }

        // Log the payment creation
        $stmt = $conn->prepare("
            INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress)
            VALUES (?, 'Admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Added " . ($isExtraPayment ? "extra" : "new") . " payment for customer ID: $customerId" .
                (!$isExtraPayment ? ", scheme ID: $schemeId" : "") .
                ", amount: $amount" . ($isCashPayment ? " (Cash Payment)" : "") . " - Pending verification",
            $_SERVER['REMOTE_ADDR']
        ]);

        $_SESSION['success_message'] = ($isExtraPayment ? "Extra payment" : "Payment") . " added successfully and is pending verification.";
         header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
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
    <title>Add New Payment - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .hidden {
            display: none !important;
            opacity: 0;
            height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .fade-transition {
            transition: opacity 0.3s ease-in-out;
        }

        .checkbox-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 0;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .btn-submit {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-submit:hover {
            background: #2980b9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        /* Select2 Custom Styles */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3498db;
        }

        .select2-dropdown {
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subscription-info {
            background-color: #e8f4f8;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 13px;
            color: #2c3e50;
            display: none;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="form-container">
            <h2>Add New Payment</h2>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="customer_id">Select Customer</label>
                    <select name="customer_id" id="customer_id" class="form-control" required>
                        <option value="">Search customer by name or ID...</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['CustomerID']; ?>" <?php echo ($submittedCustomerId == $customer['CustomerID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['Name'] . ' (' . $customer['CustomerUniqueID'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-container">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_cash_payment" id="is_cash_payment" <?php echo $submittedIsCashPayment ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Cash Payment
                        </label>
                    </div>

                    <div class="checkbox-container">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_extra_payment" id="is_extra_payment" <?php echo $submittedIsExtraPayment ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Extra Payment (Skip Scheme & Installment)
                        </label>
                    </div>
                </div>

                <div id="scheme-installment-section" class="fade-transition">
                    <div class="form-group">
                        <label for="scheme_id">Select Scheme</label>
                        <select name="scheme_id" id="scheme_id" class="form-control">
                            <option value="">Select Scheme</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?php echo $scheme['SchemeID']; ?>"
                                    data-amount="<?php echo $scheme['MonthlyPayment']; ?>"
                                    <?php echo ($submittedSchemeId == $scheme['SchemeID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scheme['SchemeName'] . ' (₹' . number_format($scheme['MonthlyPayment'], 2) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="subscription-info" class="subscription-info"></div>
                    </div>

                    <div class="form-group">
                        <label for="installment_id">Select Installment</label>
                        <select name="installment_id" id="installment_id" class="form-control">
                            <option value="">Select Installment</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" name="amount" id="amount" class="form-control" step="0.01" required value="<?php echo htmlspecialchars($submittedAmount); ?>">
                </div>

                <div class="form-group">
                    <label for="screenshot">Payment Screenshot</label>
                    <input type="file" name="screenshot" id="screenshot" class="form-control" accept="image/jpeg,image/png">
                    <small>Max file size: 5MB. Allowed formats: JPG, JPEG, PNG</small>
                </div>

                <div class="form-group">
                    <label for="payer_remark">Remarks</label>
                    <textarea name="payer_remark" id="payer_remark" class="form-control" rows="3" placeholder="Enter any remarks about the payment (optional)"><?php echo htmlspecialchars($submittedPayerRemark); ?></textarea>
                </div>

                <button type="submit" class="btn-submit">Add Payment</button>
            </form>
        </div>
    </div>

    <!-- Add jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cashPaymentCheckbox = document.getElementById('is_cash_payment');
            const screenshotInput = document.getElementById('screenshot');
            const screenshotLabel = screenshotInput.previousElementSibling;
            const screenshotSmall = screenshotInput.nextElementSibling;

            // Initialize Select2
            $('#customer_id').select2({
                placeholder: 'Search customer by name or ID...',
                allowClear: true
            });

            $('#scheme_id').select2({
                placeholder: 'Select Scheme',
                allowClear: true
            });

            $('#installment_id').select2({
                placeholder: 'Select Installment',
                allowClear: true
            });

            // Handle cash payment checkbox
            cashPaymentCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    screenshotInput.disabled = true;
                    screenshotInput.required = false;
                    screenshotLabel.style.opacity = '0.5';
                    screenshotSmall.style.opacity = '0.5';
                } else {
                    screenshotInput.disabled = false;
                    screenshotInput.required = true;
                    screenshotLabel.style.opacity = '1';
                    screenshotSmall.style.opacity = '1';
                }
            });

            // Handle scheme selection
            $('#scheme_id').on('change', function() {
                const schemeId = $(this).val();
                const customerId = $('#customer_id').val();
                const installmentSelect = $('#installment_id');
                const subscriptionInfo = $('#subscription-info');

                installmentSelect.empty().append('<option value="">Select Installment</option>');
                subscriptionInfo.hide();

                if (schemeId && customerId) {
                    // Check if customer has subscription for this scheme
                    fetch('get_customer_schemes.php?customer_id=' + customerId)
                        .then(response => response.json())
                        .then(data => {
                            const hasSubscription = data.some(scheme => scheme.SchemeID == schemeId);
                            if (!hasSubscription) {
                                subscriptionInfo.html('<i class="fas fa-info-circle"></i> This will create a new subscription for the customer.');
                                subscriptionInfo.show();
                            }
                        })
                        .catch(error => console.error('Error:', error));

                    // Get installments for the selected scheme
                    fetch('get_available_installments.php?scheme_id=' + schemeId)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Debug info:', data.debug); // Log debug information

                            if (data.error) {
                                console.error('Error:', data.error);
                                installmentSelect.html('<option value="">' + data.error + '</option>');
                                return;
                            }

                            const installments = data.installments;
                            if (!installments || installments.length === 0) {
                                installmentSelect.html('<option value="">No installments available for this scheme</option>');
                                return;
                            }

                            // Add installments to dropdown
                            installments.forEach(installment => {
                                const option = document.createElement('option');
                                option.value = installment.InstallmentID;
                                const drawDate = new Date(installment.DrawDate).toLocaleDateString('en-IN');
                                option.textContent = `Installment ${installment.InstallmentNumber} (₹${parseFloat(installment.Amount).toFixed(2)}) - Draw Date: ${drawDate}`;
                                option.dataset.amount = installment.Amount;
                                installmentSelect.append(option);
                            });

                            // Initialize Select2 for installment dropdown
                            installmentSelect.select2({
                                placeholder: "Select Installment",
                                allowClear: true,
                                width: '100%'
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            installmentSelect.html('<option value="">Error loading installments</option>');
                        });
                }
            });

            // Add event listener for installment selection
            $('#installment_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const amount = selectedOption.data('amount');
                if (amount) {
                    $('#amount').val(amount);
                }
            });

            // Handle form submission
            $('form').on('submit', function(e) {
                const isCashPayment = cashPaymentCheckbox.checked;
                const hasScreenshot = screenshotInput.files.length > 0;

                if (!isCashPayment && !hasScreenshot) {
                    e.preventDefault();
                    alert('Please upload a payment screenshot or mark as cash payment.');
                    return false;
                }
            });

            // Add function to check existing payment
            function checkExistingPayment() {
                const customerId = $('#customer_id').val();
                const schemeId = $('#scheme_id').val();
                const installmentId = $('#installment_id').val();

                if (customerId && schemeId && installmentId) {
                    fetch(`check_existing_payment.php?customer_id=${customerId}&scheme_id=${schemeId}&installment_id=${installmentId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                const info = $('#subscription-info');
                                info.html(`<i class="fas fa-exclamation-circle"></i> ${data.message}`);
                                info.css('background-color', '#fff3cd');
                                info.css('color', '#856404');
                                info.css('border', '1px solid #ffeeba');
                                info.show();
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }
            }

            // Add event listener for installment selection to check existing payment
            $('#installment_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const amount = selectedOption.data('amount');
                if (amount) {
                    $('#amount').val(amount);
                }
                checkExistingPayment();
            });

            // If there are submitted values, trigger the scheme change to load installments
            const submittedSchemeId = '<?php echo $submittedSchemeId; ?>';
            const submittedInstallmentId = '<?php echo $submittedInstallmentId; ?>';

            if (submittedSchemeId) {
                $('#scheme_id').trigger('change');

                // Wait for installments to load then set the selected value
                setTimeout(() => {
                    if (submittedInstallmentId) {
                        $('#installment_id').val(submittedInstallmentId).trigger('change');
                    }
                }, 500);
            }

            // Handle extra payment checkbox
            const extraPaymentCheckbox = document.getElementById('is_extra_payment');
            const schemeInstallmentSection = document.getElementById('scheme-installment-section');
            const schemeSelect = document.getElementById('scheme_id');
            const installmentSelect = document.getElementById('installment_id');
            const amountInput = document.getElementById('amount');

            function toggleSchemeInstallmentSection() {
                if (extraPaymentCheckbox.checked) {
                    schemeInstallmentSection.classList.add('hidden');
                    schemeSelect.required = false;
                    installmentSelect.required = false;
                    // Clear scheme and installment selections
                    schemeSelect.value = '';
                    installmentSelect.value = '';
                    // Enable direct amount input
                    amountInput.readOnly = false;
                    // Reset subscription info
                    document.getElementById('subscription-info').style.display = 'none';
                } else {
                    schemeInstallmentSection.classList.remove('hidden');
                    schemeSelect.required = true;
                    installmentSelect.required = true;
                    // If scheme is selected, update amount
                    if (schemeSelect.value) {
                        const selectedOption = schemeSelect.options[schemeSelect.selectedIndex];
                        amountInput.value = selectedOption.dataset.amount || '';
                    }
                }
            }

            extraPaymentCheckbox.addEventListener('change', toggleSchemeInstallmentSection);

            // Initialize the section visibility based on the checkbox state
            toggleSchemeInstallmentSection();

            // Modify scheme change handler to consider extra payment mode
            $('#scheme_id').on('change', function() {
                if (extraPaymentCheckbox.checked) {
                    return; // Don't update amount in extra payment mode
                }
                // ... rest of existing scheme change handler code ...
            });

            // Modify installment change handler
            $('#installment_id').on('change', function() {
                if (extraPaymentCheckbox.checked) {
                    return; // Don't update amount in extra payment mode
                }
                const selectedOption = $(this).find('option:selected');
                const amount = selectedOption.data('amount');
                if (amount) {
                    $('#amount').val(amount);
                }
                checkExistingPayment();
            });

            // Handle amount input based on mode
            amountInput.addEventListener('focus', function() {
                if (!extraPaymentCheckbox.checked && schemeSelect.value) {
                    this.readOnly = true;
                }
            });

            // Initialize amount input state
            amountInput.readOnly = !extraPaymentCheckbox.checked && schemeSelect.value;
        });
    </script>
</body>

</html>