<?php
session_start();
// require_once("../middleware/auth.php");
// verifyAuth();

$menuPath = "../";
$currentPage = "payments";
require_once("./clearSession.php");
require_once("../../config/config.php");
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
        $whatsappConfig = $stmt->fetch(PDO::FETCH_ASSOC);

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

// Handle Payment Verification
if (isset($_POST['action']) && isset($_POST['payment_id'])) {
    $paymentId = $_POST['payment_id'];
    $action = $_POST['action'];
    $verifierRemark = trim($_POST['verifier_remark'] ?? '');

    try {
        $conn->beginTransaction();

        $newStatus = ($action === 'verify') ? 'Verified' : 'Rejected';

        // Get payment details including promoter information
        $stmt = $conn->prepare("
        SELECT p.*, c.Name as CustomerName, c.CustomerUniqueID, c.Contact, s.SchemeName,
                (SELECT COUNT(*) FROM Payments WHERE CustomerID = p.CustomerID AND SchemeID = p.SchemeID AND Status = 'Verified') as payment_count
        FROM Payments p
        LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
        LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
        WHERE p.PaymentID = ?
    ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update payment status and verifier remarks
        $stmt = $conn->prepare("
        UPDATE Payments 
        SET Status = ?, AdminID = ?, VerifiedAt = CURRENT_TIMESTAMP, VerifierRemark = ?
        WHERE PaymentID = ?
    ");
        $stmt->execute([$newStatus, $_SESSION['admin_id'], $verifierRemark, $paymentId]);

        // Create notification for customer
        $notificationMessage = "Your payment of ₹" . number_format($payment['Amount'], 2) .
            " for " . $payment['SchemeName'] . " has been " . strtolower($newStatus);
        if (!empty($verifierRemark)) {
            $notificationMessage .= ". Remarks: " . $verifierRemark;
        }

        $stmt = $conn->prepare("
        INSERT INTO Notifications (UserID, UserType, Message) 
        VALUES (?, 'Customer', ?)
    ");
        $stmt->execute([$payment['CustomerID'], $notificationMessage]);

        // Send WhatsApp notification to customer
        $whatsappMessage = "Dear " . $payment['CustomerName'] . ", your payment of ₹" . number_format($payment['Amount'], 2) .
            " for " . $payment['SchemeName'] . " has been " . strtolower($newStatus);
        if (!empty($verifierRemark)) {
            $whatsappMessage .= ". Remarks: " . $verifierRemark;
        }
        sendWhatsAppMessage($payment['Contact'], $whatsappMessage);

        // Log the activity
        $stmt = $conn->prepare("
        INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
        VALUES (?, 'Admin', ?, ?)
    ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "$newStatus payment #$paymentId for customer " . $payment['CustomerName'] . (!empty($verifierRemark) ? " with remarks: " . $verifierRemark : ""),
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();

        // Set message based on first payment status
        if ($newStatus === 'Verified' && $payment['payment_count'] == 0) {
            $_SESSION['success_message'] = "Payment has been verified successfully. This was the first payment for this scheme.";
            // Redirect to promoter commission page with customer unique ID
            header("Location: ./promoterCommision.php?ref=" . urlencode(base64_encode($payment['CustomerUniqueID'])));
            exit();
        } else {
            $_SESSION['success_message'] = "Payment has been $newStatus successfully.";
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Failed to process payment: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to process payment: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$schemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : '';
$installment = isset($_GET['installment']) ? $_GET['installment'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Get all active schemes for filter
$stmt = $conn->query("SELECT SchemeID, SchemeName FROM Schemes WHERE Status = 'Active' ORDER BY SchemeName");
$schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active promoters for filter
$stmt = $conn->query("SELECT PromoterID, PromoterUniqueID, Name FROM Promoters WHERE Status = 'Active' ORDER BY Name");
$promoters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected promoter from filter
$promoterId = isset($_GET['promoter_id']) ? $_GET['promoter_id'] : '';

// Get installments for selected scheme if scheme is selected
$installments = [];
if (!empty($schemeId)) {
    $stmt = $conn->prepare("
    SELECT DISTINCT i.InstallmentNumber 
    FROM Payments p 
    JOIN Installments i ON p.InstallmentID = i.InstallmentID 
    WHERE p.SchemeID = ? 
    ORDER BY i.InstallmentNumber
");
    $stmt->execute([$schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE :search OR c.CustomerUniqueID LIKE :search OR c.Contact LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $conditions[] = "p.Status = :status";
    $params[':status'] = $status;
}

if (!empty($schemeId)) {
    $conditions[] = "p.SchemeID = :schemeId";
    $params[':schemeId'] = $schemeId;
}

if (!empty($installment)) {
    $conditions[] = "i.InstallmentNumber = :installment";
    $params[':installment'] = $installment;
}

if (!empty($startDate)) {
    $conditions[] = "DATE(p.SubmittedAt) >= :startDate";
    $params[':startDate'] = $startDate;
}

if (!empty($endDate)) {
    $conditions[] = "DATE(p.SubmittedAt) <= :endDate";
    $params[':endDate'] = $endDate;
}

if (!empty($promoterId)) {
    $conditions[] = "c.PromoterID = :promoterId";
    $params[':promoterId'] = $promoterId;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get total payments count
$countQuery = "
SELECT COUNT(*) as total 
FROM Payments p
LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID" . $whereClause;
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get payments with related data
$query = "
SELECT p.*, 
        c.Name as CustomerName, c.CustomerUniqueID, c.Contact,
        s.SchemeName,
        pr.Name as PromoterName, pr.PromoterUniqueID,
        a.Name as VerifierName,
        i.InstallmentNumber,
        (SELECT COUNT(*) FROM Payments WHERE CustomerID = p.CustomerID AND SchemeID = p.SchemeID AND Status = 'Verified') as payment_count
FROM Payments p
LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
LEFT JOIN Promoters pr ON p.PromoterID = pr.PromoterID
LEFT JOIN Admins a ON p.AdminID = a.AdminID
LEFT JOIN Installments i ON p.InstallmentID = i.InstallmentID"
    . $whereClause .
    " ORDER BY p.SubmittedAt DESC LIMIT :offset, :limit";

$stmt = $conn->prepare($query);

// Bind the search/filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind the pagination parameters
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .payment-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .payment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        /* Add status-based border colors */
        .payment-card[data-status="Pending"] {
            border-left-color: #f39c12;
        }

        .payment-card[data-status="Verified"] {
            border-left-color: #2ecc71;
        }

        .payment-card[data-status="Rejected"] {
            border-left-color: #e74c3c;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .customer-id {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .payment-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 8px;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
        }

        .verify-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 3px 6px rgba(46, 204, 113, 0.3);
        }

        .verify-btn:hover {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(46, 204, 113, 0.4);
        }

        .reject-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 3px 6px rgba(231, 76, 60, 0.3);
        }

        .reject-btn:hover {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(231, 76, 60, 0.4);
        }

        .view-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            box-shadow: 0 3px 6px rgba(52, 152, 219, 0.3);
        }

        .view-btn:hover {
            background: linear-gradient(135deg, #2980b9, #3498db);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(52, 152, 219, 0.4);
        }

        .payment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .status-pending {
            background-color: rgba(243, 156, 18, 0.15);
            color: #f39c12;
        }

        .status-pending::before {
            content: "•";
            animation: blink 1.5s infinite;
            font-size: 18px;
            line-height: 0;
        }

        .status-verified {
            background-color: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
        }

        .status-verified::before {
            content: "✓";
            font-size: 11px;
        }

        .status-rejected {
            background-color: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }

        .status-rejected::before {
            content: "✕";
            font-size: 11px;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .payment-screenshot {
            max-width: 200px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .payment-screenshot:hover {
            transform: scale(1.05);
            border-color: #3498db;
        }

        .page-header .btn {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 16px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        .page-header .btn:hover {
            background-color: #0056b3;
            color: #fff;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            animation: zoomIn 0.3s ease;
        }

        @keyframes zoomIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-image {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        }

        .close-modal {
            position: absolute;
            top: -30px;
            right: -30px;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(231, 76, 60, 0.8);
            transform: rotate(90deg);
        }

        .confirmation-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1001;
            backdrop-filter: blur(3px);
        }

        .confirmation-dialog {
            background: white;
            border-radius: 10px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .confirmation-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .icon-verify {
            color: #2ecc71;
        }

        .icon-reject {
            color: #e74c3c;
        }

        .confirmation-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .confirmation-message {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .confirmation-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .confirm-btn {
            background: #3498db;
            color: white;
        }

        .confirm-btn:hover {
            background: #2980b9;
        }

        .cancel-confirm-btn {
            background: #f1f2f6;
            color: #576574;
        }

        .cancel-confirm-btn:hover {
            background: #dfe4ea;
        }

        /* Loading animation */
        .loading {
            display: none;
            text-align: center;
            padding: 20px 15px;
            animation: fadeIn 0.3s ease;
        }

        .loading-spinner {
            border: 3px solid rgba(52, 152, 219, 0.2);
            border-radius: 50%;
            border-top: 3px solid #3498db;
            width: 28px;
            height: 28px;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Quick view panel */
        .quick-view-panel {
            position: fixed;
            top: 0;
            right: -420px;
            width: 420px;
            height: 100%;
            background: white;
            box-shadow: -2px 0 20px rgba(0, 0, 0, 0.15);
            z-index: 999;
            transition: right 0.3s ease;
            overflow-y: auto;
            border-left: 1px solid rgba(0, 0, 0, 0.05);
        }

        .quick-view-panel.active {
            right: 0;
        }

        .quick-view-header {
            padding: 20px;
            border-bottom: 1px solid #f1f2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quick-view-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .close-panel {
            font-size: 20px;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-panel:hover {
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .quick-view-content {
            padding: 25px;
        }

        .quick-view-image {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .quick-view-image:hover {
            transform: scale(1.02);
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .quick-view-panel {
                width: 100%;
                right: -100%;
            }
        }

        /* Form controls enhancement */
        .remarks-form {
            margin-top: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #3498db;
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .remarks-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 80px;
            font-family: 'Poppins', sans-serif;
        }

        .remarks-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .remarks-submit {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .cancel-btn,
        .submit-btn {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .cancel-btn {
            background: #f1f2f6;
            color: #576574;
        }

        .cancel-btn:hover {
            background: #dfe4ea;
        }

        .submit-btn {
            background: #3498db;
            color: white;
        }

        .submit-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        /* Filter section styles */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            flex: 1 1 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            padding-left: 40px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23a4b0be" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
            background-repeat: no-repeat;
            background-position: 12px center;
        }

        .search-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 200px;
        }

        .filter-label {
            font-size: 14px;
            color: #576574;
            white-space: nowrap;
            font-weight: 500;
        }

        .filter-select,
        .filter-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 14px;
            background-color: white;
            color: #2d3436;
            transition: all 0.3s ease;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .filter-select {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%23a4b0be" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 30px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        /* Responsive adjustments for filters */
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                gap: 12px;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .filter-group:has(.filter-input) {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-group:has(.filter-input) .filter-label {
                margin-bottom: 5px;
            }

            .filter-group:has(.filter-input) span {
                margin: 5px 0;
            }
        }

        /* Contact badge styling */
        .contact-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border-radius: 50px;
            font-size: 13px;
            margin-top: 8px;
        }

        /* Promoter info styling */
        .promoter-info {
            margin-top: 8px;
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Verifier info styling */
        .verifier-info {
            margin-top: 15px;
            font-size: 13px;
            color: #7f8c8d;
            font-style: italic;
            padding-top: 10px;
            border-top: 1px dashed #ecf0f1;
        }

        /* Add styles for the debug button */
        .debug-btn {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .debug-btn:hover {
            background-color: #2980b9;
        }

        .btn-clear-filter {
            background: linear-gradient(135deg, white, black);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all var(--pr_transition);
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-clear-filter:hover {
            background: linear-gradient(135deg, black, white);
            transform: translateY(-2px);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 35px;
            height: 35px;
            padding: 0 10px;
            border-radius: 8px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .pagination a:hover {
            background: #3498db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .pagination a:first-child,
        .pagination a:last-child {
            font-size: 16px;
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .pagination {
                gap: 5px;
            }

            .pagination a {
                min-width: 30px;
                height: 30px;
                font-size: 13px;
            }
        }

        .verifier-remark {
            margin-top: 8px;
            padding: 8px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 4px;
            font-size: 13px;
            color: #2c3e50;
        }

        .verifier-remark strong {
            color: #3498db;
        }

        .export-button {
            text-align: right;
            padding: 10px 0;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-success i {
            font-size: 16px;
        }

        /* Select2 Custom Styles */
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            min-height: 38px;
            padding: 4px 8px;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            display: flex;
            align-items: center;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #2d3436;
            padding: 0;
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .select2-container--bootstrap-5 .select2-search__field {
            border: 1px solid #dfe6e9;
            border-radius: 6px;
            padding: 8px;
        }

        .select2-container--bootstrap-5 .select2-results__option {
            padding: 8px 12px;
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #3498db;
            color: white;
        }

        .select2-container--bootstrap-5 .select2-results__option--selected {
            background-color: #2980b9;
            color: white;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Payment Management</h1>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Payment
            </a>
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
            <div class="card-body">
                <div class="filter-container">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search by customer name, ID or contact..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status:</label>
                        <select class="filter-select" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Verified" <?php echo $status === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Scheme:</label>
                        <select class="filter-select" name="scheme_id" id="schemeSelect">
                            <option value="">All Schemes</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?php echo $scheme['SchemeID']; ?>"
                                    <?php echo $schemeId == $scheme['SchemeID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scheme['SchemeName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Promoter:</label>
                        <select class="filter-select" name="promoter_id" id="promoterSelect">
                            <option value="">All Promoters</option>
                            <?php foreach ($promoters as $promoter): ?>
                                <option value="<?php echo $promoter['PromoterUniqueID']; ?>"
                                    <?php echo $promoterId == $promoter['PromoterUniqueID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($promoter['PromoterUniqueID'] . ' - ' . $promoter['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group" id="installmentFilterGroup" style="display: <?php echo !empty($schemeId) ? 'flex' : 'none'; ?>">
                        <label class="filter-label">Installment:</label>
                        <select class="filter-select" name="installment">
                            <option value="">All Installments</option>
                            <?php foreach ($installments as $inst): ?>
                                <option value="<?php echo $inst; ?>"
                                    <?php echo $installment == $inst ? 'selected' : ''; ?>>
                                    Installment <?php echo $inst; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range:</label>
                        <input type="date" class="filter-input" name="start_date" value="<?php echo $startDate; ?>">
                        <span>to</span>
                        <input type="date" class="filter-input" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                </div>

                <div class="export-button" style="margin-bottom: 20px;">
                    <button onclick="exportToExcel()" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>

                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="customer-info">
                                    <div class="customer-name"><?php echo htmlspecialchars($payment['CustomerName']); ?></div>
                                    <div class="customer-id"><?php echo $payment['CustomerUniqueID']; ?></div>
                                    <div class="contact-badge">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($payment['Contact']); ?>
                                    </div>
                                    <?php if ($payment['PromoterName']): ?>
                                        <div class="promoter-info">
                                            <i class="fas fa-user-tie"></i> Promoter: <?php echo htmlspecialchars($payment['PromoterName']); ?>
                                            (<?php echo $payment['PromoterUniqueID']; ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="payment-actions">
                                    <?php if ($payment['Status'] === 'Pending'): ?>
                                        <button class="action-btn verify-btn" onclick="showActionForm(this, 'verify', <?php echo $payment['PaymentID']; ?>)">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                        <button class="action-btn reject-btn" onclick="showActionForm(this, 'reject', <?php echo $payment['PaymentID']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn view-btn" onclick="quickView(<?php echo $payment['PaymentID']; ?>, '<?php echo htmlspecialchars($payment['CustomerName']); ?>', '<?php echo htmlspecialchars($payment['SchemeName']); ?>', '<?php echo number_format($payment['Amount'], 2); ?>', '<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>', '<?php echo date('M d, Y H:i', strtotime($payment['SubmittedAt'])); ?>', '<?php echo $payment['Status']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <span class="detail-label">Scheme</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($payment['SchemeName']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Amount</span>
                                    <span class="detail-value">₹<?php echo number_format($payment['Amount'], 2); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Submitted At</span>
                                    <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['SubmittedAt'])); ?></span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="status-badge status-<?php echo strtolower($payment['Status']); ?>">
                                        <?php echo $payment['Status']; ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">First Payment</span>
                                    <span class="detail-value">
                                        <?php echo ($payment['payment_count'] == 0) ? 'Yes' : 'No'; ?>
                                    </span>
                                </div>

                                <?php if (!empty($payment['PayerRemark'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Payer Remarks</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($payment['PayerRemark']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($payment['ScreenshotURL']): ?>
                                <img src="../../customer/<?php echo htmlspecialchars($payment['ScreenshotURL']); ?>"
                                    class="payment-screenshot"
                                    onclick="showImage(this.src)"
                                    alt="Payment Screenshot">
                            <?php endif; ?>

                            <?php if ($payment['Status'] !== 'Pending' && $payment['VerifierName']): ?>
                                <div class="verifier-info">
                                    <?php echo $payment['Status']; ?> by <?php echo htmlspecialchars($payment['VerifierName']); ?>
                                    on <?php echo date('M d, Y H:i', strtotime($payment['VerifiedAt'])); ?>
                                    <?php if (!empty($payment['VerifierRemark'])): ?>
                                        <div class="verifier-remark">
                                            <strong>Verifier Remarks:</strong> <?php echo htmlspecialchars($payment['VerifierRemark']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST" class="remarks-form" style="display: none;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['PaymentID']; ?>">
                                <input type="hidden" name="action" value="">
                                <div class="form-group">
                                    <label for="verifier_remark">Verifier Remarks</label>
                                    <textarea name="verifier_remark" class="remarks-input" placeholder="Enter remarks (optional)"></textarea>
                                </div>
                                <div class="remarks-submit">
                                    <button type="button" class="cancel-btn" onclick="hideActionForm(this)">Cancel</button>
                                    <button type="button" class="submit-btn action-confirm-btn" data-action="">Confirm</button>
                                </div>
                                <div class="loading">
                                    <div class="loading-spinner"></div>
                                    <div>Processing payment...</div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            // Build query string for pagination links
                            $queryParams = $_GET;
                            unset($queryParams['page']); // Remove page parameter
                            $queryString = http_build_query($queryParams);
                            $queryString = !empty($queryString) ? '&' . $queryString : '';
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo $queryString; ?>">&laquo;</a>
                                <a href="?page=<?php echo $page - 1 . $queryString; ?>">&lsaquo;</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i . $queryString; ?>"
                                    class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1 . $queryString; ?>">&rsaquo;</a>
                                <a href="?page=<?php echo $totalPages . $queryString; ?>">&raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>No payments found</p>
                        <?php if (!empty($search) || !empty($status) || !empty($schemeId) || !empty($startDate) || !empty($endDate)): ?>
                            <a href="index.php" class="btn btn-clear-filter">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .btn-clear-filter {
            background: linear-gradient(135deg, white, black);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all var(--pr_transition);
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
    <!-- Image Modal -->
    <div class="modal" id="imageModal" onclick="hideModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-image" id="modalImage">
    </div>

    <!-- Add the confirmation overlay and quick view panel after the image modal -->
    <div class="confirmation-overlay" id="confirmationOverlay">
        <div class="confirmation-dialog">
            <div class="confirmation-icon"><i class="fas fa-check-circle icon-verify" id="confirmationIcon"></i></div>
            <div class="confirmation-title" id="confirmationTitle">Verify Payment</div>
            <div class="confirmation-message" id="confirmationMessage">Are you sure you want to verify this payment?</div>
            <div class="confirmation-buttons">
                <button class="confirmation-btn cancel-confirm-btn" onclick="hideConfirmation()">Cancel</button>
                <button class="confirmation-btn confirm-btn" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>

    <div class="quick-view-panel" id="quickViewPanel">
        <div class="quick-view-header">
            <div class="quick-view-title">Payment Details</div>
            <div class="close-panel" onclick="closeQuickView()"><i class="fas fa-times"></i></div>
        </div>
        <div class="quick-view-content" id="quickViewContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>

    <script>
        // Handle search and filters
        const searchInput = document.querySelector('.search-input');
        const statusSelect = document.querySelector('select[name="status_filter"]');
        const schemeSelect = document.querySelector('select[name="scheme_id"]');
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');
        const promoterSelect = document.querySelector('select[name="promoter_id"]');

        let searchTimeout;

        function updateFilters() {
            const search = searchInput.value.trim();
            const status = statusSelect.value;
            const schemeId = schemeSelect.value;
            const installment = document.querySelector('select[name="installment"]').value;
            const start = startDate.value;
            const end = endDate.value;
            const promoterId = promoterSelect.value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status_filter', status);
            if (schemeId) params.append('scheme_id', schemeId);
            if (installment) params.append('installment', installment);
            if (start) params.append('start_date', start);
            if (end) params.append('end_date', end);
            if (promoterId) params.append('promoter_id', promoterId);

            window.location.href = 'index.php' + (params.toString() ? '?' + params.toString() : '');
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateFilters, 500);
        });

        statusSelect.addEventListener('change', updateFilters);
        schemeSelect.addEventListener('change', updateFilters);
        startDate.addEventListener('change', updateFilters);
        endDate.addEventListener('change', updateFilters);

        // Handle image modal
        function showImage(src) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modal.style.display = 'flex';
            modalImage.src = src;
        }

        function hideModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // New enhanced functions for payment actions
        function showActionForm(button, action, paymentId) {
            const card = button.closest('.payment-card');
            const form = card.querySelector('.remarks-form');
            const actionField = form.querySelector('input[name="action"]');
            const confirmBtn = form.querySelector('.action-confirm-btn');

            // Reset any other open forms
            document.querySelectorAll('.remarks-form').forEach(f => {
                if (f !== form) f.style.display = 'none';
            });

            // Setup current form
            actionField.value = action;
            confirmBtn.dataset.action = action;
            form.style.display = 'block';
            form.querySelector('.remarks-input').focus();

            // Scroll to make form visible if needed
            form.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideActionForm(button) {
            const form = button.closest('.remarks-form');
            form.style.display = 'none';
        }

        // Add event listeners to confirmation buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.action-confirm-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const form = this.closest('form');
                    if (!form) return;

                    const action = form.querySelector('input[name="action"]').value;
                    const paymentId = form.querySelector('input[name="payment_id"]').value;
                    const verifierRemark = form.querySelector('textarea[name="verifier_remark"]')?.value || '';

                    showConfirmation(action, paymentId, verifierRemark, form);
                });
            });

            // Add data-status attribute to payment cards
            document.querySelectorAll('.payment-card').forEach(card => {
                const statusBadge = card.querySelector('.status-badge');
                if (statusBadge) {
                    const status = statusBadge.textContent.trim();
                    card.setAttribute('data-status', status);
                }
            });
        });

        function showConfirmation(action, paymentId, remarks, form) {
            const overlay = document.getElementById('confirmationOverlay');
            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const icon = document.getElementById('confirmationIcon');
            const confirmBtn = document.getElementById('confirmButton');

            // Set content based on action
            if (action === 'verify') {
                title.textContent = 'Verify Payment';
                message.textContent = 'Are you sure you want to verify this payment?';
                icon.className = 'fas fa-check-circle icon-verify';
            } else {
                title.textContent = 'Reject Payment';
                message.textContent = 'Are you sure you want to reject this payment?';
                icon.className = 'fas fa-times-circle icon-reject';
            }

            // Setup confirm button
            confirmBtn.onclick = function() {
                hideConfirmation();

                // Show loading state
                const loading = form.querySelector('.loading');
                const remarksSubmit = form.querySelector('.remarks-submit');
                if (loading && remarksSubmit) {
                    remarksSubmit.style.display = 'none';
                    loading.style.display = 'block';
                }

                // Submit the form
                setTimeout(() => {
                    form.submit();
                }, 500);
            };

            // Show overlay
            overlay.style.display = 'flex';
        }

        function hideConfirmation() {
            document.getElementById('confirmationOverlay').style.display = 'none';
        }

        // Quick view functionality
        function quickView(paymentId, customerName, schemeName, amount, screenshotURL, submittedAt, status) {
            const panel = document.getElementById('quickViewPanel');
            const content = document.getElementById('quickViewContent');

            // Create status badge class
            const statusClass = `status-${status.toLowerCase()}`;

            // Prepare content
            content.innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Customer</span>
                <span class="detail-value">${customerName}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Scheme</span>
                <span class="detail-value">${schemeName}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Amount</span>
                <span class="detail-value">₹${amount}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date Submitted</span>
                <span class="detail-value">${submittedAt}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="status-badge ${statusClass}">${status}</span>
            </div>
                            <img src="../../customer/${screenshotURL}" class="quick-view-image" onclick="showImage('../../customer/${screenshotURL}')">

            <div style="margin-top: 20px;">
                <a href="view.php?id=${paymentId}" class="action-btn view-btn" style="width: 100%; justify-content: center;">
                    <i class="fas fa-external-link-alt"></i> View Full Details
                </a>
            </div>
            
        `;

            // Show panel
            panel.classList.add('active');

            // Add overlay to body for mobile
            if (window.innerWidth <= 600) {
                const overlay = document.createElement('div');
                overlay.id = 'quickViewOverlay';
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.background = 'rgba(0,0,0,0.5)';
                overlay.style.zIndex = '998';
                overlay.onclick = closeQuickView;
                document.body.appendChild(overlay);
            }
        }

        function closeQuickView() {
            const panel = document.getElementById('quickViewPanel');
            panel.classList.remove('active');

            // Remove overlay if exists
            const overlay = document.getElementById('quickViewOverlay');
            if (overlay) overlay.remove();
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideModal();
                hideConfirmation();
                closeQuickView();
            }
        });

        function exportToExcel() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search') || '';
            const status = urlParams.get('status_filter') || '';
            const schemeId = urlParams.get('scheme_id') || '';
            const startDate = urlParams.get('start_date') || '';
            const endDate = urlParams.get('end_date') || '';
            const promoterId = urlParams.get('promoter_id') || '';

            // Build export URL with current filters
            const exportUrl = `export_payments.php?search=${encodeURIComponent(search)}&status_filter=${encodeURIComponent(status)}&scheme_id=${encodeURIComponent(schemeId)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&promoter_id=${encodeURIComponent(promoterId)}`;

            // Redirect to export script
            window.location.href = exportUrl;
        }

        // Add event listener for scheme selection
        schemeSelect.addEventListener('change', function() {
            const installmentGroup = document.getElementById('installmentFilterGroup');
            if (this.value) {
                // Show installment filter
                installmentGroup.style.display = 'flex';

                // Fetch installments for selected scheme
                fetch(`get_installments.php?scheme_id=${this.value}`)
                    .then(response => response.json())
                    .then(data => {
                        const installmentSelect = document.querySelector('select[name="installment"]');
                        installmentSelect.innerHTML = '<option value="">All Installments</option>';
                        data.forEach(inst => {
                            installmentSelect.innerHTML += `<option value="${inst}">Installment ${inst}</option>`;
                        });
                    });
            } else {
                // Hide installment filter
                installmentGroup.style.display = 'none';
                // Clear installment selection
                const installmentSelect = document.querySelector('select[name="installment"]');
                installmentSelect.value = '';
            }
            updateFilters();
        });

        // Add event listener for installment selection
        document.querySelector('select[name="installment"]').addEventListener('change', updateFilters);
    </script>
    <!-- Add jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for promoter dropdown
        $(document).ready(function() {
            $('#promoterSelect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Search promoter...',
                allowClear: true
            }).on('select2:select select2:unselect', function() {
                updateFilters();
            });
        });
    </script>
</body>

</html>