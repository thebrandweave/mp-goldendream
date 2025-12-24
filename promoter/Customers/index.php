<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "Customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

$message = '';
$messageType = '';
$showNotification = false;

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get promoter's unique ID
    $promoterUniqueID = $promoter['PromoterUniqueID'];
} catch (PDOException $e) {
    $message = "Error fetching promoter data";
    $messageType = "error";
}

// Get all customers under this promoter
try {
    $stmt = $conn->prepare("
        SELECT c.*, p.Name as PromoterName, p.PromoterUniqueID 
        FROM Customers c
        JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        WHERE c.PromoterID = ? 
        ORDER BY c.CreatedAt DESC
    ");
    $stmt->execute([$promoterUniqueID]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching customer data";
    $messageType = "error";
}

// Get customer statistics
try {
    // Get total customers count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM Customers 
        WHERE PromoterID = ?
    ");
    $stmt->execute([$promoterUniqueID]);
    $totalCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active customers count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as active 
        FROM Customers 
        WHERE PromoterID = ? AND Status = 'Active'
    ");
    $stmt->execute([$promoterUniqueID]);
    $activeCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

    // Get inactive customers count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as inactive 
        FROM Customers 
        WHERE PromoterID = ? AND Status = 'Inactive'
    ");
    $stmt->execute([$promoterUniqueID]);
    $inactiveCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];

    // Get suspended customers count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as suspended 
        FROM Customers 
        WHERE PromoterID = ? AND Status = 'Suspended'
    ");
    $stmt->execute([$promoterUniqueID]);
    $suspendedCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['suspended'];
} catch (PDOException $e) {
    $message = "Error calculating customer statistics";
    $messageType = "error";
}

// Handle search functionality
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

if (!empty($searchQuery) || !empty($filterStatus)) {
    $filteredCustomers = [];
    
    foreach ($customers as $customer) {
        $matchesSearch = empty($searchQuery) || 
            stripos($customer['Name'], $searchQuery) !== false || 
            stripos($customer['CustomerUniqueID'], $searchQuery) !== false || 
            stripos($customer['Contact'], $searchQuery) !== false ||
            stripos($customer['Email'], $searchQuery) !== false;
            
        $matchesStatus = empty($filterStatus) || $customer['Status'] === $filterStatus;
        
        if ($matchesSearch && $matchesStatus) {
            $filteredCustomers[] = $customer;
        }
    }
    
    $customers = $filteredCustomers;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Customers | Golden Dreams</title>
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
            overflow-x: hidden;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
            max-width: 100%;
            overflow-x: hidden;
        }

        .top-profile {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-image-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-light);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .profile-id {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .profile-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .main-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            width: 100%;
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

        .search-filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-box {
            min-width: 150px;
        }

        .filter-box select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-box select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .customers-container {
            margin-top: 20px;
        }

        .customer-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .customer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .customer-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
        }

        .customer-detail {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .customer-detail i {
            color: var(--primary-color);
        }

        .customer-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-suspended {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .customer-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--bg-light);
            border: 1px solid var(--border-color);
        }

        .btn-action:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        .no-customers {
            text-align: center;
            padding: 50px 0;
            color: var(--text-secondary);
        }

        .no-customers i {
            font-size: 50px;
            margin-bottom: 20px;
            color: var(--border-color);
        }

        .no-customers h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .no-customers p {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .top-profile {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 15px;
            }

            .profile-left {
                flex-direction: column;
                align-items: center;
            }

            .profile-stats {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                width: 100%;
            }

            .stat-item {
                min-width: unset;
                padding: 10px;
                background: var(--bg-light);
                border-radius: 10px;
            }

            .main-content {
                padding: 15px;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .search-filter-container {
                flex-direction: column;
                gap: 10px;
            }

            .search-box, .filter-box {
                width: 100%;
            }
            
            .customer-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
                gap: 15px;
            }
            
            .customer-avatar {
                margin-bottom: 0;
                width: 50px;
                height: 50px;
            }
            
            .customer-details {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }
            
            .customer-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
                gap: 8px;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
                padding: 8px 12px;
                font-size: 13px;
            }

            .section-icon {
                width: 40px;
                height: 40px;
            }

            .section-icon i {
                font-size: 20px;
            }

            .section-info h2 {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .profile-stats {
                grid-template-columns: 1fr;
            }

            .stat-item {
                padding: 12px;
            }

            .stat-value {
                font-size: 20px;
            }

            .customer-card {
                padding: 12px;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
                padding: 10px 15px;
                font-size: 13px;
            }

            .customer-name {
                font-size: 15px;
            }

            .customer-detail {
                font-size: 13px;
            }

            .customer-status {
                font-size: 11px;
                padding: 3px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="top-profile">
            <div class="profile-left">
                <div class="profile-image-container">
                    <img src="<?php 
                        if (!empty($promoter['ProfileImageURL']) && $promoter['ProfileImageURL'] !== '-'): 
                            echo '../../' . htmlspecialchars($promoter['ProfileImageURL']);
                        else:
                            echo '../../uploads/profile/default.png';
                        endif;
                    ?>" alt="Profile" class="profile-image">
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($promoter['Name']); ?></h2>
                    <div class="profile-id">ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></div>
                </div>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo $totalCustomers; ?></span>
                    <span class="stat-label">Total Customers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $activeCustomers; ?></span>
                    <span class="stat-label">Active</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $inactiveCustomers; ?></span>
                    <span class="stat-label">Inactive</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $suspendedCustomers; ?></span>
                    <span class="stat-label">Suspended</span>
                </div>
            </div>
        </div>

        <div class="main-content">
            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="section-info">
                        <h2>My Customers</h2>
                        <p>Manage and view all customers under your account</p>
                    </div>
                </div>
                <!-- <a href="add.php" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    Add New Customer
                </a> -->
            </div>

            <div class="search-filter-container">
                <form method="GET" action="" class="search-filter-form" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <div class="search-box" style="flex: 1; min-width: 250px;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, ID, contact or email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="filter-box" style="min-width: 150px;">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $filterStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $filterStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Suspended" <?php echo $filterStatus === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="min-width: 100px;">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                </form>
            </div>

            <div class="customers-container">
                <?php if (empty($customers)): ?>
                    <div class="no-customers">
                        <i class="fas fa-users"></i>
                        <h3>No Customers Found</h3>
                        <p>You don't have any customers yet. Add your first customer to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <div class="customer-card">
                            <div class="customer-avatar">
                                <img src="<?php 
                                    if (!empty($customer['ProfileImageURL']) && $customer['ProfileImageURL'] !== '-'): 
                                        echo '../../' . htmlspecialchars($customer['ProfileImageURL']);
                                    else:
                                        echo '../image.png';
                                    endif;
                                ?>" alt="Customer Avatar">
                            </div>
                            <div class="customer-info">
                                <h3 class="customer-name"><?php echo htmlspecialchars($customer['Name']); ?></h3>
                                <div class="customer-details">
                                    <div class="customer-detail">
                                        <i class="fas fa-id-badge"></i>
                                        <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?>
                                    </div>
                                    <div class="customer-detail">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($customer['Contact']); ?>
                                    </div>
                                    <div class="customer-detail">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($customer['Email'] ?: 'N/A'); ?>
                                    </div>
                                </div>
                                <span class="customer-status status-<?php echo strtolower($customer['Status']); ?>">
                                    <?php echo htmlspecialchars($customer['Status']); ?>
                                </span>
                            </div>
                            <div class="customer-actions">
                                <a href="view.php?id=<?php echo $customer['CustomerID']; ?>" class="btn-action">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                <a href="edit.php?id=<?php echo $customer['CustomerID']; ?>" class="btn-action">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Ensure proper topbar integration
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

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html>
