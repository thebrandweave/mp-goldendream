<?php
session_start();


require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Check if scheme ID and action are provided
if (!isset($_GET['id']) || !isset($_GET['action']) || ($_GET['action'] !== 'activate' && $_GET['action'] !== 'deactivate')) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: index.php");
    exit();
}

$schemeId = intval($_GET['id']);
$action = $_GET['action'];
$newStatus = ($action === 'activate') ? 'Active' : 'Inactive';

try {
    // Begin transaction
    $conn->beginTransaction();

    // Get scheme details first
    $stmt = $conn->prepare("SELECT SchemeName FROM Schemes WHERE SchemeID = ?");
    $stmt->execute([$schemeId]);
    $scheme = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        throw new Exception("Scheme not found.");
    }

    // Update scheme status
    $stmt = $conn->prepare("UPDATE Schemes SET Status = ? WHERE SchemeID = ?");
    $stmt->execute([$newStatus, $schemeId]);

    // Log the activity
    $actionMessage = ($action === 'activate') ? "Activated" : "Deactivated";
    $stmt = $conn->prepare("
        INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) 
        VALUES (?, 'Admin', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['admin_id'],
        "$actionMessage scheme: " . $scheme['SchemeName'],
        $_SERVER['REMOTE_ADDR']
    ]);

    $conn->commit();
    $_SESSION['success_message'] = "Scheme successfully " . strtolower($actionMessage) . ".";
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Failed to update scheme status: " . $e->getMessage();
}

// Redirect back to the scheme view page
header("Location: view.php?id=$schemeId");
exit();
