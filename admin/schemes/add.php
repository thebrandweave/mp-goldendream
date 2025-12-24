<?php
session_start();


$menuPath = "../";
$currentPage = "schemes";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Validate scheme details
        $schemeName = trim($_POST['scheme_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $monthlyPayment = floatval($_POST['monthly_payment'] ?? 0);
        $totalPayments = intval($_POST['total_payments'] ?? 0);
        $schemeImageURL = null;

        // Handle scheme image upload
        if (isset($_FILES['scheme_image']) && $_FILES['scheme_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/schemes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . basename($_FILES['scheme_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['scheme_image']['tmp_name'], $targetPath)) {
                $schemeImageURL = 'uploads/schemes/' . $fileName;
            } else {
                $errors['scheme_image'] = 'Failed to upload image';
            }
        }

        // Basic validation
        if (empty($schemeName)) {
            $errors['scheme_name'] = 'Scheme name is required';
        } else {
            // Check if scheme name already exists
            $stmt = $conn->prepare("SELECT SchemeID FROM Schemes WHERE SchemeName = ?");
            $stmt->execute([$schemeName]);
            if ($stmt->rowCount() > 0) {
                $errors['scheme_name'] = 'Scheme name already exists';
            }
        }

        if ($monthlyPayment <= 0) {
            $errors['monthly_payment'] = 'Monthly payment must be greater than 0';
        }

        if ($totalPayments <= 0) {
            $errors['total_payments'] = 'Total payments must be greater than 0';
        }

        // Validate installments
        $installments = [];
        if (isset($_POST['installment'])) {
            foreach ($_POST['installment'] as $index => $installment) {
                $installmentName = trim($installment['name'] ?? '');
                $installmentNumber = intval($installment['number'] ?? 0);
                $amount = floatval($installment['amount'] ?? 0);
                $drawDate = trim($installment['draw_date'] ?? '');
                $benefits = trim($installment['benefits'] ?? '');
                $isRepayable = isset($installment['is_repayable']) ? 1 : 0;
                $repaymentPercentage = floatval($installment['repayment_percentage'] ?? 0);

                if (empty($installmentName)) {
                    $errors["installment_$index"] = 'Installment name is required';
                }

                if ($amount <= 0) {
                    $errors["installment_amount_$index"] = 'Amount must be greater than 0';
                }

                if (empty($drawDate)) {
                    $errors["installment_date_$index"] = 'Draw date is required';
                }

                // Store valid installment
                if (
                    !isset($errors["installment_$index"]) &&
                    !isset($errors["installment_amount_$index"]) &&
                    !isset($errors["installment_date_$index"])
                ) {
                    $installments[] = [
                        'name' => $installmentName,
                        'number' => $installmentNumber,
                        'amount' => $amount,
                        'draw_date' => $drawDate,
                        'benefits' => $benefits,
                        'image' => $_FILES['installment']['name'][$index] ?? null,
                        'is_repayable' => $isRepayable,
                        'repayment_percentage' => $repaymentPercentage
                    ];
                }
            }
        }

        if (empty($installments)) {
            $errors['installments'] = 'At least one installment is required';
        }

        if (empty($errors)) {
            // Insert scheme
            $stmt = $conn->prepare("
                INSERT INTO Schemes (SchemeName, Description, MonthlyPayment, TotalPayments, SchemeImageURL, Status) 
                VALUES (?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->execute([$schemeName, $description, $monthlyPayment, $totalPayments, $schemeImageURL]);
            $schemeId = $conn->lastInsertId();

            // Process installments
            $uploadDir = '../../uploads/schemes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $stmt = $conn->prepare("
                INSERT INTO Installments (
                    SchemeID, InstallmentName, InstallmentNumber, 
                    Amount, DrawDate, Benefits, ImageURL, Status,
                    IsReplayable, ReplaymentPercentage
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?)
            ");

            foreach ($installments as $index => $installment) {
                $imageURL = null;

                // Handle image upload
                if (
                    isset($_FILES['installment']['tmp_name'][$index]) &&
                    $_FILES['installment']['error'][$index] === UPLOAD_ERR_OK
                ) {
                    $fileName = uniqid() . '_' . basename($_FILES['installment']['name'][$index]);
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['installment']['tmp_name'][$index], $targetPath)) {
                        $imageURL = 'uploads/schemes/' . $fileName;
                    }
                }

                $stmt->execute([
                    $schemeId,
                    $installment['name'],
                    $installment['number'],
                    $installment['amount'],
                    $installment['draw_date'],
                    $installment['benefits'],
                    $imageURL,
                    $installment['is_repayable'],
                    $installment['repayment_percentage']
                ]);
            }

            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                VALUES (?, 'Admin', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Added new scheme: $schemeName with " . count($installments) . " installments",
                $_SERVER['REMOTE_ADDR']
            ]);

            $conn->commit();
            $_SESSION['success_message'] = "Scheme added successfully with " . count($installments) . " installments.";
            header("Location: index.php");
            exit();
        } else {
            $conn->rollBack();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors['general'] = "Failed to add scheme: " . $e->getMessage();
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
    <title>Add New Scheme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .scheme-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
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
            border-color: #3498db;
            outline: none;
        }

        .installment-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .installment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .installment-title {
            font-weight: 600;
            color: #2c3e50;
        }

        .remove-installment {
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s ease;
        }

        .remove-installment:hover {
            transform: scale(1.1);
        }

        .installment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .add-installment-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .add-installment-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
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

        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }

        .image-preview {
            max-width: 150px;
            margin-top: 10px;
            border-radius: 6px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Scheme</h1>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="form-container">
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
            <?php endif; ?>

            <div class="scheme-section">
                <div class="section-title">Scheme Details</div>

                <div class="form-group">
                    <label for="scheme_name">Scheme Name *</label>
                    <input type="text" id="scheme_name" name="scheme_name" class="form-control"
                        value="<?php echo htmlspecialchars($_POST['scheme_name'] ?? ''); ?>" required>
                    <?php if (isset($errors['scheme_name'])): ?>
                        <div class="error-message"><?php echo $errors['scheme_name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="scheme_image">Scheme Image</label>
                    <input type="file" id="scheme_image" name="scheme_image" class="form-control" accept="image/*" onchange="previewSchemeImage(this)">
                    <?php if (isset($errors['scheme_image'])): ?>
                        <div class="error-message"><?php echo $errors['scheme_image']; ?></div>
                    <?php endif; ?>
                    <div class="image-preview-container">
                        <img id="scheme-image-preview" class="image-preview" style="display: none;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="monthly_payment">Monthly Payment (₹) *</label>
                    <input type="number" id="monthly_payment" name="monthly_payment" class="form-control"
                        value="<?php echo htmlspecialchars($_POST['monthly_payment'] ?? ''); ?>" required step="0.01">
                    <?php if (isset($errors['monthly_payment'])): ?>
                        <div class="error-message"><?php echo $errors['monthly_payment']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="total_payments">Total Number of Payments *</label>
                    <input type="number" id="total_payments" name="total_payments" class="form-control"
                        value="<?php echo htmlspecialchars($_POST['total_payments'] ?? ''); ?>" required min="1">
                    <?php if (isset($errors['total_payments'])): ?>
                        <div class="error-message"><?php echo $errors['total_payments']; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="scheme-section">
                <div class="section-title">Installments</div>

                <div id="installments-container">
                    <!-- Installment template will be added here -->
                </div>

                <button type="button" class="add-installment-btn" onclick="addInstallment()">
                    <i class="fas fa-plus"></i> Add Installment
                </button>

                <?php if (isset($errors['installments'])): ?>
                    <div class="error-message"><?php echo $errors['installments']; ?></div>
                <?php endif; ?>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Scheme
                </button>
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        let installmentCount = 0;

        function addInstallment() {
            const container = document.getElementById('installments-container');
            const template = `
                <div class="installment-container">
                    <div class="installment-header">
                        <div class="installment-title">Installment #${installmentCount + 1}</div>
                        <i class="fas fa-times remove-installment" onclick="removeInstallment(this)"></i>
                    </div>
                    
                    <div class="installment-grid">
                        <div class="form-group">
                            <label>Installment Name *</label>
                            <input type="text" name="installment[${installmentCount}][name]" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Installment Number *</label>
                            <input type="number" name="installment[${installmentCount}][number]" class="form-control" 
                                   required min="1" value="${installmentCount + 1}">
                        </div>
                        
                        <div class="form-group">
                            <label>Amount (₹) *</label>
                            <input type="number" name="installment[${installmentCount}][amount]" class="form-control" 
                                   required step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label>Draw Date *</label>
                            <input type="date" name="installment[${installmentCount}][draw_date]" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Benefits</label>
                            <textarea name="installment[${installmentCount}][benefits]" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Image</label>
                            <input type="file" name="installment[${installmentCount}]" class="form-control" accept="image/*"
                                   onchange="previewImage(this)">
                            <img class="image-preview">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="installment[${installmentCount}][is_repayable]" class="is-repayable-checkbox">
                                Is Repayable
                            </label>
                        </div>
                        
                        <div class="form-group repayment-percentage-container" style="display: none;">
                            <label>Repayment Percentage (%) *</label>
                            <input type="number" name="installment[${installmentCount}][repayment_percentage]" class="form-control" 
                                   step="0.01" min="0" value="0">
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', template);

            // Add event listener to the new is-repayable checkbox
            const newContainer = container.lastElementChild;
            const isRepayableCheckbox = newContainer.querySelector('.is-repayable-checkbox');
            const repaymentPercentageContainer = newContainer.querySelector('.repayment-percentage-container');

            isRepayableCheckbox.addEventListener('change', function() {
                repaymentPercentageContainer.style.display = this.checked ? 'block' : 'none';
            });

            installmentCount++;
        }

        function removeInstallment(element) {
            element.closest('.installment-container').remove();
            updateInstallmentNumbers();
        }

        function updateInstallmentNumbers() {
            const containers = document.querySelectorAll('.installment-container');
            containers.forEach((container, index) => {
                container.querySelector('.installment-title').textContent = `Installment #${index + 1}`;
                container.querySelector('input[type="number"]').value = index + 1;
            });
        }

        function previewImage(input) {
            const preview = input.nextElementSibling;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewSchemeImage(input) {
            const preview = document.getElementById('scheme-image-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }

                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
        }

        // Add first installment by default
        document.addEventListener('DOMContentLoaded', function() {
            addInstallment();

            // Add event listener to the total payments input
            const totalPaymentsInput = document.getElementById('total_payments');
            totalPaymentsInput.addEventListener('change', function() {
                const totalPayments = parseInt(this.value) || 0;
                const currentInstallments = document.querySelectorAll('.installment-container').length;

                if (totalPayments > 0) {
                    // Add or remove installments to match the total payments
                    if (totalPayments > currentInstallments) {
                        // Add more installments
                        for (let i = currentInstallments; i < totalPayments; i++) {
                            addInstallment();
                        }
                    } else if (totalPayments < currentInstallments) {
                        // Remove excess installments
                        const containers = document.querySelectorAll('.installment-container');
                        for (let i = currentInstallments - 1; i >= totalPayments; i--) {
                            containers[i].remove();
                        }
                        updateInstallmentNumbers();
                    }
                }
            });
        });
    </script>
</body>

</html>