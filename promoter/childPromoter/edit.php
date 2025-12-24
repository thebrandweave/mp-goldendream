<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "childPromoter";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter ID from URL parameter
$promoterId = isset($_GET['id']) ? $_GET['id'] : '';

// Redirect if no promoter ID is provided
if (empty($promoterId)) {
    header("Location: index.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $teamName = $_POST['teamName'] ?? '';
    $status = $_POST['status'] ?? '';
    $commission = $_POST['commission'] ?? '';

    // Handle profile image upload
    $profileImageURL = null;
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/profile/';
        $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('profile_') . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadFile)) {
            $profileImageURL = 'uploads/profile/' . $fileName;
        }
    }

    // Update promoter details
    $updateQuery = "
        UPDATE Promoters 
        SET 
            Name = :name,
            Contact = :contact,
            Email = :email,
            Address = :address,
            TeamName = :teamName,
            Status = :status,
            Commission = :commission
    ";

    if ($profileImageURL) {
        $updateQuery .= ", ProfileImageURL = :profileImageURL";
    }

    $updateQuery .= " WHERE PromoterID = :promoterId";

    $stmt = $conn->prepare($updateQuery);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':contact', $contact);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':teamName', $teamName);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':commission', $commission);
    $stmt->bindParam(':promoterId', $promoterId);

    if ($profileImageURL) {
        $stmt->bindParam(':profileImageURL', $profileImageURL);
    }

    if ($stmt->execute()) {
        header("Location: index.php?success=1");
        exit();
    }
}

// Get promoter details
$query = "
    SELECT 
        PromoterID,
        PromoterUniqueID,
        Name,
        Contact,
        Email,
        Address,
        ProfileImageURL,
        TeamName,
        Status,
        Commission
    FROM 
        Promoters
    WHERE 
        PromoterID = :promoterId
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':promoterId', $promoterId);
$stmt->execute();
$promoter = $stmt->fetch(PDO::FETCH_ASSOC);

// If promoter not found, redirect
if (!$promoter) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Promoter - <?php echo htmlspecialchars($promoter['Name']); ?> | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .main-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .section-info p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .edit-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
        }

        .profile-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .profile-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="section-info">
                        <h2>Edit Promoter</h2>
                        <p>Update details for <?php echo htmlspecialchars($promoter['Name']); ?></p>
                    </div>
                </div>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Promoters
                </a>
            </div>

            <form class="edit-form" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Profile Image</label>
                    <div class="profile-preview">
                        <?php if (!empty($promoter['ProfileImageURL'])): ?>
                            <img src="./<?php echo htmlspecialchars($promoter['ProfileImageURL']); ?>" alt="<?php echo htmlspecialchars($promoter['Name']); ?>">
                        <?php else: ?>
                            <img src="./image.png" alt="Default Profile">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profileImage" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Promoter ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($promoter['Name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Contact</label>
                    <input type="tel" name="contact" class="form-control" value="<?php echo htmlspecialchars($promoter['Contact']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($promoter['Email']); ?>">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($promoter['Address']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Team Name</label>
                    <input type="text" name="teamName" class="form-control" value="<?php echo htmlspecialchars($promoter['TeamName']); ?>">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active" <?php echo $promoter['Status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $promoter['Status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Commission</label>
                    <input type="text" name="commission" class="form-control" value="<?php echo htmlspecialchars($promoter['Commission']); ?>">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            adjustContent();
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 