<?php
require_once '../config/config.php';
require_once '../config/session_check.php';
$c_path = "../";
$current_page = "payments";

// Get user data and validate session
$userData = checkSession();

// Get customer data
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM Customers WHERE CustomerID = ?");
$stmt->execute([$userData['customer_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active subscriptions with unpaid installments
$stmt = $db->prepare("
    SELECT 
        s.SchemeID,
        s.SchemeName,
        s.MonthlyPayment,
        i.InstallmentID,
        i.InstallmentNumber,
        i.Amount,
        i.DrawDate,
        sub.StartDate,
        sub.EndDate
    FROM Subscriptions sub
    JOIN Schemes s ON sub.SchemeID = s.SchemeID
    JOIN Installments i ON s.SchemeID = i.SchemeID
    LEFT JOIN Payments p ON i.InstallmentID = p.InstallmentID 
        AND p.CustomerID = sub.CustomerID
    WHERE sub.CustomerID = ? 
    AND sub.RenewalStatus = 'Active'
    AND (p.PaymentID IS NULL OR p.Status = 'Rejected')
    ORDER BY i.DrawDate ASC
");
$stmt->execute([$userData['customer_id']]);
$unpaid_installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group installments by scheme and sort by draw date
$schemes = [];
foreach ($unpaid_installments as $installment) {
    if (!isset($schemes[$installment['SchemeID']])) {
        $schemes[$installment['SchemeID']] = [
            'SchemeID' => $installment['SchemeID'],
            'SchemeName' => $installment['SchemeName'],
            'MonthlyPayment' => $installment['MonthlyPayment'],
            'installments' => []
        ];
    }
    $schemes[$installment['SchemeID']]['installments'][] = $installment;
}

// Sort installments by draw date for each scheme
foreach ($schemes as &$scheme) {
    usort($scheme['installments'], function ($a, $b) {
        return strtotime($a['DrawDate']) - strtotime($b['DrawDate']);
    });
}

// Get the scheme with the earliest unpaid installment
$selectedSchemeId = null;
$earliestDate = null;
foreach ($schemes as $schemeId => $scheme) {
    if (empty($scheme['installments'])) continue;

    $installmentDate = strtotime($scheme['installments'][0]['DrawDate']);
    if ($earliestDate === null || $installmentDate < $earliestDate) {
        $earliestDate = $installmentDate;
        $selectedSchemeId = $schemeId;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installmentId = isset($_POST['installment_id']) ? (int)$_POST['installment_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $screenshot = isset($_FILES['screenshot']) ? $_FILES['screenshot'] : null;

    if ($installmentId && $amount && $screenshot) {
        // Handle file upload
        $uploadDir = './uploads/payments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExtension = strtolower(pathinfo($screenshot['name'], PATHINFO_EXTENSION));
        $fileName = uniqid() . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($screenshot['tmp_name'], $targetPath)) {
            // Insert payment record
            $stmt = $db->prepare("
                INSERT INTO Payments (
                    CustomerID, SchemeID, InstallmentID, Amount, 
                    ScreenshotURL, Status, SubmittedAt
                ) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");

            $screenshotUrl = 'uploads/payments/' . $fileName;
            $stmt->execute([
                $userData['customer_id'],
                $_POST['scheme_id'],
                $installmentId,
                $amount,
                $screenshotUrl
            ]);

            header('Location: index.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Golden Dream</title>
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
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .payment-container {
            padding: 24px;
            margin-top: 70px;
        }

        .payment-header {
            background: linear-gradient(135deg, #2F9B7F 0%, #1e6e59 100%);
            border-radius: 12px;
            padding: 40px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .payment-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
        }

        .payment-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
            position: relative;
        }

        .payment-form {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border-color);
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px;
            border-radius: 6px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            color: var(--text-primary);
            box-shadow: none;
        }

        .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px;
            border-radius: 6px;
        }

        .form-select:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-green);
            color: var(--text-primary);
            box-shadow: none;
        }

        .btn-submit {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #248c6f;
            transform: translateY(-2px);
        }

        .btn-back {
            background: rgba(47, 155, 127, 0.1);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: var(--accent-green);
            color: white;
        }

        .installment-info {
            background: rgba(47, 155, 127, 0.1);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            border: 1px solid var(--border-color);
        }

        .installment-info p {
            margin: 0;
            color: var(--text-secondary);
        }

        .installment-info strong {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .payment-container {
                margin-left: 70px;
                padding: 16px;
            }

            .payment-header {
                padding: 30px 20px;
            }

            .payment-form {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../c_includes/sidebar.php'; ?>
    <?php include '../c_includes/topbar.php'; ?>

    <div class="main-content">
        <div class="payment-container">
            <div class="container">
                <div class="payment-header">
                    <h2><i class="fas fa-money-bill-wave"></i> Make Payment</h2>
                    <p class="mb-0">Select a scheme and installment to make payment</p>
                </div>

                <?php if (empty($schemes)): ?>
                    <div class="text-center">
                        <p class="text-secondary">No unpaid installments found.</p>
                        <a href="index.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Payments
                        </a>
                    </div>
                <?php else: ?>
                    <div class="payment-form">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="form-label">Select Scheme</label>
                                <select class="form-select" id="schemeSelect" name="scheme_id" required>
                                    <option value="">Choose a scheme</option>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?php echo $scheme['SchemeID']; ?>"
                                            <?php echo $scheme['SchemeID'] == $selectedSchemeId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Select Installment</label>
                                <select class="form-select" id="installmentSelect" name="installment_id" required>
                                    <option value="">Choose an installment</option>
                                </select>
                            </div>

                            <div class="installment-info" id="installmentInfo" style="display: none;">
                                <p><strong>Amount:</strong> <span id="installmentAmount"></span></p>
                                <p><strong>Draw Date:</strong> <span id="installmentDrawDate"></span></p>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Payment Amount</label>
                                <input type="number" class="form-control" name="amount" id="paymentAmount" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Payment Screenshot</label>
                                <input type="file" class="form-control" name="screenshot" accept="image/*" required>
                            </div>

                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-submit">
                                    <i class="fas fa-check"></i> Submit Payment
                                </button>
                                <a href="index.php" class="btn btn-back">
                                    <i class="fas fa-arrow-left"></i> Back to Payments
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const schemes = <?php echo json_encode($schemes); ?>;
        const schemeSelect = document.getElementById('schemeSelect');
        const installmentSelect = document.getElementById('installmentSelect');
        const installmentInfo = document.getElementById('installmentInfo');
        const installmentAmount = document.getElementById('installmentAmount');
        const installmentDrawDate = document.getElementById('installmentDrawDate');
        const paymentAmount = document.getElementById('paymentAmount');

        function updateInstallments(schemeId) {
            installmentSelect.innerHTML = '<option value="">Choose an installment</option>';
            installmentInfo.style.display = 'none';
            paymentAmount.value = '';

            if (schemeId && schemes[schemeId]) {
                schemes[schemeId].installments.forEach(installment => {
                    const option = document.createElement('option');
                    option.value = installment.InstallmentID;
                    option.textContent = `Installment ${installment.InstallmentNumber} (Due: ${new Date(installment.DrawDate).toLocaleDateString()})`;
                    installmentSelect.appendChild(option);
                });

                // Automatically select the first (earliest) installment
                if (schemes[schemeId].installments.length > 0) {
                    const firstInstallment = schemes[schemeId].installments[0];
                    installmentSelect.value = firstInstallment.InstallmentID;
                    installmentAmount.textContent = `₹${parseFloat(firstInstallment.Amount).toFixed(2)}`;
                    installmentDrawDate.textContent = new Date(firstInstallment.DrawDate).toLocaleDateString();
                    paymentAmount.value = firstInstallment.Amount;
                    installmentInfo.style.display = 'block';
                }
            }
        }

        // Initial load of installments for selected scheme
        if (schemeSelect.value) {
            updateInstallments(schemeSelect.value);
        }

        schemeSelect.addEventListener('change', function() {
            updateInstallments(this.value);
        });

        installmentSelect.addEventListener('change', function() {
            const schemeId = schemeSelect.value;
            const installmentId = this.value;

            if (schemeId && installmentId && schemes[schemeId]) {
                const installment = schemes[schemeId].installments.find(i => i.InstallmentID == installmentId);
                if (installment) {
                    installmentAmount.textContent = `₹${parseFloat(installment.Amount).toFixed(2)}`;
                    installmentDrawDate.textContent = new Date(installment.DrawDate).toLocaleDateString();
                    paymentAmount.value = installment.Amount;
                    installmentInfo.style.display = 'block';
                }
            } else {
                installmentInfo.style.display = 'none';
                paymentAmount.value = '';
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>