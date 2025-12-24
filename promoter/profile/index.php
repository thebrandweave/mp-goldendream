<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "profile";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Define upload directory and URL paths
$defaultImagePath = '../../uploads/profile/image.png';
$uploadDir = '../../uploads/profile/';
$uploadUrl = 'uploads/profile/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    
    // Copy default image if it doesn't exist
    if (!file_exists($defaultImagePath)) {
        // You should place your default image in this location
        copy('../assets/images/default-profile.png', $defaultImagePath);
    }
}

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching profile data";
    $messageType = "error";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("
            UPDATE Promoters 
            SET Name = ?, Email = ?, Contact = ?, Address = ?,
                BankAccountName = ?, BankAccountNumber = ?, 
                IFSCCode = ?, BankName = ?
            WHERE PromoterID = ?
        ");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['contact'],
            $_POST['address'],
            $_POST['bank_account_name'],
            $_POST['bank_account_number'],
            $_POST['ifsc_code'],
            $_POST['bank_name'],
            $_SESSION['promoter_id']
        ]);

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (in_array($_FILES['profile_image']['type'], $allowedTypes) && 
                $_FILES['profile_image']['size'] <= $maxSize) {
                
                // Get file extension
                $fileExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                
                // Create new filename with promoter ID
                $newFileName = 'promoter_' . $_SESSION['promoter_id'] . '.' . $fileExt;
                $filePath = $uploadDir . $newFileName;
                $dbImagePath = $uploadUrl . $newFileName;

                // Delete old profile picture if exists
                if ($promoter['ProfileImageURL']) {
                    $oldFilePath = $uploadDir . basename($promoter['ProfileImageURL']);
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filePath)) {
                    // Update database with new image path
                    $stmt = $conn->prepare("UPDATE Promoters SET ProfileImageURL = ? WHERE PromoterID = ?");
                    $stmt->execute([$dbImagePath, $_SESSION['promoter_id']]);
                    
                    // Set success message
                    $message = "Profile and image updated successfully!";
                    $messageType = "success";
                    $showNotification = true;
                } else {
                    throw new Exception("Failed to move uploaded file");
                }
            } else {
                throw new Exception("Invalid file type or size. Please use JPG or PNG files under 5MB.");
            }
        }

        $message = "Profile updated successfully!";
        $messageType = "success";
        $showNotification = true;
        
        // Refresh promoter data
        $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
        $stmt->execute([$_SESSION['promoter_id']]);
        $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
        $showNotification = true;
    }
}

$currentPage = 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --Topprimary-color: #3a7bd5;
            --Topsecondary-color: #00d2ff;
            --Toptext-dark: #2c3e50;
            --Toptext-light: #7f8c8d;
            --topborder-color: #e5e9f2;
            --Topbg-light: #f8f9fa;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--Toptext-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }

        .profile-container {
            max-width: 1200px;
            margin: 20px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-areas: 
                "sidebar main"
                "quick-actions main";
            gap: 25px;
            padding: 0 15px;
        }

        .profile-sidebar {
            grid-area: sidebar;
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            height: fit-content;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease;
        }

        .profile-image-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto 20px;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .image-upload-label {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .image-upload-label:hover {
            transform: scale(1.1);
            background: var(--secondary-color);
        }

        .profile-sidebar h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--Toptext-dark);
            font-weight: 600;
        }

        .profile-status {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 10px 0;
        }

        .profile-id {
            color: var(--Toptext-light);
            font-size: 14px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .quick-actions {
            grid-area: quick-actions;
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            gap: 15px;
            animation: slideIn 0.5s ease 0.2s backwards;
        }

        .action-btn {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--Topbg-light);
            border: 2px solid var(--topborder-color);
            border-radius: 12px;
            color: var(--Toptext-dark);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            width: 100%;
        }

        .action-btn:hover {
            background: rgba(58, 123, 213, 0.1);
            border-color: var(--Topprimary-color);
            transform: translateY(-2px);
            color: var(--Topprimary-color);
        }

        .action-btn i {
            font-size: 20px;
            color: var(--Topprimary-color);
            width: 24px;
            text-align: center;
        }

        .main-content {
            grid-area: main;
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: var(--card-shadow);
            animation: slideIn 0.5s ease 0.1s backwards;
        }

        .section-title {
            color: var(--Toptext-dark);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            position: relative;
            margin-bottom: 5px;
        }

        .form-group label {
            color: var(--Toptext-light);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--topborder-color);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--Topbg-light);
            padding-right: 40px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .form-group i {
            position: absolute;
            right: 15px;
            top: 40px;
            color: var(--Toptext-light);
        }

        .btn-submit {
            background: var(--Topprimary-color);
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            box-shadow: var(--shadow-md);
            font-family: 'Poppins', sans-serif;
        }

        .btn-submit:hover {
            background: var(--Topsecondary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 210, 255, 0.3);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            transition: opacity 0.3s ease;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border: 2px solid rgba(46, 204, 113, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
            border: 2px solid rgba(231, 76, 60, 0.2);
        }

        .kyc-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: auto;
        }

        .kyc-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }

        .kyc-verified {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .kyc-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1200px) {
            .profile-container {
                grid-template-columns: 280px 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 992px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .profile-container {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "sidebar"
                    "quick-actions"
                    "main";
                padding: 0 10px;
            }

            .profile-sidebar {
                padding: 25px;
            }

            .main-content {
                padding: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 576px) {
            .profile-container {
                margin: 10px auto;
            }

            .profile-image-container {
                width: 140px;
                height: 140px;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .form-group input {
                font-size: 14px;
            }

            .btn-submit {
                width: 100%;
                justify-content: center;
            }

            .action-btn {
                padding: 12px 15px;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--card-shadow);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .commission-input {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .referral-link {
            background: var(--bg-light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            word-break: break-all;
            display: none;
        }

        .copy-btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: none;
        }

        .copy-btn:hover {
            background: var(--secondary-color);
        }

        .error-message {
            color: var(--error-color);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-image-container">
                    <img src="<?php 
                        if (!empty($promoter['ProfileImageURL']) && $promoter['ProfileImageURL'] !== '-'): 
                            echo '../../' . htmlspecialchars($promoter['ProfileImageURL']);
                        else:
                            echo '../../uploads/profile/image.png';
                        endif;
                   ?>" alt="Profile" class="profile-image">
                    <label class="image-upload-label" for="profile_image" title="Change Profile Picture">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                <h2><?php echo htmlspecialchars($promoter['Name']); ?></h2>
                <div class="profile-status">
                    <i class="fas fa-circle"></i>
                    Active Promoter
                </div>
                <div class="profile-id">
                    <i class="fas fa-id-badge"></i>
                    ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>
                </div>
                
            </div>

            <div class="quick-actions">
                <a href="kyc.php" class="action-btn">
                    <i class="fas fa-id-card"></i>
                    <div>
                        <div>KYC</div>
                        <?php
                        // Get KYC status
                        $kycStmt = $conn->prepare("SELECT Status FROM KYC WHERE UserID = ? AND UserType = 'Promoter' LIMIT 1");
                        $kycStmt->execute([$_SESSION['promoter_id']]);
                        $kycStatus = $kycStmt->fetch(PDO::FETCH_COLUMN);
                        
                        $statusClass = 'kyc-pending';
                        if ($kycStatus === 'Verified') {
                            $statusClass = 'kyc-verified';
                        } elseif ($kycStatus === 'Rejected') {
                            $statusClass = 'kyc-rejected';
                        }
                        ?>
                        <span class="kyc-status <?php echo $statusClass; ?>">
                            <?php echo $kycStatus ? ucfirst($kycStatus) : 'Not Submitted'; ?>
                        </span>
                    </div>
                </a>
                
                <a href="change-password.php" class="action-btn">
                    <i class="fas fa-key"></i>
                    <div>Change Password</div>
                </a>
                
                <a href="id-card.php" class="action-btn">
                    <i class="fas fa-address-card"></i>
                    <div>ID Card</div>
                </a>
                
                <a href="welcome-letter.php" class="action-btn">
                    <i class="fas fa-envelope-open-text"></i>
                    <div>Welcome Letter</div>
                </a>
                
                <a href="#" class="action-btn" id="generateReferralBtn">
                    <i class="fas fa-link"></i>
                    <div>Generate Referral Link</div>
                </a>
            </div>

            <div class="main-content">
                <?php if ($showNotification && $message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">

                    <h2 class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Personal Information
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($promoter['Name']); ?>" required>
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="tel" name="contact" value="<?php echo htmlspecialchars($promoter['Contact']); ?>" required>
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($promoter['Email']); ?>">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($promoter['Address']); ?>">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>

                    <h2 class="section-title">
                        <i class="fas fa-university"></i>
                        Bank Details
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Holder Name</label>
                            <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($promoter['BankAccountName']); ?>">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($promoter['BankAccountNumber']); ?>">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>IFSC Code</label>
                            <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($promoter['IFSCCode']); ?>">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" value="<?php echo htmlspecialchars($promoter['BankName']); ?>">
                            <i class="fas fa-university"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="referralModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">Generate Referral Link</h2>
            <div class="form-group">
                <label>Enter Commission</label>
                <input type="number" id="commissionInput" class="commission-input" min="0" max="<?php echo $promoter['Commission']; ?>" step="0.01">
                <div class="error-message" id="commissionError"></div>
            </div>
            <div class="referral-link" id="referralLink"></div>
            <button class="copy-btn" id="copyBtn">Copy Link</button>
        </div>
    </div>

    <script>
        // Preview image before upload
        document.getElementById('profile_image').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Auto-hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });

        // Referral Link Generation
        const modal = document.getElementById('referralModal');
        const generateBtn = document.getElementById('generateReferralBtn');
        const closeBtn = document.querySelector('.close-modal');
        const commissionInput = document.getElementById('commissionInput');
        const referralLink = document.getElementById('referralLink');
        const copyBtn = document.getElementById('copyBtn');
        const commissionError = document.getElementById('commissionError');
        const maxCommission = <?php echo $promoter['Commission']; ?>;

        generateBtn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
            resetModal();
        }

        window.onclick = function(e) {
            if (e.target == modal) {
                modal.style.display = 'none';
                resetModal();
            }
        }

        commissionInput.oninput = function() {
            const value = parseFloat(this.value);
            if (value > maxCommission) {
                commissionError.textContent = `Commission cannot be greater than ${maxCommission}`;
                commissionError.style.display = 'block';
                referralLink.style.display = 'none';
                copyBtn.style.display = 'none';
            } else {
                commissionError.style.display = 'none';
                generateReferralLink(value);
            }
        }

        function generateReferralLink(commission) {
            const promoterId = '<?php echo $promoter['PromoterUniqueID']; ?>';
            const encodedRef = btoa(commission.toString());
            const baseUrl = '<?php echo Database::$baseUrl; ?>';
            const link = `${baseUrl}/refer?id=${promoterId}&ref=${encodedRef}`;
            referralLink.textContent = link;
            referralLink.style.display = 'block';
            copyBtn.style.display = 'inline-block';
        }

        copyBtn.onclick = function() {
            const link = referralLink.textContent;
            navigator.clipboard.writeText(link).then(() => {
                copyBtn.textContent = 'Copied!';
                setTimeout(() => {
                    copyBtn.textContent = 'Copy Link';
                }, 2000);
            });
        }

        function resetModal() {
            commissionInput.value = '';
            referralLink.style.display = 'none';
            copyBtn.style.display = 'none';
            commissionError.style.display = 'none';
        }
    </script>
</body>
</html>