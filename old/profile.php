<?php
session_start();
require_once 'login/helpers/JWT.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login/login.php');
    exit;
}

// Database connection
class Database
{
    private $host = "localhost";
    private $db_name = "goldendream";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// Initialize variables
$success_message = '';
$error_message = '';
$customer = [
    'Name' => '',
    'Contact' => '',
    'Email' => '',
    'Address' => '',
    'BankAccountName' => '',
    'BankAccountNumber' => '',
    'IFSCCode' => '',
    'BankName' => '',
    'ProfileImageURL' => 'assets/images/default-avatar.png'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, and PNG files are allowed.");
            }

            if ($_FILES['profile_picture']['size'] > $max_size) {
                throw new Exception("File size too large. Maximum size is 5MB.");
            }

            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $new_filename = 'profile_' . $_SESSION['customer_id'] . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;

            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                // Update the profile image URL in the database
                $update_image_query = "UPDATE Customers SET ProfileImageURL = :profile_image_url WHERE CustomerID = :customer_id";
                $image_stmt = $db->prepare($update_image_query);
                $image_stmt->bindParam(':profile_image_url', $target_path);
                $image_stmt->bindParam(':customer_id', $_SESSION['customer_id']);

                if (!$image_stmt->execute()) {
                    // If database update fails, delete the uploaded file
                    unlink($target_path);
                    throw new Exception("Failed to update profile image in database.");
                }

                // Update the customer array with new image URL
                $customer['ProfileImageURL'] = $target_path;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Update customer information
        $update_query = "UPDATE Customers SET 
                        Name = :name,
                        Contact = :contact,
                        Email = :email,
                        Address = :address,
                        BankAccountName = :bank_account_name,
                        BankAccountNumber = :bank_account_number,
                        IFSCCode = :ifsc_code,
                        BankName = :bank_name
                        WHERE CustomerID = :customer_id";

        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':contact', $_POST['contact']);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':bank_account_name', $_POST['bank_account_name']);
        $stmt->bindParam(':bank_account_number', $_POST['bank_account_number']);
        $stmt->bindParam(':ifsc_code', $_POST['ifsc_code']);
        $stmt->bindParam(':bank_name', $_POST['bank_name']);
        $stmt->bindParam(':customer_id', $_SESSION['customer_id']);

        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
        } else {
            throw new Exception("Failed to update profile.");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get customer data
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $query = "SELECT * FROM Customers WHERE CustomerID = :customer_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $_SESSION['customer_id']);
    $stmt->execute();
    $customer_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer_data) {
        $customer = $customer_data;
    }
} catch (Exception $e) {
    error_log("Error in profile.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Dream - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/profile.css">
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-crown"></i>
                <h2>Golden Dream</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php" class="nav-link">
                        <i class="fas fa-wallet"></i>
                        Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-history"></i>
                        Transaction History
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link active">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="welcome-section">
                    <div class="profile-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="welcome-text">
                        <h1>Profile</h1>
                        <p>Manage your profile information</p>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Profile Picture Section -->
                <div class="profile-picture-section">
                    <div class="profile-picture-container">
                        <img src="<?php echo htmlspecialchars($customer['ProfileImageURL']); ?>"
                            alt="Profile Picture"
                            class="profile-picture"
                            id="profilePicturePreview">
                        <div class="profile-picture-overlay">
                            <label for="profilePictureInput" class="upload-btn">
                                <i class="fas fa-camera"></i>
                                <span>Change Photo</span>
                            </label>
                            <input type="file"
                                id="profilePictureInput"
                                name="profile_picture"
                                accept="image/*"
                                class="hidden">
                        </div>
                    </div>
                    <p class="upload-hint">JPG, JPEG or PNG. Max size 5MB</p>
                </div>

                <!-- Profile Information Form -->
                <div class="profile-form-section">
                    <form method="POST" enctype="multipart/form-data" class="profile-form">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text"
                                class="form-input"
                                name="name"
                                value="<?php echo htmlspecialchars($customer['Name']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="tel"
                                class="form-input"
                                name="contact"
                                value="<?php echo htmlspecialchars($customer['Contact']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email"
                                class="form-input"
                                name="email"
                                value="<?php echo htmlspecialchars($customer['Email']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea class="form-input"
                                name="address"
                                rows="3"
                                required><?php echo htmlspecialchars($customer['Address']); ?></textarea>
                        </div>

                        <div class="section-divider">
                            <h3>Bank Details</h3>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bank Account Name</label>
                            <input type="text"
                                class="form-input"
                                name="bank_account_name"
                                value="<?php echo htmlspecialchars($customer['BankAccountName']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bank Account Number</label>
                            <input type="text"
                                class="form-input"
                                name="bank_account_number"
                                value="<?php echo htmlspecialchars($customer['BankAccountNumber']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">IFSC Code</label>
                            <input type="text"
                                class="form-input"
                                name="ifsc_code"
                                value="<?php echo htmlspecialchars($customer['IFSCCode']); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bank Name</label>
                            <input type="text"
                                class="form-input"
                                name="bank_name"
                                value="<?php echo htmlspecialchars($customer['BankName']); ?>"
                                required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="js/profile.js"></script>
</body>

</html>