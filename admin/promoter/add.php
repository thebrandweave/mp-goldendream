<?php
session_start();
// Check if user is logged in, redirect if not


$menuPath = "../";
$currentPage = "promoters";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get parent promoters for dropdown
try {
    $stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID, Commission FROM Promoters WHERE Status = 'Active'");
    $stmt->execute();
    $parentPromoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving parent promoters: " . $e->getMessage();
    header("Location: index.php");
    exit();
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
        $status = $_POST['status'];
        $isNewTeam = isset($_POST['is_new_team']) ? true : false;

        // Password handling
        $password = !empty($_POST['password']) ? trim($_POST['password']) : null;
        $passwordHash = null;

        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        // Bank details
        $bankName = !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null;
        $bankAccountName = !empty($_POST['bank_account_name']) ? trim($_POST['bank_account_name']) : null;
        $bankAccountNumber = !empty($_POST['bank_account_number']) ? trim($_POST['bank_account_number']) : null;
        $ifscCode = !empty($_POST['ifsc_code']) ? trim($_POST['ifsc_code']) : null;

        // Team and commission details
        if ($isNewTeam) {
            $parentPromoterID = null;
            $teamName = trim($_POST['new_team_name']);
            $commission = "750"; // Default commission for new teams
        } else {
            $parentPromoterID = $_POST['parent_promoter_id'];
            $teamName = null; // Will be set based on parent promoter
            $commission = trim($_POST['commission']);

            // Get parent promoter details
            $stmt = $conn->prepare("SELECT Commission, TeamName FROM Promoters WHERE PromoterUniqueID = ?");
            $stmt->execute([$parentPromoterID]);
            $parentPromoter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parentPromoter) {
                $parentCommission = intval($parentPromoter['Commission']);
                $teamName = $parentPromoter['TeamName'];

                // Validate commission is not greater than parent's commission
                if (intval($commission) > $parentCommission) {
                    throw new Exception("Commission cannot be greater than parent promoter's commission ({$parentCommission})");
                }

                // Calculate parent commission (parent's commission - new promoter's commission)
                $newParentCommission = $parentCommission - intval($commission);
            }
        }

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

        if ($isNewTeam && empty($teamName)) {
            $errors[] = "Team name is required for new teams";
        }

        if (!$isNewTeam && empty($parentPromoterID)) {
            $errors[] = "Parent promoter is required";
        }

        if (!$isNewTeam && empty($commission)) {
            $errors[] = "Commission is required";
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
            // Generate unique promoter ID
            // Get the latest PromoterID from the Promoters table
            $stmt = $conn->prepare("SELECT MAX(PromoterID) as max_id FROM Promoters");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextPromoterID = ($result['max_id'] ?? 0) + 1;

            // Format: GDP0[promoterid]
            $promoterUniqueID = 'GDP0' . $nextPromoterID;

            // Check if the unique ID already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Promoters WHERE PromoterUniqueID = ?");
            $stmt->execute([$promoterUniqueID]);
            $count = $stmt->fetchColumn();

            // If exists, generate a new one (this should rarely happen with the new format)
            while ($count > 0) {
                $nextPromoterID++;
                $promoterUniqueID = 'GDP0' . $nextPromoterID;
                $stmt->execute([$promoterUniqueID]);
                $count = $stmt->fetchColumn();
            }

            // Insert promoter
            $query = "INSERT INTO Promoters (
                PromoterUniqueID, Name, Contact, Email, Address, 
                ParentPromoterID, TeamName, Status, Commission, ParentCommission,
                BankName, BankAccountName, BankAccountNumber, IFSCCode,
                PasswordHash
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?
            )";

            $stmt = $conn->prepare($query);
            $stmt->execute([
                $promoterUniqueID,
                $name,
                $contact,
                $email,
                $address,
                $parentPromoterID,
                $teamName,
                $status,
                $commission,
                $newParentCommission ?? null,
                $bankName,
                $bankAccountName,
                $bankAccountNumber,
                $ifscCode,
                $passwordHash
            ]);

            $promoterId = $conn->lastInsertId();

            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                VALUES (?, 'Admin', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                "Added new promoter: $name (ID: $promoterUniqueID)",
                $_SERVER['REMOTE_ADDR']
            ]);

            $conn->commit();
            $_SESSION['success_message'] = "Promoter added successfully.";
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error adding promoter: " . $e->getMessage();
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
    <title>Add New Promoter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section {
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

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
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

        .conditional-field {
            display: none;
        }

        .conditional-field.active {
            display: block;
        }

        .custom-select-wrapper {
            position: relative;
            width: 100%;
        }

        .custom-select-header {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .custom-select-header:hover {
            border-color: #3498db;
        }

        .custom-select-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-top: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 300px;
            overflow: hidden;
        }

        .search-box {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 30px 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .options-container {
            max-height: 250px;
            overflow-y: auto;
        }

        .option {
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .option:hover {
            background-color: #f8f9fa;
        }

        .option.selected {
            background-color: #e3f2fd;
        }

        /* Custom scrollbar */
        .options-container::-webkit-scrollbar {
            width: 6px;
        }

        .options-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .options-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .options-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Add New Promoter</h1>
        </div>

        <form action="" method="POST" class="form-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <div class="section-title">Basic Information</div>

                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="contact">Contact Number *</label>
                    <input type="text" id="contact" name="contact" class="form-control" required
                        value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control">
                    <small class="text-muted">Leave blank to use default password</small>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="Active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Team & Commission</div>

                <div class="checkbox-group">
                    <input type="checkbox" id="is_new_team" name="is_new_team"
                        <?php echo (isset($_POST['is_new_team'])) ? 'checked' : ''; ?>>
                    <label for="is_new_team">Add as New Team</label>
                </div>

                <div id="new_team_section" class="conditional-field <?php echo (isset($_POST['is_new_team'])) ? 'active' : ''; ?>">
                    <div class="form-group">
                        <label for="new_team_name">Team Name *</label>
                        <input type="text" id="new_team_name" name="new_team_name" class="form-control"
                            value="<?php echo isset($_POST['new_team_name']) ? htmlspecialchars($_POST['new_team_name']) : ''; ?>">
                    </div>
                </div>

                <div id="parent_promoter_section" class="conditional-field <?php echo (!isset($_POST['is_new_team'])) ? 'active' : ''; ?>">
                    <div class="form-group">
                        <label for="parent_promoter_id">Parent Promoter *</label>
                        <div class="custom-select-wrapper">
                            <div class="custom-select-header" onclick="toggleSelect()">
                                <span id="selected-value">Select Parent Promoter</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-dropdown" id="custom-select-dropdown">
                                <div class="search-box">
                                    <input type="text" id="parent_promoter_search" placeholder="Search promoter..." onkeyup="filterOptions()">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="options-container" id="options-container">
                                    <?php foreach ($parentPromoters as $promoter): ?>
                                        <div class="option"
                                            data-value="<?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>"
                                            data-commission="<?php echo htmlspecialchars($promoter['Commission']); ?>"
                                            onclick="selectOption(this)">
                                            <?php echo htmlspecialchars($promoter['Name'] . ' (' . $promoter['PromoterUniqueID'] . ')'); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="parent_promoter_id" name="parent_promoter_id" value="<?php echo isset($_POST['parent_promoter_id']) ? htmlspecialchars($_POST['parent_promoter_id']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="commission">Commission *</label>
                        <input type="number" id="commission" name="commission" class="form-control" min="0"
                            value="<?php echo isset($_POST['commission']) ? htmlspecialchars($_POST['commission']) : ''; ?>">
                        <small class="text-muted">Must be less than parent promoter's commission</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Bank Details</div>

                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name" class="form-control"
                        value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="bank_account_name">Account Holder Name</label>
                    <input type="text" id="bank_account_name" name="bank_account_name" class="form-control"
                        value="<?php echo isset($_POST['bank_account_name']) ? htmlspecialchars($_POST['bank_account_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="bank_account_number">Account Number</label>
                    <input type="text" id="bank_account_number" name="bank_account_number" class="form-control"
                        value="<?php echo isset($_POST['bank_account_number']) ? htmlspecialchars($_POST['bank_account_number']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="ifsc_code">IFSC Code</label>
                    <input type="text" id="ifsc_code" name="ifsc_code" class="form-control"
                        value="<?php echo isset($_POST['ifsc_code']) ? htmlspecialchars($_POST['ifsc_code']) : ''; ?>">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Promoter
                </button>
                <a href="index.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isNewTeamCheckbox = document.getElementById('is_new_team');
            const newTeamSection = document.getElementById('new_team_section');
            const parentPromoterSection = document.getElementById('parent_promoter_section');
            const commissionInput = document.getElementById('commission');

            // Toggle sections based on checkbox
            isNewTeamCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    newTeamSection.classList.add('active');
                    parentPromoterSection.classList.remove('active');
                    document.getElementById('parent_promoter_id').removeAttribute('required');
                    commissionInput.removeAttribute('required');
                } else {
                    newTeamSection.classList.remove('active');
                    parentPromoterSection.classList.add('active');
                    document.getElementById('parent_promoter_id').setAttribute('required', 'required');
                    commissionInput.setAttribute('required', 'required');
                }
            });

            // Update commission max value based on selected parent promoter
            function updateCommission(commission) {
                if (commission) {
                    commissionInput.setAttribute('max', commission);
                    commissionInput.setAttribute('placeholder', `Max: ${commission}`);
                } else {
                    commissionInput.removeAttribute('max');
                    commissionInput.setAttribute('placeholder', '');
                }
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-select-wrapper')) {
                    document.getElementById('custom-select-dropdown').style.display = 'none';
                }
            });
        });

        function toggleSelect() {
            const dropdown = document.getElementById('custom-select-dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function filterOptions() {
            const input = document.getElementById('parent_promoter_search');
            const filter = input.value.toLowerCase();
            const options = document.getElementsByClassName('option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                if (text.includes(filter)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }

        function selectOption(element) {
            const value = element.getAttribute('data-value');
            const commission = element.getAttribute('data-commission');
            const text = element.textContent;

            // Update hidden input
            document.getElementById('parent_promoter_id').value = value;

            // Update displayed value
            document.getElementById('selected-value').textContent = text;

            // Update commission input
            const commissionInput = document.getElementById('commission');
            if (commission) {
                commissionInput.setAttribute('max', commission);
                commissionInput.setAttribute('placeholder', `Max: ${commission}`);
                // Set current value if it's greater than max
                if (parseInt(commissionInput.value) > parseInt(commission)) {
                    commissionInput.value = commission;
                }
            }

            // Update selected state
            document.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');

            // Close dropdown
            document.getElementById('custom-select-dropdown').style.display = 'none';
        }

        // Add input validation for commission
        document.getElementById('commission').addEventListener('input', function() {
            const maxCommission = parseInt(this.getAttribute('max'));
            const currentValue = parseInt(this.value);

            if (currentValue > maxCommission) {
                this.value = maxCommission;
            }
        });

        // Initialize selected value if exists
        document.addEventListener('DOMContentLoaded', function() {
            const selectedValue = document.getElementById('parent_promoter_id').value;
            if (selectedValue) {
                const selectedOption = document.querySelector(`.option[data-value="${selectedValue}"]`);
                if (selectedOption) {
                    document.getElementById('selected-value').textContent = selectedOption.textContent;
                    selectedOption.classList.add('selected');

                    // Set commission max value
                    const commission = selectedOption.getAttribute('data-commission');
                    if (commission) {
                        const commissionInput = document.getElementById('commission');
                        commissionInput.setAttribute('max', commission);
                        commissionInput.setAttribute('placeholder', `Max: ${commission}`);
                        // Set current value if it's greater than max
                        if (parseInt(commissionInput.value) > parseInt(commission)) {
                            commissionInput.value = commission;
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>