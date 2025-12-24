<?php
session_start();


$menuPath = "../";
$currentPage = "schemes";

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success = false;

// Get scheme ID from URL
$schemeId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get existing scheme data
try {
    $stmt = $conn->prepare("SELECT * FROM Schemes WHERE SchemeID = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        $_SESSION['error_message'] = "Scheme not found.";
        header("Location: index.php");
        exit();
    }

    // Get existing installments
    $stmt = $conn->prepare("SELECT * FROM Installments WHERE SchemeID = ? ORDER BY InstallmentNumber");
    $stmt->execute([$schemeId]);
    $existingInstallments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to load scheme data: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Validate scheme details
        $schemeName = trim($_POST['scheme_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $monthlyPayment = floatval($_POST['monthly_payment'] ?? 0);
        $totalPayments = intval($_POST['total_payments'] ?? 0);
        $schemeImageURL = $scheme['SchemeImageURL']; // Keep existing image by default

        // Handle scheme image upload
        if (isset($_FILES['scheme_image']) && $_FILES['scheme_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/schemes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . basename($_FILES['scheme_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['scheme_image']['tmp_name'], $targetPath)) {
                // Delete old image if exists
                if (!empty($scheme['SchemeImageURL'])) {
                    $oldImagePath = '../../' . $scheme['SchemeImageURL'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $schemeImageURL = 'uploads/schemes/' . $fileName;
            } else {
                $errors['scheme_image'] = 'Failed to upload image';
            }
        }

        // Basic validation
        if (empty($schemeName)) {
            $errors['scheme_name'] = 'Scheme name is required';
        } else {
            // Check if scheme name already exists (excluding current scheme)
            $stmt = $conn->prepare("SELECT SchemeID FROM Schemes WHERE SchemeName = ? AND SchemeID != ?");
            $stmt->execute([$schemeName, $schemeId]);
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
                        'image' => $_FILES['installment']['name'][$index] ?? null
                    ];
                }
            }
        }

        if (empty($installments)) {
            $errors['installments'] = 'At least one installment is required';
        }

        if (empty($errors)) {
            // Update scheme
            $stmt = $conn->prepare("
                UPDATE Schemes 
                SET SchemeName = ?, Description = ?, MonthlyPayment = ?, TotalPayments = ?, SchemeImageURL = ?
                WHERE SchemeID = ?
            ");
            $stmt->execute([$schemeName, $description, $monthlyPayment, $totalPayments, $schemeImageURL, $schemeId]);

            // Process installments
            $uploadDir = '../../uploads/schemes/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Delete existing installments
            $stmt = $conn->prepare("DELETE FROM Installments WHERE SchemeID = ?");
            $stmt->execute([$schemeId]);

            // Insert new installments
            $stmt = $conn->prepare("
                INSERT INTO Installments (
                    SchemeID, InstallmentName, InstallmentNumber, 
                    Amount, DrawDate, Benefits, ImageURL, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
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
                    $imageURL
                ]);
            }

            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                VALUES (?, 'Admin', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Updated scheme: $schemeName with " . count($installments) . " installments",
                $_SERVER['REMOTE_ADDR']
            ]);

            $conn->commit();
            $_SESSION['success_message'] = "Scheme updated successfully with " . count($installments) . " installments.";
            header("Location: index.php");
            exit();
        } else {
            $conn->rollBack();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors['general'] = "Failed to update scheme: " . $e->getMessage();
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
    <title>Edit Scheme - <?php echo htmlspecialchars($scheme['SchemeName']); ?></title>
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
            <h1 class="page-title">Edit Scheme</h1>
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
                        value="<?php echo htmlspecialchars($scheme['SchemeName']); ?>" required>
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
                        <?php if (!empty($scheme['SchemeImageURL'])): ?>
                            <img src="../../<?php echo htmlspecialchars($scheme['SchemeImageURL']); ?>" id="scheme-image-preview" class="image-preview">
                        <?php else: ?>
                            <img id="scheme-image-preview" class="image-preview" style="display: none;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($scheme['Description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="monthly_payment">Monthly Payment (₹) *</label>
                    <input type="number" id="monthly_payment" name="monthly_payment" class="form-control"
                        value="<?php echo htmlspecialchars($scheme['MonthlyPayment']); ?>" required step="0.01">
                    <?php if (isset($errors['monthly_payment'])): ?>
                        <div class="error-message"><?php echo $errors['monthly_payment']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="total_payments">Total Number of Payments *</label>
                    <input type="number" id="total_payments" name="total_payments" class="form-control"
                        value="<?php echo htmlspecialchars($scheme['TotalPayments']); ?>" required min="1">
                    <?php if (isset($errors['total_payments'])): ?>
                        <div class="error-message"><?php echo $errors['total_payments']; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="scheme-section">
                <div class="section-title">Installments</div>

                <div id="installments-container">
                    <?php foreach ($existingInstallments as $installment): ?>
                        <div class="installment-container">
                            <div class="installment-header">
                                <div class="installment-title">Installment #<?php echo $installment['InstallmentNumber']; ?></div>
                                <i class="fas fa-times remove-installment" onclick="removeInstallment(this)"></i>
                            </div>

                            <div class="installment-grid">
                                <div class="form-group">
                                    <label>Installment Name *</label>
                                    <input type="text" name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>][name]"
                                        class="form-control" required value="<?php echo htmlspecialchars($installment['InstallmentName']); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Installment Number *</label>
                                    <input type="number" name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>][number]"
                                        class="form-control" required min="1" value="<?php echo $installment['InstallmentNumber']; ?>">
                                </div>

                                <div class="form-group">
                                    <label>Amount (₹) *</label>
                                    <input type="number" name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>][amount]"
                                        class="form-control" required step="0.01" value="<?php echo $installment['Amount']; ?>">
                                </div>

                                <div class="form-group">
                                    <label>Draw Date *</label>
                                    <input type="date" name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>][draw_date]"
                                        class="form-control" required value="<?php echo $installment['DrawDate']; ?>">
                                </div>

                                <div class="form-group">
                                    <label>Benefits</label>
                                    <textarea name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>][benefits]"
                                        class="form-control" rows="2"><?php echo htmlspecialchars($installment['Benefits']); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Image</label>
                                    <input type="file" name="installment[<?php echo $installment['InstallmentNumber'] - 1; ?>]"
                                        class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <?php if ($installment['ImageURL']): ?>
                                        <img src="../../<?php echo $installment['ImageURL']; ?>" class="image-preview" style="display: block;">
                                    <?php else: ?>
                                        <img class="image-preview">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                    <i class="fas fa-save"></i> Update Scheme
                </button>
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        let installmentCount = <?php echo count($existingInstallments); ?>;

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
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', template);
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
    </script>
</body>

</html>