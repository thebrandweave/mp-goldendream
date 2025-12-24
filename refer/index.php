<?php
session_start();
include './loader.php';

// Database connection
require_once("../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Function to send WhatsApp message
function sendWhatsAppMessage($phoneNumber, $message)
{
    global $conn;

    try {
        // Get WhatsApp API configuration
        $stmt = $conn->prepare("SELECT APIEndpoint, InstanceID, AccessToken, Status FROM WhatsAppAPIConfig WHERE Status = 'Active' ORDER BY ConfigID DESC LIMIT 1");
        $stmt->execute();
        $Config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if WhatsApp is active
        if (!$whatsappConfig || $whatsappConfig['Status'] !== 'Active') {
            error_log("WhatsApp API is not configured or inactive");
            return false;
        }

        // Format phone number (add country code if not present)
        if (substr($phoneNumber, 0, 2) !== '91') {
            $phoneNumber = '91' . $phoneNumber;
        }

        // Prepare API URL
        $apiUrl = $whatsappConfig['APIEndpoint'] . 'send?number=' . $phoneNumber . '&type=text&message=' . urlencode($message) . '&instance_id=' . $whatsappConfig['InstanceID'] . '&access_token=' . $whatsappConfig['AccessToken'];

        // Send request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log the response
        error_log("WhatsApp API Response: " . $response);

        // Check if request was successful
        if ($httpCode == 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                return true;
            }
        }

        return false;
    } catch (Exception $e) {
        error_log("Error sending WhatsApp message: " . $e->getMessage());
        return false;
    }
}

// Get promoter ID from URL parameter
$promoterID = isset($_GET['id']) ? trim($_GET['id']) : '';
$registrationType = isset($_GET['type']) ? trim($_GET['type']) : '';
$referralCode = isset($_GET['ref']) ? trim($_GET['ref']) : '';

// Debug information
error_log("Registration attempt - PromoterID: $promoterID, Type: $registrationType, ReferralCode: $referralCode");

// Initialize error message variable
$error_message = '';

// Validate promoter ID
$promoterData = null;
if (!empty($promoterID)) {
    try {
        $stmt = $conn->prepare("SELECT PromoterID, Name, PromoterUniqueID, Commission, TeamName FROM Promoters WHERE PromoterUniqueID = ? AND Status = 'Active'");
        $stmt->execute([$promoterID]);
        $promoterData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$promoterData) {
            error_log("Invalid promoter ID or inactive promoter: $promoterID");
            $error_message = "Invalid promoter ID or promoter is inactive.";
        } else {
            error_log("Promoter found: " . json_encode($promoterData));

            // Validate referral code if provided - ONLY for promoter registration
            if ($registrationType === 'promoter' && !empty($referralCode)) {
                // Decode the referral code to get the commission value
                $commission = base64_decode($referralCode);

                // Debug information - remove in production
                error_log("Referral code: " . $referralCode);
                error_log("Decoded commission: " . $commission);
                error_log("Parent commission: " . $promoterData['Commission']);

                // Since Commission is stored as VARCHAR, we need to handle string comparison
                if (is_numeric($commission)) {
                    // Convert both to integers for comparison
                    $parentCommission = intval($promoterData['Commission']);
                    $newCommission = intval($commission);

                    if ($newCommission > $parentCommission) {
                        $error_message = "Invalid referral link. Please use a valid referral link."; // Commission must be less than parent promoter's commission
                    }
                } else {
                    // Try direct numeric comparison if base64 decode fails
                    if (is_numeric($referralCode)) {
                        $parentCommission = intval($promoterData['Commission']);
                        $newCommission = intval($referralCode);

                        if ($newCommission > $parentCommission) {
                            $error_message = "Invalid referral link. Please use a valid referral link."; //Commission must be less than parent promoter's commission
                        }
                    } else {
                        // If we can't determine a numeric value, allow the registration
                        // This is a fallback to prevent blocking valid registrations
                        error_log("Could not determine numeric commission value. Allowing registration.");
                    }
                }
            }
            // For customer registration, we don't need to validate commission
        }
    } catch (PDOException $e) {
        $error_message = "Error retrieving promoter data: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
} else {
    $error_message = "No promoter ID provided. Please use a valid referral link.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Validate and sanitize input
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $password = trim($_POST['password']);
        $registrationType = $_POST['registration_type'];

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required";
        }

        if (empty($contact)) {
            $errors[] = "Contact number is required";
        } elseif (!preg_match('/^[0-9]{10}$/', $contact)) {
            $errors[] = "Contact number should be 10 digits";
        } else {
            // Check if phone number already exists in Customers table
            // $stmt = $conn->prepare("SELECT COUNT(*) FROM Customers WHERE Contact = ?");
            // $stmt->execute([$contact]);
            // $customerCount = $stmt->fetchColumn();

            // // Check if phone number already exists in Promoters table
            // $stmt = $conn->prepare("SELECT COUNT(*) FROM Promoters WHERE Contact = ?");
            // $stmt->execute([$contact]);
            // $promoterCount = $stmt->fetchColumn();

            // if ($customerCount > 0 || $promoterCount > 0) {
            //     $errors[] = "This phone number is already registered. Please use a different phone number or contact support.";
            // }
        }

        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }

        // Additional validation for promoter registration
        if ($registrationType === 'promoter' && empty($referralCode)) {
            $errors[] = "Invalid registration link. Please use a valid referral link.";
        }

        if (empty($errors)) {
            // Generate unique ID
            $uniqueID = '';
            $tableName = '';

            if ($registrationType === 'customer') {
                // Get the latest CustomerID from the Customers table
                $stmt = $conn->prepare("SELECT MAX(CustomerID) as max_id FROM Customers");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $nextCustomerID = ($result['max_id'] ?? 0) + 1;

                // Format: GDB0[customerid]
                $uniqueID = 'GDB0' . $nextCustomerID;
                $tableName = 'Customers';
            } else {
                // Get the latest PromoterID from the Promoters table
                $stmt = $conn->prepare("SELECT MAX(PromoterID) as max_id FROM Promoters");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $nextPromoterID = ($result['max_id'] ?? 0) + 1;

                // Format: GDP0[promoterid]
                $uniqueID = 'GDP0' . $nextPromoterID;
                $tableName = 'Promoters';
            }

            // Check if the unique ID already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM $tableName WHERE " . ($registrationType === 'customer' ? 'CustomerUniqueID' : 'PromoterUniqueID') . " = ?");
            $stmt->execute([$uniqueID]);
            $count = $stmt->fetchColumn();

            // If exists, generate a new one (this should rarely happen with the new format)
            while ($count > 0) {
                if ($registrationType === 'customer') {
                    $nextCustomerID++;
                    $uniqueID = 'GDB0' . $nextCustomerID;
                } else {
                    $nextPromoterID++;
                    $uniqueID = 'GDP0' . $nextPromoterID;
                }
                $stmt->execute([$uniqueID]);
                $count = $stmt->fetchColumn();
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($registrationType === 'customer') {
                // Insert customer
                $query = "INSERT INTO Customers (
                    CustomerUniqueID, Name, Contact, PasswordHash, 
                    PromoterID, TeamName, Status, JoinedDate, Address
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, 'Inactive', ?, ?
                )";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $uniqueID,
                    $name,
                    $contact,
                    $passwordHash,
                    $promoterID,
                    $promoterData['TeamName'],
                    date('Y-m-d'),
                    $_POST['address']
                ]);

                $userId = $conn->lastInsertId();

                // Log activity
                $stmt = $conn->prepare("
                    INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                    VALUES (?, 'Promoter', ?, ?)
                ");
                $stmt->execute([
                    $promoterData['PromoterID'],
                    "Registered new customer: $name (ID: $uniqueID)",
                    $_SERVER['REMOTE_ADDR']
                ]);

                // Send welcome message
                $welcomeMessage = "🎉 Congratulations! 🎉\n\n$name\n\nYou have successfully registered for the Golden Dream Savings Plan. 🌟\n\n🆔 Your ID: $uniqueID\n🔑 Your Password: goldendream-25\n🔗 https://mp.goldendream.in/customer";
                sendWhatsAppMessage($contact, $welcomeMessage);

                $_SESSION['success_message'] = "Customer registered successfully. Your ID is: $uniqueID";
            } else {
                // Decode the referral code to get the commission value
                $commission = base64_decode($referralCode);

                // Calculate parent promoter's commission (difference between parent's original commission and child's commission)
                $parentCommission = intval($promoterData['Commission']);
                $childCommission = intval($commission);
                $parentPromoterCommission = $parentCommission - $childCommission;

                // Insert promoter
                $query = "INSERT INTO Promoters (
                    PromoterUniqueID, Name, Contact, PasswordHash, 
                    ParentPromoterID, TeamName, Status, Commission, ParentCommission
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, 'Active', ?, ?
                )";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $uniqueID,
                    $name,
                    $contact,
                    $passwordHash,
                    $promoterID,
                    $promoterData['TeamName'],
                    $commission,
                    $parentPromoterCommission
                ]);

                $userId = $conn->lastInsertId();

                // Create a record in the PromoterWallet
                $stmt = $conn->prepare("
                    INSERT INTO PromoterWallet (UserID, PromoterUniqueID, BalanceAmount, Message)
                    VALUES (?, ?, 0.00, ?)
                ");
                $walletMessage = "Initial wallet creation upon registration";
                $stmt->execute([$userId, $uniqueID, $walletMessage]);

                // Log activity
                $stmt = $conn->prepare("
                    INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
                    VALUES (?, 'Promoter', ?, ?)
                ");
                $stmt->execute([
                    $promoterData['PromoterID'],
                    "Registered new promoter: $name (ID: $uniqueID) with commission: $commission, parent commission: $parentPromoterCommission",
                    $_SERVER['REMOTE_ADDR']
                ]);

                // Send welcome message
                $welcomeMessage = "🎉 Congratulations! 🎉\n\n$name\n\nYou have successfully registered as a Golden Dream Promoter. 🌟\n\n🆔 Your ID: $uniqueID\n🔑 Your Password: goldendream-25\n💰 Commission Rate: $commission\n🔗 https://mp.goldendream.in/promoter";
                sendWhatsAppMessage($contact, $welcomeMessage);

                $_SESSION['success_message'] = "Promoter registered successfully. Your ID is: $uniqueID";
            }

            $conn->commit();

            // Instead of redirecting, we'll just set a flag to show the success message
            $registrationSuccess = true;
            $registeredUniqueID = $uniqueID;
            $registeredType = $registrationType;
            $registeredName = $name;

            // Reset form data
            $name = '';
            $contact = '';
            $password = '';
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
            $conn->rollBack();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error during registration: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Golden Dream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --secondary-color: #00d2ff;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --border-color: #ddd;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        .logo {
            max-width: 200px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            color: #666;
        }

        .registration-options {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
        }

        .option-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 300px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .option-card.active {
            border: 2px solid var(--primary-color);
        }

        .option-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .option-card h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .option-card p {
            color: #666;
            margin-bottom: 20px;
        }

        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn:disabled {
            background: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: var(--error-color);
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }

        .info-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .info-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .info-text a:hover {
            text-decoration: underline;
        }

        .hidden {
            display: none;
        }

        .footer {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.8);
            margin-top: 40px;
        }

        .footer p {
            color: #666;
        }

        .back-btn {
            display: inline-block;
            background: #f8f9fa;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 20px;
        }

        .back-btn:hover {
            background: #e9ecef;
        }

        .success-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 20px;
        }

        .success-title {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .success-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 25px;
        }

        .success-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: left;
        }

        .success-details p {
            margin-bottom: 10px;
        }

        .success-details strong {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .registration-options {
                flex-direction: column;
                align-items: center;
            }

            .option-card {
                width: 100%;
                max-width: 400px;
            }
        }

        .phone-input-group {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }

        .country-code {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 12px 15px;
            font-weight: 500;
            border-right: 2px solid var(--border-color);
        }

        .phone-input-group .form-control {
            border: none;
            border-radius: 0;
            flex: 1;
        }

        .phone-input-group .form-control:focus {
            box-shadow: none;
        }

        .form-text {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Golden Dream</h1>
            <p>Join our community and start your journey</p>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="../index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php elseif (empty($promoterID)): ?>
            <div class="alert alert-danger">
                No promoter ID provided. Please use a valid promoter referral link.
                <div style="margin-top: 15px; text-align: center;">
                    <a href="../index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php elseif (isset($registrationSuccess) && $registrationSuccess): ?>
            <!-- Registration Success Message -->
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="success-title">Registration Successful!</h2>
                <p class="success-message">Thank you for registering with Golden Dream.</p>

                <div class="success-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($registeredName); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($contact); ?></p>
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($registeredUniqueID); ?></p>
                    <p><strong>Type:</strong> <?php echo ucfirst($registeredType); ?></p>
                    <?php if ($registeredType === 'customer'): ?>
                        <p><strong>Status:</strong> Inactive (Pending verification)</p>
                    <?php endif; ?>
                </div>

                <div class="info-text">
                    <p>You can now login to your account.</p>
                    <a href="<?php echo $registeredType === 'customer' ? '../customer/login.php' : '../promoter/login.php'; ?>" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>

                <div style="margin-top: 20px;">
                    <a href="../index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if (empty($registrationType)): ?>
                <!-- Registration Type Selection -->
                <div class="registration-options">
                    <div class="option-card" onclick="selectRegistrationType('customer')">
                        <div class="option-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2>Register as Customer</h2>
                        <p>Join as a customer to participate in our schemes and win exciting prizes.</p>
                    </div>

                    <div class="option-card" onclick="selectRegistrationType('promoter')">
                        <div class="option-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h2>Register as Promoter</h2>
                        <p>Join as a promoter to earn commissions and build your network.</p>
                    </div>
                </div>

                <div class="info-text">
                    <p>Already have an account? <a href="index.php">Go to login page</a></p>
                </div>
            <?php else: ?>
                <!-- Registration Form -->
                <div class="form-container">
                    <h2 class="form-title">Register as <?php echo ucfirst($registrationType); ?></h2>

                    <form action="" method="POST">
                        <input type="hidden" name="registration_type" value="<?php echo htmlspecialchars($registrationType); ?>">

                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="contact">Contact Number *</label>
                            <div class="phone-input-group">
                                <span class="country-code">+91</span>
                                <input type="text" id="contact" name="contact" class="form-control" required
                                    value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>"
                                    pattern="[0-9]{10}" maxlength="10" placeholder="Enter 10 digit number">
                            </div>
                            <small class="form-text text-muted">Enter 10 digit mobile number without country code</small>
                        </div>

                        <div class="form-group">
                            <label for="address">Address *</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small>Password must be at least 6 characters long</small>
                        </div>

                        <?php if ($registrationType === 'promoter' && !empty($promoterData)): ?>
                            <div class="form-group">
                                <label>Parent Promoter</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($promoterData['Name'] . ' (' . $promoterData['PromoterUniqueID'] . ')'); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Team Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($promoterData['TeamName']); ?>" readonly>
                            </div>

                            <?php if (empty($referralCode)): ?>
                                <div class="alert alert-danger">
                                    Invalid registration link. Please use a valid referral link.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <button type="submit" class="btn" <?php echo ($registrationType === 'promoter' && empty($referralCode)) ? 'disabled' : ''; ?>>
                            Register
                        </button>
                    </form>

                    <div class="info-text">
                        <p>Already have an account? <a href="<?php echo $registrationType === 'customer' ? 'customer/login.php' : 'promoter/login.php'; ?>">Login here</a></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Golden Dream. All rights reserved.</p>
    </div>

    <script>
        function selectRegistrationType(type) {
            window.location.href = './?id=<?php echo htmlspecialchars($promoterID); ?>&type=' + type<?php echo !empty($referralCode) ? ' + "&ref=' . htmlspecialchars($referralCode) . '"' : ''; ?>;
        }
    </script>
</body>

</html>
