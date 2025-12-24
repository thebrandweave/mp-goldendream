<?php
session_start();
// Check if user is logged in, redirect if not


$menuPath = "../";
$currentPage = "17 Scheme Customers";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Add this after the database connection
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Handle Delete Customer
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $customerId = $_GET['delete'];

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Deleted customer account";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now delete the customer
        $stmt = $conn->prepare("DELETE FROM mp_customers WHERE CustomerID = ?");
        $stmt->execute([$customerId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Customer deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to delete customer: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Change Customer Status
if (isset($_GET['status']) && !empty($_GET['status']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $customerId = $_GET['id'];
    $newStatus = $_GET['status'];

    // Validate status value
    if (!in_array($newStatus, ['Active', 'Inactive', 'Suspended'])) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: index.php");
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // First, log the activity
        $action = "Changed customer status to " . $newStatus;
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        // Now update the customer status
        $stmt = $conn->prepare("UPDATE mp_customers SET Status = ? WHERE CustomerID = ?");
        $stmt->execute([$newStatus, $customerId]);

        // Commit the transaction
        $conn->commit();

        $_SESSION['success_message'] = "Customer status updated successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update customer status: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$promoterId = isset($_GET['promoter_id']) ? $_GET['promoter_id'] : '';
$referredBy = isset($_GET['referred_by']) ? $_GET['referred_by'] : '';

// Build query conditions
$conditions = [];
$params = [];
$paramCount = 0;

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE :search1 OR c.Email LIKE :search2 OR c.Contact LIKE :search3 OR c.CustomerUniqueID LIKE :search4)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
    $params[':search4'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "c.Status = :status";
    $params[':status'] = $status;
}

if (!empty($promoterId)) {
    $conditions[] = "c.PromoterID = :promoterId";
    $params[':promoterId'] = $promoterId;
}

if (!empty($referredBy)) {
    $conditions[] = "c.ReferredBy = :referredBy";
    $params[':referredBy'] = $referredBy;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Handle Excel Export - Moved after filter conditions
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Clear any previous output
    ob_clean();

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Golden Dreams')
        ->setLastModifiedBy('Golden Dreams')
        ->setTitle('Customer Export')
        ->setSubject('Customer Export')
        ->setDescription('Customer export generated on ' . date('Y-m-d H:i:s'));

    // Set headers
    $headers = ['ID', 'Name', 'Contact', 'Email', 'Promoter', 'Status', 'Joined Date', 'Total Payments', 'Total Amount'];
    $sheet->fromArray($headers, NULL, 'A1');

    // Get filtered data with payment information
    $exportQuery = "
        SELECT 
            c.CustomerUniqueID, 
            c.Name, 
            c.Contact, 
            c.Email, 
                    CONCAT(p.PromoterUniqueID, ' - ', p.Name) as PromoterName,
            c.Status, 
            c.JoinedDate,
            COUNT(py.PaymentID) as total_payments,
            COALESCE(SUM(CASE WHEN py.Status = 'Verified' THEN py.Amount ELSE 0 END), 0) as total_amount
                    FROM mp_customers c 
        LEFT JOIN mp_promoters p ON c.PromoterID = p.PromoterUniqueID
        LEFT JOIN Payments py ON c.CustomerID = py.CustomerID" .
        $whereClause .
        " GROUP BY c.CustomerID
        ORDER BY c.JoinedDate DESC";

    $stmt = $conn->prepare($exportQuery);

    // Bind parameters if they exist
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();
    $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add data to spreadsheet
    $row = 2;
    foreach ($exportData as $data) {
        $sheet->setCellValue('A' . $row, $data['CustomerUniqueID']);
        $sheet->setCellValue('B' . $row, $data['Name']);
        $sheet->setCellValue('C' . $row, $data['Contact']);
        $sheet->setCellValue('D' . $row, $data['Email']);
        $sheet->setCellValue('E' . $row, $data['PromoterName'] ?? 'Direct');
        $sheet->setCellValue('F' . $row, $data['Status']);
        $sheet->setCellValue('G' . $row, $data['JoinedDate'] ? date('M d, Y', strtotime($data['JoinedDate'])) : '-');
        $sheet->setCellValue('H' . $row, $data['total_payments']);
        $sheet->setCellValue('I' . $row, number_format($data['total_amount'], 2));
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '000000']
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E0E0E0']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            ]
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ]
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

    // Add filter information to the filename if any filters are applied
    $filename = 'mp_customers_export';
    if (!empty($search)) $filename .= '_search_' . preg_replace('/[^a-z0-9]/', '_', strtolower($search));
    if (!empty($status)) $filename .= '_status_' . strtolower($status);
    if (!empty($promoterId)) $filename .= '_promoter_' . $promoterId;
    if (!empty($referredBy)) $filename .= '_referred_' . $referredBy;
    $filename .= '_' . date('Y-m-d_His') . '.xlsx';

    // Set headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Fri, 11 Nov 1980 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Create Excel writer and save file
    $writer = new Xlsx($spreadsheet);
    ob_end_clean(); // Clean output buffer before sending file
    $writer->save('php://output');
    exit;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Get total number of customers (for pagination)
$countQuery = "SELECT COUNT(*) as total FROM mp_customers c" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get customers with pagination
$query = "SELECT c.*, CONCAT(p.PromoterUniqueID, ' - ', p.Name) as PromoterName 
          FROM mp_customers c 
          LEFT JOIN mp_promoters p ON c.PromoterID COLLATE utf8mb4_unicode_ci = p.PromoterUniqueID COLLATE utf8mb4_unicode_ci" .
    $whereClause .
    " ORDER BY c.JoinedDate DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind all parameters including search and filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination parameters
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);

$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get count of payments per customer
$customerPaymentCounts = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'CustomerID');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

    $countQuery = "SELECT CustomerID, COUNT(*) as payment_count 
                   FROM Payments 
                   WHERE CustomerID IN ($placeholders) 
                   GROUP BY CustomerID";

    $stmt = $conn->prepare($countQuery);
    foreach ($customerIds as $key => $id) {
        $stmt->bindValue($key + 1, $id);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customerPaymentCounts[$row['CustomerID']] = $row['payment_count'];
    }
}

// Get sum of payments per customer
$customerPaymentSums = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'CustomerID');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

    $sumQuery = "SELECT CustomerID, SUM(Amount) as payment_sum 
                 FROM Payments 
                 WHERE CustomerID IN ($placeholders) AND Status = 'Verified'
                 GROUP BY CustomerID";

    $stmt = $conn->prepare($sumQuery);
    foreach ($customerIds as $key => $id) {
        $stmt->bindValue($key + 1, $id);
    }
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customerPaymentSums[$row['CustomerID']] = $row['payment_sum'];
    }
}

// Get all promoters for filter dropdown
$promoterQuery = "SELECT PromoterUniqueID, Name FROM mp_promoters WHERE Status = 'Active' ORDER BY Name";
$stmt = $conn->prepare($promoterQuery);
$stmt->execute();
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        :root {
            --ad_primary-color: #3a7bd5;
            --ad_primary-hover: #2c60a9;
            --ad_secondary-color: #00d2ff;
            --ad_success-color: #2ecc71;
            --ad_success-hover: #27ae60;
            --warning-color: #f39c12;
            --warning-hover: #d35400;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
            --text-dark: #2c3e50;
            --text-medium: #34495e;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --border-color: #e0e0e0;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.08);
            --transition-speed: 0.3s;
        }

        /* Page-specific styles */
        .customer-actions {
            display: flex;
            gap: 8px;
        }

        .customer-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: white;
            transition: all 0.3s ease;
        }

        .customer-actions .view-btn {
            background: #3498db;
        }

        .customer-actions .view-btn:hover {
            background: #2980b9;
        }

        .customer-actions .edit-btn {
            background: #3a7bd5;
        }

        .customer-actions .edit-btn:hover {
            background: #2c60a9;
        }

        .customer-actions .delete-btn {
            background: #e74c3c;
        }

        .customer-actions .delete-btn:hover {
            background: #c0392b;
        }

        .customer-actions .status-btn {
            background: #2ecc71;
        }

        .customer-actions .status-btn:hover {
            background: #27ae60;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-suspended {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .add-customer-btn {
            background: linear-gradient(135deg, #3a7bd5, #00d2ff);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .add-customer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            align-items: center;
        }

        .filter-group label {
            margin-right: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #555;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: #3a7bd5;
            outline: none;
        }

        .payment-count {
            background: #f1c40f;
            color: #2c3e50;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .payment-sum {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .customer-search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .customer-search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .customer-search-box button {
            background: #3a7bd5;
            color: white;
            border: none;
            padding: 0 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .customer-search-box button:hover {
            background: #2c60a9;
        }

        .promoter-info {
            color: #7f8c8d;
            font-size: 12px;
            display: block;
            margin-top: 3px;
        }

        /* Responsive tables */
        @media (max-width: 992px) {
            .responsive-table {
                overflow-x: auto;
            }

            .customer-table {
                min-width: 800px;
            }
        }

        /* Filter button */
        .filter-btn {
            padding: 8px 15px;
            background: #f5f7fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #e0e0e0;
        }

        .reset-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            color: #e74c3c;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .reset-btn:hover {
            background: #fee;
        }

        .status-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 100;
            width: 150px;
        }

        .status-dropdown a {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 500;
            text-align: left;
            width: 100%;
        }

        .status-dropdown a:hover {
            background: #f5f7fa;
        }

        .status-dropdown a.active-status {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .status-dropdown a.inactive-status {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-dropdown a.suspended-status {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .status-container {
            position: relative;
        }

        .status-container:hover .status-dropdown {
            display: block;
        }

        /* Customer Table Improvements */
        .customer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 15px;
            font-size: 14px;
        }

        .customer-table th {
            background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .customer-table td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .customer-table tr:hover {
            background-color: rgba(0, 123, 255, 0.04);
        }

        .customer-table tr:last-child td {
            border-bottom: none;
        }

        .customer-id {
            font-family: 'Roboto Mono', monospace;
            font-size: 13px;
            color: #6c757d;
        }

        .action-buttons-cell {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-button {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .view-btn {
            background-color: #17a2b8;
        }

        .view-btn:hover {
            background-color: #138496;
        }

        .edit-btn {
            background-color: #ffc107;
            color: #212529;
        }

        .edit-btn:hover {
            background-color: #e0a800;
        }

        .delete-btn {
            background-color: #dc3545;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        .customer-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .status-inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        /* Responsive table */
        @media (max-width: 768px) {
            .customer-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 25px 0;
            justify-content: center;
            gap: 6px;
        }

        .pagination li {
            margin: 0;
        }

        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-medium);
            background: white;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .pagination a:hover {
            background: var(--bg-light);
            border-color: var(--ad_primary-color);
            color: var(--ad_primary-color);
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }

        .pagination .active a {
            background: var(--ad_primary-color);
            color: white;
            border-color: var(--ad_primary-color);
            box-shadow: 0 2px 5px rgba(58, 123, 213, 0.3);
        }

        .pagination span {
            color: var(--text-light);
            background: var(--bg-light);
        }

        .pagination i {
            font-size: 12px;
        }

        @media (max-width: 576px) {
            .pagination {
                gap: 4px;
            }

            .pagination a,
            .pagination span {
                min-width: 32px;
                height: 32px;
                padding: 0 6px;
                font-size: 13px;
            }
        }

        .export-btn {
            margin-left: 10px;
            display: inline-block;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }

        .btn-success:hover {
            background-color: #218838;
            color: white;
        }

        .select2-container {
            min-width: 200px;
        }

        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .select2-dropdown {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .select2-search--dropdown .select2-search__field {
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--ad_primary-color);
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Customer Management</h1>
            <a href="add.php" class="add-customer-btn">
                <i class="fas fa-user-plus"></i> Add New Customer
            </a>
            <div class="export-btn">
                <a href="?export=excel<?php
                                        echo !empty($search) ? '&search=' . urlencode($search) : '';
                                        echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                        echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                        echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '';
                                        ?>" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Manage Customers</h2>
                <form action="" method="GET" class="customer-search-box">
                    <input type="text" name="search" placeholder="Search by name, email, contact or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body">
                <form action="" method="GET" class="filter-container">
                    <div class="filter-group">
                        <label for="status_filter">Status:</label>
                        <select name="status_filter" id="status_filter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active" <?php if ($status === 'Active') echo 'selected'; ?>>Active</option>
                            <option value="Inactive" <?php if ($status === 'Inactive') echo 'selected'; ?>>Inactive</option>
                            <option value="Suspended" <?php if ($status === 'Suspended') echo 'selected'; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="promoter_id">Promoter:</label>
                        <select name="promoter_id" id="promoter_id" class="filter-select promoter-select">
                            <option value="">All Promoters</option>
                            <?php foreach ($promoters as $promoter): ?>
                                <option value="<?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>"
                                    <?php echo ($promoterId == $promoter['PromoterUniqueID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($promoter['PromoterUniqueID'] . ' - ' . $promoter['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="referred_by">Referred By:</label>
                        <input type="text" name="referred_by" id="referred_by" class="filter-select" value="<?php echo htmlspecialchars($referredBy); ?>" placeholder="Referral Code">
                    </div>

                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>

                    <?php if (!empty($search) || !empty($status) || !empty($promoterId) || !empty($referredBy)): ?>
                        <a href="index.php" class="reset-btn">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>

                <?php if (count($customers) > 0): ?>
                    <div class="responsive-table">
                        <table class="customer-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Promoter</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><span class="customer-id"><?php echo $customer['CustomerUniqueID']; ?></span></td>
                                        <td><?php echo htmlspecialchars($customer['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['Contact']); ?></td>
                                        <td><?php echo !empty($customer['Email']) ? htmlspecialchars($customer['Email']) : '-'; ?></td>
                                        <td><?php echo !empty($customer['PromoterName']) ? htmlspecialchars($customer['PromoterName']) : 'Direct'; ?></td>
                                        <td><span class="customer-status status-<?php echo strtolower($customer['Status']); ?>"><?php echo $customer['Status']; ?></span></td>
                                        <td><?php echo !empty($customer['JoinedDate']) ? date('M d, Y', strtotime($customer['JoinedDate'])) : '-'; ?></td>
                                        <td class="action-buttons-cell">
                                            <a href="view.php?id=<?php echo $customer['CustomerID']; ?>" class="action-button view-btn">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="edit.php?id=<?php echo $customer['CustomerID']; ?>" class="action-button edit-btn">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="index.php?delete=<?php echo $customer['CustomerID']; ?>"
                                                class="action-button delete-btn"
                                                onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li>
                                    <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                    echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                    echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                    echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                            echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') .
                                    (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li><span>...</span></li>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="' . ($page == $i ? 'active' : '') . '">
                                    <a href="?page=' . $i .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') .
                                    (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">' . $i . '</a>
                                    </li>';
                            }

                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li><span>...</span></li>';
                                }
                                echo '<li><a href="?page=' . $totalPages .
                                    (!empty($search) ? '&search=' . urlencode($search) : '') .
                                    (!empty($status) ? '&status_filter=' . urlencode($status) : '') .
                                    (!empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '') .
                                    (!empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : '') . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <li>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                            echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                            echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                            echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>" title="Next Page">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : '';
                                                                                echo !empty($status) ? '&status_filter=' . urlencode($status) : '';
                                                                                echo !empty($promoterId) ? '&promoter_id=' . urlencode($promoterId) : '';
                                                                                echo !empty($referredBy) ? '&referred_by=' . urlencode($referredBy) : ''; ?>" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-users-slash"></i>
                        <p>No customers found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($promoterId) || !empty($referredBy)): ?>
                            <a href="index.php" class="reset-btn">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Confirm delete action
        function confirmDelete(customerID, customerName) {
            return confirm(`Are you sure you want to delete customer "${customerName}"? This action cannot be undone.`);
        }

        // Handle status dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const statusContainers = document.querySelectorAll('.status-container');

            statusContainers.forEach(container => {
                const badge = container.querySelector('.status-badge');
                const dropdown = container.querySelector('.status-dropdown');

                // Show dropdown on hover
                container.addEventListener('mouseenter', () => {
                    dropdown.style.display = 'block';
                });

                container.addEventListener('mouseleave', () => {
                    dropdown.style.display = 'none';
                });
            });

            // Initialize tooltips if you're using Bootstrap
            if (typeof bootstrap !== 'undefined') {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(tooltip => {
                    new bootstrap.Tooltip(tooltip);
                });
            }
        });

        // Handle search form submission
        document.querySelector('.customer-search-box').addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim() === '') {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Add loading indicator for actions
        document.querySelectorAll('.customer-actions a').forEach(link => {
            link.addEventListener('click', function() {
                if (this.classList.contains('delete-btn')) {
                    if (confirm('Are you sure you want to delete this customer?')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        this.style.pointerEvents = 'none';
                    } else {
                        return false;
                    }
                }
            });
        });

        $(document).ready(function() {
            // Initialize Select2 for promoter dropdown
            $('.promoter-select').select2({
                placeholder: 'Search promoter...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 1,
                language: {
                    inputTooShort: function() {
                        return "Please enter 1 or more characters";
                    }
                }
            });

            // Preserve selected value after form submission
            <?php if (!empty($promoterId)): ?>
                $('.promoter-select').val('<?php echo $promoterId; ?>').trigger('change');
            <?php endif; ?>
        });
    </script>
</body>

</html>