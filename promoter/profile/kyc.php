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
$uploadDir = '../../uploads/kyc/';
$uploadUrl = '../../uploads/kyc/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get KYC details
try {
    $stmt = $conn->prepare("SELECT * FROM KYC WHERE UserID = ? AND UserType = 'Promoter'");
    $stmt->execute([$_SESSION['promoter_id']]);
    $kyc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching KYC data";
    $messageType = "error";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $aadharNumber = $_POST['aadhar_number'];
        $panNumber = $_POST['pan_number'];
        $idProofType = $_POST['id_proof_type'];
        $addressProofType = $_POST['address_proof_type'];

        // Initialize file variables
        $idProofFileName = $kyc ? $kyc['IDProofImageURL'] : null;
        $addressProofFileName = $kyc ? $kyc['AddressProofImageURL'] : null;

        // Handle file uploads only if files are provided
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
            $idProofFile = $_FILES['id_proof'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($idProofFile['error'] === 0 && 
                in_array($idProofFile['type'], $allowedTypes) && 
                $idProofFile['size'] <= $maxSize) {
                
                $timestamp = time();
                $uniqueId = uniqid($timestamp . '_');
                $idProofExt = pathinfo($idProofFile['name'], PATHINFO_EXTENSION);
                $idProofFileName = 'id_proof_' . $uniqueId . '.' . $idProofExt;
                
                // Delete old file if exists
                if ($kyc && $kyc['IDProofImageURL']) {
                    $oldFile = $uploadDir . $kyc['IDProofImageURL'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                move_uploaded_file($idProofFile['tmp_name'], $uploadDir . $idProofFileName);
            } else {
                throw new Exception("Invalid ID proof file. Please use JPG, PNG or PDF under 5MB.");
            }
        }

        if (isset($_FILES['address_proof']) && $_FILES['address_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
            $addressProofFile = $_FILES['address_proof'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($addressProofFile['error'] === 0 && 
                in_array($addressProofFile['type'], $allowedTypes) && 
                $addressProofFile['size'] <= $maxSize) {
                
                $timestamp = time();
                $uniqueId = uniqid($timestamp . '_');
                $addressProofExt = pathinfo($addressProofFile['name'], PATHINFO_EXTENSION);
                $addressProofFileName = 'address_proof_' . $uniqueId . '.' . $addressProofExt;
                
                // Delete old file if exists
                if ($kyc && $kyc['AddressProofImageURL']) {
                    $oldFile = $uploadDir . $kyc['AddressProofImageURL'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                move_uploaded_file($addressProofFile['tmp_name'], $uploadDir . $addressProofFileName);
            } else {
                throw new Exception("Invalid address proof file. Please use JPG, PNG or PDF under 5MB.");
            }
        }

        // Set status based on whether it's an update or new submission
        $newStatus = $kyc ? ($kyc['Status'] === 'Verified' ? 'Verified' : 'Verified') : 'Verified';

        // Insert or Update KYC details
        if ($kyc) {
            $stmt = $conn->prepare("
                UPDATE KYC SET 
                AadharNumber = ?, PANNumber = ?, 
                IDProofType = ?, IDProofImageURL = ?,
                AddressProofType = ?, AddressProofImageURL = ?,
                Status = ?, SubmittedAt = CURRENT_TIMESTAMP,
                Remarks = NULL
                WHERE UserID = ? AND UserType = 'Promoter'
            ");
            $params = [
                $aadharNumber, 
                $panNumber, 
                $idProofType, 
                $idProofFileName, 
                $addressProofType, 
                $addressProofFileName,
                $newStatus,
                $_SESSION['promoter_id']
            ];
        } else {
            $stmt = $conn->prepare("
                INSERT INTO KYC (
                    UserID, UserType, AadharNumber, PANNumber,
                    IDProofType, IDProofImageURL,
                    AddressProofType, AddressProofImageURL,
                    Status, SubmittedAt
                ) VALUES (?, 'Promoter', ?, ?, ?, ?, ?, ?, 'Verified', CURRENT_TIMESTAMP)
            ");
            $params = [
                $_SESSION['promoter_id'],
                $aadharNumber,
                $panNumber,
                $idProofType,
                $idProofFileName,
                $addressProofType,
                $addressProofFileName
            ];
        }

        $stmt->execute($params);

        header("Location: kyc.php");
        exit();

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        header("Location: kyc.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Include the same CSS variables and common styles from index.php */
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

        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        .content-wrapper {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        .profile-container {
            max-width: 1100px;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-areas: 
                "sidebar main"
                "quick-actions main";
            gap: 20px;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .profile-sidebar {
            grid-area: sidebar;
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            height: fit-content;
            box-shadow: var(--card-shadow);
        }

        .main-content {
            grid-area: main;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            grid-row: span 2;
        }

        .kyc-status-wrapper {
            text-align: center;
            margin-bottom: 30px;
        }

        .kyc-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .kyc-icon i {
            font-size: 40px;
            color: var(--primary-color);
        }

        .kyc-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .kyc-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-verified {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-light);
            appearance: none;
            -webkit-appearance: none;
            background-image: none;
            -moz-appearance: none;
            padding-right: 40px;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: white;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .file-upload-container {
            position: relative;
            min-height: 200px;
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .file-upload-container:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .file-upload-container i.upload-icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .file-upload-text {
            font-size: 14px;
            color: var(--text-secondary);
            max-width: 200px;
            margin: 0 auto;
        }

        .file-upload-text strong {
            display: block;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .uploaded-preview {
            width: 100%;
            padding: 12px;
            background: var(--bg-light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .uploaded-preview i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .uploaded-preview .file-info {
            flex: 1;
            overflow: hidden;
            text-align: left;
        }

        .uploaded-preview .file-name {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .uploaded-preview .file-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .remove-file {
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            color: var(--error-color);
            transition: all 0.3s ease;
        }

        .remove-file:hover {
            transform: scale(1.1);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(255,255,255,0) 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .section-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .section-info h2 {
            margin: 0;
            font-size: 20px;
            color: var(--text-primary);
        }

        .section-info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-row:last-child {
            margin-bottom: 0;
        }

        .input-group {
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .input-wrapper {
            position: relative;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .input-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 20px;
            padding-right: 45px;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-primary);
            background: transparent;
            transition: all 0.3s ease;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .input-wrapper input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .input-wrapper:hover i {
            color: var(--primary-color);
        }

        .input-wrapper:focus-within i {
            color: var(--primary-color);
        }

        .input-hint {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            padding-left: 5px;
        }

        @media (max-width: 768px) {
            .form-section {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .input-wrapper input {
                font-size: 14px;
                padding: 12px 15px;
                padding-right: 40px;
            }

            .input-hint {
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .form-section {
                padding: 15px;
            }

            .input-group label {
                font-size: 13px;
            }

            .input-wrapper input {
                padding: 10px 12px;
                padding-right: 35px;
            }
        }

        .document-section {
            margin-top: 30px;
            background: var(--bg-light);
            border-radius: 15px;
            padding: 25px;
        }

        .document-section .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .document-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                grid-template-areas: 
                    "sidebar"
                    "main";
                padding: 0 15px;
            }

            .main-content {
                padding: 20px;
            }

            .form-section {
                padding: 15px;
            }

            .section-header {
                padding: 15px;
            }
        }

        /* Update form-row to handle overflow */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Ensure all elements respect box-sizing */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* Update input groups to prevent overflow */
        .input-group {
            width: 100%;
            margin-bottom: 25px;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            box-sizing: border-box;
        }

        /* Update document upload section */
        .document-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            width: 100%;
        }

        .file-upload-container {
            width: 100%;
            box-sizing: border-box;
        }

        /* Update uploaded preview to prevent overflow */
        .uploaded-preview {
            width: 100%;
            box-sizing: border-box;
        }

        .uploaded-preview .file-info {
            flex: 1;
            min-width: 0; /* Allows text truncation to work */
        }

        .form-actions {
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            width: 100%;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--bg-light);
            color: var(--text-secondary);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-decoration: none;
            flex: 1;
        }

        .btn-back:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-back:active {
            transform: translateY(0);
        }

        .btn-back i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .btn-back:hover i {
            transform: translateX(-3px);
        }

        .btn-submit {
            flex: 2;
        }

        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                margin: 20px auto;
                padding: 0 15px;
            }

            .main-content {
                padding: 20px;
            }

            .form-section {
                padding: 20px;
            }

            .section-header {
                padding: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .input-wrapper input {
                font-size: 14px;
                padding: 12px 15px;
            }

            .input-hint {
                font-size: 11px;
            }

            .document-upload-grid {
                grid-template-columns: 1fr;
            }

            .file-upload-container {
                min-height: 150px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-back, .btn-submit {
                width: 100%;
                padding: 12px 20px;
            }

            .btn-status {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .profile-container {
                margin: 15px auto;
                padding: 0 10px;
            }

            .main-content {
                padding: 15px;
            }

            .form-section {
                padding: 15px;
            }

            .input-group label {
                font-size: 13px;
            }

            .input-wrapper input {
                padding: 10px 12px;
                font-size: 13px;
            }

            .section-header {
                padding: 12px;
            }

            .section-icon {
                width: 40px;
                height: 40px;
            }

            .section-info h2 {
                font-size: 18px;
            }

            .section-info p {
                font-size: 12px;
            }

            .document-section {
                padding: 15px;
            }

            .document-section .section-title {
                font-size: 16px;
            }

            .file-upload-text {
                font-size: 12px;
            }

            .upload-icon {
                font-size: 32px;
            }
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #084c39 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(13, 106, 80, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            background: linear-gradient(135deg, #a8a8a8 0%, #8a8a8a 100%);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-submit i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .btn-submit:hover i {
            transform: translateY(-2px);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit .btn-text {
            position: relative;
            z-index: 1;
        }

        /* Add loading state styles */
        .btn-submit.loading {
            pointer-events: none;
        }

        .btn-submit.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add status indicator */
        .btn-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            background: var(--bg-light);
            color: var(--text-secondary);
            margin-right: auto;
        }

        .btn-status.verified {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        /* Hide default arrow in IE */
        .form-group select::-ms-expand {
            display: none;
        }

        /* Update the icon positioning and style */
        .input-group i.fa-chevron-down {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        /* Optional: Add animation for the icon when select is focused */
        .input-group:focus-within i.fa-chevron-down {
            color: var(--primary-color);
            transform: translateY(-50%) rotate(180deg);
        }

        /* Add popup styles */
        .kyc-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .kyc-popup.active {
            display: flex;
        }

        .popup-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s ease;
        }

        .popup-close:hover {
            color: var(--error-color);
            transform: rotate(90deg);
        }

        .popup-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .popup-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 20px;
        }

        .popup-body {
            margin-bottom: 20px;
        }

        .popup-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .btn-cancel {
            padding: 10px 20px;
            background: var(--bg-light);
            border: none;
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-edit {
            padding: 10px 20px;
            background: var(--primary-color);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: #084c39;
        }

        .document-preview {
            margin-top: 15px;
            padding: 15px;
            background: var(--bg-light);
            border-radius: 8px;
        }

        .document-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .document-preview a {
            display: inline-block;
            margin-top: 10px;
            color: var(--primary-color);
            text-decoration: none;
        }

        .document-preview a:hover {
            text-decoration: underline;
        }

        .document-preview {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .document-preview h4 {
            margin: 0 0 15px 0;
            color: var(--text-primary);
            font-size: 16px;
        }

        .document-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary-light);
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .document-link:hover {
            background: var(--primary-color);
            color: white;
        }

        .document-actions {
            margin-top: 15px;
        }

        .document-actions .btn-edit {
            padding: 8px 16px;
            font-size: 14px;
        }

        .select-wrapper {
            position: relative;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .select-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .select-wrapper select {
            width: 100%;
            padding: 14px 20px;
            padding-right: 40px;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-primary);
            background: transparent;
            appearance: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .select-wrapper select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .select-wrapper select option {
            padding: 10px;
            font-size: 15px;
        }

        .select-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .select-wrapper:hover i {
            color: var(--primary-color);
        }

        .select-wrapper:focus-within i {
            transform: translateY(-50%) rotate(180deg);
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="kyc-status-wrapper">
                    <div class="kyc-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2 class="kyc-title">KYC Verification</h2>
                    <p class="kyc-subtitle">Complete your verification to unlock all features</p>
                    
                    <?php if ($kyc): ?>
                        <div class="status-badge status-<?php echo strtolower($kyc['Status']); ?>">
                
                            <?php echo $kyc['Status']; ?>
                        </div>
                        <?php if ($kyc['Status'] === 'Rejected' && $kyc['Remarks']): ?>
                            <div class="remarks">
                                <strong>Rejection Reason:</strong><br>
                                <?php echo htmlspecialchars($kyc['Remarks']); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-badge status-pending">
                            <i class="fas fa-clock"></i>
                            Not Submitted
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="main-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($kyc && $kyc['Status'] === 'Verified'): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="section-info">
                                <h2>KYC Verified</h2>
                                <p>Your KYC has been verified. You can view your details below.</p>
                            </div>
                        </div>

                        <div class="document-preview">
                            <h3>Personal Information</h3>
                            <p><strong>Aadhar Number:</strong> <?php echo htmlspecialchars($kyc['AadharNumber']); ?></p>
                            <p><strong>PAN Number:</strong> <?php echo htmlspecialchars($kyc['PANNumber']); ?></p>
                            <p><strong>ID Proof Type:</strong> <?php echo htmlspecialchars($kyc['IDProofType']); ?></p>
                            <p><strong>Address Proof Type:</strong> <?php echo htmlspecialchars($kyc['AddressProofType']); ?></p>
                            
                            <h3>Documents</h3>
                            <div class="document-grid">
                                <div>
                                    <h4>ID Proof</h4>
                                    <?php if (strtolower(pathinfo($kyc['IDProofImageURL'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                        <a href="<?php echo $uploadUrl . $kyc['IDProofImageURL']; ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> View PDF
                                        </a>
                                    <?php else: ?>
                                        <img src="<?php echo $uploadUrl . $kyc['IDProofImageURL']; ?>" alt="ID Proof">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4>Address Proof</h4>
                                    <?php if (strtolower(pathinfo($kyc['AddressProofImageURL'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                        <a href="<?php echo $uploadUrl . $kyc['AddressProofImageURL']; ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> View PDF
                                        </a>
                                    <?php else: ?>
                                        <img src="<?php echo $uploadUrl . $kyc['AddressProofImageURL']; ?>" alt="Address Proof">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-edit" onclick="openEditPopup()">
                                <i class="fas fa-edit"></i> Update KYC Details
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="section-info">
                                <h2>Personal Information</h2>
                                <p>Please provide your valid identification details for verification</p>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-row">
                                <div class="input-group">
                                    <label>Aadhar Number</label>
                                    <div class="input-wrapper">
                                        <input 
                                            type="text" 
                                            name="aadhar_number" 
                                            value="<?php echo $kyc ? htmlspecialchars($kyc['AadharNumber']) : ''; ?>" 
                                            required 
                                            pattern="[0-9]{12}" 
                                            maxlength="12"
                                            placeholder="Enter 12-digit Aadhar number"
                                        >
                                        <i class="fas fa-id-card"></i>
                                    </div>
                                    <div class="input-hint">Format: 1234 5678 9012</div>
                                </div>
                                <div class="input-group">
                                    <label>PAN Number</label>
                                    <div class="input-wrapper">
                                        <input 
                                            type="text" 
                                            name="pan_number" 
                                            value="<?php echo $kyc ? htmlspecialchars($kyc['PANNumber']) : ''; ?>" 
                                            required 
                                            pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" 
                                            maxlength="10"
                                            placeholder="Enter 10-character PAN NO"
                                            style="text-transform: uppercase;"
                                        >
                                        <i class="fas fa-address-card"></i>
                                    </div>
                                    <div class="input-hint">Format: ABCDE1234F</div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-group">
                                    <label>ID Proof Type</label>
                                    <div class="select-wrapper">
                                        <select name="id_proof_type" required>
                                            <option value="">Select ID Proof Type</option>
                                            <option value="Aadhar" <?php echo ($kyc && $kyc['IDProofType'] === 'Aadhar') ? 'selected' : ''; ?>>Aadhar Card</option>
                                            <option value="PAN" <?php echo ($kyc && $kyc['IDProofType'] === 'PAN') ? 'selected' : ''; ?>>PAN Card</option>
                                            <option value="Voter ID" <?php echo ($kyc && $kyc['IDProofType'] === 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                                            <option value="Passport" <?php echo ($kyc && $kyc['IDProofType'] === 'Passport') ? 'selected' : ''; ?>>Passport</option>
                                            <option value="Driving License" <?php echo ($kyc && $kyc['IDProofType'] === 'Driving License') ? 'selected' : ''; ?>>Driving License</option>
                                        </select>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>Address Proof Type</label>
                                    <div class="select-wrapper">
                                        <select name="address_proof_type" required>
                                            <option value="">Select Address Proof Type</option>
                                            <option value="Aadhar" <?php echo ($kyc && $kyc['AddressProofType'] === 'Aadhar') ? 'selected' : ''; ?>>Aadhar Card</option>
                                            <option value="Voter ID" <?php echo ($kyc && $kyc['AddressProofType'] === 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                                            <option value="Utility Bill" <?php echo ($kyc && $kyc['AddressProofType'] === 'Utility Bill') ? 'selected' : ''; ?>>Utility Bill</option>
                                            <option value="Bank Statement" <?php echo ($kyc && $kyc['AddressProofType'] === 'Bank Statement') ? 'selected' : ''; ?>>Bank Statement</option>
                                            <option value="Ration Card" <?php echo ($kyc && $kyc['AddressProofType'] === 'Ration Card') ? 'selected' : ''; ?>>Ration Card</option>
                                        </select>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="document-section">
                                <div class="section-title">
                                    <i class="fas fa-file-upload"></i>
                                    Document Upload
                                </div>
                                <div class="document-upload-grid">
                                    <div class="form-group">
                                        <?php if ($kyc && $kyc['IDProofImageURL']): ?>
                                            <div class="document-preview">
                                                <h4>ID Proof</h4>
                                                <?php if (strtolower(pathinfo($kyc['IDProofImageURL'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                                    <a href="<?php echo $uploadUrl . $kyc['IDProofImageURL']; ?>" target="_blank" class="document-link">
                                                        <i class="fas fa-file-pdf"></i> View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <img src="<?php echo $uploadUrl . $kyc['IDProofImageURL']; ?>" alt="ID Proof" class="document-image">
                                                <?php endif; ?>
                                                <div class="document-actions">
                                                    <button type="button" class="btn-edit" onclick="openEditPopup()">
                                                        <i class="fas fa-edit"></i> Update Document
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="file-upload-container" id="id-proof-upload">
                                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                                <div class="file-upload-text">
                                                    <strong>Upload ID Proof</strong>
                                                    Drag and drop or click to upload<br>
                                                    JPG, PNG or PDF (max. 5MB)
                                                </div>
                                                <input type="file" name="id_proof" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required>
                                                <div class="uploaded-file-name"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <?php if ($kyc && $kyc['AddressProofImageURL']): ?>
                                            <div class="document-preview">
                                                <h4>Address Proof</h4>
                                                <?php if (strtolower(pathinfo($kyc['AddressProofImageURL'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                                    <a href="<?php echo $uploadUrl . $kyc['AddressProofImageURL']; ?>" target="_blank" class="document-link">
                                                        <i class="fas fa-file-pdf"></i> View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <img src="<?php echo $uploadUrl . $kyc['AddressProofImageURL']; ?>" alt="Address Proof" class="document-image">
                                                <?php endif; ?>
                                                <div class="document-actions">
                                                    <button type="button" class="btn-edit" onclick="openEditPopup()">
                                                        <i class="fas fa-edit"></i> Update Document
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="file-upload-container" id="address-proof-upload">
                                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                                <div class="file-upload-text">
                                                    <strong>Upload Address Proof</strong>
                                                    Drag and drop or click to upload<br>
                                                    JPG, PNG or PDF (max. 5MB)
                                                </div>
                                                <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" required>
                                                <div class="uploaded-file-name"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <?php if ($kyc && $kyc['Status'] === 'Verified'): ?>
                                <div class="btn-status verified">
                                    <i class="fas fa-check-circle"></i>
                                    KYC Verified
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <a href="../profile.php" class="btn-back">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back</span>
                                </a>
                                
                                <button type="submit" 
                                        class="btn-submit" 
                                        id="submitBtn"
                                        <?php echo ($kyc && $kyc['Status'] === 'Verified') ? 'disabled' : ''; ?>>
                                    <i class="fas fa-upload"></i>
                                    <span class="btn-text">
                                        <?php echo $kyc ? 'Update KYC Details' : 'Submit KYC Details'; ?>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- KYC Edit Popup -->
    <div class="kyc-popup" id="kycEditPopup">
        <div class="popup-content">
            <button class="popup-close" onclick="closeEditPopup()">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="popup-header">
                <h2>Edit KYC Details</h2>
            </div>
            
            <div class="popup-body">
                <form method="POST" enctype="multipart/form-data" id="editKycForm">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Aadhar Number</label>
                            <div class="input-wrapper">
                                <input 
                                    type="text" 
                                    name="aadhar_number" 
                                    value="<?php echo $kyc ? htmlspecialchars($kyc['AadharNumber']) : ''; ?>" 
                                    pattern="[0-9]{12}" 
                                    maxlength="12"
                                    placeholder="Enter 12-digit Aadhar number"
                                >
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="input-hint">Format: 1234 5678 9012</div>
                        </div>
                        <div class="input-group">
                            <label>PAN Number</label>
                            <div class="input-wrapper">
                                <input 
                                    type="text" 
                                    name="pan_number" 
                                    value="<?php echo $kyc ? htmlspecialchars($kyc['PANNumber']) : ''; ?>" 
                                    pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" 
                                    maxlength="10"
                                    placeholder="Enter 10-character PAN NO"
                                    style="text-transform: uppercase;"
                                >
                                <i class="fas fa-address-card"></i>
                            </div>
                            <div class="input-hint">Format: ABCDE1234F</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>ID Proof Type</label>
                            <div class="select-wrapper">
                                <select name="id_proof_type">
                                    <option value="">Select ID Proof Type</option>
                                    <option value="Aadhar" <?php echo ($kyc && $kyc['IDProofType'] === 'Aadhar') ? 'selected' : ''; ?>>Aadhar Card</option>
                                    <option value="PAN" <?php echo ($kyc && $kyc['IDProofType'] === 'PAN') ? 'selected' : ''; ?>>PAN Card</option>
                                    <option value="Voter ID" <?php echo ($kyc && $kyc['IDProofType'] === 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                                    <option value="Passport" <?php echo ($kyc && $kyc['IDProofType'] === 'Passport') ? 'selected' : ''; ?>>Passport</option>
                                    <option value="Driving License" <?php echo ($kyc && $kyc['IDProofType'] === 'Driving License') ? 'selected' : ''; ?>>Driving License</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Address Proof Type</label>
                            <div class="select-wrapper">
                                <select name="address_proof_type">
                                    <option value="">Select Address Proof Type</option>
                                    <option value="Aadhar" <?php echo ($kyc && $kyc['AddressProofType'] === 'Aadhar') ? 'selected' : ''; ?>>Aadhar Card</option>
                                    <option value="Voter ID" <?php echo ($kyc && $kyc['AddressProofType'] === 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                                    <option value="Utility Bill" <?php echo ($kyc && $kyc['AddressProofType'] === 'Utility Bill') ? 'selected' : ''; ?>>Utility Bill</option>
                                    <option value="Bank Statement" <?php echo ($kyc && $kyc['AddressProofType'] === 'Bank Statement') ? 'selected' : ''; ?>>Bank Statement</option>
                                    <option value="Ration Card" <?php echo ($kyc && $kyc['AddressProofType'] === 'Ration Card') ? 'selected' : ''; ?>>Ration Card</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="document-section">
                        <div class="section-title">
                            <i class="fas fa-file-upload"></i>
                            Document Upload
                        </div>
                        <div class="document-upload-grid">
                            <div class="form-group">
                                <div class="file-upload-container" id="id-proof-upload">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="file-upload-text">
                                        <strong>Upload ID Proof</strong>
                                        Drag and drop or click to upload<br>
                                        JPG, PNG or PDF (max. 5MB)
                                    </div>
                                    <input type="file" name="id_proof" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                                    <div class="uploaded-file-name"></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-container" id="address-proof-upload">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="file-upload-text">
                                        <strong>Upload Address Proof</strong>
                                        Drag and drop or click to upload<br>
                                        JPG, PNG or PDF (max. 5MB)
                                    </div>
                                    <input type="file" name="address_proof" accept=".jpg,.jpeg,.png,.pdf" style="display: none;">
                                    <div class="uploaded-file-name"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="popup-footer">
                <button type="button" class="btn-cancel" onclick="closeEditPopup()">Cancel</button>
                <button type="submit" form="editKycForm" class="btn-edit">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <script>
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function setupFileUpload(containerId) {
            const container = document.getElementById(containerId);
            if (!container || container.dataset.initialized === 'true') return;
            
            const input = container.querySelector('input[type="file"]');
            const fileNameDiv = container.querySelector('.uploaded-file-name');
            const uploadText = container.querySelector('.file-upload-text');
            const uploadIcon = container.querySelector('.upload-icon');

            function updatePreview(file) {
                if (file) {
                    const fileSize = formatFileSize(file.size);
                    const isImage = file.type.startsWith('image/');
                    const icon = isImage ? 'image' : 'pdf';
                    
                    fileNameDiv.innerHTML = `
                        <div class="uploaded-preview">
                            <i class="fas fa-file-${icon}"></i>
                            <div class="file-info">
                                <div class="file-name">${file.name}</div>
                                <div class="file-size">${fileSize}</div>
                            </div>
                            <button type="button" class="remove-file" title="Remove file">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>`;
                    uploadText.style.display = 'none';
                    uploadIcon.style.display = 'none';
                } else {
                    fileNameDiv.innerHTML = '';
                    uploadText.style.display = 'block';
                    uploadIcon.style.display = 'block';
                }
            }

            container.addEventListener('click', (e) => {
                if (!e.target.closest('.remove-file')) {
                    input.click();
                }
            });

            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    updatePreview(e.target.files[0]);
                }
            });

            // Drag and drop handlers
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                container.style.borderColor = 'var(--primary-color)';
                container.style.background = 'var(--primary-light)';
            });

            container.addEventListener('dragleave', (e) => {
                e.preventDefault();
                container.style.borderColor = 'var(--border-color)';
                container.style.background = 'white';
            });

            container.addEventListener('drop', (e) => {
                e.preventDefault();
                container.style.borderColor = 'var(--border-color)';
                container.style.background = 'white';
                
                if (e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    updatePreview(e.dataTransfer.files[0]);
                }
            });

            // File removal handler
            container.addEventListener('click', (e) => {
                if (e.target.closest('.remove-file')) {
                    e.preventDefault();
                    input.value = '';
                    updatePreview(null);
                }
            });

            // Mark as initialized
            container.dataset.initialized = 'true';
        }

        // Initialize file uploads only once when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup main form file uploads
            setupFileUpload('id-proof-upload');
            setupFileUpload('address-proof-upload');
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const icon = submitBtn.querySelector('i');
            const text = submitBtn.querySelector('.btn-text');
            
            // Check if documents are uploaded
            const idProofInput = document.querySelector('input[name="id_proof"]');
            const addressProofInput = document.querySelector('input[name="address_proof"]');
            
            if (!idProofInput.files.length || !addressProofInput.files.length) {
                e.preventDefault();
                alert('Please upload both ID Proof and Address Proof documents before submitting.');
                return;
            }
            
            // Store original icon class
            const originalIconClass = icon.className;
            
            // Update button state
            submitBtn.classList.add('loading');
            icon.className = 'fas fa-spinner';
            text.textContent = 'Processing...';
            
            // Optional: Restore button state if submission takes too long
            setTimeout(() => {
                if (submitBtn.classList.contains('loading')) {
                    submitBtn.classList.remove('loading');
                    icon.className = originalIconClass;
                    text.textContent = '<?php echo $kyc ? 'Update KYC Details' : 'Submit KYC Details'; ?>';
                }
            }, 10000); // Reset after 10 seconds
        });

        function openEditPopup() {
            document.getElementById('kycEditPopup').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditPopup() {
            document.getElementById('kycEditPopup').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close popup when clicking outside
        document.getElementById('kycEditPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditPopup();
            }
        });

        // Add form validation for edit popup
        document.getElementById('editKycForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const aadharNumber = this.querySelector('input[name="aadhar_number"]').value;
            const panNumber = this.querySelector('input[name="pan_number"]').value;
            const idProofType = this.querySelector('select[name="id_proof_type"]').value;
            const addressProofType = this.querySelector('select[name="address_proof_type"]').value;
            const idProofFile = this.querySelector('input[name="id_proof"]').files[0];
            const addressProofFile = this.querySelector('input[name="address_proof"]').files[0];
            
            let hasError = false;
            let errorMessage = '';
            
            // Validate Aadhar Number
            if (aadharNumber && !/^[0-9]{12}$/.test(aadharNumber)) {
                hasError = true;
                errorMessage = 'Please enter a valid 12-digit Aadhar number';
            }
            
            // Validate PAN Number
            if (panNumber && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNumber)) {
                hasError = true;
                errorMessage = 'Please enter a valid PAN number (e.g., ABCDE1234F)';
            }
            
            // Validate ID Proof Type
            if (!idProofType) {
                hasError = true;
                errorMessage = 'Please select an ID Proof Type';
            }
            
            // Validate Address Proof Type
            if (!addressProofType) {
                hasError = true;
                errorMessage = 'Please select an Address Proof Type';
            }
            
            // Validate file sizes if files are selected
            if (idProofFile && idProofFile.size > 5 * 1024 * 1024) {
                hasError = true;
                errorMessage = 'ID Proof file size should be less than 5MB';
            }
            
            if (addressProofFile && addressProofFile.size > 5 * 1024 * 1024) {
                hasError = true;
                errorMessage = 'Address Proof file size should be less than 5MB';
            }
            
            if (hasError) {
                alert(errorMessage);
                return;
            }
            
            // If validation passes, submit the form
            this.submit();
        });
    </script>
</body>
</html>